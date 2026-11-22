<?php
// WebPanel - lightweight hosting control panel. Bootstrap: config, session, db, helpers.
if (defined('PANEL')) { return; }
define('PANEL', 1);
define('PANEL_VERSION', '1.0.0');
define('PANEL_ROOT', dirname(__DIR__));

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', '0');

// Ensure writable runtime dirs exist
foreach (array(PANEL_ROOT . '/data', PANEL_ROOT . '/data/tasks', PANEL_ROOT . '/data/cache', PANEL_ROOT . '/data/sites') as $dir) {
    if (!is_dir($dir)) { @mkdir($dir, 0700, true); }
}

// Load config (created by installer / settings)
$configFile = PANEL_ROOT . '/data/config.php';
$config = array();
if (is_file($configFile)) {
    $loaded = @include $configFile;
    if (is_array($loaded)) { $config = $loaded; }
}
define('HAS_CONFIG', is_file($configFile));

function config_get($key, $default = null) {
    global $config;
    return array_key_exists($key, $config) ? $config[$key] : $default;
}

date_default_timezone_set(config_get('timezone', 'Asia/Jakarta'));

// Session
if (session_status() === PHP_SESSION_NONE) {
    session_name('wpanel_sid');
    session_set_cookie_params(array(
        'lifetime' => 86400,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ));
    session_start();
}

// ---------------------------------------------------------------------------
// SQLite database (panel state: tasks, login attempts, virtual sites, settings)
// ---------------------------------------------------------------------------
function db() {
    static $pdo = null;
    if ($pdo instanceof PDO) { return $pdo; }
    $pdo = new PDO('sqlite:' . PANEL_ROOT . '/data/panel.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA busy_timeout=5000');
    $pdo->exec("CREATE TABLE IF NOT EXISTS tasks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'running',
        created_at INTEGER NOT NULL,
        finished_at INTEGER DEFAULT NULL
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS attempts (
        ip TEXT PRIMARY KEY,
        fails INTEGER NOT NULL DEFAULT 0,
        last INTEGER NOT NULL DEFAULT 0
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS vhosts (
        domain TEXT PRIMARY KEY,
        root TEXT NOT NULL,
        php TEXT NOT NULL DEFAULT 'default',
        ssl INTEGER NOT NULL DEFAULT 0,
        enabled INTEGER NOT NULL DEFAULT 1,
        created_at INTEGER NOT NULL
    )");
    return $pdo;
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------
function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function json_out($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $out = @json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    if ($out === false) {
        $out = json_encode(array('ok' => false, 'error' => 'JSON encoding failed: ' . json_last_error_msg()));
    }
    echo $out;
    exit;
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function is_post() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_check_json() {
    $t = isset($_POST['csrf']) ? $_POST['csrf'] : (isset($_SERVER['HTTP_X_CSRF']) ? $_SERVER['HTTP_X_CSRF'] : '');
    if (!$t || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $t)) {
        json_out(array('ok' => false, 'error' => 'Invalid CSRF token. Refresh the page and try again.'), 403);
    }
}

function flash_set($key, $value) {
    $_SESSION['flash'][$key] = $value;
}

function flash_get($key) {
    $v = isset($_SESSION['flash'][$key]) ? $_SESSION['flash'][$key] : null;
    unset($_SESSION['flash'][$key]);
    return $v;
}

function is_linux() {
    return PHP_OS_FAMILY !== 'Windows';
}

function is_root() {
    if (!function_exists('posix_geteuid')) { return true; } // unknown -> assume
    return @posix_geteuid() === 0;
}

// Run a command synchronously, capturing output.
// Returns [output_array, exit_code, success_bool]
function sh($cmd, $timeout = 30) {
    $out = array();
    $code = 1;
    $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
    if (function_exists('exec') && !in_array('exec', $disabled, true)) {
        @exec($cmd . ' 2>&1', $out, $code);
    } elseif (function_exists('shell_exec') && !in_array('shell_exec', $disabled, true)) {
        $raw = @shell_exec($cmd . ' 2>&1');
        $out = $raw === null ? array() : preg_split('/\r?\n/', $raw);
        $code = 0;
    }
    return array($out, (int)$code, (int)$code === 0);
}

// Prefix command with sudo when running as non-root on Linux (root not required, but
// most server-management actions are). Falls back to plain command if sudo is absent.
function sudo_prefix() {
    static $prefix = null;
    if ($prefix !== null) { return $prefix; }
    $prefix = '';
    if (is_linux() && !is_root()) {
        list(, , $ok) = sh('command -v sudo');
        if ($ok) { $prefix = 'sudo '; }
    }
    return $prefix;
}

function human_bytes($bytes, $dec = 1) {
    $bytes = (float)$bytes;
    if ($bytes <= 0) { return '0 B'; }
    $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
    $i = (int)floor(log($bytes, 1024));
    $i = min($i, count($units) - 1);
    return round($bytes / pow(1024, $i), $dec) . ' ' . $units[$i];
}

function human_uptime($seconds) {
    $seconds = (int)$seconds;
    $d = intdiv($seconds, 86400);
    $h = intdiv($seconds % 86400, 3600);
    $m = intdiv($seconds % 3600, 60);
    $parts = array();
    if ($d) { $parts[] = $d . 'd'; }
    if ($h) { $parts[] = $h . 'h'; }
    if ($m) { $parts[] = $m . 'm'; }
    return $parts ? implode(' ', $parts) : (($seconds % 60) . 's');
}

function get_client_ip() {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function panel_url() {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($https ? 'https://' : 'http://') . $host;
}

function safe_name($s) {
    $s = strtolower(trim((string)$s));
    return preg_replace('/[^a-z0-9_\-\.]/', '', $s);
}
