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
$newChats = 0;
try {
    $newMsgs = (int) Database::fetch("SELECT COUNT(*) c FROM contact_messages WHERE status = 'new'")['c'];
} catch (Exception $e) {}
try {
    $newChats = (int) Database::fetch("SELECT COUNT(*) c FROM chat_messages WHERE sender='visitor' AND is_read=0")['c'];
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
        'type' => 'order',
    ];
}

// Live chat previews
$chats = [];
try {
    $chats = Database::fetchAll(
        "SELECT c.id, c.visitor_name, c.guest_no, c.user_id,
            (SELECT message FROM chat_messages WHERE conversation_id=c.id ORDER BY id DESC LIMIT 1) AS last_msg,
            (SELECT COUNT(*) FROM chat_messages WHERE conversation_id=c.id AND sender='visitor' AND is_read=0) AS unread,
            c.last_message_at
         FROM chat_conversations c
         WHERE EXISTS (
           SELECT 1 FROM chat_messages m WHERE m.conversation_id=c.id AND m.sender='visitor' AND m.is_read=0
         )
         ORDER BY c.last_message_at DESC LIMIT 8"
    );
} catch (Exception $e) { $chats = []; }

$chatOut = [];
foreach ($chats as $c) {
    $name = !empty($c['visitor_name']) ? $c['visitor_name'] : (!empty($c['guest_no']) ? ('Guest ' . $c['guest_no']) : 'Guest');
    $chatOut[] = [
        'id' => (int)$c['id'],
        'title' => 'Live Chat: ' . $name,
        'preview' => $c['last_msg'] ?? '',
        'time' => !empty($c['last_message_at']) ? timeAgo($c['last_message_at']) : '',
        'url' => '/admin/live-chat.php',
        'is_new' => true,
        'type' => 'chat',
        'unread' => (int)($c['unread'] ?? 0),
    ];
}

echo json_encode([
    'pending_orders' => $pending,
    'new_messages' => $newMsgs,
    'new_chats' => $newChats,
    'total_badge' => $pending + $newMsgs + $newChats,
    'orders' => $out,
    'chats' => $chatOut,
]);