<?php
declare(strict_types=1);

/**
 * Dual session: admin and customer can be tracked separately.
 * - Admin uses $_SESSION['admin_user']
 * - Customer uses $_SESSION['customer_user']
 * - Legacy $_SESSION['user'] kept in sync for compatibility
 */
class Auth
{
    public static function startSession()
    {
        $c = require dirname(__DIR__) . '/config/config.php';
        if (session_status() === PHP_SESSION_NONE) {
            session_name($c['session_name']);
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
        // Migrate old single session if needed
        if (!empty($_SESSION['user']) && empty($_SESSION['admin_user']) && empty($_SESSION['customer_user'])) {
            if ($_SESSION['user']['role_slug'] === 'super-admin') {
                $_SESSION['admin_user'] = $_SESSION['user'];
            } else {
                $_SESSION['customer_user'] = $_SESSION['user'];
            }
        }
        if (class_exists('Security')) {
            Security::secureHeaders();
            if (self::check() || self::checkAdmin()) {
                Security::checkSessionTimeout();
            }
        }
    }

    private static function loadUser($email)
    {
        return Database::fetch(
            "SELECT u.*, r.slug as role_slug, r.name as role_name
             FROM users u JOIN roles r ON r.id = u.role_id
             WHERE u.email = ? AND u.status = 'active'",
            [$email]
        );
    }

    private static function sessionPayload($user)
    {
        $perms = Database::fetchAll(
            "SELECT p.slug FROM permissions p
             JOIN role_permissions rp ON rp.permission_id = p.id
             WHERE rp.role_id = ?",
            [$user['role_id']]
        );
        return [
            'id' => (int)$user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role_id' => (int)$user['role_id'],
            'role_slug' => $user['role_slug'],
            'role_name' => $user['role_name'],
            'permissions' => array_column($perms, 'slug'),
        ];
    }

    /** Admin login only */
    public static function attemptAdmin($email, $password)
    {
        $user = self::loadUser($email);
        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }
        if ($user['role_slug'] !== 'super-admin') {
            return false; // Only Super Admin may access admin panel
        }
        session_regenerate_id(true);
        $payload = self::sessionPayload($user);
        $_SESSION['admin_user'] = $payload;
        $_SESSION['user'] = $payload; // compat for admin area
        $_SESSION['last_activity'] = time();
        return true;
    }

    /** Customer login only — never logs in as admin */
    public static function attemptCustomer($email, $password)
    {
        $user = self::loadUser($email);
        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }
        // Only customers (role_id 4 / slug customer)
        if (in_array($user['role_slug'], ['super-admin', 'admin', 'staff'], true)) {
            return false; // staff must use admin login — not customer area
        }
        try {
            $cust = Database::fetch("SELECT status FROM customers WHERE email = ?", [strtolower($email)]);
            if ($cust && $cust['status'] === 'blocked') {
                return false;
            }
        } catch (Exception $e) {
        }
        session_regenerate_id(true);
        $payload = self::sessionPayload($user);
        $_SESSION['customer_user'] = $payload;
        // Do NOT overwrite admin_user — admin can stay logged in admin panel in another tab
        // For storefront "current user", prefer customer
        $_SESSION['user'] = $payload;
        $_SESSION['last_activity'] = time();
        return true;
    }

    /** Generic attempt (legacy) */
    public static function attempt($email, $password)
    {
        $user = self::loadUser($email);
        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }
        if (in_array($user['role_slug'], ['super-admin', 'admin', 'staff'], true)) {
            return self::attemptAdmin($email, $password);
        }
        return self::attemptCustomer($email, $password);
    }

    public static function checkAdmin()
    {
        return isset($_SESSION['admin_user']['id']);
    }

    public static function checkCustomer()
    {
        return isset($_SESSION['customer_user']['id']);
    }

    /** Storefront: customer session preferred */
    public static function check()
    {
        return self::checkCustomer() || (isset($_SESSION['user']['id']) && !self::isAdminSession());
    }

    private static function isAdminSession()
    {
        return isset($_SESSION['user']['role_slug'])
            && in_array($_SESSION['user']['role_slug'], ['super-admin', 'admin', 'staff'], true)
            && !self::checkCustomer();
    }

    public static function user()
    {
        if (self::checkCustomer()) {
            return $_SESSION['customer_user'];
        }
        return isset($_SESSION['user']) ? $_SESSION['user'] : null;
    }

    public static function adminUser()
    {
        return isset($_SESSION['admin_user']) ? $_SESSION['admin_user'] : null;
    }

    public static function id()
    {
        $u = self::user();
        return $u ? (int)$u['id'] : null;
    }

    public static function isAdmin()
    {
        // Only Super Admin
        if (self::checkAdmin()) {
            $a = self::adminUser();
            return $a && $a['role_slug'] === 'super-admin';
        }
        if (!isset($_SESSION['user']['role_slug'])) {
            return false;
        }
        return $_SESSION['user']['role_slug'] === 'super-admin';
    }

    public static function isCustomer()
    {
        return self::checkCustomer();
    }

    public static function can($perm)
    {
        $u = self::checkAdmin() ? $_SESSION['admin_user'] : self::user();
        if (!$u) {
            return false;
        }
        if ($u['role_slug'] === 'super-admin') {
            return true;
        }
        return in_array($perm, $u['permissions'], true);
    }

    public static function requireLogin()
    {
        if (!self::checkAdmin() && !isset($_SESSION['user']['id'])) {
            flash('error', 'Please login.');
            redirect('/admin/login.php');
        }
    }

    public static function requireAdmin()
    {
        if (!self::checkAdmin() || !self::isAdmin()) {
            Auth::logoutAdmin();
            flash('error', 'Access denied. Super Admin only.');
            redirect('/admin/login.php');
        }
        $_SESSION['user'] = $_SESSION['admin_user'];
    }

    public static function requireCustomer()
    {
        if (!self::checkCustomer()) {
            flash('error', 'Please login to continue.');
            redirect('/login.php');
        }
    }

    public static function logoutAdmin()
    {
        unset($_SESSION['admin_user']);
        if (isset($_SESSION['user']['role_slug'])
            && $_SESSION['user']['role_slug'] === 'super-admin') {
            unset($_SESSION['user']);
        }
        if (self::checkCustomer()) {
            $_SESSION['user'] = $_SESSION['customer_user'];
        }
    }

    public static function logoutCustomer()
    {
        unset($_SESSION['customer_user']);
        if (isset($_SESSION['user']) && !self::checkAdmin()) {
            unset($_SESSION['user']);
        }
        if (self::checkAdmin()) {
            $_SESSION['user'] = $_SESSION['admin_user'];
        }
    }

    public static function logout()
    {
        // Full logout
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], isset($p['domain']) ? $p['domain'] : '', $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
}
