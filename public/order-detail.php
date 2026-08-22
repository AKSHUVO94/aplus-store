<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
if (!Auth::checkCustomer()) redirect('/login.php');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$order = Database::fetch("SELECT * FROM orders WHERE id=?", [$id]);
if (!$order) { flash('error','Order not found'); redirect('/my-orders.php'); }

// Security: only own orders (or admin)
$user = Auth::user();
$allowed = Auth::checkAdmin()
    || (int)$order['user_id'] === (int)Auth::id()
    || strcasecmp($order['customer_email'], $user['email']) === 0;
if (!$allowed) {
    http_response_code(403);
    die('Access denied');
}

$items = Database::fetchAll("SELECT * FROM order_items WHERE order_id=? ORDER BY id", [$id]);
$pageTitle = 'Order ' . $order['order_number'];
$steps = ['pending','confirmed','processing','shipped','delivered'];
$cur = array_search($order['status'], $steps, true);
if ($order['status'] === 'cancelled') $cur = -1;

ob_start();
?>
<section class="section" style="padding-top:calc(var(--header-h) + 40px)">
<div class="container" style="max-width:800px">
  <a href="/my-orders.php" class="text-muted" style="display:inline-flex;gap:6px;align-items:center;margin-bottom:20px;font-size:.9rem"><i class="fas fa-arrow-left"></i> My Orders</a>

  <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px">
    <div>
      <h1 style="font-size:1.5rem;margin-bottom:4px"><?= e($order['order_number']) ?></h1>
      <p class="text-muted"><?= formatDate($order['created_at'], 'F d, Y · h:i A') ?></p>
    </div>
    <a href="/invoice.php?order=<?= urlencode($order['order_number']) ?>&email=<?= urlencode($order['customer_email']) ?>" class="btn btn-primary" target="_blank">
      <i class="fas fa-download"></i> Invoice
    </a>
  </div>

  <!-- Tracking -->
  <div style="background:var(--color-surface);border:1px solid var(--color-border);border-radius:14px;padding:24px;margin-bottom:20px">
    <h3 style="margin-bottom:16px">Order Tracking</h3>
    <?php if ($order['status'] === 'cancelled'): ?>
    <div class="alert alert-error">This order was cancelled.</div>
    <?php else: ?>
    <div style="display:flex;gap:6px;flex-wrap:wrap">
      <?php foreach ($steps as $si => $st): ?>
      <div style="flex:1;min-width:70px;text-align:center">
        <div style="width:28px;height:28px;border-radius:50%;margin:0 auto 8px;display:grid;place-items:center;font-size:.75rem;font-weight:700;
          background:<?= $si <= $cur ? 'var(--color-primary)' : 'var(--color-border)' ?>;
          color:<?= $si <= $cur ? '#fff' : 'var(--color-text-muted)' ?>">
          <?php if ($si < $cur): ?><i class="fas fa-check"></i><?php else: ?><?= $si+1 ?><?php endif; ?>
        </div>
        <div style="font-size:.75rem;font-weight:<?= $si <= $cur ? '700' : '400' ?>;color:<?= $si <= $cur ? 'var(--color-text)' : 'var(--color-text-muted)' ?>"><?= ucfirst($st) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <p style="margin-top:16px;font-size:.9rem">Current status: <strong style="color:var(--color-primary)"><?= e(ucfirst($order['status'])) ?></strong></p>
    <?php endif; ?>
  </div>

  <div style="background:var(--color-surface);border:1px solid var(--color-border);border-radius:14px;padding:24px;margin-bottom:20px">
    <h3 style="margin-bottom:14px">Items</h3>
    <?php foreach ($items as $it):
      $img = order_item_image($it);
    ?>
    <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--color-border);font-size:.9rem;gap:14px;flex-wrap:wrap">
      <div style="display:flex;align-items:center;gap:12px;flex:1;min-width:200px">
        <?php if ($img): ?>
        <img src="<?= e($img) ?>" alt="" style="width:56px;height:70px;object-fit:cover;border-radius:8px;border:1px solid var(--color-border)">
        <?php else: ?>
        <div style="width:56px;height:70px;border-radius:8px;background:var(--color-border);display:grid;place-items:center;opacity:.5"><i class="fas fa-shirt"></i></div>
        <?php endif; ?>
        <div>
          <strong><?= e($it['product_name']) ?></strong>
          <div class="text-muted" style="font-size:.8rem">
            <?php if ($it['size']): ?>Size: <?= e($it['size']) ?> · <?php endif; ?>
            <?php if ($it['color']): ?>Color: <?= e($it['color']) ?> · <?php endif; ?>
            Qty: <?= (int)$it['quantity'] ?>
          </div>
        </div>
      </div>
      <div style="font-weight:600"><?= money($it['total']) ?></div>
    </div>
    <?php endforeach; ?>
    <div style="margin-top:14px">
      <div style="display:flex;justify-content:space-between;padding:4px 0"><span class="text-muted">Subtotal</span><span><?= money($order['subtotal']) ?></span></div>
      <div style="display:flex;justify-content:space-between;padding:4px 0"><span class="text-muted">Shipping</span><span><?= money($order['shipping_cost']) ?></span></div>
      <div style="display:flex;justify-content:space-between;padding:10px 0;font-weight:800;font-size:1.1rem;border-top:1px solid var(--color-border);margin-top:8px"><span>Total</span><span><?= money($order['total']) ?></span></div>
    </div>
  </div>

  <div style="background:var(--color-surface);border:1px solid var(--color-border);border-radius:14px;padding:24px">
    <h3 style="margin-bottom:12px">Shipping Address</h3>
    <p><strong><?= e($order['customer_name']) ?></strong></p>
    <p class="text-muted"><?= e($order['customer_phone']) ?> · <?= e($order['customer_email']) ?></p>
    <p style="margin-top:8px"><?= nl2br(e($order['shipping_address'])) ?></p>
    <p class="text-muted"><?= e($order['shipping_city']) ?>, <?= e($order['shipping_country']) ?></p>
    <p style="margin-top:12px;font-size:.9rem">Payment: <strong><?= e(strtoupper($order['payment_method'])) ?></strong> (<?= e($order['payment_status']) ?>)</p>
  </div>
</div>
</section>
<?php $content=ob_get_clean(); require dirname(__DIR__).'/app/views/layouts/frontend.php'; ?>
