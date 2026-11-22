<?php
// WebPanel - MySQL/MariaDB database management via PDO.

if (!defined('PANEL')) { exit('Direct access denied.'); }

final class Databases {

    private static $pdo = null;

    public static function configured() {
        return config_get('db_host', '') !== '' || config_get('db_socket', '') !== '';
    }

    public static function connect() {
        if (self::$pdo instanceof PDO) { return self::$pdo; }
        $host = config_get('db_host', '127.0.0.1');
        $port = config_get('db_port', '3306');
        $user = config_get('db_user', 'root');
        $pass = config_get('db_pass', '');
        $socket = config_get('db_socket', '');
        $dsn = $socket
            ? 'mysql:unix_socket=' . $socket . ';charset=utf8mb4'
            : 'mysql:host=' . $host . ';port=' . $port . ';charset=utf8mb4';
        self::$pdo = new PDO($dsn, $user, $pass, array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ));
        return self::$pdo;
    }

    private static function name_ok($name) {
        return (bool)preg_match('/^[a-zA-Z0-9_]+$/', (string)$name) && strlen((string)$name) <= 64;
    }

    public static function list_databases() {
        $pdo = self::connect();
        $rows = $pdo->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN);
        $skip = array('information_schema', 'performance_schema', 'mysql', 'sys');
        $list = array();
        foreach ($rows as $db) {
            if (in_array($db, $skip, true)) { continue; }
            $size = null;
            try {
                $size = $pdo->query("SELECT ROUND(SUM(data_length + index_length)) FROM information_schema.tables WHERE table_schema = " . $pdo->quote($db))->fetchColumn();
            } catch (Exception $ex) { }
            $list[] = array('name' => $db, 'size' => $size === null ? null : (float)$size);
        }
        return $list;
    }

    public static function create_database($name) {
        if (!self::name_ok($name)) { return array(false, 'Invalid database name (a-z, 0-9, _ only).'); }
        try {
            self::connect()->exec('CREATE DATABASE `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        } catch (PDOException $ex) {
            return array(false, $ex->getMessage());
        }
        return array(true, 'Database ' . $name . ' created.');
    }

    public static function drop_database($name) {
        if (!self::name_ok($name)) { return array(false, 'Invalid database name.'); }
        try {
            self::connect()->exec('DROP DATABASE `' . $name . '`');
        } catch (PDOException $ex) {
            return array(false, $ex->getMessage());
        }
        return array(true, 'Database ' . $name . ' dropped.');
    }

    public static function list_users() {
        $pdo = self::connect();
        try {
            return $pdo->query("SELECT User, Host FROM mysql.user ORDER BY User, Host")->fetchAll();
        } catch (PDOException $ex) {
            return array();
        }
    }

    public static function create_user($user, $pass, $host, $grantDb) {
        if (!self::name_ok($user) || $user === '') { return array(false, 'Invalid username (a-z, 0-9, _ only).'); }
        $host = self::name_ok(str_replace('.', '_', $host)) ? $host : 'localhost';
        if (!preg_match('/^[a-zA-Z0-9_.%\-]+$/', $host)) { $host = 'localhost'; }
        if (strlen($pass) < 6) { return array(false, 'Password must be at least 6 characters.'); }
        try {
            $pdo = self::connect();
            $pdo->exec("CREATE USER '" . $user . "'@'" . $host . "' IDENTIFIED BY " . $pdo->quote($pass));
            if ($grantDb) {
                if (!self::name_ok($grantDb) && $grantDb !== '*') { return array(false, 'Invalid database to grant.'); }
                $target = $grantDb === '*' ? '*.*' : '`' . $grantDb . '`.*';
                $pdo->exec('GRANT ALL PRIVILEGES ON ' . $target . " TO '" . $user . "'@'" . $host . "'");
                $pdo->exec('FLUSH PRIVILEGES');
            }
        } catch (PDOException $ex) {
            return array(false, $ex->getMessage());
        }
        return array(true, 'User ' . $user . '@' . $host . ' created' . ($grantDb ? ' with access to ' . $grantDb : '') . '.');
    }

    public static function drop_user($user, $host) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', (string)$user)) { return array(false, 'Invalid user.'); }
        if (!preg_match('/^[a-zA-Z0-9_.%\-]+$/', (string)$host)) { $host = 'localhost'; }
        try {
            self::connect()->exec("DROP USER '" . $user . "'@'" . $host . "'");
        } catch (PDOException $ex) {
            return array(false, $ex->getMessage());
        }
        return array(true, 'User ' . $user . '@' . $host . ' dropped.');
    }

    public static function phpmyadmin_path() {
        $candidates = array('/usr/share/phpmyadmin', '/var/www/html/phpmyadmin');
        foreach ($candidates as $c) {
            if (is_dir($c)) { return $c; }
        }
        return null;
    }
}
