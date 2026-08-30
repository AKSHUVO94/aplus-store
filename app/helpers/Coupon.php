<?php
declare(strict_types=1);

class Coupon
{
    public static function ensureTable()
    {
        static $done = false;
        if ($done) return;
        $done = true;
        try {
            Database::query("CREATE TABLE IF NOT EXISTS coupons (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(30) NOT NULL,
                type VARCHAR(20) NOT NULL DEFAULT 'fixed',
                value DECIMAL(12,2) NOT NULL DEFAULT 0,
                min_order DECIMAL(12,2) DEFAULT 0,
                usage_limit INT DEFAULT NULL,
                used_count INT NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                expires_at DATE DEFAULT NULL,
                UNIQUE KEY uq_code (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (Exception $e) {}
        // Soft upgrades if old schema
        foreach (array(
            "ALTER TABLE coupons ADD COLUMN value DECIMAL(12,2) NOT NULL DEFAULT 0",
            "ALTER TABLE coupons ADD COLUMN usage_limit INT DEFAULT NULL",
            "ALTER TABLE coupons ADD COLUMN used_count INT NOT NULL DEFAULT 0",
            "ALTER TABLE coupons ADD COLUMN min_order DECIMAL(12,2) DEFAULT 0",
            "ALTER TABLE coupons ADD COLUMN expires_at DATE DEFAULT NULL",
        ) as $sql) {
            try { Database::query($sql); } catch (Exception $e) {}
        }
        try {
            Database::query("ALTER TABLE orders ADD COLUMN coupon_code VARCHAR(40) NULL DEFAULT NULL");
        } catch (Exception $e) {}
    }

    /** Normalize row to always have amount/max_uses aliases */
    public static function normalizeRow($row)
    {
        if (!$row || !is_array($row)) return $row;
        if (!isset($row['amount']) && isset($row['value'])) {
            $row['amount'] = $row['value'];
        }
        if (!isset($row['value']) && isset($row['amount'])) {
            $row['value'] = $row['amount'];
        }
        if (!isset($row['max_uses']) && array_key_exists('usage_limit', $row)) {
            $row['max_uses'] = $row['usage_limit'];
        }
        if (!isset($row['usage_limit']) && array_key_exists('max_uses', $row)) {
            $row['usage_limit'] = $row['max_uses'];
        }
        return $row;
    }

    public static function normalizeCode($code)
    {
        return strtoupper(trim((string)$code));
    }

    public static function findActive($code)
    {
        self::ensureTable();
        $code = self::normalizeCode($code);
        if ($code === '') return null;
        $row = Database::fetch(
            "SELECT * FROM coupons WHERE code = ? AND status = 'active' LIMIT 1",
            array($code)
        );
        $row = self::normalizeRow($row);
        if (!$row) return null;
        if (!empty($row['expires_at']) && strtotime($row['expires_at']) < strtotime('today')) {
            return null;
        }
        $limit = isset($row['usage_limit']) ? $row['usage_limit'] : null;
        if ($limit !== null && $limit !== '' && (int)$row['used_count'] >= (int)$limit) {
            return null;
        }
        return $row;
    }

    public static function calcDiscount($coupon, $subtotal)
    {
        $coupon = self::normalizeRow($coupon);
        $subtotal = (float)$subtotal;
        if ($subtotal <= 0 || !$coupon) return 0.0;
        $min = (float)(isset($coupon['min_order']) ? $coupon['min_order'] : 0);
        if ($min > 0 && $subtotal < $min) return 0.0;
        $amount = (float)(isset($coupon['value']) ? $coupon['value'] : (isset($coupon['amount']) ? $coupon['amount'] : 0));
        $type = isset($coupon['type']) ? $coupon['type'] : 'fixed';
        if ($type === 'percent') {
            $disc = round($subtotal * ($amount / 100), 2);
        } else {
            $disc = $amount;
        }
        if ($disc > $subtotal) $disc = $subtotal;
        return max(0.0, (float)$disc);
    }

    public static function apply($code)
    {
        $coupon = self::findActive($code);
        if (!$coupon) {
            return 'Invalid or expired coupon.';
        }
        $sub = Cart::subtotal();
        $min = (float)(isset($coupon['min_order']) ? $coupon['min_order'] : 0);
        if ($min > 0 && $sub < $min) {
            return 'Minimum order for this coupon is ৳' . number_format($min, 0);
        }
        $disc = self::calcDiscount($coupon, $sub);
        if ($disc <= 0) {
            return 'Coupon cannot be applied to this order.';
        }
        $_SESSION['coupon'] = array(
            'id' => (int)$coupon['id'],
            'code' => $coupon['code'],
            'type' => $coupon['type'],
            'amount' => (float)(isset($coupon['value']) ? $coupon['value'] : $coupon['amount']),
            'discount' => $disc,
        );
        return true;
    }

    public static function remove()
    {
        unset($_SESSION['coupon']);
    }

    public static function current()
    {
        return isset($_SESSION['coupon']) && is_array($_SESSION['coupon']) ? $_SESSION['coupon'] : null;
    }

    public static function discount()
    {
        $c = self::current();
        if (!$c) return 0.0;
        $coupon = self::findActive($c['code']);
        if (!$coupon) {
            self::remove();
            return 0.0;
        }
        return self::calcDiscount($coupon, Cart::subtotal());
    }

    public static function markUsed($code)
    {
        self::ensureTable();
        $code = self::normalizeCode($code);
        try {
            Database::query(
                "UPDATE coupons SET used_count = used_count + 1 WHERE code = ?",
                array($code)
            );
        } catch (Exception $e) {}
    }
}