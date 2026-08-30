<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['coupon_action']) ? $_POST['coupon_action'] : '';
    if ($action === 'apply' || $action === 'remove') {
        if ($action === 'remove') {
            Coupon::remove();
            flash('success', 'Coupon removed.');
        } else {
            $code = isset($_POST['coupon_code']) ? $_POST['coupon_code'] : '';
            $res = Coupon::apply($code);
            if ($res === true) {
                flash('success', 'Coupon applied: ' . Coupon::current()['code']);
            } else {
                flash('error', is_string($res) ? $res : 'Invalid coupon.');
            }
        }
        redirect('/checkout.php');
    }
    require dirname(__DIR__) . '/app/controllers/CheckoutController.php';
    exit;
}

view('frontend/checkout');