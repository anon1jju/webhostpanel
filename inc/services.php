<?php
// WebPanel - service management (nginx, mysql, php-fpm, ...).

if (!defined('PANEL')) { exit('Direct access denied.'); }

final class Services {

    private static function valid($name) {
        $allowed = array(
            'nginx', 'apache2', 'mysql', 'mariadb', 'redis-server', 'memcached', 'fail2ban',
        );
        if (preg_match('/^php\d+\.\d+-fpm$/', $name)) { return true; }
        return in_array($name, $allowed, true);
    }

    public static function control($name, $action) {
        if (!self::valid($name)) { return array(false, 'Invalid service name.'); }
        if (!in_array($action, array('start', 'stop', 'restart', 'reload'), true)) {
            return array(false, 'Invalid action.');
        }
        if (!is_linux()) {
            return array(false, 'Service management is only available on Linux.');
        }
        $cmd = sudo_prefix() . 'systemctl ' . $action . ' ' . escapeshellarg($name) . ' 2>&1';
        if ($action === 'reload' && $name === 'nginx') {
            $cmd = sudo_prefix() . 'sh -c "nginx -t && nginx -s reload" 2>&1';
        }
        list($out, $code, $ok) = sh($cmd);
        $msg = implode("\n", array_slice($out, -5));
        if (!$ok && $msg === '') {
            // systemctl may not exist -> fall back to service command
            list($out2, $code2, $ok2) = sh(sudo_prefix() . 'service ' . escapeshellarg($name) . ' ' . $action . ' 2>&1');
            $msg = implode("\n", array_slice($out2, -5));
            return array($ok2, $ok2 ? 'Service ' . $name . ' ' . $action . ' ok.' : trim($msg) ?: 'Failed.');
        }
        return array($ok, $ok ? 'Service ' . $name . ' ' . $action . ' ok.' : trim($msg) ?: 'Failed.');
    }

    public static function nginx_test_and_reload() {
        if (!is_linux()) { return array(false, 'nginx reload is only available on Linux.'); }
        list($out, $code, $ok) = sh(sudo_prefix() . 'sh -c "nginx -t 2>&1 && systemctl reload nginx 2>&1 || nginx -s reload 2>&1"');
        return array($ok, implode("\n", array_slice($out, -8)));
    }
}
