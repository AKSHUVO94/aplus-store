<?php
declare(strict_types=1);

class Customer
{
    public static function findByEmail($email)
    {
        return Database::fetch("SELECT * FROM customers WHERE email = ?", [strtolower(trim($email))]);
    }

    public static function findByUserId($userId)
    {
        return Database::fetch("SELECT * FROM customers WHERE user_id = ?", [(int)$userId]);
    }

    public static function find($id)
    {
        return Database::fetch("SELECT * FROM customers WHERE id = ?", [(int)$id]);
    }

    /**
     * Create or update customer profile from registration / checkout
     */
    public static function upsert(array $data, $userId = null)
    {
        $email = strtolower(trim(isset($data['email']) ? $data['email'] : ''));
        if ($email === '') return null;

        $existing = self::findByEmail($email);
        $row = [
            'full_name' => trim(isset($data['name']) ? $data['name'] : (isset($data['full_name']) ? $data['full_name'] : '')),
            'email' => $email,
            'phone' => isset($data['phone']) ? trim($data['phone']) : null,
            'address' => isset($data['address']) ? trim($data['address']) : null,
            'city' => isset($data['city']) ? trim($data['city']) : null,
            'country' => isset($data['country']) ? trim($data['country']) : 'Bangladesh',
            'postal_code' => isset($data['postal_code']) ? trim($data['postal_code']) : null,
        ];
        if ($userId) {
            $row['user_id'] = (int)$userId;
        }

        if ($existing) {
            // only update non-empty fields
            $update = [];
            foreach ($row as $k => $v) {
                if ($v !== null && $v !== '') $update[$k] = $v;
            }
            if ($update) {
                Database::update('customers', $update, 'id=?', [$existing['id']]);
            }
            return (int)$existing['id'];
        }

        if ($row['full_name'] === '') $row['full_name'] = 'Customer';
        return Database::insert('customers', $row);
    }

    public static function refreshStats($customerId)
    {
        $customerId = (int)$customerId;
        $stats = Database::fetch(
            "SELECT COUNT(*) as c, COALESCE(SUM(total),0) as spent
             FROM orders WHERE customer_id=? AND status NOT IN ('cancelled')",
            [$customerId]
        );
        Database::update('customers', [
            'total_orders' => (int)$stats['c'],
            'total_spent' => (float)$stats['spent'],
        ], 'id=?', [$customerId]);
    }

    public static function forLoggedIn()
    {
        if (!Auth::check()) return null;
        $c = self::findByUserId(Auth::id());
        if ($c) return $c;
        $u = Auth::user();
        $c = self::findByEmail($u['email']);
        if ($c) {
            Database::update('customers', ['user_id' => Auth::id()], 'id=?', [$c['id']]);
            return self::find($c['id']);
        }
        // auto-create
        $id = self::upsert([
            'name' => $u['name'],
            'email' => $u['email'],
            'phone' => isset($u['phone']) ? $u['phone'] : '',
        ], Auth::id());
        return $id ? self::find($id) : null;
    }
}
