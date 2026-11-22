<?php
// WebPanel - package management: PHP extension installs + one-click app installs.

if (!defined('PANEL')) { exit('Direct access denied.'); }

final class Pkgs {

    // ------------------------------------------------------------ distro
    public static function distro() {
        static $v = null;
        if ($v !== null) { return $v; }
        $v = array('id' => 'unknown', 'pretty' => '', 'debian_like' => false, 'apt' => false);
        if (is_linux()) {
            $raw = @file_get_contents('/etc/os-release');
            if ($raw && preg_match('/^ID="?([a-z]+)"?/m', $raw, $m)) { $v['id'] = $m[1]; }
            if ($raw && preg_match('/^PRETTY_NAME="?([^"\n]+)"?/m', $raw, $m)) { $v['pretty'] = $m[1]; }
            $v['debian_like'] = in_array($v['id'], array('debian', 'ubuntu', 'linuxmint', 'pop', 'kali'), true);
            if ($v['debian_like']) {
                list(, , $ok) = sh('command -v apt-get');
                $v['apt'] = $ok;
            }
        }
        return $v;
    }

    public static function apt_available() {
        $d = self::distro();
        return $d['apt'] && is_linux();
    }

    // ------------------------------------------------------------ php versions / extensions
    /** PHP major.minor used by the CLI binary. */
    public static function php_version() {
        return PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
    }

    public static function loaded_extensions() {
        return Sys::php_loaded_extensions();
    }

    /** Curated extension catalog: id => apt package suffix (php{ver}-{suffix}) */
    public static function extension_catalog() {
        return array(
            'bcmath'    => array('desc' => 'Arbitrary precision mathematics'),
            'bz2'       => array('desc' => 'Bzip2 compression'),
            'calendar'  => array('desc' => 'Calendar conversion functions'),
            'ctype'     => array('desc' => 'Character type checking'),
            'curl'      => array('desc' => 'HTTP requests (cURL)'),
            'dom'       => array('desc' => 'XML/DOM manipulation'),
            'exif'      => array('desc' => 'Exchangeable image information'),
            'fileinfo'  => array('desc' => 'File type detection'),
            'gd'        => array('desc' => 'Image processing (GD)'),
            'gettext'   => array('desc' => 'Native language support'),
            'gmp'       => array('desc' => 'GNU multiple precision'),
            'iconv'     => array('desc' => 'Character set conversion'),
            'imagick'   => array('desc' => 'ImageMagick bindings'),
            'imap'      => array('desc' => 'IMAP/POP3 mail access'),
            'intl'      => array('desc' => 'Internationalization (ICU)'),
            'mbstring'  => array('desc' => 'Multibyte string handling'),
            'memcached' => array('desc' => 'Memcached client'),
            'opcache'   => array('desc' => 'PHP bytecode cache'),
            'pdo_mysql' => array('desc' => 'MySQL via PDO'),
            'pdo_pgsql' => array('desc' => 'PostgreSQL via PDO'),
            'pdo_sqlite'=> array('desc' => 'SQLite via PDO'),
            'redis'     => array('desc' => 'Redis client (phpredis)'),
            'soap'      => array('desc' => 'SOAP web services'),
            'sqlite3'   => array('desc' => 'SQLite3 extension'),
            'sockets'   => array('desc' => 'Low-level sockets'),
            'xmlrpc'    => array('desc' => 'XML-RPC support'),
            'xsl'       => array('desc' => 'XSL transformations'),
            'zip'       => array('desc' => 'ZIP archive support'),
        );
    }

    /** Build catalog rows with installed state for the chosen PHP version. */
    public static function extension_status($phpVer) {
        $loaded = self::loaded_extensions();
        $loadedLower = array_map('strtolower', $loaded);
        $rows = array();
        foreach (self::extension_catalog() as $id => $info) {
            $installed = in_array(strtolower($id), $loadedLower, true);
            $pkg = $phpVer === 'default' ? ('php-' . $id) : ('php' . $phpVer . '-' . $id);
            $rows[] = array(
                'id' => $id,
                'desc' => $info['desc'],
                'installed' => $installed,
                'pkg' => $pkg,
            );
        }
        usort($rows, function ($a, $b) {
            if ($a['installed'] !== $b['installed']) { return $a['installed'] ? -1 : 1; }
            return strcmp($a['id'], $b['id']);
        });
        return $rows;
    }

    /** Install a PHP extension via apt for the given PHP version (async task). */
    public static function extension_install($phpVer, $extId) {
        if (!self::apt_available()) { return array(false, 'apt is not available on this system.'); }
        $catalog = self::extension_catalog();
        if (!isset($catalog[$extId])) { return array(false, 'Unknown extension.'); }
        if ($phpVer !== 'default' && !preg_match('/^\d+\.\d+$/', $phpVer)) { return array(false, 'Invalid PHP version.'); }
        $base = $phpVer === 'default' ? 'php' : 'php' . $phpVer;
        $cmd = 'DEBIAN_FRONTEND=noninteractive ' . sudo_prefix() . 'apt-get install -y ' . $base . '-' . $extId . ' 2>&1';
        // make sure fpm picks it up too
        $fpmPkg = $phpVer === 'default' ? 'php-fpm' : 'php' . $phpVer . '-fpm';
        $cmd .= '; DEBIAN_FRONTEND=noninteractive ' . sudo_prefix() . 'apt-get install -y ' . $fpmPkg . ' >/dev/null 2>&1';
        if ($phpVer !== 'default') {
            $cmd .= '; if command -v phpenmod >/dev/null; then ' . sudo_prefix() . 'phpenmod -v ' . $phpVer . ' ' . $extId . ' 2>&1; fi';
        } else {
            $cmd .= '; if command -v phpenmod >/dev/null; then ' . sudo_prefix() . 'phpenmod ' . $extId . ' 2>&1; fi';
        }
        $unit = $phpVer === 'default' ? 'php-fpm' : 'php' . $phpVer . '-fpm';
        $cmd .= '; ' . sudo_prefix() . 'systemctl restart ' . $unit . ' >/dev/null 2>&1 || true';
        $id = Tasks::start('Install php ext: ' . $extId, $cmd);
        return array(true, 'Installation started (task #' . $id . ').', array('task_id' => $id));
    }

    public static function php_restart($phpVer) {
        if (!is_linux()) { return array(false, 'Only available on Linux.'); }
        $unit = $phpVer === 'default' ? 'php-fpm' : 'php' . $phpVer . '-fpm';
        list(, , $ok) = sh(sudo_prefix() . 'systemctl restart ' . escapeshellarg($unit) . ' 2>&1');
        return array($ok, $ok ? 'php-fpm restarted.' : 'Could not restart ' . $unit . '.');
    }

    // ------------------------------------------------------------ one-click apps
    public static function app_catalog() {
        $d = self::distro();
        $osId = $d['id'];
        $A = sudo_prefix();
        return array(
            'nginx' => array(
                'name' => 'Nginx', 'cat' => 'web', 'color' => 'green',
                'desc' => 'High-performance web server & reverse proxy',
                'check' => 'command -v nginx',
                'install' => 'DEBIAN_FRONTEND=noninteractive ' . $A . 'apt-get install -y nginx 2>&1',
            ),
            'apache' => array(
                'name' => 'Apache', 'cat' => 'web', 'color' => 'blue',
                'desc' => 'Classic HTTP server (apache2)',
                'check' => 'command -v apache2',
                'install' => 'DEBIAN_FRONTEND=noninteractive ' . $A . 'apt-get install -y apache2 2>&1',
            ),
            'mariadb' => array(
                'name' => 'MariaDB', 'cat' => 'db', 'color' => 'brown',
                'desc' => 'MySQL-compatible database server',
                'check' => 'command -v mariadbd || command -v mysqld',
                'install' => 'DEBIAN_FRONTEND=noninteractive ' . $A . 'apt-get install -y mariadb-server 2>&1',
            ),
            'mysql' => array(
                'name' => 'MySQL', 'cat' => 'db', 'color' => 'orange',
                'desc' => 'Oracle MySQL server',
                'check' => 'command -v mysqld',
                'install' => 'DEBIAN_FRONTEND=noninteractive ' . $A . 'apt-get install -y mysql-server 2>&1',
            ),
            'php-default' => array(
                'name' => 'PHP (distro)', 'cat' => 'lang', 'color' => 'indigo',
                'desc' => 'Latest PHP from ' . $d['pretty'] . ' + common extensions',
                'check' => 'command -v php-fpm || command -v php',
                'install' => 'DEBIAN_FRONTEND=noninteractive ' . $A . 'apt-get install -y php-fpm php-cli php-mysql php-curl php-gd php-mbstring php-xml php-zip php-intl php-opcache php-imagick 2>&1',
            ),
            'php82' => array(
                'name' => 'PHP 8.2 (PPA)', 'cat' => 'lang', 'color' => 'indigo',
                'desc' => 'PHP 8.2 via ondrej/php repository',
                'check' => 'test -d /etc/php/8.2',
                'install' => 'DEBIAN_FRONTEND=noninteractive ' . $A . 'apt-get install -y software-properties-common >/dev/null 2>&1; ' . $A . 'add-apt-repository -y ppa:ondrej/php 2>&1; DEBIAN_FRONTEND=noninteractive ' . $A . 'apt-get update -qq 2>&1; DEBIAN_FRONTEND=noninteractive ' . $A . 'apt-get install -y php8.2-fpm php8.2-cli php8.2-mysql php8.2-curl php8.2-gd php8.2-mbstring php8.2-xml php8.2-zip php8.2-intl php8.2-opcache 2>&1',
            ),
            'php83' => array(
                'name' => 'PHP 8.3 (PPA)', 'cat' => 'lang', 'color' => 'indigo',
                'desc' => 'PHP 8.3 via ondrej/php repository',
                'check' => 'test -d /etc/php/8.3',
                'install' => 'DEBIAN_FRONTEND=noninteractive ' . $A . 'apt-get install -y software-properties-common >/dev/null 2>&1; ' . $A . 'add-apt-repository -y ppa:ondrej/php 2>&1; DEBIAN_FRONTEND=noninteractive ' . $A . 'apt-get update -qq 2>&1; DEBIAN_FRONTEND=noninteractive ' . $A . 'apt-get install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-curl php8.3-gd php8.3-mbstring php8.3-xml php8.3-zip php8.3-intl php8.3-opcache 2>&1',
            ),
            'phpmyadmin' => array(
                'name' => 'phpMyAdmin', 'cat' => 'tool', 'color' => 'teal',
                'desc' => 'Web-based MySQL administration',
                'check' => 'test -d /usr/share/phpmyadmin',
                'install' => $A . 'mkdir -p /usr/share/phpmyadmin 2>&1; cd /tmp 2>&1; curl -sSL -o pma.zip https://files.phpmyadmin.net/phpMyAdmin/5.2.1/phpMyAdmin-5.2.1-all-languages.zip 2>&1 && unzip -qo pma.zip 2>&1 && ' . $A . 'cp -rf phpMyAdmin-5.2.1-all-languages/* /usr/share/phpmyadmin/ 2>&1 && ' . $A . 'chown -R www-data:www-data /usr/share/phpmyadmin 2>&1 && rm -rf pma.zip phpMyAdmin-5.2.1-all-languages 2>&1',
            ),
            'redis' => array(
                'name' => 'Redis', 'cat' => 'db', 'color' => 'red',
                'desc' => 'In-memory key-value store',
                'check' => 'command -v redis-server',
                'install' => 'DEBIAN_FRONTEND=noninteractive ' . $A . 'apt-get install -y redis-server 2>&1',
            ),
            'memcached' => array(
                'name' => 'Memcached', 'cat' => 'db', 'color' => 'slate',
                'desc' => 'Distributed memory object cache',
                'check' => 'command -v memcached',
                'install' => 'DEBIAN_FRONTEND=noninteractive ' . $A . 'apt-get install -y memcached 2>&1',
            ),
            'nodejs' => array(
                'name' => 'Node.js LTS', 'cat' => 'lang', 'color' => 'green',
                'desc' => 'JavaScript runtime (NodeSource)',
                'check' => 'command -v node',
                'install' => 'curl -fsSL https://deb.nodesource.com/setup_20.x | ' . $A . 'bash - 2>&1; DEBIAN_FRONTEND=noninteractive ' . $A . 'apt-get install -y nodejs 2>&1',
            ),
            'composer' => array(
                'name' => 'Composer', 'cat' => 'tool', 'color' => 'stone',
                'desc' => 'PHP dependency manager',
                'check' => 'command -v composer',
                'install' => 'curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer 2>&1',
            ),
            'git' => array(
                'name' => 'Git', 'cat' => 'tool', 'color' => 'orange',
                'desc' => 'Distributed version control',
                'check' => 'command -v git',
                'install' => 'DEBIAN_FRONTEND=noninteractive ' . $A . 'apt-get install -y git 2>&1',
            ),
            'certbot' => array(
                'name' => 'Certbot', 'cat' => 'ssl', 'color' => 'amber',
                'desc' => 'Free Let\'s Encrypt SSL certificates',
                'check' => 'command -v certbot',
                'install' => 'DEBIAN_FRONTEND=noninteractive ' . $A . 'apt-get install -y certbot python3-certbot-nginx 2>&1',
            ),
            'fail2ban' => array(
                'name' => 'Fail2ban', 'cat' => 'sec', 'color' => 'rose',
                'desc' => 'Brute-force protection daemon',
                'check' => 'command -v fail2ban-client',
                'install' => 'DEBIAN_FRONTEND=noninteractive ' . $A . 'apt-get install -y fail2ban 2>&1',
            ),
        );
    }

    public static function app_status($id) {
        $catalog = self::app_catalog();
        if (!isset($catalog[$id])) { return null; }
        $app = $catalog[$id];
        if (!is_linux()) { return array('id' => $id, 'installed' => false, 'linux_only' => true); }
        list(, , $ok) = sh($app['check'] . ' >/dev/null 2>&1');
        return array('id' => $id, 'installed' => $ok, 'linux_only' => false);
    }

    public static function app_install($id) {
        $catalog = self::app_catalog();
        if (!isset($catalog[$id])) { return array(false, 'Unknown app.'); }
        if (!is_linux()) { return array(false, 'App Store installs require a Linux server.'); }
        $app = $catalog[$id];
        $taskId = Tasks::start('Install: ' . $app['name'], $app['install']);
        return array(true, 'Installation of ' . $app['name'] . ' started (task #' . $taskId . ').', array('task_id' => $taskId));
    }
}
