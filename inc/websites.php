<?php
// WebPanel - nginx website (vhost) management.
// On Linux: manages /etc/nginx/sites-available + sites-enabled and reloads nginx.
// Elsewhere (e.g. Windows test): "virtual mode" - configs stored in data/sites/.

if (!defined('PANEL')) { exit('Direct access denied.'); }

final class Websites {

    public static function mode() {
        return (is_linux() && is_dir('/etc/nginx/sites-available')) ? 'nginx' : 'virtual';
    }

    private static function dirs() {
        return array(
            'avail' => '/etc/nginx/sites-available',
            'enabled' => '/etc/nginx/sites-enabled',
            'ssl' => '/etc/nginx/ssl',
        );
    }

    public static function validate_domain($d) {
        $d = strtolower(trim((string)$d));
        if ($d === 'localhost') { return $d; }
        if (filter_var($d, FILTER_VALIDATE_IP)) { return $d; }
        if (preg_match('/^(?=.{1,253}$)([a-z0-9]([a-z0-9\-]*[a-z0-9])?\.)+[a-z]{2,63}$/', $d)) { return $d; }
        return false;
    }

    // ------------------------------------------------------------ listing
    public static function list_sites() {
        $sites = array();
        if (self::mode() === 'nginx') {
            $dirs = self::dirs();
            $avail = @glob($dirs['avail'] . '/*') ?: array();
            foreach ($avail as $conf) {
                if (!is_file($conf)) { continue; }
                $name = basename($conf, '.conf');
                $content = @file_get_contents($conf);
                $domain = '';
                $root = '';
                $php = 'default';
                $ssl = 0;
                if (preg_match('/server_name\s+([^;]+);/', $content, $m)) {
                    $domain = trim(strtok($m[1], ' '));
                }
                if (preg_match('/root\s+([^;]+);/', $content, $m)) {
                    $root = trim(trim($m[1]), '"\'');
                }
                if (preg_match('#unix:(/run/php/|/var/run/php/)(php)?([0-9.]+)-fpm\.sock#', $content, $m)) {
                    $php = $m[3];
                }
                if (strpos($content, 'ssl_certificate') !== false) { $ssl = 1; }
                $enabled = is_link($dirs['enabled'] . '/' . $name) || is_file($dirs['enabled'] . '/' . $name);
                $sites[] = array(
                    'domain' => $domain ?: $name, 'root' => $root, 'php' => $php,
                    'ssl' => $ssl, 'enabled' => $enabled, 'conf' => $name,
                    'mode' => 'nginx',
                );
            }
        } else {
            foreach (db()->query('SELECT * FROM vhosts ORDER BY domain')->fetchAll() as $r) {
                $sites[] = array(
                    'domain' => $r['domain'], 'root' => $r['root'], 'php' => $r['php'],
                    'ssl' => (int)$r['ssl'], 'enabled' => (int)$r['enabled'], 'conf' => $r['domain'],
                    'mode' => 'virtual',
                );
            }
        }
        return $sites;
    }

    public static function find($domain) {
        foreach (self::list_sites() as $s) {
            if ($s['domain'] === $domain || $s['conf'] === $domain) { return $s; }
        }
        return null;
    }

    // ------------------------------------------------------------ config text
    private static function config_text($domain, $root, $php, $ssl, $sslPath = '') {
        $www = (strpos($domain, 'www.') === 0) ? '' : ' www.' . $domain;
        $sslLines = '';
        if ($ssl) {
            $sslLines = "    listen 443 ssl;\n    listen [::]:443 ssl;\n";
            if ($sslPath) {
                $sslLines .= "    ssl_certificate " . $sslPath . "/" . $domain . ".crt;\n";
                $sslLines .= "    ssl_certificate_key " . $sslPath . "/" . $domain . ".key;\n";
                $sslLines .= "    ssl_protocols TLSv1.2 TLSv1.3;\n";
            }
        }
        $socket = $php === 'default' ? 'unix:/run/php/php-fpm.sock' : 'unix:/run/php/php' . $php . '-fpm.sock';
        $logName = preg_replace('/[^a-z0-9.\-]/', '_', $domain);
        return "# WebPanel - managed site\n" .
            "server {\n" .
            "    listen 80;\n" .
            "    listen [::]:80;\n" .
            $sslLines .
            "    server_name " . $domain . $www . ";\n" .
            "\n" .
            "    root " . $root . ";\n" .
            "    index index.php index.html index.htm;\n" .
            "\n" .
            "    access_log /var/log/nginx/" . $logName . ".access.log;\n" .
            "    error_log /var/log/nginx/" . $logName . ".error.log;\n" .
            "    client_max_body_size 100m;\n" .
            "\n" .
            "    location / {\n" .
            "        try_files \$uri \$uri/ /index.php?\$query_string;\n" .
            "    }\n" .
            "\n" .
            "    location ~ \\.php\$ {\n" .
            "        try_files \$uri =404;\n" .
            "        fastcgi_pass " . $socket . ";\n" .
            "        fastcgi_index index.php;\n" .
            "        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;\n" .
            "        include fastcgi_params;\n" .
            "    }\n" .
            "\n" .
            "    location ~ /\\.(?!well-known).* {\n" .
            "        deny all;\n" .
            "    }\n" .
            "}\n";
    }

    // ------------------------------------------------------------ actions
    public static function add($domain, $root, $php, $ssl) {
        $domain = self::validate_domain($domain);
        if (!$domain) { return array(false, 'Invalid domain name.'); }
        if (self::find($domain)) { return array(false, 'Site already exists.'); }

        $php = (string)$php;
        if ($php !== 'default' && !preg_match('/^\d+\.\d+$/', $php)) { $php = 'default'; }

        $defaultRoot = self::mode() === 'nginx' ? (config_get('www_root', '/var/www') . '/' . $domain) : str_replace('/', DIRECTORY_SEPARATOR, PANEL_ROOT . '/data/sites/www/' . $domain);
        $root = trim((string)$root) !== '' ? rtrim(trim((string)$root), '/\\') : $defaultRoot;
        if (!@is_dir($root)) {
            if (!@mkdir($root, 0755, true)) {
                return array(false, 'Could not create web root: ' . $root);
            }
        }

        if (self::mode() === 'nginx') {
            $dirs = self::dirs();
            $conf = $dirs['avail'] . '/' . $domain . '.conf';
            if (is_file($conf)) { return array(false, 'Config file already exists.'); }
            $sslPath = '';
            if ($ssl) {
                if (!@is_dir($dirs['ssl'])) { @mkdir($dirs['ssl'], 0700, true); }
                list(, $code, $ok) = sh(sudo_prefix() . 'openssl req -x509 -nodes -days 3650 -newkey rsa:2048 '
                    . '-keyout ' . escapeshellarg($dirs['ssl'] . '/' . $domain . '.key') . ' '
                    . '-out ' . escapeshellarg($dirs['ssl'] . '/' . $domain . '.crt') . ' '
                    . '-subj "/CN=' . $domain . '" 2>&1');
                if (!$ok) { return array(false, 'Could not generate SSL certificate.'); }
                $sslPath = $dirs['ssl'];
            }
            $text = self::config_text($domain, $root, $php, (bool)$ssl, $sslPath);
            if (@file_put_contents($conf, $text, LOCK_EX) === false) {
                return array(false, 'Could not write config (permission denied?). Run panel as root.');
            }
            if (!@symlink($conf, $dirs['enabled'] . '/' . $domain) && !@copy($conf, $dirs['enabled'] . '/' . $domain)) {
                @unlink($conf);
                return array(false, 'Could not enable site.');
            }
            list($ok2, $msg) = Services::nginx_test_and_reload();
            if (!$ok2) {
                // roll back so nginx keeps working
                @unlink($dirs['enabled'] . '/' . $domain);
                return array(false, 'nginx config test failed: ' . $msg);
            }
            // default index page
            if (!is_file($root . '/index.html') && !is_file($root . '/index.php')) {
                @file_put_contents($root . '/index.html', "<!DOCTYPE html>\n<html><head><title>" . $domain . "</title></head>\n<body style='font-family:sans-serif;text-align:center;padding-top:15vh'><h1>" . $domain . "</h1><p>Site created by WebPanel. Replace this file with your website.</p></body></html>\n");
            }
        } else {
            $st = db()->prepare('INSERT INTO vhosts (domain, root, php, ssl, enabled, created_at) VALUES (?,?,?,?,1,?)');
            $st->execute(array($domain, $root, $php, (int)(bool)$ssl, time()));
            @file_put_contents(PANEL_ROOT . '/data/sites/' . $domain . '.conf', self::config_text($domain, $root, $php, (bool)$ssl));
            if (!is_file($root . '/index.html')) {
                @file_put_contents($root . '/index.html', "<!DOCTYPE html>\n<html><body style='font-family:sans-serif;text-align:center;padding-top:15vh'><h1>" . $domain . "</h1><p>Virtual site (test mode) created by WebPanel.</p></body></html>\n");
            }
        }
        return array(true, 'Site ' . $domain . ' created and enabled.');
    }

    public static function remove($domain) {
        $site = self::find($domain);
        if (!$site) { return array(false, 'Site not found.'); }
        if (self::mode() === 'nginx') {
            $dirs = self::dirs();
            @unlink($dirs['enabled'] . '/' . $site['conf']);
            @unlink($dirs['avail'] . '/' . $site['conf'] . '.conf');
            if ($site['ssl']) {
                @unlink($dirs['ssl'] . '/' . $site['conf'] . '.crt');
                @unlink($dirs['ssl'] . '/' . $site['conf'] . '.key');
            }
            list($ok, $msg) = Services::nginx_test_and_reload();
            if (!$ok) { return array(false, 'Site removed but nginx reload failed: ' . $msg); }
        } else {
            db()->prepare('DELETE FROM vhosts WHERE domain = ?')->execute(array($site['domain']));
            @unlink(PANEL_ROOT . '/data/sites/' . $site['domain'] . '.conf');
        }
        return array(true, 'Site ' . $site['domain'] . ' removed. Web root was kept.');
    }

    public static function toggle($domain, $enable) {
        $site = self::find($domain);
        if (!$site) { return array(false, 'Site not found.'); }
        if (self::mode() === 'nginx') {
            $dirs = self::dirs();
            if ($enable) {
                if (!is_file($dirs['enabled'] . '/' . $site['conf']) && !@symlink($dirs['avail'] . '/' . $site['conf'] . '.conf', $dirs['enabled'] . '/' . $site['conf'])) {
                    return array(false, 'Could not enable site.');
                }
            } else {
                @unlink($dirs['enabled'] . '/' . $site['conf']);
            }
            list($ok, $msg) = Services::nginx_test_and_reload();
            if (!$ok) { return array(false, 'nginx reload failed: ' . $msg); }
        } else {
            db()->prepare('UPDATE vhosts SET enabled = ? WHERE domain = ?')->execute(array($enable ? 1 : 0, $site['domain']));
        }
        return array(true, 'Site ' . $site['domain'] . ($enable ? ' enabled.' : ' disabled.'));
    }

    public static function issue_certbot($domain) {
        $site = self::find($domain);
        if (!$site) { return array(false, 'Site not found.'); }
        if (self::mode() !== 'nginx') { return array(false, 'Let\'s Encrypt requires nginx on Linux.'); }
        list(, , $hasCertbot) = sh('command -v certbot');
        if (!$hasCertbot) { return array(false, 'certbot is not installed. Install it from App Store.'); }
        $email = config_get('le_email', '');
        $mailArg = $email ? '-m ' . escapeshellarg($email) : '--register-unsafely-without-email';
        $cmd = sudo_prefix() . 'certbot --nginx -d ' . escapeshellarg($domain)
            . (strpos($domain, 'www.') !== 0 ? ' -d ' . escapeshellarg('www.' . $domain) : '')
            . ' --non-interactive --agree-tos ' . $mailArg . ' --redirect 2>&1';
        $id = Tasks::start('Let\'s Encrypt: ' . $domain, $cmd);
        return array(true, 'SSL request started (task #' . $id . ').', array('task_id' => $id));
    }

    public static function reload_nginx() {
        return Services::nginx_test_and_reload();
    }
}
