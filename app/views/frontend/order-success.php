<?php
$pageTitle = 'Order Placed';
$orderNo = isset($_SESSION['last_order']) ? $_SESSION['last_order'] : '';
$order = null;
$orderItems = [];
if ($orderNo) {
    $order = Database::fetch("SELECT * FROM orders WHERE order_number=?", [$orderNo]);
    if ($order) {
        $orderItems = Database::fetchAll("SELECT * FROM order_items WHERE order_id=?", [(int)$order['id']]);
    }
}
ob_start();
?>
<section class="section order-success-page" style="padding-top:calc(var(--header-h) + 48px);padding-bottom:56px">
<div class="container" style="max-width:720px">
  <div class="os-hero">
    <div class="os-check"><i class="fas fa-check"></i></div>
    <h1>Order placed successfully</h1>
    <?php if ($orderNo): ?>
    <p class="os-order-no">Order <strong><?= e($orderNo) ?></strong></p>
    <?php endif; ?>
    <p class="os-thanks text-muted">Thank you for shopping with us. Save your order number to track status.</p>
  </div>

  <?php if ($order): ?>
  <div class="os-summary cart-summary">
    <h3>Order Summary</h3>
    <?php if (!empty($orderItems)): ?>
    <div class="summary-items">
      <?php foreach ($orderItems as $it):
        $sz = !empty($it['size']) ? $it['size'] : '';
        $cl = !empty($it['color']) ? $it['color'] : '';
        $line = (float)($it['price'] ?? 0) * (int)($it['quantity'] ?? $it['qty'] ?? 1);
        if (!empty($it['total'])) $line = (float)$it['total'];
        $qty = (int)($it['quantity'] ?? $it['qty'] ?? 1);
        $pname = $it['product_name'] ?? $it['name'] ?? 'Item';
      ?>
      <div class="summary-line">
        <div class="summary-line-main">
          <span class="summary-line-name"><?= e($pname) ?></span>
          <span class="summary-line-qty">× <?= $qty ?></span>
        </div>
        <?php if ($sz !== '' || $cl !== ''): ?>
        <div class="summary-line-opts">
          <?php if ($sz !== ''): ?>Size: <?= e($sz) ?><?php endif; ?>
          <?php if ($sz !== '' && $cl !== ''): ?> · <?php endif; ?>
          <?php if ($cl !== ''): ?>Color: <?= e($cl) ?><?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="summary-line-price"><?= money($line) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="summary-row"><span>Subtotal</span><span><?= money($order['subtotal']) ?></span></div>
    <div class="summary-row"><span>Shipping<?= !empty($order['shipping_city']) ? ' (' . e($order['shipping_city']) . ')' : '' ?></span><span><?= (float)$order['shipping_cost'] > 0 ? money($order['shipping_cost']) : 'FREE' ?></span></div>
    <div class="summary-row total"><span>Total</span><span><?= money($order['total']) ?></span></div>

    <div class="os-meta">
      <div><span class="os-meta-label">Payment</span> <?= e(ucfirst(str_replace('_', ' ', $order['payment_method'] ?? ''))) ?> · <?= e(ucfirst($order['payment_status'] ?? 'pending')) ?></div>
      <div><span class="os-meta-label">Status</span> <?= e(ucfirst($order['status'] ?? 'pending')) ?></div>
      <?php if (!empty($order['shipping_address'])): ?>
      <div><span class="os-meta-label">Ship to</span> <?= e($order['customer_name'] ?? '') ?>, <?= e($order['shipping_address']) ?><?= !empty($order['shipping_city']) ? ', ' . e($order['shipping_city']) : '' ?></div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="os-actions">
    <?php if ($order): ?>
    <a href="/invoice.php?order=<?= urlencode($order['order_number']) ?>&email=<?= urlencode($order['customer_email']) ?>" class="btn btn-primary" target="_blank">
      <i class="fas fa-file-invoice"></i> Invoice
    </a>
    <a href="/track-order.php?order=<?= urlencode($order['order_number']) ?>" class="btn btn-outline">
      <i class="fas fa-truck"></i> Track Order
    </a>
    <?php endif; ?>
    <?php if (Auth::check() && !Auth::isAdmin()): ?>
    <a href="/my-orders.php" class="btn btn-outline">My Orders</a>
    <?php endif; ?>
    <a href="/shop.php" class="btn btn-outline">Continue Shopping</a>
  </div>
</div>
</section>
<style>
.os-hero{text-align:center;margin-bottom:28px}
.os-check{
  width:64px;height:64px;border-radius:50%;background:#111;color:#fff;
  display:inline-flex;align-items:center;justify-content:center;font-size:1.5rem;margin-bottom:16px;
}
.os-hero h1{font-size:1.6rem;margin:0 0 8px}
.os-order-no{margin:0 0 6px;font-size:1.05rem}
.os-thanks{margin:0;font-size:.95rem}
.os-summary{margin:0 auto 24px;max-width:480px}
.os-meta{margin-top:16px;padding-top:14px;border-top:1px solid #eee;font-size:.85rem;color:#555;line-height:1.55;display:flex;flex-direction:column;gap:6px}
.os-meta-label{display:inline-block;min-width:70px;color:#888;font-weight:600}
.os-actions{display:flex;flex-wrap:wrap;gap:10px;justify-content:center}
</style>
<?php $content = ob_get_clean(); require dirname(__DIR__).'/layouts/frontend.php'; ?>