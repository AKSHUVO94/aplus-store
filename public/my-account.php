<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
if (!Auth::checkCustomer()) { flash('error','Please login.'); redirect('/login.php'); }
if (Auth::checkAdmin()) redirect('/admin/');

$customer = Customer::forLoggedIn();
$user = Auth::user();
$error = null;
$success = flash('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify(isset($_POST['_csrf']) ? $_POST['_csrf'] : '')) {
        $error = 'Invalid security token.';
    } else {
        $name = trim(isset($_POST['full_name']) ? $_POST['full_name'] : '');
        $phone = trim(isset($_POST['phone']) ? $_POST['phone'] : '');
        $address = trim(isset($_POST['address']) ? $_POST['address'] : '');
        $city = trim(isset($_POST['city']) ? $_POST['city'] : '');
        $country = trim(isset($_POST['country']) ? $_POST['country'] : 'Bangladesh');
        if ($name === '') {
            $error = 'Name is required.';
        } else {
            if ($customer) {
                Database::update('customers', [
                    'full_name' => $name,
                    'phone' => $phone ?: null,
                    'address' => $address ?: null,
                    'city' => $city ?: null,
                    'country' => $country ?: 'Bangladesh',
                ], 'id=?', [$customer['id']]);
            } else {
                Customer::upsert([
                    'name' => $name,
                    'email' => $user['email'],
                    'phone' => $phone,
                    'address' => $address,
                    'city' => $city,
                    'country' => $country,
                ], Auth::id());
            }
            Database::update('users', [
                'name' => $name,
                'phone' => $phone ?: null,
                'address' => $address ?: null,
                'city' => $city ?: null,
            ], 'id=?', [Auth::id()]);
            $_SESSION['user']['name'] = $name;
            flash('success', 'Profile updated successfully.');
            redirect('/my-account.php');
        }
    }
}

$customer = Customer::forLoggedIn();
if ($customer) {
    Customer::refreshStats((int)$customer['id']);
    $customer = Customer::forLoggedIn();
}

$orders = [];
if ($customer) {
    $orders = Database::fetchAll(
        "SELECT * FROM orders WHERE customer_id=? OR user_id=? OR customer_email=? ORDER BY created_at DESC LIMIT 20",
        [$customer['id'], Auth::id(), $user['email']]
    );
} else {
    $orders = Database::fetchAll(
        "SELECT * FROM orders WHERE user_id=? OR customer_email=? ORDER BY created_at DESC LIMIT 20",
        [Auth::id(), $user['email']]
    );
}

// Live stats from actual orders (not only cached customer fields)
$orderCount = 0;
$totalSpent = 0.0;
foreach ($orders as $o) {
    $st = strtolower((string)($o['status'] ?? ''));
    if ($st === 'cancelled') continue;
    $orderCount++;
    $totalSpent += (float)($o['total'] ?? 0);
}
if ($customer) {
    $orderCount = max($orderCount, (int)($customer['total_orders'] ?? 0));
    $totalSpent = max($totalSpent, (float)($customer['total_spent'] ?? 0));
}

$pageTitle = 'My Account';
ob_start();
?>
<section class="section account-page" style="padding-top:calc(var(--header-h) + 40px)">
<div class="container account-wrap">
  <div class="account-top">
    <div>
      <h1 class="account-title">My Account</h1>
      <p class="text-muted account-sub"><?= e($customer ? $customer['full_name'] : $user['name']) ?> · <?= e($user['email']) ?></p>
    </div>
    <div class="account-top-actions">
      <a href="/cart.php" class="btn btn-outline btn-sm">Cart</a>
      <a href="/my-orders.php" class="btn btn-outline btn-sm">My Orders</a>
      <a href="/checkout.php" class="btn btn-primary btn-sm">Checkout</a>
      <a href="/logout-customer.php" class="btn btn-outline btn-sm">Logout</a>
    </div>
  </div>

  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

  <div class="account-grid">
    <div class="account-card">
      <h3 class="account-card-title">My Details</h3>
      <form method="POST" class="account-form">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <div class="form-group"><label>Full Name</label>
          <input type="text" name="full_name" class="form-control" required value="<?= e($customer ? $customer['full_name'] : $user['name']) ?>">
        </div>
        <div class="form-group"><label>Email</label>
          <input type="email" class="form-control" value="<?= e($user['email']) ?>" disabled>
          <small class="text-muted">Email cannot be changed</small>
        </div>
        <div class="form-group"><label>Phone</label>
          <input type="text" name="phone" class="form-control" value="<?= e($customer ? ($customer['phone'] ?? '') : '') ?>">
        </div>
        <div class="form-group"><label>Address</label>
          <textarea name="address" class="form-control" rows="3"><?= e($customer ? ($customer['address'] ?? '') : '') ?></textarea>
        </div>
        <div class="form-row">
          <div class="form-group"><label>City</label>
            <input type="text" name="city" class="form-control" value="<?= e($customer ? ($customer['city'] ?? '') : '') ?>">
          </div>
          <div class="form-group"><label>Country</label>
            <input type="text" name="country" class="form-control" value="<?= e($customer && !empty($customer['country']) ? $customer['country'] : 'Bangladesh') ?>">
          </div>
        </div>
        <button type="submit" class="btn btn-primary">Save Details</button>
      </form>
    </div>

    <div class="account-side">
      <div class="account-card">
        <h3 class="account-card-title">Summary</h3>
        <div class="account-stats">
          <div class="account-stat">
            <div class="account-stat-val"><?= (int)$orderCount ?></div>
            <div class="account-stat-label">Orders</div>
          </div>
          <div class="account-stat">
            <div class="account-stat-val"><?= money($totalSpent) ?></div>
            <div class="account-stat-label">Total Spent</div>
          </div>
        </div>
        <p class="account-hint">Place new orders from <a href="/shop.php">Shop</a> → Cart → <a href="/checkout.php">Checkout</a>.</p>
      </div>

      <div class="account-card">
        <div class="account-card-head">
          <h3 class="account-card-title" style="margin:0">Recent Orders</h3>
          <a href="/my-orders.php" class="btn btn-sm btn-outline">All</a>
        </div>
        <?php if (empty($orders)): ?>
        <p class="text-muted" style="font-size:.9rem;margin:0">No orders yet.</p>
        <?php else: ?>
        <?php foreach (array_slice($orders, 0, 5) as $o): ?>
        <div class="account-order-row">
          <div>
            <a href="/order-detail.php?id=<?= (int)$o['id'] ?>" class="account-order-no"><?= e($o['order_number']) ?></a>
            <div class="text-muted account-order-meta"><?= formatDate($o['created_at']) ?> · <?= e(ucfirst($o['status'])) ?></div>
          </div>
          <div class="account-order-total"><?= money($o['total']) ?></div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
</section>
<style>
.account-wrap{max-width:960px}
.account-top{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:24px}
.account-title{font-size:1.75rem;margin:0 0 4px}
.account-sub{margin:0}
.account-top-actions{display:flex;gap:8px;flex-wrap:wrap}
.account-grid{display:grid;grid-template-columns:1.1fr .9fr;gap:20px;align-items:start}
.account-card{background:var(--color-surface,#fff);border:1px solid var(--color-border,#eee);border-radius:16px;padding:24px}
.account-card-title{margin:0 0 16px;font-size:1.05rem}
.account-card-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
.account-side{display:flex;flex-direction:column;gap:16px}
.account-stats{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.account-stat{padding:16px;background:var(--color-bg,#f8f8f8);border-radius:12px;text-align:center}
.account-stat-val{font-size:1.25rem;font-weight:800;color:#111}
.account-stat-label{font-size:.8rem;color:#888;margin-top:4px}
.account-hint{margin:14px 0 0;font-size:.8rem;color:#888;line-height:1.45}
.account-hint a{color:var(--color-primary,#e11d48);font-weight:600}
.account-order-row{display:flex;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--color-border,#eee);font-size:.875rem;gap:10px}
.account-order-row:last-child{border-bottom:0}
.account-order-no{color:var(--color-primary,#e11d48);font-weight:600;text-decoration:none}
.account-order-meta{font-size:.75rem;margin-top:2px}
.account-order-total{font-weight:700;white-space:nowrap}
.account-form .form-group{margin-bottom:14px}
@media(max-width:768px){
  .account-grid{grid-template-columns:1fr}
}
</style>
<?php $content=ob_get_clean(); require dirname(__DIR__).'/app/views/layouts/frontend.php'; ?>