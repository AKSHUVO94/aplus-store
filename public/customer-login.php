<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';

if (Auth::check() && !Auth::isAdmin()) {
    redirect('/my-orders.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
    $pass = isset($_POST['password']) ? $_POST['password'] : '';
    if ($email && $pass && Auth::attempt($email, $pass)) {
        if (Auth::isAdmin()) {
            // admin can also view but redirect admin to admin
            redirect('/admin/');
        }
        redirect('/my-orders.php');
    }
    $error = 'Invalid email or password.';
    if (Auth::check()) Auth::logout();
}
$viewError = $error;
view('frontend/customer-login', ['error' => $error]);
