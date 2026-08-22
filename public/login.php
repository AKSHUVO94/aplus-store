<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';

if (Auth::checkCustomer()) {
    redirect('/my-account.php');
}

$error = null;
$info = null;
if (Auth::checkAdmin() && !Auth::checkCustomer()) {
    $info = 'You are logged in as Admin. Customer login is separate — enter a customer account below.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['_csrf']) ? $_POST['_csrf'] : '';
    if (!csrf_verify($token)) {
        $error = 'Invalid security token. Please refresh the page.';
    } else {
        $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
        $pass = isset($_POST['password']) ? $_POST['password'] : '';
        if ($email && $pass && Auth::attemptCustomer($email, $pass)) {
            redirect('/my-account.php');
        }
        $error = 'Invalid customer email or password. Admin accounts must use Admin Login.';
    }
}

$pageTitle = 'Login';
ob_start();
?>
<section class="section" style="padding-top:calc(var(--header-h) + 40px);min-height:70vh">
<div class="container" style="max-width:420px">
  <h1 style="margin-bottom:8px;font-size:1.75rem">Customer Login</h1>
  <p class="text-muted" style="margin-bottom:24px">View your details, orders & invoices.</p>
  <?php if ($info): ?><div class="alert alert-success"><?= e($info) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <?php if ($m = flash('success')): ?><div class="alert alert-success"><?= e($m) ?></div><?php endif; ?>
  <form method="POST" style="background:var(--color-surface);border:1px solid var(--color-border);border-radius:16px;padding:28px">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <div class="form-group">
      <label>Email</label>
      <input type="email" name="email" class="form-control" required autofocus value="<?= e(isset($_POST['email']) ? $_POST['email'] : '') ?>">
    </div>
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-primary btn-block">Login as Customer</button>
  </form>
  <p class="text-center text-muted" style="margin-top:16px;font-size:.9rem">
    No account? <a href="/register.php" style="color:var(--color-primary)">Register</a>
    · <a href="/track-order.php" style="color:var(--color-primary)">Track Order</a>
  </p>
  <p class="text-center text-muted" style="margin-top:8px;font-size:.8rem">
    Staff? <a href="/admin/login.php" style="color:var(--color-primary)">Admin Login</a>
  </p>
</div>
</section>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/app/views/layouts/frontend.php';
