<?php
declare(strict_types=1);
class Database {
    private static $instance = null;
    public static function getInstance() {
        if (self::$instance === null) {
            $c = require dirname(__DIR__) . '/config/config.php';
            $d = $c['db'];
            $dsn = "mysql:host={$d['host']};port={$d['port']};dbname={$d['name']};charset={$d['charset']}";
            try {
                self::$instance = new PDO($dsn, $d['user'], $d['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                die($c['app_debug'] ? 'DB Error: '.$e->getMessage() : 'Database connection failed.');
            }
        }
        return self::$instance;
    }
    public static function query($sql, $params = []) {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    public static function fetch($sql, $params = []) {
        $r = self::query($sql, $params)->fetch();
        return $r ?: null;
    }
    public static function fetchAll($sql, $params = []) {
        return self::query($sql, $params)->fetchAll();
    }
    public static function insert($table, $data) {
        $cols = implode(',', array_map(function($k){ return "`$k`"; }, array_keys($data)));
        $ph = implode(',', array_fill(0, count($data), '?'));
        self::query("INSERT INTO `$table` ($cols) VALUES ($ph)", array_values($data));
        return (int) self::getInstance()->lastInsertId();
    }
    public static function update($table, $data, $where, $whereParams = []) {
        $set = implode(',', array_map(function($k){ return "`$k`=?"; }, array_keys($data)));
        return self::query("UPDATE `$table` SET $set WHERE $where", array_merge(array_values($data), $whereParams))->rowCount();
    }
    public static function delete($table, $where, $params = []) {
        return self::query("DELETE FROM `$table` WHERE $where", $params)->rowCount();
    }
}
