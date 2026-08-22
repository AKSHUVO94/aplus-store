<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';

if (!Auth::checkCustomer()) {
    flash('error', 'Please login to track your orders.');
    redirect('/login.php');
}

$user = Auth::user();
$customer = Customer::forLoggedIn();
$pageTitle = 'Track Order';
$order = null;
$items = [];
$error = null;
$myOrders = [];

try {
    if ($customer) {
        $myOrders = Database::fetchAll(
            "SELECT * FROM orders WHERE customer_id = ? OR user_id = ? OR customer_email = ? ORDER BY created_at DESC LIMIT 20",
            [$customer['id'], Auth::id(), $user['email']]
        );
    } else {
        $myOrders = Database::fetchAll(
            "SELECT * FROM orders WHERE user_id = ? OR customer_email = ? ORDER BY created_at DESC LIMIT 20",
            [Auth::id(), $user['email']]
        );
    }
} catch (Exception $e) {
    $myOrders = [];
}

$orderNo = trim(isset($_GET['order']) ? $_GET['order'] : (isset($_POST['order_number']) ? $_POST['order_number'] : ''));

if ($orderNo !== '' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($orderNo === '') {
        $error = 'Enter your order number.';
    } else {
        $order = Database::fetch(
            "SELECT * FROM orders WHERE order_number = ? AND (user_id = ? OR customer_email = ?" .
            ($customer ? " OR customer_id = ?" : "") . ")",
            $customer
                ? [$orderNo, Auth::id(), $user['email'], $customer['id']]
                : [$orderNo, Auth::id(), $user['email']]
        );
        if (!$order) {
            $error = 'Order not found in your account. Check the order number.';
        } else {
            $items = Database::fetchAll("SELECT * FROM order_items WHERE order_id = ?", [$order['id']]);
        }
    }
}

$steps = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];
ob_start();
?>
<section class="section" style="padding-top:calc(var(--header-h) + 40px)">
<div class="container" style="max-width:720px">
  <h1 style="font-size:1.75rem;margin-bottom:8px">Track Your Order</h1>
  <p class="text-muted" style="margin-bottom:24px">Logged in as <?= e($user['email']) ?> — only your orders are shown.</p>

  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

  <form method="GET" style="background:var(--color-surface);border:1px solid var(--color-border);border-radius:16px;padding:24px;margin-bottom:28px">
    <div class="form-group">
      <label>Order Number</label>
      <input type="text" name="order" class="form-control" required placeholder="AK-20260301-XXXXX" value="<?= e($orderNo) ?>">
    </div>
    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Track</button>
    <a href="/my-orders.php" class="btn btn-outline" style="margin-left:8px">My Orders</a>
  </form>

  <?php if ($order):
    $cur = array_search($order['status'], $steps, true);
    if ($order['status'] === 'cancelled') $cur = -1;
  ?>
  <div style="background:var(--color-surface);border:1px solid var(--color-border);border-radius:16px;padding:24px;margin-bottom:24px">
    <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:20px">
      <div>
        <div style="font-weight:800;font-size:1.1rem"><?= e($order['order_number']) ?></div>
        <div class="text-muted" style="font-size:.85rem"><?= formatDate($order['created_at'], 'M d, Y') ?> · <?= money($order['total']) ?></div>
      </div>
      <a href="/invoice.php?order=<?= urlencode($order['order_number']) ?>&email=<?= urlencode($order['customer_email']) ?>" class="btn btn-sm btn-primary" target="_blank"><i class="fas fa-file-invoice"></i> Invoice</a>
    </div>
    <h3 style="margin-bottom:14px;font-size:1rem">Status</h3>
    <?php if ($order['status'] === 'cancelled'): ?>
    <div class="alert alert-error">Order cancelled</div>
    <?php else: ?>
    <div style="display:flex;gap:4px;margin-bottom:16px;flex-wrap:wrap">
      <?php foreach ($steps as $si => $st): ?>
      <div style="flex:1;min-width:55px;text-align:center">
        <div style="height:5px;border-radius:4px;background:<?= $si <= $cur ? 'var(--color-primary)' : 'var(--color-border)' ?>;margin-bottom:6px"></div>
        <div style="font-size:.65rem;font-weight:<?= $si === $cur ? '700' : '400' ?>"><?= ucfirst($st) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <p>Current: <strong style="color:var(--color-primary)"><?= e(ucfirst($order['status'])) ?></strong></p>
    <?php endif; ?>

    <h3 style="margin:20px 0 12px;font-size:1rem">Items</h3>
    <?php foreach ($items as $it):
      $img = order_item_image($it);
    ?>
    <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--color-border);font-size:.875rem">
      <?php if ($img): ?><img src="<?= e($img) ?>" alt="" style="width:44px;height:54px;object-fit:cover;border-radius:6px"><?php endif; ?>
      <span style="flex:1"><?= e($it['product_name']) ?> × <?= (int)$it['quantity'] ?></span>
      <span><?= money($it['total']) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (!$order && $myOrders): ?>
  <h3 style="margin-bottom:14px;font-size:1.05rem">Your recent orders</h3>
  <div style="display:flex;flex-direction:column;gap:10px">
    <?php foreach ($myOrders as $o): ?>
    <a href="/track-order.php?order=<?= urlencode($o['order_number']) ?>" style="display:flex;justify-content:space-between;padding:14px 16px;background:var(--color-surface);border:1px solid var(--color-border);border-radius:12px;color:inherit">
      <div>
        <strong><?= e($o['order_number']) ?></strong>
        <div class="text-muted" style="font-size:.8rem"><?= formatDate($o['created_at']) ?> · <?= e(ucfirst($o['status'])) ?></div>
      </div>
      <span style="font-weight:700"><?= money($o['total']) ?></span>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
</section>
<?php $content = ob_get_clean(); require dirname(__DIR__) . '/app/views/layouts/frontend.php'; ?>
