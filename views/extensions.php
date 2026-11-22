<?php
// WebPanel - PHP extensions page: view loaded extensions, one-click install via apt.
if (!defined('PANEL')) { exit('Direct access denied.'); }
require PANEL_ROOT . '/views/layout.php';

layout_top('PHP Extensions', 'extensions');
?>
<div id="ext-banner"></div>

<div class="card">
  <div class="card-head">
    <h3>Installed extensions (CLI)</h3>
    <span class="muted" id="ext-count"></span>
  </div>
  <div id="loaded-list" class="chips"><div class="loading">Loading&hellip;</div></div>
</div>

<div class="card">
  <div class="card-head">
    <h3>Install / manage extensions</h3>
    <div class="btn-group">
      <select id="php-ver"></select>
      <button id="php-restart" class="btn">Restart php-fpm</button>
    </div>
  </div>
  <table class="table">
    <thead><tr><th>Extension</th><th>Description</th><th>Package</th><th>Status</th><th class="w140">Actions</th></tr></thead>
    <tbody id="ext-list"><tr><td colspan="5" class="loading">Loading&hellip;</td></tr></tbody>
  </table>
</div>

<div id="task-box" class="card hidden">
  <div class="card-head"><h3>Task output <span id="task-name"></span></h3><button id="task-close" class="btn btn-xs">Close</button></div>
  <pre id="task-log" class="log"></pre>
</div>

<script>
(function () {
  var phpVer = 'default';

  var load = function () {
    WP.api('ext_list', { php: phpVer }, 'GET').then(function (d) {
      if (!d.ok) { WP.toast(d.error, 'err'); return; }
      var banner = document.getElementById('ext-banner');
      if (d.linux && !d.apt) {
        banner.innerHTML = '<div class="alert alert-warn">apt not found &mdash; installs are unavailable. This page needs a Debian/Ubuntu server.</div>';
      } else if (!d.linux) {
        banner.innerHTML = '<div class="alert alert-warn">Windows mode &mdash; extension installs via apt are available on Linux only. Listing still works.</div>';
      } else {
        banner.innerHTML = '';
      }

      var sel = document.getElementById('php-ver');
      var cur = sel.value || 'default';
      sel.innerHTML = '';
      d.php_versions.forEach(function (v) {
        var o = document.createElement('option');
        o.value = v; o.textContent = 'PHP ' + v;
        sel.appendChild(o);
      });
      sel.value = d.php_versions.indexOf(cur) !== -1 ? cur : d.php_versions[0];
      phpVer = sel.value;

      var loaded = document.getElementById('loaded-list');
      loaded.innerHTML = '';
      d.loaded.forEach(function (x) {
        var s = document.createElement('span');
        s.className = 'chip';
        s.textContent = x;
        loaded.appendChild(s);
      });
      document.getElementById('ext-count').textContent = d.loaded.length + ' loaded';

      var tb = document.getElementById('ext-list');
      tb.innerHTML = '';
      d.catalog.forEach(function (e2) {
        var tr = document.createElement('tr');
        tr.innerHTML =
          '<td><b>' + WP.esc(e2.id) + '</b></td>' +
          '<td class="muted">' + WP.esc(e2.desc) + '</td>' +
          '<td class="mono">' + WP.esc(e2.pkg) + '</td>' +
          '<td>' + (e2.installed ? '<span class="pill ok">installed</span>' : '<span class="pill off">not installed</span>') + '</td>' +
          '<td>' + (e2.installed
            ? '<span class="muted">&mdash;</span>'
            : '<button class="btn btn-xs" data-inst="' + WP.esc(e2.id) + '">Install</button>') + '</td>';
        tb.appendChild(tr);
      });
      tb.querySelectorAll('[data-inst]').forEach(function (b) {
        b.addEventListener('click', function () {
          var id = b.dataset.inst;
          WP.confirm('Install <b>' + WP.esc(id) + '</b> for PHP ' + phpVer + '?<br><span class="muted">Runs apt-get install in the background.</span>').then(function () {
            WP.api('ext_install', { php: phpVer, ext: id }).then(function (r) {
              if (r.ok && r.result[0]) {
                WP.toast(r.result[1], 'ok');
                if (r.result[2] && r.result[2].task_id) { watchTask(r.result[2].task_id); }
              } else {
                WP.toast(r.result && r.result[1] ? r.result[1] : 'Failed', 'err');
              }
            });
          });
        });
      });
    });
  };

  var watchTask = function (id) {
    var box = document.getElementById('task-box');
    box.classList.remove('hidden');
    var timer = setInterval(function () {
      WP.api('task_poll', { id: id }).then(function (r) {
        if (!r.ok) { clearInterval(timer); return; }
        document.getElementById('task-name').textContent = '#' + id;
        var log = document.getElementById('task-log');
        log.textContent = r.log;
        log.scrollTop = log.scrollHeight;
        if (r.task.status === 'done') { clearInterval(timer); WP.toast('Task #' + id + ' finished.', 'ok'); load(); }
        if (r.task.status === 'failed') { clearInterval(timer); WP.toast('Task #' + id + ' failed.', 'err'); }
      });
    }, 1500);
  };

  document.getElementById('php-ver').addEventListener('change', function () {
    phpVer = this.value;
    load();
  });
  document.getElementById('php-restart').addEventListener('click', function () {
    WP.api('php_restart', { php: phpVer }).then(function (r) {
      if (r.ok && r.result[0]) { WP.toast(r.result[1], 'ok'); }
      else { WP.toast(r.result && r.result[1] ? r.result[1] : 'Failed', 'err'); }
    });
  });
  document.getElementById('task-close').addEventListener('click', function () {
    document.getElementById('task-box').classList.add('hidden');
  });

  load();
})();
</script>
<?php layout_bottom(); ?>
