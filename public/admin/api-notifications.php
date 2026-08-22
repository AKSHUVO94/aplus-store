<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
header('Content-Type: application/json');
if (!Auth::checkAdmin() || !Auth::isAdmin()) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$pending = (int) Database::fetch("SELECT COUNT(*) c FROM orders WHERE status = 'pending'")['c'];
$newMsgs = 0;
try {
    $newMsgs = (int) Database::fetch("SELECT COUNT(*) c FROM contact_messages WHERE status = 'new'")['c'];
} catch (Exception $e) {}

$recent = Database::fetchAll(
    "SELECT id, order_number, customer_name, total, status, created_at
     FROM orders ORDER BY created_at DESC LIMIT 8"
);

$out = [];
foreach ($recent as $o) {
    $out[] = [
        'id' => (int)$o['id'],
        'order_number' => $o['order_number'],
        'customer_name' => $o['customer_name'],
        'total' => money($o['total']),
        'status' => $o['status'],
        'time' => timeAgo($o['created_at']),
        'url' => '/admin/order-view.php?id=' . (int)$o['id'],
        'is_new' => $o['status'] === 'pending',
    ];
}

echo json_encode([
    'pending_orders' => $pending,
    'new_messages' => $newMsgs,
    'total_badge' => $pending + $newMsgs,
    'orders' => $out,
]);
