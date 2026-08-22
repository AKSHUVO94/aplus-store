<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/shop.php');
}

// CSRF
$token = isset($_POST['_csrf']) ? $_POST['_csrf'] : '';
if (!csrf_verify($token)) {
    flash('error', 'Security check failed. Please try again.');
    $ref = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/shop.php';
    redirect($ref);
}

$pid = (int)(isset($_POST['product_id']) ? $_POST['product_id'] : 0);
$qty = max(1, min(99, (int)(isset($_POST['qty']) ? $_POST['qty'] : 1)));
$size = isset($_POST['size']) ? trim($_POST['size']) : '';
$color = isset($_POST['color']) ? trim($_POST['color']) : '';
$selectedImage = isset($_POST['selected_image']) ? trim($_POST['selected_image']) : '';

$result = Cart::add($pid, $qty, $size, $color, $selectedImage);
if ($result === true) {
    flash('success', 'Added to cart!');
} else {
    flash('error', is_string($result) ? $result : 'Could not add product.');
}

$ref = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/shop.php';
redirect($ref);