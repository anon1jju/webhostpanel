<?php
// WebPanel - system information gathering. Linux is the target platform;
// Windows is supported as a degraded fallback (useful for local testing).

if (!defined('PANEL')) { exit('Direct access denied.'); }

final class Sys {

    /** Cache helper: store computed values as JSON in data/cache with TTL. */
    private static function cached($key, $ttl, $fn) {
        $file = PANEL_ROOT . '/data/cache/' . $key . '.json';
        $now = time();
        if (is_file($file)) {
            $raw = @json_decode(@file_get_contents($file), true);
            if (is_array($raw) && ($now - (int)$raw['t']) < $ttl) {
                return $raw['v'];
            }
        }
        try {
            $v = $fn();
        } catch (Throwable $ex) {
            $v = null;
        }
        @file_put_contents($file, json_encode(array('t' => $now, 'v' => $v)), LOCK_EX);
        return $v;
    }

    /** Windows fallback: gather every metric in ONE PowerShell process (fast). */
    private static function win_snapshot() {
        return self::cached('win_snap', 20, function () {
            $ps1 = PANEL_ROOT . '/data/cache/win_stats.ps1';
            $script = <<<'PS'
$ErrorActionPreference = 'SilentlyContinue'
$cpu = (Get-Counter '\Processor(_Total)\% Processor Time').CounterSamples.CookedValue
$os  = Get-CimInstance Win32_OperatingSystem
$pr  = Get-CimInstance Win32_Processor | Select-Object -First 1
$boot = 0
if ($os.LastBootUpTime) {
    $t = $os.LastBootUpTime
    if ($t -is [string]) { $t = [System.Management.ManagementDateTimeConverter]::ToDateTime($t) }
    if ($t -is [datetime]) { $boot = [int]((Get-Date) - $t).TotalSeconds }
    if ($boot -lt 0) { $boot = 0 }
}
$disks = @()
Get-PSDrive -PSProvider FileSystem | ForEach-Object {
    $total = $_.Used + $_.Free
    if ($total -gt 0) {
        $disks += [ordered]@{ fs=$_.Name; mount=$_.Root; total=[double]$total; used=[double]$_.Used; avail=[double]$_.Free; pct=[int][math]::Round(100 * $_.Used / $total) }
    }
}
$nets = Get-NetAdapterStatistics | Where-Object { $_.ReceivedBytes -gt 0 }
$net_rx = ($nets | Measure-Object -Property ReceivedBytes -Sum).Sum
$net_tx = ($nets | Measure-Object -Property SentBytes -Sum).Sum
$procs = @()
Get-Process | Sort-Object CPU -Descending | Select-Object -First 8 | ForEach-Object {
    $procs += [ordered]@{ pid=[int]$_.Id; name=$_.ProcessName; cpu=[double][math]::Round($_.CPU,1) }
}
[ordered]@{
    cpu=[double]$cpu
    mem_total=[double]$os.TotalVisibleMemorySize * 1024
    mem_free=[double]$os.FreePhysicalMemory * 1024
    cores=[int]$pr.NumberOfLogicalProcessors
    cpu_name=$pr.Name
    boot=[int]$boot
    disks=$disks
    net_rx=[double]$net_rx
    net_tx=[double]$net_tx
    procs=$procs
} | ConvertTo-Json -Depth 5 -Compress
PS;
            @file_put_contents($ps1, $script);
            list($o, , $ok) = sh('powershell -NoProfile -ExecutionPolicy Bypass -File ' . escapeshellarg($ps1), 20);
            $raw = is_array($o) ? implode("\n", $o) : '';
            $j = json_decode($raw, true);
            return is_array($j) ? $j : null;
        });
    }

    // ---------------------------------------------------------- basics
    public static function os() {
        static $v = null;
        if ($v !== null) { return $v; }
        if (is_linux()) {
            $p = @file_get_contents('/etc/os-release');
            if ($p && preg_match('/PRETTY_NAME="?([^"\n]+)"?/', $p, $m)) { $v = trim($m[1]); }
        }
        if (!$v) { $v = php_uname('s') . ' ' . php_uname('r'); }
        return $v;
    }

    public static function hostname() {
        static $v = null;
        if ($v !== null) { return $v; }
        if (is_linux()) {
            $h = @trim(@file_get_contents('/etc/hostname'));
            if ($h) { $v = $h; }
        }
        if (!$v) { $v = function_exists('gethostname') ? (string)gethostname() : php_uname('n'); }
        return $v;
    }

    public static function kernel() { return php_uname('r'); }

    public static function arch() { return php_uname('m'); }

    public static function php_cli_version() { return PHP_VERSION; }

    public static function uptime_seconds() {
        if (is_linux()) {
            $u = @file_get_contents('/proc/uptime');
            if ($u) { return (float)strtok($u, ' '); }
        }
        $snap = self::win_snapshot();
        return isset($snap['boot']) ? (float)$snap['boot'] : 0;
    }

    // ---------------------------------------------------------- CPU
    public static function cpu_model() {
        static $v = null;
        if ($v !== null) { return $v; }
        if (is_linux()) {
            $f = @file('/proc/cpuinfo');
            if ($f) {
                foreach ($f as $line) {
                    if (stripos($line, 'model name') === 0) {
                        $v = trim(substr($line, strpos($line, ':') + 1));
                        break;
                    }
                }
                if (!$v) { $v = 'Unknown CPU'; }
                return $v;
            }
        }
        $snap = self::win_snapshot();
        $v = isset($snap['cpu_name']) && $snap['cpu_name'] ? $snap['cpu_name'] : 'Unknown CPU';
        return $v;
    }

    public static function cpu_cores() {
        if (is_linux()) {
            $f = @file('/proc/cpuinfo');
            if ($f) {
                $n = 0;
                foreach ($f as $line) { if (stripos($line, 'processor') === 0) { $n++; } }
                if ($n > 0) { return $n; }
            }
        }
        $snap = self::win_snapshot();
        return isset($snap['cores']) && (int)$snap['cores'] > 0 ? (int)$snap['cores'] : 1;
    }

    /** CPU usage percent (0-100). Linux: /proc/stat delta over 200ms. Windows: cached counter. */
    public static function cpu_pct() {
        if (is_linux()) {
            $read = function () {
                $l = @file_get_contents('/proc/stat');
                if (!$l) { return null; }
                foreach (explode("\n", $l) as $line) {
                    if (strpos($line, 'cpu ') !== 0) { continue; }
                    $p = array_values(array_filter(preg_split('/\s+/', trim($line)), 'strlen'));
                    array_shift($p);
                    $p = array_map('intval', $p);
                    if (count($p) < 5) { return null; }
                    return array(
                        'idle' => $p[3] + $p[4],
                        'busy' => $p[0] + $p[1] + $p[2] + (isset($p[5]) ? $p[5] : 0) + (isset($p[6]) ? $p[6] : 0) + (isset($p[7]) ? $p[7] : 0),
                    );
                }
                return null;
            };
            $a = $read();
            usleep(200000);
            $b = $read();
            if ($a && $b) {
                $di = $b['idle'] - $a['idle'];
                $db = $b['busy'] - $a['busy'];
                $total = $di + $db;
                if ($total > 0) { return round(100 * $db / $total, 1); }
            }
            return 0.0;
        }
        $snap = self::win_snapshot();
        return isset($snap['cpu']) ? (float)$snap['cpu'] : 0.0;
    }

    public static function load_average() {
        if (is_linux()) {
            $l = @file_get_contents('/proc/loadavg');
            if ($l) {
                $p = explode(' ', $l);
                return array((float)$p[0], (float)$p[1], (float)$p[2]);
            }
        }
        return array(0.0, 0.0, 0.0);
    }

    // ---------------------------------------------------------- memory
    public static function memory() {
        if (is_linux()) {
            $m = array();
            foreach (@file('/proc/meminfo') as $line) {
                if (preg_match('/^(MemTotal|MemAvailable|SwapTotal|SwapFree):\s+(\d+)/', $line, $mm)) {
                    $m[strtolower($mm[1])] = (int)$mm[2] * 1024;
                }
            }
            $total = isset($m['memtotal']) ? $m['memtotal'] : 0;
            $avail = isset($m['memavailable']) ? $m['memavailable'] : 0;
            $swapTotal = isset($m['swaptotal']) ? $m['swaptotal'] : 0;
            $swapFree  = isset($m['swapfree']) ? $m['swapfree'] : 0;
            return array(
                'total' => $total,
                'used'  => $total - $avail,
                'avail' => $avail,
                'pct'   => $total > 0 ? round(100 * ($total - $avail) / $total, 1) : 0,
                'swap_total' => $swapTotal,
                'swap_used'  => $swapTotal - $swapFree,
                'swap_pct'   => $swapTotal > 0 ? round(100 * ($swapTotal - $swapFree) / $swapTotal, 1) : 0,
            );
        }
        $snap = self::win_snapshot();
        $total = isset($snap['mem_total']) ? (float)$snap['mem_total'] : 0;
        $free  = isset($snap['mem_free']) ? (float)$snap['mem_free'] : 0;
        $used  = max(0, $total - $free);
        return array(
            'total' => $total, 'used' => $used, 'avail' => $free,
            'pct'   => $total > 0 ? round(100 * $used / $total, 1) : 0,
            'swap_total' => 0, 'swap_used' => 0, 'swap_pct' => 0,
        );
    }

    // ---------------------------------------------------------- disks
    public static function disks() {
        if (is_linux()) {
            $skip = array('tmpfs', 'devtmpfs', 'squashfs', 'overlay', 'udev');
            list($out, , $ok) = sh('df -P -B1 2>/dev/null');
            if (!$ok) { return array(); }
            $disks = array();
            foreach ($out as $i => $line) {
                if ($i === 0) { continue; }
                if (strpos($line, ' /') === false && $line[0] !== '/') { continue; }
                $cols = preg_split('/\s+/', trim($line));
                if (count($cols) < 6) { continue; }
                list($fs, $blocks, $used, $avail, $pct, $mount) = array($cols[0], $cols[1], $cols[2], $cols[3], $cols[4], $cols[5]);
                if (in_array($fs, $skip, true)) { continue; }
                if ($blocks == 0) { continue; }
                $disks[] = array(
                    'fs'    => $fs,
                    'mount' => $mount,
                    'total' => (float)$blocks,
                    'used'  => (float)$used,
                    'avail' => (float)$avail,
                    'pct'   => (int)rtrim($pct, '%'),
                );
            }
            // merge duplicate mounts (keep the real device)
            $seen = array();
            $result = array();
            foreach ($disks as $d) {
                if (isset($seen[$d['mount']])) { continue; }
                $seen[$d['mount']] = 1;
                $result[] = $d;
            }
            return $result;
        }
        $snap = self::win_snapshot();
        $disks = array();
        if (isset($snap['disks']) && is_array($snap['disks'])) {
            foreach ($snap['disks'] as $d) {
                $disks[] = array(
                    'fs' => isset($d['fs']) ? $d['fs'] : '',
                    'mount' => isset($d['mount']) ? $d['mount'] : '',
                    'total' => isset($d['total']) ? (float)$d['total'] : 0,
                    'used' => isset($d['used']) ? (float)$d['used'] : 0,
                    'avail' => isset($d['avail']) ? (float)$d['avail'] : 0,
                    'pct' => isset($d['pct']) ? (int)$d['pct'] : 0,
                );
            }
        }
        return $disks;
    }

    // ---------------------------------------------------------- network
    /** Current rx/tx rates in bytes/sec (measured since previous call). */
    public static function network() {
        if (is_linux()) {
            $raw = @file_get_contents('/proc/net/dev');
            if ($raw) {
                $rx = 0; $tx = 0;
                foreach (explode("\n", $raw) as $line) {
                    if (strpos($line, ':') === false) { continue; }
                    list($if, $rest) = explode(':', trim($line), 2);
                    $if = trim($if);
                    if ($if === 'lo') { continue; }
                    $cols = preg_split('/\s+/', trim($rest));
                    if (count($cols) >= 9) { $rx += (float)$cols[0]; $tx += (float)$cols[8]; }
                }
                $file = PANEL_ROOT . '/data/cache/net.json';
                $prev = is_file($file) ? @json_decode(@file_get_contents($file), true) : null;
                $nowT = microtime(true);
                $rate = array('rx' => 0.0, 'tx' => 0.0, 'rx_total' => $rx, 'tx_total' => $tx);
                if (is_array($prev)) {
                    $dt = $nowT - (float)$prev['t'];
                    if ($dt > 0.1 && $rx >= (float)$prev['rx']) {
                        $rate['rx'] = round(($rx - (float)$prev['rx']) / $dt, 0);
                        $rate['tx'] = round(($tx - (float)$prev['tx']) / $dt, 0);
                    }
                }
                @file_put_contents($file, json_encode(array('t' => $nowT, 'rx' => $rx, 'tx' => $tx)), LOCK_EX);
                return $rate;
            }
        }
        $snap = self::win_snapshot();
        $rx = isset($snap['net_rx']) ? (float)$snap['net_rx'] : 0;
        $tx = isset($snap['net_tx']) ? (float)$snap['net_tx'] : 0;
        return array('rx' => 0, 'tx' => 0, 'rx_total' => $rx, 'tx_total' => $tx);
    }

    // ---------------------------------------------------------- services
    public static function service_units() {
        $units = array('nginx', 'apache2', 'mysql', 'mariadb', 'redis-server', 'memcached', 'fail2ban');
        if (is_linux()) {
            $glob = @glob('/etc/php/*/fpm');
            if ($glob) {
                foreach ($glob as $dir) {
                    $v = basename($dir);
                    if (preg_match('/^\d+\.\d+$/', $v)) { $units[] = 'php' . $v . '-fpm'; }
                }
            }
        }
        return $units;
    }

    public static function service_status($name) {
        if (is_linux()) {
            list($out, , $ok) = sh('systemctl is-active ' . escapeshellarg($name) . ' 2>/dev/null');
            if ($ok && $out) {
                $s = trim($out[0]);
                return in_array($s, array('active', 'activating'), true) ? 'active' : ($s === 'inactive' ? 'inactive' : $s);
            }
            // fallback: process check
            list(, , $running) = sh('pgrep -x ' . escapeshellarg($name) . ' >/dev/null 2>&1');
            return $running ? 'active' : 'unknown';
        }
        return 'unknown';
    }

    /** statuses of interesting units + whether the unit exists at all */
    public static function services() {
        return self::cached('services', 3, function () {
            $result = array();
            foreach (self::service_units() as $unit) {
                $exists = true;
                if (is_linux()) {
                    list(, , $ok) = sh('command -v ' . escapeshellarg(preg_replace('/^php\d+\.\d+-fpm$/', 'php-fpm', $unit)) . ' >/dev/null 2>&1 || systemctl list-unit-files ' . escapeshellarg($unit . '.service') . ' 2>/dev/null | grep -q ' . escapeshellarg($unit));
                    $exists = $ok;
                }
                $result[] = array('name' => $unit, 'status' => $exists ? self::service_status($unit) : 'missing', 'exists' => $exists);
            }
            return $result;
        });
    }

    // ---------------------------------------------------------- processes
    public static function processes() {
        if (is_linux()) {
            list($out, , $ok) = sh('ps -eo pid,user,pcpu,pmem,comm --sort=-pcpu 2>/dev/null | head -n 9');
            if (!$ok) { return array(); }
            $list = array();
            foreach ($out as $i => $line) {
                if ($i === 0) { continue; }
                $line = trim($line);
                if ($line === '') { continue; }
                $cols = preg_split('/\s+/', $line);
                if (count($cols) < 5) { continue; }
                $list[] = array(
                    'pid' => (int)$cols[0],
                    'user' => $cols[1],
                    'cpu' => (float)$cols[2],
                    'mem' => (float)$cols[3],
                    'name' => implode(' ', array_slice($cols, 4)),
                );
            }
            return $list;
        }
        $snap = self::win_snapshot();
        $list = array();
        if (isset($snap['procs']) && is_array($snap['procs'])) {
            foreach ($snap['procs'] as $p) {
                $list[] = array(
                    'pid' => isset($p['pid']) ? (int)$p['pid'] : 0,
                    'user' => '-',
                    'cpu' => isset($p['cpu']) ? (float)$p['cpu'] : 0.0,
                    'mem' => 0.0,
                    'name' => isset($p['name']) ? (string)$p['name'] : '',
                );
            }
        }
        return $list;
    }

    // ---------------------------------------------------------- php versions (fpm)
    public static function php_fpm_versions() {
        $versions = array();
        if (is_linux()) {
            $glob = @glob('/etc/php/*/fpm');
            if ($glob) {
                foreach ($glob as $dir) {
                    $v = basename(dirname($dir));
                    if (preg_match('/^\d+\.\d+$/', $v)) { $versions[] = $v; }
                }
            }
            sort($versions, SORT_NATURAL);
        }
        if (!$versions) { $versions[] = 'default'; }
        return $versions;
    }

    public static function php_loaded_extensions() {
        $exts = @get_loaded_extensions();
        natcasesort($exts);
        return array_values($exts);
    }
}
