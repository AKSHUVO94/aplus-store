<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/');
}

$token = isset($_POST['_csrf']) ? $_POST['_csrf'] : '';
if (!csrf_verify($token)) {
    flash('error', 'Invalid request. Please try again.');
    redirect(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/');
}

if (!Auth::checkCustomer()) {
    flash('error', 'Please login to write a review.');
    redirect('/login.php?redirect=' . urlencode($_SERVER['HTTP_REFERER'] ?? '/'));
}

$productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 5;
$comment = trim(isset($_POST['comment']) ? (string)$_POST['comment'] : '');
$slug = trim(isset($_POST['product_slug']) ? (string)$_POST['product_slug'] : '');

$rating = max(1, min(5, $rating));
$back = $slug !== '' ? '/index.php?route=product&slug=' . urlencode($slug) : '/';

if ($productId < 1 || $comment === '') {
    flash('error', 'Please enter your review comment.');
    redirect($back);
}

if (strlen($comment) < 5) {
    flash('error', 'Review is too short.');
    redirect($back);
}

$product = Database::fetch("SELECT id, slug FROM products WHERE id=? AND status='active'", array($productId));
if (!$product) {
    flash('error', 'Product not found.');
    redirect('/');
}

$user = Auth::user();
$name = isset($user['name']) ? $user['name'] : 'Customer';
$email = isset($user['email']) ? $user['email'] : '';
$customerId = Auth::id();

try {
    $cust = Customer::forLoggedIn();
    if ($cust) {
        $customerId = (int)$cust['id'];
        if (!empty($cust['name'])) $name = $cust['name'];
        if (!empty($cust['email'])) $email = $cust['email'];
    }
} catch (Exception $e) {}

// Ensure table
try {
    Database::query("CREATE TABLE IF NOT EXISTS `product_reviews` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `product_id` INT UNSIGNED NOT NULL,
      `customer_id` INT UNSIGNED DEFAULT NULL,
      `customer_name` VARCHAR(120) NOT NULL,
      `customer_email` VARCHAR(160) DEFAULT NULL,
      `rating` TINYINT UNSIGNED NOT NULL DEFAULT 5,
      `comment` TEXT NOT NULL,
      `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
      `show_on_home` TINYINT(1) NOT NULL DEFAULT 0,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

// Always insert a new review (do not replace previous reviews)
try {
    Database::insert('product_reviews', array(
        'product_id' => $productId,
        'customer_id' => $customerId,
        'customer_name' => $name,
        'customer_email' => $email,
        'rating' => $rating,
        'comment' => $comment,
        'status' => 'pending',
        'show_on_home' => 0,
    ));
    flash('success', 'Thank you! Your review was submitted and is waiting for admin approval.');
} catch (Exception $e) {
    flash('error', 'Could not save review. Please try again.');
}

redirect('/index.php?route=product&slug=' . urlencode($product['slug']));