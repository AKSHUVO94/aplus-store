<?php
/**
 * AK Invoice — Preview + Print / Download (print as PDF)
 */
require_once dirname(__DIR__) . '/app/bootstrap.php';

$orderNo = trim(isset($_GET['order']) ? $_GET['order'] : '');
$email = trim(isset($_GET['email']) ? $_GET['email'] : '');
$token = isset($_GET['token']) ? $_GET['token'] : '';

if ($orderNo === '') {
    http_response_code(400);
    die('Order number required.');
}

$order = Database::fetch("SELECT * FROM orders WHERE order_number=?", [$orderNo]);
if (!$order) {
    http_response_code(404);
    die('Invoice not found.');
}

// Access control: matching email, logged-in owner, admin, or session last order
$allowed = false;
if (Auth::check()) {
    $u = Auth::user();
    if (Auth::isAdmin()) $allowed = true;
    if ((int)$order['user_id'] === (int)Auth::id()) $allowed = true;
    if (strcasecmp($order['customer_email'], $u['email']) === 0) $allowed = true;
}
if ($email !== '' && strcasecmp($order['customer_email'], $email) === 0) $allowed = true;
if (isset($_SESSION['last_order']) && $_SESSION['last_order'] === $orderNo) $allowed = true;

if (!$allowed) {
    http_response_code(403);
    die('You do not have access to this invoice. Use the link from order confirmation or login.');
}

$items = Database::fetchAll("SELECT * FROM order_items WHERE order_id=? ORDER BY id", [$order['id']]);
$siteName = setting('site_name', 'AK');
$siteEmail = setting('site_email', 'hello@ak.com');
$sitePhone = setting('site_phone', '');
$siteAddress = setting('site_address', '');
$print = isset($_GET['print']) && $_GET['print'] == '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invoice <?= e($order['order_number']) ?> — <?= e($siteName) ?></title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#f4f4f5;color:#18181b;line-height:1.5;padding:24px}
  .toolbar{max-width:800px;margin:0 auto 16px;display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end}
  .btn{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border-radius:8px;font-weight:600;font-size:.9rem;border:none;cursor:pointer;text-decoration:none}
  .btn-primary{background:#e11d48;color:#fff}
  .btn-outline{background:#fff;border:1px solid #d4d4d8;color:#18181b}
  .invoice{max-width:800px;margin:0 auto;background:#fff;border-radius:12px;padding:40px;box-shadow:0 4px 24px rgba(0,0,0,.06)}
  .inv-header{display:flex;justify-content:space-between;gap:24px;flex-wrap:wrap;padding-bottom:24px;border-bottom:2px solid #e4e4e7;margin-bottom:28px}
  .brand{font-size:2rem;font-weight:900;letter-spacing:-.03em}
  .brand span{color:#e11d48}
  .inv-meta{text-align:right}
  .inv-meta h1{font-size:1.5rem;margin-bottom:8px}
  .badge{display:inline-block;padding:4px 10px;border-radius:999px;font-size:.75rem;font-weight:700;background:#fef3c7;color:#b45309}
  .badge.paid{background:#dcfce7;color:#15803d}
  .badge.delivered{background:#dbeafe;color:#1d4ed8}
  .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:28px}
  .box h3{font-size:.75rem;text-transform:uppercase;letter-spacing:.06em;color:#71717a;margin-bottom:8px}
  table{width:100%;border-collapse:collapse;margin-bottom:24px}
  th{text-align:left;font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;color:#71717a;padding:10px 8px;border-bottom:2px solid #e4e4e7}
  td{padding:12px 8px;border-bottom:1px solid #f4f4f5;font-size:.9rem}
  .totals{margin-left:auto;width:260px}
  .totals .row{display:flex;justify-content:space-between;padding:6px 0;font-size:.9rem}
  .totals .row.grand{font-weight:800;font-size:1.15rem;border-top:2px solid #e4e4e7;margin-top:8px;padding-top:12px}
  .footer{margin-top:36px;padding-top:20px;border-top:1px solid #e4e4e7;font-size:.8rem;color:#71717a;text-align:center}
  @media print{
    body{background:#fff;padding:0}
    .toolbar{display:none!important}
    .invoice{box-shadow:none;border-radius:0;padding:20px}
  }
  @media(max-width:600px){
    .grid-2{grid-template-columns:1fr}
    .inv-meta{text-align:left}
    .invoice{padding:20px}
  }

  .pay-highlight{
    margin:24px 0 0;
    padding:18px 20px;
    border-radius:12px;
    border:2px solid #e11d48;
    background:linear-gradient(135deg,#fff1f2 0%,#ffffff 60%);
    display:flex;
    flex-wrap:wrap;
    gap:16px 28px;
    align-items:center;
    justify-content:space-between;
  }
  .pay-highlight .pay-label{
    font-size:.7rem;
    font-weight:700;
    letter-spacing:.08em;
    text-transform:uppercase;
    color:#9f1239;
    margin-bottom:4px;
  }
  .pay-highlight .pay-method{
    font-size:1.35rem;
    font-weight:800;
    color:#e11d48;
    letter-spacing:.02em;
  }
  .pay-highlight .pay-status{
    display:inline-block;
    margin-top:6px;
    padding:6px 14px;
    border-radius:999px;
    font-size:.8rem;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.04em;
  }
  .pay-highlight .pay-status.pending{background:#fef3c7;color:#b45309}
  .pay-highlight .pay-status.paid{background:#dcfce7;color:#15803d}
  .pay-highlight .pay-status.failed{background:#fee2e2;color:#b91c1c}
  .pay-highlight .pay-status.refunded{background:#e0e7ff;color:#4338ca}
  .pay-highlight .pay-trx{
    font-size:.9rem;
    color:#3f3f46;
  }
  .pay-highlight .pay-trx strong{color:#18181b}
  @media print{
    .pay-highlight{border-color:#e11d48 !important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
    .pay-highlight .pay-method{color:#e11d48 !important}
    .pay-highlight .pay-status{-webkit-print-color-adjust:exact;print-color-adjust:exact}
  }
</style>
</head>
<body>
<?php if (!$print): ?>
<div class="toolbar">
  <button type="button" class="btn btn-primary" onclick="window.print()"><i></i> Print / Save as PDF</button>
  <a href="?order=<?= urlencode($orderNo) ?>&email=<?= urlencode($order['customer_email']) ?>&print=1" class="btn btn-outline" target="_blank">Print View</a>
  <a href="/my-orders.php" class="btn btn-outline">My Orders</a>
</div>
<?php else: ?>
<script>window.onload=function(){window.print();}</script>
<?php endif; ?>

<div class="invoice" id="invoice">
  <div class="inv-header">
    <div>
      <div class="brand">A<span>K</span></div>
      <div style="font-size:.85rem;color:#71717a;margin-top:6px">
        <?= e($siteAddress) ?><br>
        <?= e($siteEmail) ?><?= $sitePhone ? ' · '.e($sitePhone) : '' ?>
      </div>
    </div>
    <div class="inv-meta">
      <h1>INVOICE</h1>
      <div style="font-size:.9rem">
        <strong><?= e($order['order_number']) ?></strong><br>
        Date: <?= formatDate($order['created_at'], 'M d, Y') ?><br>
        <span class="badge <?= $order['payment_status']==='paid'?'paid':'' ?>"><?= e(ucfirst($order['payment_status'])) ?></span>
        <span class="badge <?= $order['status']==='delivered'?'delivered':'' ?>" style="margin-left:4px"><?= e(ucfirst($order['status'])) ?></span>
      </div>
    </div>
  </div>

  <div class="grid-2">
    <div class="box">
      <h3>Bill To</h3>
      <strong><?= e($order['customer_name']) ?></strong><br>
      <?= e($order['customer_email']) ?><br>
      <?= e($order['customer_phone']) ?>
    </div>
    <div class="box">
      <h3>Ship To</h3>
      <?= nl2br(e($order['shipping_address'])) ?><br>
      <?= e($order['shipping_city']) ?>, <?= e($order['shipping_country']) ?>
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Product</th>
        <th>Size</th>
        <th>Color</th>
        <th>Price</th>
        <th>Qty</th>
        <th style="text-align:right">Total</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($items as $i => $it): ?>
      <tr>
        <td><?= $i + 1 ?></td>
        <td>
          <strong><?= e($it['product_name']) ?></strong>
          
        </td>
        <td><?= e($it['size'] ?: '—') ?></td>
        <td><?= e($it['color'] ?: '—') ?></td>
        <td><?= money($it['price']) ?></td>
        <td><?= (int)$it['quantity'] ?></td>
        <td style="text-align:right"><?= money($it['total']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div class="totals">
    <div class="row"><span>Subtotal</span><span><?= money($order['subtotal']) ?></span></div>
    <div class="row"><span>Shipping</span><span><?= money($order['shipping_cost']) ?></span></div>
    <?php if ((float)$order['discount'] > 0): ?>
    <div class="row"><span>Discount</span><span>-<?= money($order['discount']) ?></span></div>
    <?php endif; ?>
    <div class="row grand"><span>Total</span><span><?= money($order['total']) ?></span></div>
  </div>

  <?php
    $pm = strtolower($order['payment_method']);
    $payLabels = array(
      'cod' => 'Cash on Delivery (COD)',
      'bkash' => 'bKash',
      'nagad' => 'Nagad',
      'rocket' => 'Rocket',
      'bank' => 'Bank Transfer',
      'visa' => 'Visa Card',
      'mastercard' => 'Master Card',
      'card' => 'Card',
    );
    $payLabel = isset($payLabels[$pm]) ? $payLabels[$pm] : strtoupper($order['payment_method']);
    $ps = strtolower($order['payment_status']);
  ?>
  <div class="pay-highlight">
    <div>
      <div class="pay-label">Payment Method</div>
      <div class="pay-method"><?= e($payLabel) ?></div>
      <?php if (!empty($order['transaction_id'])): ?>
      <div class="pay-trx" style="margin-top:8px">Transaction ID: <strong><?= e($order['transaction_id']) ?></strong></div>
      <?php endif; ?>
    </div>
    <div style="text-align:right">
      <div class="pay-label">Payment Status</div>
      <span class="pay-status <?= e($ps) ?>"><?= e(ucfirst($order['payment_status'])) ?></span>
    </div>
  </div>

  <?php if ($order['notes']): ?>
  <div style="margin-top:24px;font-size:.85rem;color:#52525b">
    <strong>Notes:</strong> <?= nl2br(e($order['notes'])) ?>
  </div>
  <?php endif; ?>

  <div class="footer">
    Thank you for shopping with <?= e($siteName) ?>!<br>
    For support: <?= e($siteEmail) ?>
  </div>
</div>
</body>
</html>