<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';

$uri = parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
$uri = rtrim($uri, '/') ?: '/';

// Strip .php so /order-success.php maps to /order-success
if (substr($uri, -4) === '.php') {
    $uri = substr($uri, 0, -4);
    if ($uri === '') $uri = '/';
}

// Query string routes (fallback)
if (isset($_GET['route'])) {
    $r = $_GET['route'];
    if ($r === 'about') { view('frontend/about'); exit; }
    if ($r === 'contact') { view('frontend/contact'); exit; }
    if ($r === 'category' && !empty($_GET['slug'])) {
        $_GET['category'] = $_GET['slug'];
        view('frontend/shop');
        exit;
    }
    if ($r === 'cart') { view('frontend/cart'); exit; }
    if ($r === 'shop') { view('frontend/shop'); exit; }
    if ($r === 'product' && !empty($_GET['slug'])) {
        view('frontend/product');
        exit;
    }
    if ($r === 'order-success') { view('frontend/order-success'); exit; }
}

// Cart actions
if ($uri === '/cart/add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid = (int)(isset($_POST['product_id']) ? $_POST['product_id'] : 0);
    $qty = max(1, (int)(isset($_POST['qty']) ? $_POST['qty'] : 1));
    $size = isset($_POST['size']) ? trim($_POST['size']) : '';
    $color = isset($_POST['color']) ? trim($_POST['color']) : '';
    if (Cart::add($pid, $qty, $size, $color)) {
        flash('success', 'Added to cart!');
    } else {
        flash('error', 'Product not found.');
    }
    $ref = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/shop.php';
    redirect($ref);
}

if ($uri === '/cart/update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $key = isset($_POST['key']) ? $_POST['key'] : '';
    $qty = (int)(isset($_POST['qty']) ? $_POST['qty'] : 1);
    Cart::update($key, $qty);
    redirect('/cart.php');
}

if ($uri === '/cart/remove' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $key = isset($_POST['key']) ? $_POST['key'] : '';
    Cart::remove($key);
    flash('success', 'Item removed.');
    redirect('/cart.php');
}

if (($uri === '/checkout') && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require dirname(__DIR__) . '/app/controllers/CheckoutController.php';
    exit;
}

$routes = array(
    '/' => 'frontend/home',
    '/shop' => 'frontend/shop',
    '/cart' => 'frontend/cart',
    '/checkout' => 'frontend/checkout',
    '/about' => 'frontend/about',
    '/contact' => 'frontend/contact',
    '/order-success' => 'frontend/order-success',
    '/login' => 'frontend/home', // use login.php file
    '/track-order' => 'frontend/home',
);

if (preg_match('#^/product/([a-z0-9\-]+)$#', $uri, $m)) {
    $_GET['slug'] = $m[1];
    $view = 'frontend/product';
} elseif (preg_match('#^/category/([a-z0-9\-]+)$#', $uri, $m)) {
    $_GET['category'] = $m[1];
    $view = 'frontend/shop';
} elseif (isset($routes[$uri])) {
    $view = $routes[$uri];
} else {
    http_response_code(404);
    $view = 'frontend/404';
}

view($view);