<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require dirname(__DIR__) . '/app/controllers/CheckoutController.php';
    exit;
}

view('frontend/checkout');
