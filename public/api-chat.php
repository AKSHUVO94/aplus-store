<?php
/**
 * Live chat API — visitor + admin messages
 */
require_once dirname(__DIR__) . '/app/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function chat_json($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function chat_ensure_tables() {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        Database::query("CREATE TABLE IF NOT EXISTS chat_conversations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            session_key VARCHAR(64) NOT NULL DEFAULT '',
            user_id INT UNSIGNED NULL DEFAULT NULL,
            guest_no INT UNSIGNED NULL DEFAULT NULL,
            visitor_name VARCHAR(120) DEFAULT '',
            visitor_email VARCHAR(180) DEFAULT '',
            status VARCHAR(20) DEFAULT 'open',
            last_message_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY idx_session (session_key),
            KEY idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        Database::query("CREATE TABLE IF NOT EXISTS chat_messages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            conversation_id INT UNSIGNED NOT NULL,
            sender VARCHAR(20) NOT NULL,
            message TEXT NOT NULL,
            is_read TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY idx_conv (conversation_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {}

    // Soft column upgrades
    foreach (array(
        "ALTER TABLE chat_conversations ADD COLUMN user_id INT UNSIGNED NULL DEFAULT NULL",
        "ALTER TABLE chat_conversations ADD COLUMN guest_no INT UNSIGNED NULL DEFAULT NULL",
        "ALTER TABLE chat_conversations ADD COLUMN visitor_name VARCHAR(120) DEFAULT ''",
        "ALTER TABLE chat_conversations ADD COLUMN visitor_email VARCHAR(180) DEFAULT ''",
        "ALTER TABLE chat_conversations ADD COLUMN status VARCHAR(20) DEFAULT 'open'",
        "ALTER TABLE chat_conversations ADD COLUMN last_message_at DATETIME NULL",
        "ALTER TABLE chat_conversations ADD COLUMN session_key VARCHAR(64) NOT NULL DEFAULT ''",
    ) as $sql) {
        try { Database::query($sql); } catch (Exception $e) {}
    }
    // Drop unique session constraint if present (causes insert/update failures)
    foreach (array('uq_session', 'session_key', 'chat_conversations_session_key_unique') as $idx) {
        try { Database::query("ALTER TABLE chat_conversations DROP INDEX `{$idx}`"); } catch (Exception $e) {}
    }
    try { Database::query("ALTER TABLE chat_conversations ADD INDEX idx_session (session_key)"); } catch (Exception $e) {}
    // ENUM(open,closed) breaks pending/blocked — force VARCHAR
    try { Database::query("ALTER TABLE chat_conversations MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'open'"); } catch (Exception $e) {}
    try { Database::query("ALTER TABLE chat_messages MODIFY COLUMN sender VARCHAR(20) NOT NULL"); } catch (Exception $e) {}
    try { Database::query("ALTER TABLE chat_conversations ADD COLUMN visitor_hidden TINYINT(1) NOT NULL DEFAULT 0"); } catch (Exception $e) {}
    try { Database::query("ALTER TABLE chat_conversations ADD COLUMN expires_at DATETIME NULL DEFAULT NULL"); } catch (Exception $e) {}
}

function chat_purge_expired_guests() {
    try {
        $rows = Database::fetchAll(
            "SELECT id FROM chat_conversations
             WHERE (user_id IS NULL OR user_id = 0)
               AND (
                 (expires_at IS NOT NULL AND expires_at < NOW())
                 OR (expires_at IS NULL AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR))
               )
             LIMIT 100"
        );
        foreach ($rows ?: array() as $r) {
            $id = (int)$r['id'];
            Database::query("DELETE FROM chat_messages WHERE conversation_id=?", array($id));
            Database::query("DELETE FROM chat_conversations WHERE id=?", array($id));
        }
    } catch (Exception $e) {}
}

function chat_is_guest_conv($c) {
    return empty($c['user_id']);
}


function chat_session_key() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    if (empty($_SESSION['chat_key'])) {
        try {
            $_SESSION['chat_key'] = bin2hex(random_bytes(16));
        } catch (Exception $e) {
            $_SESSION['chat_key'] = md5(uniqid('chat', true));
        }
    }
    return (string)$_SESSION['chat_key'];
}

function chat_next_guest_no() {
    try {
        $row = Database::fetch("SELECT COALESCE(MAX(guest_no), 0) AS m FROM chat_conversations");
        return (int)($row['m'] ?? 0) + 1;
    } catch (Exception $e) {
        return (int)time() % 100000;
    }
}

function chat_display_name($c) {
    if (!is_array($c)) return 'Guest';
    if (!empty($c['user_id']) && !empty($c['visitor_name'])) return $c['visitor_name'];
    if (!empty($c['guest_no'])) return 'Guest ' . (int)$c['guest_no'];
    if (!empty($c['visitor_name'])) return $c['visitor_name'];
    return 'Guest';
}

function chat_current_user() {
    $userId = 0;
    $name = '';
    $email = '';
    try {
        if (class_exists('Auth') && method_exists('Auth', 'checkCustomer') && Auth::checkCustomer()) {
            $u = Auth::user();
            if (is_array($u)) {
                $userId = isset($u['id']) ? (int)$u['id'] : 0;
                $name = isset($u['name']) ? trim((string)$u['name']) : '';
                $email = isset($u['email']) ? trim((string)$u['email']) : '';
            }
        }
    } catch (Exception $e) {}
    return array($userId, $name, $email);
}

function chat_get_or_create_conversation($name = '', $email = '') {
    $key = chat_session_key();
    list($userId, $custName, $custEmail) = chat_current_user();
    if ($custName !== '') $name = $custName;
    if ($custEmail !== '') $email = $custEmail;

    if ($userId > 0) {
        $row = Database::fetch(
            'SELECT * FROM chat_conversations WHERE user_id = ? AND (visitor_hidden IS NULL OR visitor_hidden = 0) ORDER BY id DESC LIMIT 1',
            array($userId)
        );
        if ($row) {
            $upd = array();
            if ($name !== '' && $row['visitor_name'] !== $name) $upd['visitor_name'] = $name;
            if ($email !== '' && (string)$row['visitor_email'] !== $email) $upd['visitor_email'] = $email;
            if ((string)$row['session_key'] !== $key) $upd['session_key'] = $key;
            if ($upd) {
                try {
                    Database::update('chat_conversations', $upd, 'id=?', array((int)$row['id']));
                    $row = array_merge($row, $upd);
                } catch (Exception $e) {}
            }
            return $row;
        }
        $cdata = array(
            'session_key' => $key . '_u' . $userId,
            'user_id' => $userId,
            'visitor_name' => $name !== '' ? $name : 'Customer',
            'visitor_email' => $email,
            'status' => 'pending',
            'last_message_at' => date('Y-m-d H:i:s'),
            'visitor_hidden' => 0,
        );
        try {
            $id = Database::insert('chat_conversations', $cdata);
        } catch (Exception $e) {
            $cdata['status'] = 'open';
            $id = Database::insert('chat_conversations', $cdata);
        }
        return Database::fetch('SELECT * FROM chat_conversations WHERE id=?', array((int)$id));
    }

    $row = Database::fetch(
        'SELECT * FROM chat_conversations WHERE session_key = ? AND (user_id IS NULL OR user_id = 0) ORDER BY id DESC LIMIT 1',
        array($key)
    );
    if ($row) {
        if (empty($row['guest_no'])) {
            $gno = chat_next_guest_no();
            try {
                Database::update('chat_conversations', array(
                    'guest_no' => $gno,
                    'visitor_name' => 'Guest ' . $gno,
                ), 'id=?', array((int)$row['id']));
                $row['guest_no'] = $gno;
                $row['visitor_name'] = 'Guest ' . $gno;
            } catch (Exception $e) {}
        }
        return $row;
    }

    $gno = chat_next_guest_no();
    $data = array(
        'session_key' => $key,
        'guest_no' => $gno,
        'visitor_name' => 'Guest ' . $gno,
        'visitor_email' => $email,
        'status' => 'pending',
        'last_message_at' => date('Y-m-d H:i:s'),
        'expires_at' => date('Y-m-d H:i:s', time() + 24 * 3600),
        'visitor_hidden' => 0,
    );
    try {
        $id = Database::insert('chat_conversations', $data);
    } catch (Exception $e) {
        // Fallback if ENUM only allows open/closed
        $data['status'] = 'open';
        $id = Database::insert('chat_conversations', $data);
    }
    return Database::fetch('SELECT * FROM chat_conversations WHERE id=?', array((int)$id));
}

function chat_find_conversation_for_visitor($preferId = 0) {
    list($userId) = chat_current_user();
    if ($userId > 0) {
        $row = Database::fetch(
            'SELECT * FROM chat_conversations WHERE user_id = ? AND (visitor_hidden IS NULL OR visitor_hidden = 0) ORDER BY id DESC LIMIT 1',
            array($userId)
        );
        if ($row) return $row;
    }
    $key = chat_session_key();
    $row = Database::fetch(
        'SELECT * FROM chat_conversations WHERE session_key = ? AND (user_id IS NULL OR user_id = 0) ORDER BY id DESC LIMIT 1',
        array($key)
    );
    if ($row) return $row;

    $preferId = (int)$preferId;
    if ($preferId > 0) {
        $row = Database::fetch('SELECT * FROM chat_conversations WHERE id = ?', array($preferId));
        if ($row) {
            // Logged-in customer must not inherit a Guest thread from localStorage
            if ($userId > 0 && empty($row['user_id'])) {
                return null;
            }
            if (!empty($row['user_id']) && (int)$row['user_id'] !== $userId) {
                return null;
            }
            if (empty($row['user_id']) && $userId <= 0) {
                try {
                    Database::update('chat_conversations', array('session_key' => $key), 'id=?', array($preferId));
                    $row['session_key'] = $key;
                } catch (Exception $e) {}
            }
            return $row;
        }
    }
    return null;
}

try {
    chat_ensure_tables();

    $action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');
    $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';

    if ($action === 'config' && $method === 'GET') {
        list($userId, $custName) = chat_current_user();
        chat_json(array(
            'ok' => true,
            'enabled' => setting('chat_enabled', '1') === '1',
            'greeting' => setting('chat_greeting', 'Hi there! Let us know if we can help you with anything at all.'),
            'title' => setting('chat_title', 'Hi there!'),
            'livechat' => setting('chat_livechat_enabled', '1') === '1',
            'messenger' => setting('chat_messenger_enabled', '1') === '1',
            'whatsapp' => setting('chat_whatsapp_enabled', '1') === '1',
            'messenger_url' => setting('chat_messenger_url', setting('social_facebook_url', '')),
            'whatsapp_url' => setting('chat_whatsapp_url', setting('social_whatsapp_url', '')),
            'is_customer' => $userId > 0,
            'customer_name' => $custName,
        ));
    }

    if ($action === 'admin_list' && $method === 'GET') {
        Auth::requireAdmin();
        $rows = Database::fetchAll(
            "SELECT c.*,
                (SELECT message FROM chat_messages WHERE conversation_id=c.id ORDER BY id DESC LIMIT 1) AS last_msg,
                (SELECT COUNT(*) FROM chat_messages WHERE conversation_id=c.id AND sender='visitor' AND is_read=0) AS unread
             FROM chat_conversations c
             ORDER BY COALESCE(c.last_message_at, c.created_at) DESC
             LIMIT 150"
        );
        $items = array();
        foreach ($rows ?: array() as $c) {
            if (empty($c['user_id']) && empty($c['guest_no'])) {
                try {
                    $gno = chat_next_guest_no();
                    Database::update('chat_conversations', array(
                        'guest_no' => $gno,
                        'visitor_name' => 'Guest ' . $gno,
                    ), 'id=?', array((int)$c['id']));
                    $c['guest_no'] = $gno;
                    $c['visitor_name'] = 'Guest ' . $gno;
                } catch (Exception $e) {}
            }
            $c['display_name'] = chat_display_name($c);
            $items[] = $c;
        }
        chat_json(array('ok' => true, 'items' => $items));
    }

    if ($action === 'admin_thread' && $method === 'GET') {
        Auth::requireAdmin();
        $cid = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
        $conv = Database::fetch('SELECT * FROM chat_conversations WHERE id=?', array($cid));
        if (!$conv) chat_json(array('ok' => false, 'error' => 'Not found'), 404);
        try {
            Database::query("UPDATE chat_messages SET is_read=1 WHERE conversation_id=? AND sender='visitor'", array($cid));
        } catch (Exception $e) {}
        $msgs = Database::fetchAll(
            'SELECT * FROM chat_messages WHERE conversation_id=? ORDER BY id ASC LIMIT 500',
            array($cid)
        );
        $conv['display_name'] = chat_display_name($conv);
        chat_json(array('ok' => true, 'conversation' => $conv, 'messages' => $msgs ?: array()));
    }

    if ($action === 'admin_reply' && $method === 'POST') {
        Auth::requireAdmin();
        $cid = (int)(isset($_POST['conversation_id']) ? $_POST['conversation_id'] : 0);
        $msg = trim(isset($_POST['message']) ? $_POST['message'] : '');
        if ($cid <= 0 || $msg === '') chat_json(array('ok' => false, 'error' => 'Empty message'));
        $conv = Database::fetch('SELECT id FROM chat_conversations WHERE id=?', array($cid));
        if (!$conv) chat_json(array('ok' => false, 'error' => 'Not found'), 404);
        Database::insert('chat_messages', array(
            'conversation_id' => $cid,
            'sender' => 'admin',
            'message' => $msg,
            'is_read' => 0,
        ));
        Database::update('chat_conversations', array(
            'last_message_at' => date('Y-m-d H:i:s'),
            'status' => 'open',
        ), 'id=?', array($cid));
        chat_json(array('ok' => true));
    }


    if ($action === 'admin_approve' && $method === 'POST') {
        Auth::requireAdmin();
        $cid = (int)(isset($_POST['conversation_id']) ? $_POST['conversation_id'] : 0);
        if ($cid <= 0) chat_json(array('ok' => false, 'error' => 'Invalid'));
        Database::update('chat_conversations', array('status' => 'open'), 'id=?', array($cid));
        $note = trim(setting('chat_approved_text', 'You are now connected with our support team. How can we help?'));
        if ($note !== '') {
            Database::insert('chat_messages', array(
                'conversation_id' => $cid,
                'sender' => 'admin',
                'message' => $note,
                'is_read' => 0,
            ));
            Database::update('chat_conversations', array('last_message_at' => date('Y-m-d H:i:s')), 'id=?', array($cid));
        }
        chat_json(array('ok' => true, 'status' => 'open'));
    }

    if ($action === 'admin_block' && $method === 'POST') {
        Auth::requireAdmin();
        $cid = (int)(isset($_POST['conversation_id']) ? $_POST['conversation_id'] : 0);
        if ($cid <= 0) chat_json(array('ok' => false, 'error' => 'Invalid'));
        Database::update('chat_conversations', array('status' => 'blocked'), 'id=?', array($cid));
        Database::insert('chat_messages', array(
            'conversation_id' => $cid,
            'sender' => 'admin',
            'message' => 'This chat has been closed by support.',
            'is_read' => 0,
        ));
        chat_json(array('ok' => true, 'status' => 'blocked'));
    }

    if ($action === 'admin_unblock' && $method === 'POST') {
        Auth::requireAdmin();
        $cid = (int)(isset($_POST['conversation_id']) ? $_POST['conversation_id'] : 0);
        if ($cid <= 0) chat_json(array('ok' => false, 'error' => 'Invalid'));
        Database::update('chat_conversations', array('status' => 'open'), 'id=?', array($cid));
        chat_json(array('ok' => true, 'status' => 'open'));
    }

    if ($action === 'poll' && $method === 'GET') {
        $preferId = (int)(isset($_GET['cid']) ? $_GET['cid'] : 0);
        $conv = chat_find_conversation_for_visitor($preferId);
        if (!$conv) {
            list($uid) = chat_current_user();
            chat_json(array(
                'ok' => true,
                'messages' => array(),
                'conversation_id' => 0,
                'is_guest' => ($uid <= 0),
                'is_customer' => ($uid > 0),
            ));
        }
        // Customer deleted on their end
        if (!empty($conv['visitor_hidden'])) {
            list($uid) = chat_current_user();
            chat_json(array(
                'ok' => true,
                'messages' => array(),
                'conversation_id' => 0,
                'cleared' => true,
                'is_guest' => ($uid <= 0),
                'is_customer' => ($uid > 0),
            ));
        }
        // Guest expired
        if (chat_is_guest_conv($conv)) {
            $exp = !empty($conv['expires_at']) ? strtotime($conv['expires_at']) : 0;
            if ($exp && $exp < time()) {
                try {
                    Database::query("DELETE FROM chat_messages WHERE conversation_id=?", array((int)$conv['id']));
                    Database::query("DELETE FROM chat_conversations WHERE id=?", array((int)$conv['id']));
                } catch (Exception $e) {}
                chat_json(array('ok' => true, 'messages' => array(), 'conversation_id' => 0, 'expired' => true, 'is_guest' => true));
            }
        }
        $after = (int)(isset($_GET['after']) ? $_GET['after'] : 0);
        $msgs = Database::fetchAll(
            'SELECT id, sender, message, created_at FROM chat_messages WHERE conversation_id=? AND id>? ORDER BY id ASC LIMIT 200',
            array((int)$conv['id'], $after)
        );
        try {
            Database::query("UPDATE chat_messages SET is_read=1 WHERE conversation_id=? AND sender='admin' AND is_read=0", array((int)$conv['id']));
        } catch (Exception $e) {}
        $isGuest = chat_is_guest_conv($conv);
        $expiresAt = !empty($conv['expires_at']) ? $conv['expires_at'] : null;
        list($uidNow) = chat_current_user();
        chat_json(array(
            'ok' => true,
            'messages' => $msgs ?: array(),
            'conversation_id' => (int)$conv['id'],
            'display_name' => chat_display_name($conv),
            'is_guest' => $isGuest && $uidNow <= 0,
            'is_customer' => $uidNow > 0,
            'expires_at' => ($isGuest && $uidNow <= 0) ? $expiresAt : null,
            'status' => isset($conv['status']) ? $conv['status'] : 'open',
        ));
    }



    if ($action === 'delete_mine' && $method === 'POST') {
        // Customer/guest: hide or wipe on their side only
        $preferId = (int)(isset($_POST['cid']) ? $_POST['cid'] : 0);
        $conv = chat_find_conversation_for_visitor($preferId);
        if (!$conv) {
            chat_json(array('ok' => true, 'cleared' => true));
        }
        $cid = (int)$conv['id'];
        if (chat_is_guest_conv($conv)) {
            // Guest: full delete of this conversation
            Database::query("DELETE FROM chat_messages WHERE conversation_id=?", array($cid));
            Database::query("DELETE FROM chat_conversations WHERE id=?", array($cid));
        } else {
            // Registered customer: hide only from customer end (admin still sees)
            try {
                Database::update('chat_conversations', array('visitor_hidden' => 1), 'id=?', array($cid));
            } catch (Exception $e) {
                Database::query("DELETE FROM chat_messages WHERE conversation_id=?", array($cid));
                Database::query("DELETE FROM chat_conversations WHERE id=?", array($cid));
            }
        }
        chat_json(array('ok' => true, 'cleared' => true));
    }

    if ($action === 'admin_delete' && $method === 'POST') {
        Auth::requireAdmin();
        $cid = (int)(isset($_POST['conversation_id']) ? $_POST['conversation_id'] : 0);
        if ($cid <= 0) chat_json(array('ok' => false, 'error' => 'Invalid'));
        Database::query("DELETE FROM chat_messages WHERE conversation_id=?", array($cid));
        Database::query("DELETE FROM chat_conversations WHERE id=?", array($cid));
        chat_json(array('ok' => true));
    }

    if ($action === 'send_image' && $method === 'POST') {
        $preferId = (int)(isset($_POST['cid']) ? $_POST['cid'] : 0);
        $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
        $isAdmin = false;
        try {
            if (class_exists('Auth') && method_exists('Auth', 'checkAdmin') && Auth::checkAdmin()) {
                $isAdmin = true;
            }
        } catch (Exception $e) {}

        if (empty($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
            chat_json(array('ok' => false, 'error' => 'No image. Paste a screenshot (Ctrl+V).'));
        }
        $file = $_FILES['image'];
        if (!empty($file['error'])) {
            chat_json(array('ok' => false, 'error' => 'Upload failed'));
        }
        if ($file['size'] > 3 * 1024 * 1024) {
            chat_json(array('ok' => false, 'error' => 'Image too large (max 3MB)'));
        }
        $mime = '';
        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($fi, $file['tmp_name']);
            finfo_close($fi);
        } elseif (function_exists('getimagesize')) {
            $gi = @getimagesize($file['tmp_name']);
            $mime = is_array($gi) && !empty($gi['mime']) ? $gi['mime'] : '';
        }
        if ($mime === '' && !empty($file['type'])) $mime = $file['type'];
        $map = array(
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        );
        if (!isset($map[$mime])) {
            chat_json(array('ok' => false, 'error' => 'Only screenshot images allowed (PNG/JPG/GIF/WEBP)'));
        }
        $dir = dirname(__DIR__) . '/public/uploads/chat';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $fname = 'ss_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $map[$mime];
        $dest = $dir . '/' . $fname;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            chat_json(array('ok' => false, 'error' => 'Could not save image'));
        }
        $url = '/uploads/chat/' . $fname;
        $msgBody = '{{img:' . $url . '}}';

        if ($isAdmin) {
            $cid = (int)(isset($_POST['conversation_id']) ? $_POST['conversation_id'] : 0);
            if ($cid <= 0) chat_json(array('ok' => false, 'error' => 'No conversation'));
            $mid = Database::insert('chat_messages', array(
                'conversation_id' => $cid,
                'sender' => 'admin',
                'message' => $msgBody,
                'is_read' => 0,
            ));
            Database::update('chat_conversations', array(
                'last_message_at' => date('Y-m-d H:i:s'),
                'status' => 'open',
            ), 'id=?', array($cid));
            chat_json(array(
                'ok' => true,
                'id' => (int)$mid,
                'conversation_id' => $cid,
                'message' => $msgBody,
                'image_url' => $url,
                'created_at' => date('Y-m-d H:i:s'),
            ));
        }

        // Visitor path (same as send)
        $conv = null;
        if ($preferId > 0) {
            $conv = chat_find_conversation_for_visitor($preferId);
            if ($conv && isset($conv['status']) && $conv['status'] === 'blocked') $conv = null;
        }
        if (!$conv) {
            $conv = chat_get_or_create_conversation($name, '');
        }
        if (!$conv || empty($conv['id'])) {
            chat_json(array('ok' => false, 'error' => 'Could not start chat'));
        }
        $cid = (int)$conv['id'];
        if (isset($conv['status']) && $conv['status'] === 'blocked') {
            chat_json(array('ok' => false, 'error' => 'This chat has been blocked by support.', 'blocked' => true));
        }
        $prev = Database::fetch(
            "SELECT COUNT(*) AS c FROM chat_messages WHERE conversation_id=? AND sender='visitor'",
            array($cid)
        );
        $isFirst = $prev ? ((int)$prev['c'] === 0) : true;
        $mid = Database::insert('chat_messages', array(
            'conversation_id' => $cid,
            'sender' => 'visitor',
            'message' => $msgBody,
            'is_read' => 0,
        ));
        $requireApprove = setting('chat_require_approve', '1') === '1';
        $st = isset($conv['status']) ? $conv['status'] : 'open';
        $newStatus = ($isFirst && $requireApprove && $st !== 'open') ? 'pending' : (($st === 'pending') ? 'pending' : 'open');
        Database::update('chat_conversations', array(
            'last_message_at' => date('Y-m-d H:i:s'),
            'status' => $newStatus,
        ), 'id=?', array($cid));
        $autoId = 0;
        if ($isFirst && setting('chat_auto_reply_enabled', '1') === '1') {
            $autoText = trim(setting('chat_auto_reply_text', 'Thank you for contacting us. Our customer care team is a bit busy right now — please be patient. We will reply soon.'));
            if ($autoText !== '') {
                $autoId = Database::insert('chat_messages', array(
                    'conversation_id' => $cid,
                    'sender' => 'admin',
                    'message' => $autoText,
                    'is_read' => 0,
                ));
            }
        }
        chat_json(array(
            'ok' => true,
            'id' => (int)$mid,
            'conversation_id' => $cid,
            'message' => $msgBody,
            'image_url' => $url,
            'created_at' => date('Y-m-d H:i:s'),
            'status' => $newStatus,
            'auto_reply_id' => (int)$autoId,
        ));
    }

    if ($action === 'send' && $method === 'POST') {
        $msg = trim(isset($_POST['message']) ? $_POST['message'] : '');
        $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
        $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
        if ($msg === '') chat_json(array('ok' => false, 'error' => 'Empty message'));
        if (strlen($msg) > 2000) $msg = substr($msg, 0, 2000);

        $preferId = (int)(isset($_POST['cid']) ? $_POST['cid'] : 0);
        $conv = null;
        if ($preferId > 0) {
            try {
                $conv = chat_find_conversation_for_visitor($preferId);
                // Don't reuse blocked thread — start a fresh one
                if ($conv && isset($conv['status']) && $conv['status'] === 'blocked') {
                    $conv = null;
                }
            } catch (Exception $e) {
                $conv = null;
            }
        }
        if (!$conv) {
            try {
                $conv = chat_get_or_create_conversation($name, $email);
            } catch (Exception $e) {
                chat_json(array('ok' => false, 'error' => 'Could not start chat', 'detail' => $e->getMessage()), 500);
            }
        }
        if (!$conv || empty($conv['id'])) {
            chat_json(array('ok' => false, 'error' => 'Could not start chat'));
        }

        $cid = (int)$conv['id'];
        $st = isset($conv['status']) ? $conv['status'] : 'open';
        if ($st === 'blocked') {
            chat_json(array('ok' => false, 'error' => 'This chat has been blocked by support.', 'blocked' => true));
        }

        // Count existing visitor messages
        $prev = Database::fetch(
            "SELECT COUNT(*) AS c FROM chat_messages WHERE conversation_id=? AND sender='visitor'",
            array($cid)
        );
        $visitorCount = $prev ? (int)$prev['c'] : 0;
        $isFirst = ($visitorCount === 0);

        $mid = Database::insert('chat_messages', array(
            'conversation_id' => $cid,
            'sender' => 'visitor',
            'message' => $msg,
            'is_read' => 0,
        ));

        $requireApprove = setting('chat_require_approve', '1') === '1';
        $newStatus = $st;
        if ($isFirst && $requireApprove && $st !== 'open') {
            $newStatus = 'pending';
        } elseif ($st === 'pending') {
            $newStatus = 'pending';
        } else {
            $newStatus = 'open';
        }

        try {
            Database::update('chat_conversations', array(
                'last_message_at' => date('Y-m-d H:i:s'),
                'status' => $newStatus,
            ), 'id=?', array($cid));
        } catch (Exception $e) {}

        // Auto-reply only on first visitor message
        $autoId = 0;
        if ($isFirst && setting('chat_auto_reply_enabled', '1') === '1') {
            $autoText = trim(setting(
                'chat_auto_reply_text',
                'Thank you for contacting us. Our customer care team is a bit busy right now — please be patient. We will reply soon.'
            ));
            if ($autoText !== '') {
                $autoId = Database::insert('chat_messages', array(
                    'conversation_id' => $cid,
                    'sender' => 'admin',
                    'message' => $autoText,
                    'is_read' => 0,
                ));
                try {
                    Database::update('chat_conversations', array(
                        'last_message_at' => date('Y-m-d H:i:s'),
                    ), 'id=?', array($cid));
                } catch (Exception $e) {}
            }
        }

        chat_json(array(
            'ok' => true,
            'id' => (int)$mid,
            'conversation_id' => $cid,
            'created_at' => date('Y-m-d H:i:s'),
            'display_name' => chat_display_name($conv),
            'status' => $newStatus,
            'auto_reply_id' => (int)$autoId,
        ));
    }

    if ($action === '' || $action === 'ping') {
        chat_json(array('ok' => true, 'ping' => true, 'time' => date('c')));
    }

    chat_json(array('ok' => false, 'error' => 'Unknown action'));
} catch (Throwable $e) {
    chat_json(array(
        'ok' => false,
        'error' => 'Server error',
        'detail' => $e->getMessage(),
    ), 500);
}