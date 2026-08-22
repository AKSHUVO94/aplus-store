<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
if (Auth::check()) redirect('/my-account.php');

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify(isset($_POST['_csrf']) ? $_POST['_csrf'] : '')) {
        $error = 'Invalid security token.';
    } else {
        $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
        $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
        $phone = trim(isset($_POST['phone']) ? $_POST['phone'] : '');
        $address = trim(isset($_POST['address']) ? $_POST['address'] : '');
        $city = trim(isset($_POST['city']) ? $_POST['city'] : 'Dhaka');
        $pass = isset($_POST['password']) ? $_POST['password'] : '';
        $pass2 = isset($_POST['password2']) ? $_POST['password2'] : '';

        if ($name === '' || $email === '' || $pass === '') {
            $error = 'Please fill all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } elseif (strlen($pass) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif ($pass !== $pass2) {
            $error = 'Passwords do not match.';
        } else {
            $exists = Database::fetch("SELECT id FROM users WHERE email=?", [$email]);
            if ($exists) {
                $error = 'Email already registered. Please login.';
            } else {
                $uid = Database::insert('users', [
                    'name' => $name,
                    'email' => $email,
                    'password' => password_hash($pass, PASSWORD_DEFAULT),
                    'phone' => $phone ?: null,
                    'address' => $address ?: null,
                    'city' => $city ?: null,
                    'role_id' => 4,
                    'status' => 'active',
                ]);
                Customer::upsert([
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'address' => $address,
                    'city' => $city,
                ], $uid);
                Auth::attempt($email, $pass);
                flash('success', 'Welcome to AK! Your account is ready.');
                redirect('/my-account.php');
            }
        }
    }
}
$pageTitle = 'Create Account';
ob_start();
?>
<section class="section" style="padding-top:calc(var(--header-h) + 40px);min-height:70vh">
<div class="container" style="max-width:480px">
  <h1 style="margin-bottom:8px;font-size:1.75rem">Create Account</h1>
  <p class="text-muted" style="margin-bottom:24px">Save your details, track orders & invoices.</p>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <form method="POST" style="background:var(--color-surface);border:1px solid var(--color-border);border-radius:16px;padding:28px">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <div class="form-group"><label>Full Name *</label><input type="text" name="name" class="form-control" required value="<?= e(isset($_POST['name'])?$_POST['name']:'') ?>"></div>
    <div class="form-group"><label>Email *</label><input type="email" name="email" class="form-control" required value="<?= e(isset($_POST['email'])?$_POST['email']:'') ?>"></div>
    <div class="form-group"><label>Phone</label><input type="text" name="phone" class="form-control" value="<?= e(isset($_POST['phone'])?$_POST['phone']:'') ?>" placeholder="01XXXXXXXXX"></div>
    <div class="form-group"><label>Address</label><textarea name="address" class="form-control" rows="2"><?= e(isset($_POST['address'])?$_POST['address']:'') ?></textarea></div>
    <div class="form-group"><label>City</label><input type="text" name="city" class="form-control" value="<?= e(isset($_POST['city'])?$_POST['city']:'Dhaka') ?>"></div>
    <div class="form-group"><label>Password *</label><input type="password" name="password" class="form-control" required minlength="6"></div>
    <div class="form-group"><label>Confirm Password *</label><input type="password" name="password2" class="form-control" required></div>
    <button type="submit" class="btn btn-primary btn-block">Register</button>
  </form>
  <p class="text-center text-muted" style="margin-top:16px;font-size:.9rem">Already have an account? <a href="/login.php" style="color:var(--color-primary)">Login</a></p>
</div>
</section>
<?php $content=ob_get_clean(); require dirname(__DIR__).'/app/views/layouts/frontend.php'; ?>
