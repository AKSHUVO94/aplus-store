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
$orders = [];
if ($customer) {
    $orders = Database::fetchAll(
        "SELECT * FROM orders WHERE customer_id=? OR user_id=? OR customer_email=? ORDER BY created_at DESC LIMIT 10",
        [$customer['id'], Auth::id(), $user['email']]
    );
} else {
    $orders = Database::fetchAll(
        "SELECT * FROM orders WHERE user_id=? OR customer_email=? ORDER BY created_at DESC LIMIT 10",
        [Auth::id(), $user['email']]
    );
}

$pageTitle = 'My Account';
ob_start();
?>
<section class="section" style="padding-top:calc(var(--header-h) + 40px)">
<div class="container" style="max-width:900px">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:28px">
    <div>
      <h1 style="font-size:1.75rem;margin-bottom:4px">My Account</h1>
      <p class="text-muted"><?= e($customer ? $customer['full_name'] : $user['name']) ?> · <?= e($user['email']) ?></p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a href="/my-orders.php" class="btn btn-outline btn-sm">My Orders</a>
      <a href="/logout-customer.php" class="btn btn-outline btn-sm">Logout</a>
    </div>
  </div>

  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">
    <div style="background:var(--color-surface);border:1px solid var(--color-border);border-radius:16px;padding:24px">
      <h3 style="margin-bottom:16px">My Details</h3>
      <form method="POST">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <div class="form-group"><label>Full Name</label>
          <input type="text" name="full_name" class="form-control" required value="<?= e($customer ? $customer['full_name'] : $user['name']) ?>">
        </div>
        <div class="form-group"><label>Email</label>
          <input type="email" class="form-control" value="<?= e($user['email']) ?>" disabled>
          <small class="text-muted">Email cannot be changed</small>
        </div>
        <div class="form-group"><label>Phone</label>
          <input type="text" name="phone" class="form-control" value="<?= e($customer ? $customer['phone'] : '') ?>">
        </div>
        <div class="form-group"><label>Address</label>
          <textarea name="address" class="form-control" rows="2"><?= e($customer ? $customer['address'] : '') ?></textarea>
        </div>
        <div class="form-row">
          <div class="form-group"><label>City</label>
            <input type="text" name="city" class="form-control" value="<?= e($customer ? $customer['city'] : '') ?>">
          </div>
          <div class="form-group"><label>Country</label>
            <input type="text" name="country" class="form-control" value="<?= e($customer && $customer['country'] ? $customer['country'] : 'Bangladesh') ?>">
          </div>
        </div>
        <button type="submit" class="btn btn-primary">Save Details</button>
      </form>
    </div>

    <div>
      <div style="background:var(--color-surface);border:1px solid var(--color-border);border-radius:16px;padding:24px;margin-bottom:16px">
        <h3 style="margin-bottom:12px">Summary</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div style="padding:14px;background:var(--color-bg);border-radius:12px;text-align:center">
            <div style="font-size:1.4rem;font-weight:800"><?= $customer ? (int)$customer['total_orders'] : count($orders) ?></div>
            <div class="text-muted" style="font-size:.8rem">Orders</div>
          </div>
          <div style="padding:14px;background:var(--color-bg);border-radius:12px;text-align:center">
            <div style="font-size:1.1rem;font-weight:800"><?= money($customer ? $customer['total_spent'] : 0) ?></div>
            <div class="text-muted" style="font-size:.8rem">Total Spent</div>
          </div>
        </div>
      </div>

      <div style="background:var(--color-surface);border:1px solid var(--color-border);border-radius:16px;padding:24px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
          <h3>Recent Orders</h3>
          <a href="/my-orders.php" class="btn btn-sm btn-outline">All</a>
        </div>
        <?php if (empty($orders)): ?>
        <p class="text-muted" style="font-size:.9rem">No orders yet.</p>
        <?php else: ?>
        <?php foreach (array_slice($orders, 0, 5) as $o): ?>
        <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--color-border);font-size:.875rem;gap:8px;flex-wrap:wrap">
          <div>
            <a href="/order-detail.php?id=<?= $o['id'] ?>" style="color:var(--color-primary);font-weight:600"><?= e($o['order_number']) ?></a>
            <div class="text-muted" style="font-size:.75rem"><?= formatDate($o['created_at']) ?> · <?= e(ucfirst($o['status'])) ?></div>
          </div>
          <div style="font-weight:700"><?= money($o['total']) ?></div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
</section>
<style>@media(max-width:768px){div[style*="grid-template-columns:1fr 1fr"]{grid-template-columns:1fr!important}}</style>
<?php $content=ob_get_clean(); require dirname(__DIR__).'/app/views/layouts/frontend.php'; ?>
