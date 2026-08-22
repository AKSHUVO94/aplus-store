<?php
declare(strict_types=1);

class Security
{
    public static function clientIp()
    {
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }

    public static function isLoginLocked($email = '')
    {
        $max = (int) setting('admin_login_max_attempts', 5);
        $mins = (int) setting('admin_login_lock_minutes', 15);
        $ip = self::clientIp();
        try {
            $row = Database::fetch(
                "SELECT COUNT(*) as c FROM login_attempts
                 WHERE success=0 AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
                 AND (ip_address=? OR email=?)",
                [$mins, $ip, strtolower($email)]
            );
            return (int)$row['c'] >= $max;
        } catch (Exception $e) {
            return false;
        }
    }

    public static function recordLoginAttempt($email, $success)
    {
        try {
            Database::insert('login_attempts', [
                'ip_address' => self::clientIp(),
                'email' => strtolower(trim($email)),
                'success' => $success ? 1 : 0,
            ]);
        } catch (Exception $e) {}
    }

    public static function requireCsrf()
    {
        $token = isset($_POST['_csrf']) ? $_POST['_csrf'] : '';
        if (!csrf_verify($token)) {
            http_response_code(403);
            die('Invalid security token. Please go back and try again.');
        }
    }

    public static function secureHeaders()
    {
        if (headers_sent()) return;
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header("Content-Security-Policy: frame-ancestors 'self'");
    }

    public static function checkSessionTimeout()
    {
        $mins = (int) setting('session_timeout_minutes', 120);
        if ($mins < 5) $mins = 5;
        if (!empty($_SESSION['last_activity'])) {
            if (time() - (int)$_SESSION['last_activity'] > $mins * 60) {
                Auth::logout();
                return false;
            }
        }
        $_SESSION['last_activity'] = time();
        return true;
    }
}
