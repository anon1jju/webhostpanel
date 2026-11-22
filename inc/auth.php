<?php
// WebPanel - authentication: login, logout, rate limiting.

if (!defined('PANEL')) { exit('Direct access denied.'); }

define('LOGIN_MAX_FAILS', 5);
define('LOGIN_LOCK_SECS', 600);

function is_logged_in() {
    return !empty($_SESSION['panel_admin']);
}

function login_locked($ip) {
    try {
        $row = db()->prepare('SELECT fails, last FROM attempts WHERE ip = ?');
        $row->execute(array($ip));
        $r = $row->fetch();
        if ($r && (int)$r['fails'] >= LOGIN_MAX_FAILS && (time() - (int)$r['last']) < LOGIN_LOCK_SECS) {
            return LOGIN_LOCK_SECS - (time() - (int)$r['last']);
        }
    } catch (Exception $ex) { /* table not ready yet */ }
    return 0;
}

function attempt_login($username, $password) {
    $ip = get_client_ip();
    $locked = login_locked($ip);
    if ($locked > 0) {
        return array(false, 'Too many failed attempts. Try again in ' . ceil($locked / 60) . ' minute(s).');
    }
    $user = (string)config_get('admin_user', 'admin');
    $hash = (string)config_get('admin_pass', '');
    $ok = false;
    if ($hash && strpos($hash, '$') === 0) {
        $ok = password_verify($password, $hash);
    }
    // Rehash transparently when PHP defaults change
    if ($ok && password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        panel_save_config(array('admin_pass' => password_hash($password, PASSWORD_DEFAULT)));
    }
    if ($ok && hash_equals(strtolower($username), strtolower($user))) {
        db()->prepare('DELETE FROM attempts WHERE ip = ?')->execute(array($ip));
        session_regenerate_id(true);
        $_SESSION['panel_admin'] = true;
        $_SESSION['panel_user'] = $user;
        return array(true, '');
    }
    // record failure
    try {
        $st = db()->prepare('INSERT INTO attempts (ip, fails, last) VALUES (?, 1, ?)
            ON CONFLICT(ip) DO UPDATE SET fails = fails + 1, last = excluded.last');
        $st->execute(array($ip, time()));
    } catch (Exception $ex) { }
    sleep(1); // slow down brute force
    return array(false, 'Invalid username or password.');
}

function require_login() {
    if (!is_logged_in()) {
        require PANEL_ROOT . '/views/login.php';
        exit;
    }
}

function panel_logout() {
    $_SESSION = array();
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// Merge + write config file. $data keys override current config.
function panel_save_config(array $data) {
    global $config;
    $merged = array_merge($config, $data);
    $text = "<?php\n// WebPanel configuration - generated " . date('c') . "\nreturn " . var_export($merged, true) . ";\n";
    if (@file_put_contents(PANEL_ROOT . '/data/config.php', $text, LOCK_EX) === false) {
        return false;
    }
    @chmod(PANEL_ROOT . '/data/config.php', 0600);
    $config = $merged;
    return true;
}
