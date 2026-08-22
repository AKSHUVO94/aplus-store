<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
$slug = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (is_array($input) && isset($input['theme'])) $slug = trim($input['theme']);
    elseif (isset($_POST['theme'])) $slug = trim($_POST['theme']);
}
$isJson = !empty($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'json') !== false;
if ($slug && Theme::activate($slug)) {
    if ($isJson) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'theme'=>$slug]); exit; }
    $ref = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/';
    redirect($ref);
}
if ($isJson) { header('Content-Type: application/json'); echo json_encode(['success'=>false]); exit; }
redirect('/');
