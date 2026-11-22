<?php
// WebPanel - file manager backend (browse, read, write, upload, rename, delete, chmod).

if (!defined('PANEL')) { exit('Direct access denied.'); }

final class Filesys {

    const MAX_EDIT_SIZE = 2 * 1024 * 1024; // 2 MB

    /** Normalize a user-supplied path. Returns canonical path or null. */
    public static function real($path) {
        $path = trim((string)$path);
        if ($path === '' || strpos($path, "\0") !== false) { return null; }
        if (is_linux()) {
            if ($path[0] !== '/') { return null; }
        } else {
            $path = str_replace('/', '\\', $path);
            if (!preg_match('/^[a-zA-Z]:\\\\/', $path)) { return null; }
        }
        // collapse . and .. segments and clean separators
        $parts = array();
        foreach (preg_split('#[/\\\\]#', $path) as $seg) {
            if ($seg === '' || $seg === '.') { continue; }
            if ($seg === '..') {
                if ($parts) { array_pop($parts); }
                continue;
            }
            $parts[] = $seg;
        }
        $sep = is_linux() ? '/' : '\\';
        $canon = (is_linux() ? '/' : '') . implode($sep, $parts);
        if (is_linux() && $canon === '') { $canon = '/'; }
        if (!is_linux() && preg_match('/^[a-zA-Z]:$/', $canon)) { $canon .= '\\'; }
        return $canon;
    }

    public static function default_path() {
        if (is_linux()) {
            foreach (array('/var/www', '/home', '/etc/nginx', '/') as $p) {
                if (is_dir($p)) { return $p; }
            }
        }
        return 'C:\\';
    }

    private static function seg($path) {
        $sep = is_linux() ? '/' : '\\';
        return $sep === '/' ? trim($path, '/') : trim($path, '\\');
    }

    public static function join($dir, $name) {
        $dir = rtrim($dir, '/\\');
        return $dir . (is_linux() ? '/' : '\\') . $name;
    }

    public static function exists($path) {
        return $path !== null && @file_exists($path);
    }

    // ------------------------------------------------------------ listing
    public static function list_dir($path) {
        if (trim((string)$path) === '') { $path = self::default_path(); }
        $path = self::real($path);
        if ($path === null || !is_dir($path)) {
            return array(false, 'Invalid directory.');
        }
        $entries = array();
        $items = @scandir($path);
        if ($items === false) { return array(false, 'Permission denied.'); }
        foreach ($items as $name) {
            if ($name === '.' || $name === '..') { continue; }
            $full = self::join($path, $name);
            $isDir = is_dir($full);
            $isLink = is_link($full);
            $stat = @stat($full);
            $entries[] = array(
                'name'     => $name,
                'full'     => $full,
                'is_dir'   => $isDir,
                'is_link'  => $isLink,
                'size'     => $isDir ? null : (int)($stat['size'] ?? 0),
                'mtime'    => (int)($stat['mtime'] ?? 0),
                'perm'     => $stat ? substr(sprintf('%o', $stat['mode']), -4) : '',
                'owner'    => self::owner_name($stat),
                'editable' => !$isDir && self::is_editable($full),
            );
        }
        usort($entries, function ($a, $b) {
            if ($a['is_dir'] !== $b['is_dir']) { return $a['is_dir'] ? -1 : 1; }
            return strcasecmp($a['name'], $b['name']);
        });
        // parent path
        $parent = null;
        $sep = is_linux() ? '/' : '\\';
        if (is_linux()) {
            if ($path !== '/') { $parent = dirname($path); }
        } else {
            $parent = dirname($path);
        }
        return array(true, array('path' => $path, 'parent' => $parent, 'entries' => $entries));
    }

    private static function owner_name($stat) {
        if (!$stat) { return '-'; }
        if (function_exists('posix_getpwuid') && isset($stat['uid'])) {
            $info = @posix_getpwuid($stat['uid']);
            if ($info) { return $info['name']; }
        }
        return '-';
    }

    public static function is_editable($file) {
        $size = @filesize($file);
        if ($size === false || $size > self::MAX_EDIT_SIZE) { return false; }
        $fh = @fopen($file, 'rb');
        if (!$fh) { return false; }
        $chunk = fread($fh, 8192);
        fclose($fh);
        return strpos($chunk, "\0") === false; // no NUL bytes => text-ish
    }

    // ------------------------------------------------------------ file ops
    public static function read_file($path) {
        $path = self::real($path);
        if ($path === null || !is_file($path)) { return array(false, 'File not found.'); }
        if (!self::is_editable($path)) { return array(false, 'File is binary or too large to edit.'); }
        $data = @file_get_contents($path);
        if ($data === false) { return array(false, 'Permission denied.'); }
        return array(true, array('path' => $path, 'name' => basename($path), 'content' => $data, 'size' => strlen($data)));
    }

    public static function write_file($path, $content) {
        $path = self::real($path);
        if ($path === null) { return array(false, 'Invalid path.'); }
        if (!@file_exists($path)) { return array(false, 'File does not exist.'); }
        if (strlen($content) > self::MAX_EDIT_SIZE) { return array(false, 'Content too large (max 2 MB).'); }
        if (@file_put_contents($path, $content, LOCK_EX) === false) {
            return array(false, 'Could not write file (permission denied?).');
        }
        return array(true, 'File saved: ' . basename($path));
    }

    public static function create_file($dir, $name) {
        $dir = self::real($dir);
        $name = (string)$name;
        if ($dir === null || !is_dir($dir)) { return array(false, 'Invalid directory.'); }
        if (!preg_match('/^[^\/\\\\:\*\?"<>\|]+$/', $name) || $name === '' || $name === '.' || $name === '..') {
            return array(false, 'Invalid file name.');
        }
        $full = self::join($dir, $name);
        if (@file_exists($full)) { return array(false, 'Already exists.'); }
        if (@file_put_contents($full, '') === false) { return array(false, 'Could not create file (permission denied?).'); }
        return array(true, 'File created.');
    }

    public static function create_dir($dir, $name) {
        $dir = self::real($dir);
        $name = (string)$name;
        if ($dir === null || !is_dir($dir)) { return array(false, 'Invalid directory.'); }
        if (!preg_match('/^[^\/\\\\:\*\?"<>\|]+$/', $name) || $name === '' || $name === '.' || $name === '..') {
            return array(false, 'Invalid folder name.');
        }
        $full = self::join($dir, $name);
        if (@file_exists($full)) { return array(false, 'Already exists.'); }
        if (!@mkdir($full, 0755)) { return array(false, 'Could not create folder (permission denied?).'); }
        return array(true, 'Folder created.');
    }

    public static function rename($old, $new) {
        $old = self::real($old);
        $new = self::real($new);
        if ($old === null || $new === null) { return array(false, 'Invalid path.'); }
        if (!@file_exists($old)) { return array(false, 'Source not found.'); }
        if (@file_exists($new)) { return array(false, 'Target name already exists.'); }
        if (!@rename($old, $new)) { return array(false, 'Rename failed (permission denied?).'); }
        return array(true, 'Renamed to ' . basename($new) . '.');
    }

    public static function delete($path) {
        $path = self::real($path);
        if ($path === null) { return array(false, 'Invalid path.'); }
        if ($path === self::real(self::default_path())) { return array(false, 'Refusing to delete the panel base path.'); }
        if (!@file_exists($path) && !@is_link($path)) { return array(false, 'Not found.'); }
        if (is_dir($path) && !is_link($path)) {
            self::rm_r($path);
        } elseif (!@unlink($path)) {
            return array(false, 'Delete failed (permission denied?).');
        }
        return array(true, 'Deleted ' . basename($path) . '.');
    }

    private static function rm_r($dir) {
        $items = @scandir($dir) ?: array();
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') { continue; }
            $full = self::join($dir, $item);
            if (is_dir($full) && !is_link($full)) { self::rm_r($full); }
            else { @unlink($full); }
        }
        @rmdir($dir);
    }

    public static function chmod($path, $mode) {
        $path = self::real($path);
        if ($path === null || !@file_exists($path)) { return array(false, 'Not found.'); }
        $mode = trim((string)$mode);
        if (!preg_match('/^[0-7]{3,4}$/', $mode)) { return array(false, 'Mode must be octal, e.g. 0755.'); }
        $oct = intval($mode, 8);
        if (!@chmod($path, $oct)) { return array(false, 'chmod failed (permission denied?).'); }
        return array(true, 'Permissions set to ' . $mode . '.');
    }

    public static function upload($dir) {
        $dir = self::real($dir);
        if ($dir === null || !is_dir($dir)) { return array(false, 'Invalid directory.'); }
        if (empty($_FILES['file'])) { return array(false, 'No file uploaded.'); }
        $f = $_FILES['file'];
        if ($f['error'] !== UPLOAD_ERR_OK) { return array(false, 'Upload error code ' . $f['error'] . '.'); }
        $name = basename(str_replace('\\', '/', $f['name']));
        if ($name === '' || $name === '.' || $name === '..') { return array(false, 'Invalid file name.'); }
        $target = self::join($dir, $name);
        if (@file_exists($target)) { return array(false, 'File already exists.'); }
        if (!@move_uploaded_file($f['tmp_name'], $target)) { return array(false, 'Move failed (permission denied?).'); }
        return array(true, 'Uploaded ' . $name . '.');
    }

    public static function download($path) {
        $path = self::real($path);
        if ($path === null || !is_file($path)) { return array(false, 'File not found.'); }
        $name = basename($path);
        $size = @filesize($path);
        while (ob_get_level()) { ob_end_clean(); }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . (string)$size);
        header('X-Content-Type-Options: nosniff');
        $fh = @fopen($path, 'rb');
        if ($fh) {
            while (!feof($fh)) { echo fread($fh, 1048576); }
            fclose($fh);
        }
        exit;
    }

    public static function dir_size($path) {
        $path = self::real($path);
        if ($path === null || !is_dir($path)) { return null; }
        if (is_linux()) {
            list($out, , $ok) = sh('du -sb ' . escapeshellarg($path) . ' 2>/dev/null | cut -f1', 20);
            if ($ok && $out) { return (float)trim($out[0]); }
        }
        return null;
    }
}
