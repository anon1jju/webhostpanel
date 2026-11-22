<?php
// WebPanel - asynchronous background task runner.
// Tasks are long-running shell commands (apt installs, service restarts, etc).
// They run detached and write their output to data/tasks/{id}.log.

if (!defined('PANEL')) { exit('Direct access denied.'); }

final class Tasks {

    public static function start($name, $cmd) {
        $id = (int)db()->lastInsertId();
        $st = db()->prepare('INSERT INTO tasks (name, status, created_at) VALUES (?, ?, ?)');
        $st->execute(array($name, 'running', time()));
        $id = (int)db()->lastInsertId();
        if ($id <= 0) { return 0; }

        $log = PANEL_ROOT . '/data/tasks/' . $id . '.log';
        @file_put_contents($log, "== WebPanel task: " . $name . " ==\n$ " . $cmd . "\n\n");

        if (is_linux()) {
            $wrapper = PANEL_ROOT . '/data/tasks/' . $id . '.sh';
            $script  = "#!/bin/sh\n";
            $script .= "{\n" . $cmd . "\n} >> " . escapeshellarg($log) . " 2>&1\n";
            $script .= "echo \"EXIT:\$?\" >> " . escapeshellarg($log) . "\n";
            @file_put_contents($wrapper, $script);
            @chmod($wrapper, 0700);
            list(, $code, $ok) = sh('nohup ' . escapeshellarg($wrapper) . ' >/dev/null 2>&1 & echo $!');
            if (!$ok || $code !== 0) {
                db()->prepare("UPDATE tasks SET status='failed' WHERE id=?")->execute(array($id));
                @file_put_contents($log, "\n[task] failed to start background process\n", FILE_APPEND);
                return $id;
            }
        } else {
            // Windows (local testing): run via cmd, write EXIT marker too.
            $wrapper = PANEL_ROOT . '/data/tasks/' . $id . '.bat';
            $script  = "@echo off\r\n";
            $script .= "(\r\n" . str_replace('%', '%%', $cmd) . "\r\n) >> \"" . $log . "\" 2>&1\r\n";
            $script .= "echo EXIT:%errorlevel% >> \"" . $log . "\"\r\n";
            @file_put_contents($wrapper, $script);
            list(, , $ok) = sh('powershell -NoProfile -WindowStyle Hidden -Command "Start-Process -FilePath cmd.exe -ArgumentList \'/c \"' . $wrapper . '\"\' -WindowStyle Hidden"');
            if (!$ok) {
                db()->prepare("UPDATE tasks SET status='failed' WHERE id=?")->execute(array($id));
                @file_put_contents($log, "\n[task] failed to start background process\n", FILE_APPEND);
                return $id;
            }
        }
        return $id;
    }

    public static function all() {
        return db()->query('SELECT * FROM tasks ORDER BY id DESC LIMIT 30')->fetchAll();
    }

    /** Returns [status, exit_code] and marks finished tasks in db. */
    public static function poll($id) {
        $st = db()->prepare('SELECT * FROM tasks WHERE id = ?');
        $st->execute(array($id));
        $task = $st->fetch();
        if (!$task) { return null; }

        $log = PANEL_ROOT . '/data/tasks/' . $id . '.log';
        $status = 'running';
        $exit = null;
        if (is_file($log)) {
            $tail = '';
            $fh = @fopen($log, 'r');
            if ($fh) {
                fseek($fh, max(0, filesize($log) - 4096));
                $tail = stream_get_contents($fh);
                fclose($fh);
            }
            if (preg_match('/EXIT:(\d+)\s*$/', $tail, $m)) { $exit = (int)$m[1]; }
            // Batch wrapper may append trailing newline; search anywhere in last chunk
            if ($exit === null && preg_match('/EXIT:(\d+)/', $tail, $m)) { $exit = (int)$m[1]; }
        }
        if ($exit !== null) {
            $status = ($exit === 0) ? 'done' : 'failed';
            if ($task['status'] !== $status) {
                db()->prepare('UPDATE tasks SET status = ?, finished_at = ? WHERE id = ?')
                   ->execute(array($status, time(), $id));
            }
        }
        return array('status' => $status, 'exit' => $exit);
    }

    public static function log_tail($id, $lines = 200) {
        $log = PANEL_ROOT . '/data/tasks/' . $id . '.log';
        if (!is_file($log)) { return ''; }
        $data = @file_get_contents($log);
        if ($data === false) { return ''; }
        $linesArr = explode("\n", $data);
        return implode("\n", array_slice($linesArr, -max(1, $lines)));
    }

    public static function delete_old($keep = 50) {
        $rows = db()->query('SELECT id FROM tasks ORDER BY id DESC LIMIT -1 OFFSET ' . (int)$keep)->fetchAll();
        foreach ($rows as $r) {
            @unlink(PANEL_ROOT . '/data/tasks/' . $r['id'] . '.log');
            @unlink(PANEL_ROOT . '/data/tasks/' . $r['id'] . '.sh');
            @unlink(PANEL_ROOT . '/data/tasks/' . $r['id'] . '.bat');
            db()->prepare('DELETE FROM tasks WHERE id = ?')->execute(array($r['id']));
        }
    }
}
