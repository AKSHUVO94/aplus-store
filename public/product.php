<?php
require dirname(__DIR__) . '/app/bootstrap.php';
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
if ($slug === '') {
    http_response_code(404);
    view('frontend/404');
    exit;
}
$_GET['slug'] = $slug;
view('frontend/product');