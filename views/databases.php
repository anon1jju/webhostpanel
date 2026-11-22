<?php
// WebPanel - databases page: MySQL databases & users.
if (!defined('PANEL')) { exit('Direct access denied.'); }
require PANEL_ROOT . '/views/layout.php';

layout_top('Databases', 'databases');
?>
<div id="db-banner"></div>

<div class="grid-side">
  <div class="card">
    <div class="card-head"><h3>Create Database</h3></div>
    <form id="db-form" class="form">
      <div class="field">
        <label>Database name</label>
        <input name="name" type="text" placeholder="myapp_db" required pattern="[A-Za-z0-9_]+" />
      </div>
      <button type="submit" class="btn btn-primary">Create</button>
    </form>
  </div>

  <div class="card">
    <div class="card-head"><h3>Create User</h3></div>
    <form id="user-form" class="form">
      <div class="field">
        <label>Username</label>
        <input name="user" type="text" placeholder="app_user" required pattern="[A-Za-z0-9_]+" />
      </div>
      <div class="field">
        <label>Password</label>
        <input name="pass" type="text" placeholder="min. 6 characters" required />
      </div>
      <div class="field">
        <label>Host</label>
        <input name="host" type="text" value="localhost" />
      </div>
      <div class="field">
        <label>Grant access to database</label>
        <input name="grant" type="text" placeholder="myapp_db or *" />
      </div>
      <button type="submit" class="btn btn-primary">Create user</button>
    </form>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-head"><h3>Databases</h3></div>
    <table class="table">
      <thead><tr><th>Name</th><th>Size</th><th class="w80">Actions</th></tr></thead>
      <tbody id="db-list"><tr><td colspan="3" class="loading">Loading&hellip;</td></tr></tbody>
    </table>
  </div>
  <div class="card">
    <div class="card-head"><h3>Users</h3></div>
    <table class="table">
      <thead><tr><th>User</th><th>Host</th><th class="w80">Actions</th></tr></thead>
      <tbody id="user-list"><tr><td colspan="3" class="loading">Loading&hellip;</td></tr></tbody>
    </table>
  </div>
</div>

<script>
(function () {
  var fmt = function (b) {
    if (b === null || b === undefined) return '-';
    var u = ['B','KB','MB','GB'], i = 0, v = Number(b);
    while (v >= 1024 && i < u.length - 1) { v /= 1024; i++; }
    return (i === 0 ? v + ' B' : v.toFixed(1) + ' ' + u[i]);
  };

  var load = function () {
    WP.api('db_list', {}, 'GET').then(function (d) {
      var banner = document.getElementById('db-banner');
      if (!d.ok) {
        banner.innerHTML = '<div class="alert alert-warn">' + WP.esc(d.error) +
          (d.unconfigured ? ' <a href="index.php?page=settings">Open Settings</a>' : '') + '</div>';
        document.getElementById('db-list').innerHTML = '<tr><td colspan="3" class="muted">No connection.</td></tr>';
        document.getElementById('user-list').innerHTML = '<tr><td colspan="3" class="muted">No connection.</td></tr>';
        return;
      }
      banner.innerHTML = '';
      var tb = document.getElementById('db-list');
      tb.innerHTML = '';
      d.databases.forEach(function (db) {
        var tr = document.createElement('tr');
        tr.innerHTML = '<td><b>' + WP.esc(db.name) + '</b></td><td>' + fmt(db.size) + '</td>' +
          '<td><button class="btn btn-xs btn-danger" data-drop="' + WP.esc(db.name) + '">Drop</button></td>';
        tb.appendChild(tr);
      });
      if (!d.databases.length) { tb.innerHTML = '<tr><td colspan="3" class="muted">No databases.</td></tr>'; }
      tb.querySelectorAll('[data-drop]').forEach(function (b) {
        b.addEventListener('click', function () {
          WP.confirm('Drop database <b>' + WP.esc(b.dataset.drop) + '</b>? ALL DATA WILL BE LOST.').then(function () {
            WP.api('db_drop', { name: b.dataset.drop }).then(handle);
          });
        });
      });

      var ut = document.getElementById('user-list');
      ut.innerHTML = '';
      d.users.forEach(function (u) {
        var tr = document.createElement('tr');
        tr.innerHTML = '<td>' + WP.esc(u.User) + '</td><td>' + WP.esc(u.Host) + '</td>' +
          '<td><button class="btn btn-xs btn-danger" data-udrop="' + WP.esc(u.User) + '" data-uhost="' + WP.esc(u.Host) + '">Drop</button></td>';
        ut.appendChild(tr);
      });
      if (!d.users.length) { ut.innerHTML = '<tr><td colspan="3" class="muted">No users listed.</td></tr>'; }
      ut.querySelectorAll('[data-udrop]').forEach(function (b) {
        b.addEventListener('click', function () {
          WP.confirm('Drop user <b>' + WP.esc(b.dataset.udrop) + '@' + WP.esc(b.dataset.uhost) + '</b>?').then(function () {
            WP.api('db_user_drop', { user: b.dataset.udrop, host: b.dataset.uhost }).then(handle);
          });
        });
      });

      if (d.phpmyadmin) {
        banner.innerHTML = '<div class="alert alert-ok">phpMyAdmin is installed. Your nginx config can expose it, e.g. <code>location /phpmyadmin { root /usr/share; index index.php; }</code>.</div>';
      }
    });
  };

  var handle = function (r) {
    if (r.ok && r.result && r.result[0]) { WP.toast(r.result[1], 'ok'); load(); }
    else { WP.toast(r.result && r.result[1] ? r.result[1] : (r.error || 'Failed'), 'err'); }
  };

  document.getElementById('db-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    WP.api('db_create', { name: new FormData(ev.target).get('name') }).then(function (r) {
      handle(r); if (r.ok && r.result[0]) { ev.target.reset(); }
    });
  });
  document.getElementById('user-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var f = new FormData(ev.target);
    WP.api('db_user_create', {
      user: f.get('user'), pass: f.get('pass'), host: f.get('host'), grant: f.get('grant')
    }).then(function (r) {
      handle(r); if (r.ok && r.result[0]) { ev.target.reset(); }
    });
  });

  load();
})();
</script>
<?php layout_bottom(); ?>
