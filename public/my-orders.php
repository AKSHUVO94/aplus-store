<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
if (!Auth::checkCustomer()) {
    flash('error', 'Please login to view your orders.');
    redirect('/login.php');
}
if (Auth::checkAdmin()) {
    redirect('/admin/orders.php');
}

$user = Auth::user();
$pageTitle = 'My Orders';

$orders = Database::fetchAll(
    "SELECT * FROM orders WHERE user_id=? OR customer_email=? ORDER BY created_at DESC",
    [Auth::id(), $user['email']]
);

ob_start();
?>
<section class="section" style="padding-top:calc(var(--header-h) + 40px)">
<div class="container">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:28px">
    <div>
      <h1 style="font-size:1.75rem;margin-bottom:4px">My Orders</h1>
      <p class="text-muted">Hello, <?= e($user['name']) ?></p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <a href="/track-order.php" class="btn btn-outline btn-sm"><i class="fas fa-search"></i> Track Order</a>
      <a href="/shop.php" class="btn btn-primary btn-sm">Continue Shopping</a>
      <a href="/logout-customer.php" class="btn btn-outline btn-sm">Logout</a>
    </div>
  </div>

  <?php if (empty($orders)): ?>
  <div class="empty-state">
    <i class="fas fa-box-open"></i>
    <p>You have no orders yet.</p>
    <a href="/shop.php" class="btn btn-primary" style="margin-top:12px">Shop Now</a>
  </div>
  <?php else: ?>
  <div style="display:flex;flex-direction:column;gap:14px">
    <?php foreach ($orders as $o):
      $itemCount = (int) Database::fetch("SELECT COUNT(*) c FROM order_items WHERE order_id=?", [$o['id']])['c'];
      $statusColor = $o['status']==='delivered'?'#22c55e':($o['status']==='cancelled'?'#ef4444':($o['status']==='pending'?'#f59e0b':'#3b82f6'));
    ?>
    <div style="background:var(--color-surface);border:1px solid var(--color-border);border-radius:14px;padding:20px">
      <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:12px;align-items:flex-start">
        <div>
          <div style="font-weight:700;font-size:1.05rem;margin-bottom:4px"><?= e($o['order_number']) ?></div>
          <div class="text-muted" style="font-size:.85rem"><?= formatDate($o['created_at'], 'M d, Y · h:i A') ?> · <?= $itemCount ?> item(s)</div>
          <div style="margin-top:10px">
            <span style="display:inline-block;padding:4px 12px;border-radius:999px;font-size:.75rem;font-weight:700;background:color-mix(in srgb,<?= $statusColor ?> 18%,transparent);color:<?= $statusColor ?>">
              <?= e(ucfirst($o['status'])) ?>
            </span>
            <span class="text-muted" style="font-size:.8rem;margin-left:8px"><?= e(strtoupper($o['payment_method'])) ?> · <?= e(ucfirst($o['payment_status'])) ?></span>
          </div>
        </div>
        <div style="text-align:right">
          <div style="font-weight:800;font-size:1.15rem;margin-bottom:10px"><?= money($o['total']) ?></div>
          <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end">
            <a href="/order-detail.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-outline">Details</a>
            <a href="/invoice.php?order=<?= urlencode($o['order_number']) ?>&email=<?= urlencode($o['customer_email']) ?>" class="btn btn-sm btn-primary" target="_blank"><i class="fas fa-file-invoice"></i> Invoice</a>
          </div>
        </div>
      </div>
      <!-- mini tracking steps -->
      <?php
        $steps = ['pending','confirmed','processing','shipped','delivered'];
        $cur = array_search($o['status'], $steps, true);
        if ($o['status'] === 'cancelled') $cur = -1;
      ?>
      <?php if ($cur >= 0): ?>
      <div class="track-steps" style="display:flex;gap:4px;margin-top:18px;flex-wrap:wrap">
        <?php foreach ($steps as $si => $st): ?>
        <div style="flex:1;min-width:60px;text-align:center">
          <div style="height:4px;border-radius:4px;background:<?= $si <= $cur ? $statusColor : 'var(--color-border)' ?>;margin-bottom:6px"></div>
          <div style="font-size:.65rem;color:<?= $si <= $cur ? 'var(--color-text)' : 'var(--color-text-muted)' ?>;font-weight:<?= $si <= $cur ? '600' : '400' ?>"><?= ucfirst($st) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
</section>
<?php $content=ob_get_clean(); require dirname(__DIR__).'/app/views/layouts/frontend.php'; ?>
