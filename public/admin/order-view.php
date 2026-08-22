<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
Auth::requireAdmin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$order = Database::fetch("SELECT * FROM orders WHERE id=?", [$id]);
if (!$order) { flash('error','Order not found'); redirect('/admin/orders.php'); }
$pageTitle = 'Order ' . $order['order_number'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = isset($_POST['status']) ? $_POST['status'] : $order['status'];
    $payStatus = isset($_POST['payment_status']) ? $_POST['payment_status'] : $order['payment_status'];
    $notes = trim(isset($_POST['notes']) ? $_POST['notes'] : '');
    $allowed = ['pending','confirmed','processing','shipped','delivered','cancelled'];
    $allowedPay = ['pending','paid','failed','refunded'];
    if (!in_array($status, $allowed, true)) $status = $order['status'];
    if (!in_array($payStatus, $allowedPay, true)) $payStatus = $order['payment_status'];

    Database::update('orders', [
        'status' => $status,
        'payment_status' => $payStatus,
        'notes' => $notes !== '' ? $notes : $order['notes'],
    ], 'id=?', [$id]);

    if ($status === 'delivered' && $order['payment_method'] === 'cod') {
        Database::update('orders', ['payment_status' => 'paid'], 'id=?', [$id]);
    }
    flash('success', 'Order updated.');
    redirect('/admin/order-view.php?id=' . $id);
}

$items = Database::fetchAll("SELECT * FROM order_items WHERE order_id=? ORDER BY id", [$id]);
ob_start();
?>

<style>
.status-chips{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px}
.status-chip{padding:10px 16px;border-radius:12px;font-weight:700;font-size:.85rem;border:2px solid transparent}
.status-chip.dim{opacity:.45;filter:saturate(.5);background:var(--color-border);color:var(--color-text-muted)}
.status-chip.hot{opacity:1;box-shadow:0 0 0 3px color-mix(in srgb,var(--color-primary) 25%,transparent)}
.status-chip.pending{background:#fef3c7;color:#b45309;border-color:#f59e0b}
.status-chip.confirmed,.status-chip.processing,.status-chip.shipped{background:#dbeafe;color:#1d4ed8;border-color:#3b82f6}
.status-chip.delivered{background:#dcfce7;color:#15803d;border-color:#22c55e}
.status-chip.cancelled{background:#fee2e2;color:#b91c1c;border-color:#ef4444}
.status-chip.pay-pending{background:#fef3c7;color:#b45309;border-color:#f59e0b}
.status-chip.pay-paid{background:#dcfce7;color:#15803d;border-color:#22c55e}
.status-chip.pay-failed{background:#fee2e2;color:#b91c1c;border-color:#ef4444}
.status-chip.pay-refunded{background:#e0e7ff;color:#4338ca;border-color:#6366f1}
.order-item-img{width:52px;height:64px;object-fit:cover;border-radius:8px;border:1px solid var(--color-border)}
</style>

<?php
  $st = $order['status'];
  $ps = $order['payment_status'];
  $orderDone = in_array($st, array('delivered','cancelled'), true);
  $payDone = in_array($ps, array('paid','refunded'), true);
?>
<div class="status-chips">
  <div class="status-chip <?= e($st) ?> <?= $orderDone ? 'dim' : 'hot' ?>">
    Order: <?= e(ucfirst($st)) ?><?= $st==='pending' ? ' ★' : '' ?>
  </div>
  <div class="status-chip pay-<?= e($ps) ?> <?= $payDone ? 'dim' : 'hot' ?>">
    Payment: <?= e(ucfirst($ps)) ?><?= $ps==='pending' ? ' ★' : '' ?>
  </div>
</div>
<div style="margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap">
  <a href="/admin/orders.php" class="btn btn-sm btn-outline"><i class="fas fa-arrow-left"></i> All Orders</a>
  <a href="/admin/orders.php?delete=<?= (int)$order['id'] ?>" class="btn btn-sm" style="color:#f87171;margin-left:auto"
     onclick="return confirm('Delete this order permanently?\n\nOrder items and payment screenshot will also be removed.\nThis cannot be undone.');">
    <i class="fas fa-trash"></i> Delete Order
  </a>
</div>

<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:20px;align-items:start">
  <div>
    <div class="panel">
      <div class="panel-header"><h3>Order Items</h3></div>
      <div class="panel-body" style="padding:0">
        <table class="data-table">
          <thead><tr><th></th><th>Product</th><th>Size</th><th>Color</th><th>Price</th><th>Qty</th><th>Total</th></tr></thead>
          <tbody>
          <?php foreach ($items as $it):
            $img = order_item_image($it);
          ?>
          <tr>
            <td style="width:56px">
              <?php if ($img): ?>
              <img src="<?= e($img) ?>" alt="" style="width:48px;height:60px;object-fit:cover;border-radius:8px;border:1px solid var(--color-border)">
              <?php else: ?>
              <div style="width:48px;height:60px;border-radius:8px;background:linear-gradient(145deg,var(--color-primary),var(--color-secondary));opacity:.4;display:grid;place-items:center;color:#fff;font-size:.8rem"><i class="fas fa-shirt"></i></div>
              <?php endif; ?>
            </td>
            <td>
              <strong><?= e($it['product_name']) ?></strong>
              <?php if ($it['product_sku']): ?><br><span class="text-muted" style="font-size:.8rem"><?= e($it['product_sku']) ?></span><?php endif; ?>
            </td>
            <td><?= e($it['size'] ?: '—') ?></td>
            <td><?= e($it['color'] ?: '—') ?></td>
            <td><?= money($it['price']) ?></td>
            <td><?= (int)$it['quantity'] ?></td>
            <td><?= money($it['total']) ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="panel">
      <div class="panel-header"><h3>Customer & Shipping</h3></div>
      <div class="panel-body">
        <p><strong><?= e($order['customer_name']) ?></strong></p>
        <p class="text-muted"><?= e($order['customer_email']) ?> · <?= e($order['customer_phone']) ?></p>
        <p style="margin-top:12px"><?= nl2br(e($order['shipping_address'])) ?></p>
        <p class="text-muted"><?= e($order['shipping_city']) ?>, <?= e($order['shipping_country']) ?></p>
      </div>
    </div>
  </div>

  <div>
    <div class="panel">
      <div class="panel-header"><h3>Update Order</h3></div>
      <div class="panel-body">
        <form method="POST">
          <div class="form-group">
            <label>Order Status</label>
            <select name="status" class="form-control" style="<?= $order['status']==='pending'?'border-color:#f59e0b;background:#fffbeb;font-weight:700':($orderDone?'opacity:.75':'') ?>">
              <?php foreach (['pending','confirmed','processing','shipped','delivered','cancelled'] as $s): ?>
              <option value="<?= $s ?>" <?= $order['status']===$s?'selected':'' ?>><?= ucfirst($s) ?><?= $s==='pending'?' ★':'' ?><?= $s==='delivered'?' (done)':'' ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Payment Status</label>
            <select name="payment_status" class="form-control" style="<?= $order['payment_status']==='pending'?'border-color:#f59e0b;background:#fffbeb;font-weight:700':($payDone?'opacity:.75':'') ?>">
              <?php foreach (['pending','paid','failed','refunded'] as $s): ?>
              <option value="<?= $s ?>" <?= $order['payment_status']===$s?'selected':'' ?>><?= ucfirst($s) ?><?= $s==='pending'?' ★':'' ?><?= $s==='paid'?' (done)':'' ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Admin Notes</label>
            <textarea name="notes" class="form-control" rows="3"><?= e($order['notes']) ?></textarea>
          </div>
          <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-save"></i> Save Changes</button>
        </form>
      </div>
    </div>
    <div class="panel">
      <div class="panel-header"><h3>Payment Proof</h3></div>
      <div class="panel-body">
        <p style="font-size:.9rem;margin-bottom:8px">Method: <strong><?= e(strtoupper($order['payment_method'])) ?></strong></p>
                <?php
          $trx = !empty($order['transaction_id']) ? $order['transaction_id'] : '';
          $proof = !empty($order['payment_proof']) ? $order['payment_proof'] : '';
          if ($proof === '' && !empty($order['notes']) && preg_match('/Payment proof(?: file)?:\s*(\S+)/i', $order['notes'], $m)) {
              $proof = $m[1];
          }
          if ($trx === '' && !empty($order['notes']) && preg_match('/TrxID:\s*([^\|\n]+)/i', $order['notes'], $m)) {
              $trx = trim($m[1]);
          }
          $proofUrl = $proof !== '' ? '/' . ltrim(str_replace('\\', '/', $proof), '/') : '';
          $proofFile = $proof !== '' ? (dirname(__DIR__, 2) . '/public/' . ltrim(str_replace('\\', '/', $proof), '/')) : '';
          $proofExists = $proofFile !== '' && is_file($proofFile);
        ?>
        <?php if ($trx !== ''): ?>
        <p style="font-size:.9rem;margin-bottom:8px">TrxID: <code style="font-weight:700;color:var(--color-primary)"><?= e($trx) ?></code></p>
        <?php else: ?>
        <p class="text-muted" style="font-size:.85rem">No transaction ID (COD or not provided)</p>
        <?php endif; ?>
        <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--color-border)">
          <p style="font-size:.85rem;font-weight:700;margin-bottom:10px">Payment screenshot</p>
          <?php if ($proof !== '' && $proofExists): ?>
          <a href="<?= e($proofUrl) ?>" target="_blank" rel="noopener">
            <img src="<?= e($proofUrl) ?>" alt="Payment proof" style="max-width:100%;max-height:360px;object-fit:contain;border-radius:12px;border:1px solid var(--color-border);background:#0a0a0a">
          </a>
          <p style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap">
            <a class="btn btn-sm btn-outline" href="<?= e($proofUrl) ?>" target="_blank"><i class="fas fa-external-link-alt"></i> Open full size</a>
            <a class="btn btn-sm btn-outline" href="<?= e($proofUrl) ?>" download><i class="fas fa-download"></i> Download</a>
          </p>
          <?php elseif ($proof !== ''): ?>
          <p style="color:#f59e0b;font-size:.85rem">Proof path saved but file missing:<br><code><?= e($proof) ?></code></p>
          <?php else: ?>
          <p class="text-muted" style="font-size:.85rem">No payment screenshot uploaded for this order.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="panel">
      <div class="panel-header"><h3>Summary</h3></div>
      <div class="panel-body">
        <div style="display:flex;justify-content:space-between;padding:6px 0"><span class="text-muted">Subtotal</span><span><?= money($order['subtotal']) ?></span></div>
        <div style="display:flex;justify-content:space-between;padding:6px 0"><span class="text-muted">Shipping</span><span><?= money($order['shipping_cost']) ?></span></div>
        <div style="display:flex;justify-content:space-between;padding:10px 0;border-top:1px solid var(--color-border);font-weight:800;font-size:1.1rem"><span>Total</span><span><?= money($order['total']) ?></span></div>
        <p style="margin-top:12px;font-size:.9rem">Payment: <strong><?= e(strtoupper($order['payment_method'])) ?></strong></p>
        <p class="text-muted" style="font-size:.8rem">Placed <?= formatDate($order['created_at'], 'M d, Y H:i') ?></p>
      </div>
    </div>
  </div>
</div>
<style>@media(max-width:900px){div[style*="grid-template-columns:1.4fr"]{grid-template-columns:1fr!important}}</style>
<?php $content=ob_get_clean(); require dirname(__DIR__,2).'/app/views/layouts/admin.php'; ?>