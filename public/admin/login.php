<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
if (Auth::checkAdmin()) {
    redirect('/admin/');
}

$error = null;
$locked = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
    $pass = isset($_POST['password']) ? $_POST['password'] : '';
    $token = isset($_POST['_csrf']) ? $_POST['_csrf'] : '';

    if (!csrf_verify($token)) {
        $error = 'Invalid security token. Refresh the page.';
    } elseif (Security::isLoginLocked($email)) {
        $locked = true;
        $error = 'Too many failed attempts. Try again later.';
        Security::recordLoginAttempt($email, false);
    } else {
        if ($email && $pass && Auth::attemptAdmin($email, $pass)) {
            Security::recordLoginAttempt($email, true);
            flash('success', 'Welcome back!');
            redirect('/admin/');
        }
        Security::recordLoginAttempt($email, false);
        $error = 'Invalid credentials. Only Super Admin can access the admin panel.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login — AK</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<style><?= Theme::cssVariables() ?></style>
<style>
body{min-height:100vh;display:grid;place-items:center;background:var(--color-bg);padding:20px}
.login-box{width:100%;max-width:400px;background:var(--color-surface);border:1px solid var(--color-border);border-radius:20px;padding:36px;box-shadow:0 20px 50px rgba(0,0,0,.15)}
.login-box h1{font-size:1.5rem;margin-bottom:6px}
.login-box .sub{color:var(--color-text-muted);font-size:.9rem;margin-bottom:24px}
.sec-note{font-size:.75rem;color:var(--color-text-muted);margin-top:16px;text-align:center}
</style>
</head>
<body>
<div class="login-box">
  <div style="text-align:center;margin-bottom:8px"><a href="/" class="logo" style="font-size:2rem">A<span>K</span></a></div>
  <h1>Admin Login</h1>
  <p class="sub">Super Admin only — customers use store login</p>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <form method="POST" autocomplete="off">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <div class="form-group">
      <label>Email</label>
      <input type="email" name="email" class="form-control" required autofocus <?= $locked ? 'disabled' : '' ?> value="<?= e(isset($_POST['email'])?$_POST['email']:'') ?>">
    </div>
    <div class="form-group">
      <label>Password</label>
      <input type="password" name="password" class="form-control" required <?= $locked ? 'disabled' : '' ?>>
    </div>
    <button type="submit" class="btn btn-primary btn-block" <?= $locked ? 'disabled' : '' ?>><i class="fas fa-lock"></i> Sign In</button>
  </form>
  <p class="sec-note"><i class="fas fa-shield-alt"></i> Separate from customer accounts</p>
  <p style="text-align:center;margin-top:12px"><a href="/login.php" class="text-muted" style="font-size:.85rem">Customer login →</a></p>
</div>
</body>
</html>
