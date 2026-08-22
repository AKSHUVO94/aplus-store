<?php
/**
 * Direct cart entry (works even if rewrite has issues)
 */
require_once dirname(__DIR__) . '/app/bootstrap.php';

// Handle POST actions when form posts to cart.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update') {
        $key = isset($_POST['key']) ? $_POST['key'] : '';
        $qty = (int)(isset($_POST['qty']) ? $_POST['qty'] : 1);
        Cart::update($key, $qty);
        redirect('/cart.php');
    }
    if (isset($_POST['action']) && $_POST['action'] === 'remove') {
        $key = isset($_POST['key']) ? $_POST['key'] : '';
        Cart::remove($key);
        flash('success', 'Item removed.');
        redirect('/cart.php');
    }
    if (isset($_POST['action']) && $_POST['action'] === 'change_options') {
        $key = isset($_POST['key']) ? $_POST['key'] : '';
        $size = isset($_POST['size']) ? trim($_POST['size']) : '';
        $color = isset($_POST['color']) ? trim($_POST['color']) : '';
        $result = Cart::changeOptions($key, $size, $color);
        if ($result === true) {
            flash('success', 'Size / color updated.');
        } else {
            flash('error', is_string($result) ? $result : 'Could not update options.');
        }
        redirect('/cart.php');
    }
}

view('frontend/cart');
