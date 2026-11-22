<?php
// WebPanel - JSON API used by the frontend (AJAX). All actions require an
// authenticated session; mutating actions require the CSRF token header.

require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/sysinfo.php';
require __DIR__ . '/inc/tasks.php';
require __DIR__ . '/inc/services.php';
require __DIR__ . '/inc/websites.php';
require __DIR__ . '/inc/databases.php';
require __DIR__ . '/inc/filesys.php';
require __DIR__ . '/inc/pkgs.php';

if (!is_logged_in()) {
    json_out(array('ok' => false, 'error' => 'Not authenticated.'), 401);
}

$action = isset($_GET['a']) ? preg_replace('/[^a-z0-9_]/', '', $_GET['a']) : '';
$p = $_POST;

switch ($action) {

    // ---------------------------------------------------------------- stats
    case 'stats':
        $mem = Sys::memory();
        $net = Sys::network();
        $diskFirst = null;
        $disks = Sys::disks();
        foreach ($disks as $d) {
            if (!$diskFirst || $d['mount'] === '/' || $d['mount'] === 'C:\\') { $diskFirst = $d; }
            if ($d['mount'] === '/' || $d['mount'] === 'C:\\') { break; }
        }
        json_out(array(
            'ok' => true,
            'server' => array(
                'os' => Sys::os(), 'hostname' => Sys::hostname(), 'kernel' => Sys::kernel(),
                'arch' => Sys::arch(), 'cpu_model' => Sys::cpu_model(), 'cpu_cores' => Sys::cpu_cores(),
                'uptime' => Sys::uptime_seconds(), 'php' => Sys::php_cli_version(),
                'panel_version' => PANEL_VERSION, 'is_root' => is_root(), 'is_linux' => is_linux(),
                'www_root' => config_get('www_root', '/var/www'),
                'websites_mode' => Websites::mode(),
            ),
            'cpu' => Sys::cpu_pct(),
            'load' => Sys::load_average(),
            'mem' => $mem,
            'disks' => $disks,
            'disk_primary' => $diskFirst,
            'net' => $net,
            'services' => Sys::services(),
            'processes' => Sys::processes(),
            'time' => time(),
        ));

    // ---------------------------------------------------------------- services
    case 'service':
        csrf_check_json();
        json_out(array('ok' => true, 'result' => Services::control((string)($p['name'] ?? ''), (string)($p['action'] ?? ''))));

    // ---------------------------------------------------------------- websites
    case 'website_list':
        json_out(array('ok' => true, 'sites' => Websites::list_sites(), 'mode' => Websites::mode(),
            'php_versions' => Sys::php_fpm_versions(), 'www_root' => config_get('www_root', '/var/www')));
    case 'website_add':
        csrf_check_json();
        json_out(array('ok' => true, 'result' => Websites::add(
            (string)($p['domain'] ?? ''), (string)($p['root'] ?? ''), (string)($p['php'] ?? 'default'),
            !empty($p['ssl'])
        )));
    case 'website_remove':
        csrf_check_json();
        json_out(array('ok' => true, 'result' => Websites::remove((string)($p['domain'] ?? ''))));
    case 'website_toggle':
        csrf_check_json();
        json_out(array('ok' => true, 'result' => Websites::toggle((string)($p['domain'] ?? ''), !empty($p['enable']))));
    case 'website_certbot':
        csrf_check_json();
        json_out(array('ok' => true, 'result' => Websites::issue_certbot((string)($p['domain'] ?? ''))));
    case 'nginx_reload':
        csrf_check_json();
        json_out(array('ok' => true, 'result' => Websites::reload_nginx()));

    // ---------------------------------------------------------------- databases
    case 'db_list':
        if (!Databases::configured()) {
            json_out(array('ok' => false, 'error' => 'Database root credentials are not set. Configure them in Settings.', 'unconfigured' => true));
        }
        try {
            json_out(array('ok' => true, 'databases' => Databases::list_databases(),
                'users' => Databases::list_users(), 'phpmyadmin' => Databases::phpmyadmin_path()));
        } catch (Exception $ex) {
            json_out(array('ok' => false, 'error' => 'Could not connect to MySQL: ' . $ex->getMessage()));
        }
    case 'db_create':
        csrf_check_json();
        json_out(array('ok' => true, 'result' => Databases::create_database((string)($p['name'] ?? ''))));
    case 'db_drop':
        csrf_check_json();
        json_out(array('ok' => true, 'result' => Databases::drop_database((string)($p['name'] ?? ''))));
    case 'db_user_create':
        csrf_check_json();
        json_out(array('ok' => true, 'result' => Databases::create_user(
            (string)($p['user'] ?? ''), (string)($p['pass'] ?? ''),
            (string)($p['host'] ?? 'localhost'), (string)($p['grant'] ?? '')
        )));
    case 'db_user_drop':
        csrf_check_json();
        json_out(array('ok' => true, 'result' => Databases::drop_user(
            (string)($p['user'] ?? ''), (string)($p['host'] ?? 'localhost')
        )));

    // ---------------------------------------------------------------- file manager
    case 'fs_list':
        $r = Filesys::list_dir((string)($_GET['path'] ?? ''));
        if (!$r[0]) { json_out(array('ok' => false, 'error' => $r[1])); }
        json_out(array('ok' => true, 'data' => $r[1], 'default_path' => Filesys::default_path()));
    case 'fs_read':
        $r = Filesys::read_file((string)($_GET['path'] ?? ''));
        if (!$r[0]) { json_out(array('ok' => false, 'error' => $r[1])); }
        json_out(array('ok' => true, 'data' => $r[1]));
    case 'fs_save':
        csrf_check_json();
        json_out(array('ok' => true, 'result' => Filesys::write_file((string)($p['path'] ?? ''), (string)($p['content'] ?? ''))));
    case 'fs_upload':
        csrf_check_json();
        json_out(array('ok' => true, 'result' => Filesys::upload((string)($p['dir'] ?? ''))));
    case 'fs_newfile':
        csrf_check_json();
        json_out(array('ok' => true, 'result' => Filesys::create_file((string)($p['dir'] ?? ''), (string)($p['name'] ?? ''))));
    case 'fs_newdir':
        csrf_check_json();
        json_out(array('ok' => true, 'result' => Filesys::create_dir((string)($p['dir'] ?? ''), (string)($p['name'] ?? ''))));
    case 'fs_rename':
        csrf_check_json();
        json_out(array('ok' => true, 'result' => Filesys::rename((string)($p['path'] ?? ''), (string)($p['new_path'] ?? ''))));
    case 'fs_delete':
        csrf_check_json();
        json_out(array('ok' => true, 'result' => Filesys::delete((string)($p['path'] ?? ''))));
    case 'fs_chmod':
        csrf_check_json();
        json_out(array('ok' => true, 'result' => Filesys::chmod((string)($p['path'] ?? ''), (string)($p['mode'] ?? ''))));
    case 'fs_download':
        // GET + csrf in query; streams the file directly
        $tok = $_GET['csrf'] ?? '';
        if (!$tok || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $tok)) {
            json_out(array('ok' => false, 'error' => 'Invalid CSRF token.'), 403);
        }
        Filesys::download((string)($_GET['path'] ?? ''));
        json_out(array('ok' => false, 'error' => 'File not found.'));
    case 'fs_dirsize':
        json_out(array('ok' => true, 'size' => Filesys::dir_size((string)($_GET['path'] ?? ''))));

    // ---------------------------------------------------------------- php extensions
    case 'ext_list':
        $ver = (string)($_GET['php'] ?? Pkgs::php_version());
        json_out(array('ok' => true,
            'php_versions' => Sys::php_fpm_versions(),
            'active_version' => $ver,
            'loaded' => Pkgs::loaded_extensions(),
            'catalog' => Pkgs::extension_status($ver),
            'apt' => Pkgs::apt_available(),
            'distro' => Pkgs::distro(),
            'linux' => is_linux(),
        ));
    case 'ext_install':
        csrf_check_json();
        json_out(array('ok' => true, 'result' => Pkgs::extension_install((string)($p['php'] ?? 'default'), (string)($p['ext'] ?? ''))));
    case 'php_restart':
        csrf_check_json();
        json_out(array('ok' => true, 'result' => Pkgs::php_restart((string)($p['php'] ?? 'default'))));

    // ---------------------------------------------------------------- app store
    case 'app_list':
        $apps = array();
        foreach (Pkgs::app_catalog() as $id => $a) {
            $apps[] = array(
                'id' => $id, 'name' => $a['name'], 'desc' => $a['desc'],
                'cat' => $a['cat'], 'color' => $a['color'],
                'installed' => Pkgs::app_status($id),
            );
        }
        json_out(array('ok' => true, 'apps' => $apps, 'linux' => is_linux(), 'distro' => Pkgs::distro()));
    case 'app_install':
        csrf_check_json();
        json_out(array('ok' => true, 'result' => Pkgs::app_install((string)($p['app'] ?? ''))));

    // ---------------------------------------------------------------- tasks
    case 'task_poll':
        $id = (int)($p['id'] ?? 0);
        $r = Tasks::poll($id);
        if ($r === null) { json_out(array('ok' => false, 'error' => 'Task not found.')); }
        json_out(array('ok' => true, 'task' => $r, 'log' => Tasks::log_tail($id)));
    case 'task_log':
        json_out(array('ok' => true, 'log' => Tasks::log_tail((int)($_GET['id'] ?? 0), 500)));

    // ---------------------------------------------------------------- settings
    case 'settings_save':
        csrf_check_json();
        $data = array();
        $panelName = trim((string)($p['panel_name'] ?? ''));
        if ($panelName !== '') { $data['panel_name'] = $panelName; }
        $tz = (string)($p['timezone'] ?? '');
        if (in_array($tz, DateTimeZone::listIdentifiers(), true)) { $data['timezone'] = $tz; }
        $data['www_root'] = rtrim(trim((string)($p['www_root'] ?? '/var/www')), '/\\');
        $data['db_host'] = trim((string)($p['db_host'] ?? ''));
        $data['db_port'] = trim((string)($p['db_port'] ?? '3306'));
        $data['db_user'] = trim((string)($p['db_user'] ?? 'root'));
        $data['db_socket'] = trim((string)($p['db_socket'] ?? ''));
        if (isset($p['db_pass']) && $p['db_pass'] !== '') { $data['db_pass'] = (string)$p['db_pass']; }
        $data['le_email'] = trim((string)($p['le_email'] ?? ''));
        if (!panel_save_config($data)) {
            json_out(array('ok' => false, 'error' => 'Could not write data/config.php. Check folder permissions.'));
        }
        json_out(array('ok' => true, 'result' => array(true, 'Settings saved.')));
    case 'password_change':
        csrf_check_json();
        $cur = (string)($p['current'] ?? '');
        $new = (string)($p['new'] ?? '');
        $hash = (string)config_get('admin_pass', '');
        if (!$hash || !password_verify($cur, $hash)) {
            json_out(array('ok' => true, 'result' => array(false, 'Current password is incorrect.')));
        }
        if (strlen($new) < 8) {
            json_out(array('ok' => true, 'result' => array(false, 'New password must be at least 8 characters.')));
        }
        panel_save_config(array('admin_pass' => password_hash($new, PASSWORD_DEFAULT)));
        json_out(array('ok' => true, 'result' => array(true, 'Password changed.')));
    case 'backup':
        $dbFile = PANEL_ROOT . '/data/panel.db';
        $tmp = PANEL_ROOT . '/data/panel-backup-' . date('Ymd-His') . '.db';
        @copy($dbFile, $tmp);
        if (!is_file($tmp)) { json_out(array('ok' => false, 'error' => 'Could not create backup.')); }
        while (ob_get_level()) { ob_end_clean(); }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($tmp) . '"');
        header('Content-Length: ' . (string)filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;

    default:
        json_out(array('ok' => false, 'error' => 'Unknown API action.'), 404);
}
