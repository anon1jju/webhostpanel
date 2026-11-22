<?php
// WebPanel - settings: panel name, timezone, db creds, password, backup.
if (!defined('PANEL')) { exit('Direct access denied.'); }
require PANEL_ROOT . '/views/layout.php';

layout_top('Settings', 'settings');
?>
<div class="grid-2">
  <div class="card">
    <div class="card-head"><h3>Panel</h3></div>
    <form id="panel-form" class="form">
      <div class="field">
        <label>Panel name</label>
        <input name="panel_name" type="text" value="<?php echo e(config_get('panel_name', 'WebPanel')); ?>" />
      </div>
      <div class="field">
        <label>Timezone</label>
        <select name="timezone">
          <?php
          $tzs = array('Asia/Jakarta', 'Asia/Makassar', 'Asia/Singapore', 'Asia/Kuala_Lumpur', 'UTC', 'Europe/London', 'Europe/Amsterdam', 'Europe/Berlin', 'America/New_York', 'America/Chicago', 'America/Los_Angeles', 'Asia/Dubai', 'Asia/Kolkata', 'Australia/Sydney');
          $cur = config_get('timezone', 'Asia/Jakarta');
          foreach ($tzs as $tz) {
              echo '<option value="' . e($tz) . '"' . ($tz === $cur ? ' selected' : '') . '>' . e($tz) . '</option>';
          }
          ?>
        </select>
      </div>
      <div class="field">
        <label>Default web root for new sites</label>
        <input name="www_root" type="text" value="<?php echo e(config_get('www_root', '/var/www')); ?>" />
      </div>
      <div class="field">
        <label>Let&rsquo;s Encrypt notification email</label>
        <input name="le_email" type="email" placeholder="admin@yourdomain.com" value="<?php echo e(config_get('le_email', '')); ?>" />
      </div>
      <button type="submit" class="btn btn-primary">Save settings</button>
    </form>
  </div>

  <div class="card">
    <div class="card-head"><h3>Change password</h3></div>
    <form id="pass-form" class="form">
      <div class="field">
        <label>Current password</label>
        <input name="current" type="password" required />
      </div>
      <div class="field">
        <label>New password</label>
        <input name="new" type="password" required minlength="8" />
      </div>
      <button type="submit" class="btn btn-primary">Change password</button>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-head"><h3>MySQL root credentials</h3></div>
  <p class="muted">Used by the Databases page. On Debian/Ubuntu with auth_socket, set Host/Port empty and use the socket path.</p>
  <form id="db-form" class="form form-row">
    <div class="field">
      <label>Host</label>
      <input name="db_host" type="text" placeholder="127.0.0.1" value="<?php echo e(config_get('db_host', '')); ?>" />
    </div>
    <div class="field">
      <label>Port</label>
      <input name="db_port" type="text" value="<?php echo e(config_get('db_port', '3306')); ?>" />
    </div>
    <div class="field">
      <label>User</label>
      <input name="db_user" type="text" value="<?php echo e(config_get('db_user', 'root')); ?>" />
    </div>
    <div class="field">
      <label>Password <span class="muted">(blank keeps current)</span></label>
      <input name="db_pass" type="password" placeholder="<?php echo config_get('db_pass', '') !== '' ? '&bull;&bull;&bull;&bull;&bull;&bull; (set)' : 'not set'; ?>" />
    </div>
    <div class="field">
      <label>Socket (optional)</label>
      <input name="db_socket" type="text" placeholder="/var/run/mysqld/mysqld.sock" value="<?php echo e(config_get('db_socket', '')); ?>" />
    </div>
    <button type="submit" class="btn btn-primary">Save database config</button>
  </form>
</div>

<div class="card">
  <div class="card-head"><h3>Backup</h3></div>
  <div class="btn-group">
    <a class="btn" href="api.php?a=backup">Download panel database</a>
  </div>
  <p class="muted" style="margin-top:10px">
    Panel data: <code class="mono">data/panel.db</code> &middot; Version: v<?php echo PANEL_VERSION; ?> &middot;
    Running as: <?php echo is_linux() ? (is_root() ? 'root' : 'non-root (sudo)') : 'Windows'; ?> &middot;
    PHP <?php echo PHP_VERSION; ?>
  </p>
</div>

<script>
(function () {
  var handle = function (r, okMsg) {
    if (r.ok && r.result && r.result[0]) { WP.toast(okMsg || r.result[1], 'ok'); }
    else { WP.toast(r.result && r.result[1] ? r.result[1] : (r.error || 'Failed'), 'err'); }
  };

  document.getElementById('panel-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var f = new FormData(ev.target);
    WP.api('settings_save', {
      panel_name: f.get('panel_name'), timezone: f.get('timezone'),
      www_root: f.get('www_root'), le_email: f.get('le_email')
    }).then(function (r) {
      handle(r, 'Settings saved.');
      if (r.ok && r.result[0]) { setTimeout(function () { location.reload(); }, 800); }
    });
  });

  document.getElementById('pass-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var f = new FormData(ev.target);
    WP.api('password_change', { current: f.get('current'), new: f.get('new') }).then(function (r) {
      if (r.ok && r.result[0]) { WP.toast(r.result[1], 'ok'); ev.target.reset(); }
      else { WP.toast(r.result && r.result[1] ? r.result[1] : 'Failed', 'err'); }
    });
  });

  document.getElementById('db-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var f = new FormData(ev.target);
    WP.api('settings_save', {
      db_host: f.get('db_host'), db_port: f.get('db_port'), db_user: f.get('db_user'),
      db_pass: f.get('db_pass'), db_socket: f.get('db_socket')
    }).then(function (r) {
      handle(r, 'Database config saved.');
    });
  });
})();
</script>
<?php layout_bottom(); ?>
