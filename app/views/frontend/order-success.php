<?php
$pageTitle = 'Order Placed';
$orderNo = isset($_SESSION['last_order']) ? $_SESSION['last_order'] : '';
$order = null;
if ($orderNo) {
    $order = Database::fetch("SELECT * FROM orders WHERE order_number=?", [$orderNo]);
}
ob_start();
?>
<section class="section" style="padding-top:calc(var(--header-h) + 60px);min-height:70vh;display:flex;align-items:center">
<div class="container text-center" style="max-width:520px">
  <div style="font-size:4rem;color:var(--color-primary);margin-bottom:16px"><i class="fas fa-check-circle"></i></div>
  <h1 style="margin-bottom:12px">Order Placed Successfully!</h1>
  <?php if ($orderNo): ?>
  <p class="text-muted" style="font-size:1.1rem;margin-bottom:8px">Order Number: <strong style="color:var(--color-text)"><?= e($orderNo) ?></strong></p>
  <?php endif; ?>
  <p class="text-muted" style="margin-bottom:28px">Thank you for shopping with AK. Save your order number to track status.</p>

  <div style="display:flex;flex-wrap:wrap;gap:12px;justify-content:center">
    <?php if ($order): ?>
    <a href="/invoice.php?order=<?= urlencode($order['order_number']) ?>&email=<?= urlencode($order['customer_email']) ?>" class="btn btn-primary" target="_blank">
      <i class="fas fa-file-invoice"></i> View / Download Invoice
    </a>
    <a href="/track-order.php?order=<?= urlencode($order['order_number']) ?>" class="btn btn-outline">
      <i class="fas fa-truck"></i> Track Order
    </a>
    <?php endif; ?>
    <?php if (Auth::check() && !Auth::isAdmin()): ?>
    <a href="/my-orders.php" class="btn btn-outline">My Orders</a>
    <?php else: ?>
    <a href="/register.php" class="btn btn-outline">Create Account</a>
    <?php endif; ?>
    <a href="/shop.php" class="btn btn-outline">Continue Shopping</a>
  </div>
</div>
</section>
<?php $content = ob_get_clean(); require dirname(__DIR__).'/layouts/frontend.php'; ?>
