<?php
// WebPanel - App Store: one-click component installs.
if (!defined('PANEL')) { exit('Direct access denied.'); }
require PANEL_ROOT . '/views/layout.php';

layout_top('App Store', 'apps');
?>
<div id="app-banner"></div>
<div id="apps-grid" class="apps-grid"><div class="loading">Loading&hellip;</div></div>

<div id="task-box" class="card hidden">
  <div class="card-head">
    <h3>Installation log <span id="task-name"></span></h3>
    <button id="task-close" class="btn btn-xs">Close</button>
  </div>
  <pre id="task-log" class="log"></pre>
</div>

<script>
(function () {
  var colors = {
    green: 'c-green', blue: 'c-blue', brown: 'c-brown', orange: 'c-orange',
    indigo: 'c-indigo', teal: 'c-teal', red: 'c-red', slate: 'c-slate',
    stone: 'c-stone', amber: 'c-amber', rose: 'c-rose'
  };

  var load = function () {
    WP.api('app_list', {}, 'GET').then(function (d) {
      if (!d.ok) { WP.toast(d.error, 'err'); return; }
      var banner = document.getElementById('app-banner');
      if (!d.linux) {
        banner.innerHTML = '<div class="alert alert-warn">App installs require a Linux server. You are viewing the catalog in test mode.</div>';
      } else if (!d.distro.apt) {
        banner.innerHTML = '<div class="alert alert-warn">apt not detected on this system (' + WP.esc(d.distro.pretty) + ') &mdash; installs are unavailable.</div>';
      } else {
        banner.innerHTML = '';
      }

      var grid = document.getElementById('apps-grid');
      grid.innerHTML = '';
      d.apps.forEach(function (a) {
        var card = document.createElement('div');
        card.className = 'app-card';
        var st = a.installed;
        card.innerHTML =
          '<div class="app-ico ' + (colors[a.color] || 'c-blue') + '">' + WP.esc(a.name[0]) + '</div>' +
          '<div class="app-name">' + WP.esc(a.name) + '</div>' +
          '<div class="app-desc">' + WP.esc(a.desc) + '</div>' +
          (st.linux_only
            ? '<span class="pill off">Linux only</span>'
            : (st.installed
              ? '<span class="pill ok">Installed</span>'
              : '<button class="btn btn-xs" data-app="' + WP.esc(a.id) + '">Install</button>'));
        grid.appendChild(card);
      });
      grid.querySelectorAll('[data-app]').forEach(function (b) {
        b.addEventListener('click', function () {
          var id = b.dataset.app;
          var name = b.closest('.app-card').querySelector('.app-name').textContent;
          WP.confirm('Install <b>' + WP.esc(name) + '</b> now?<br><span class="muted">This runs package commands on the server and may take a few minutes.</span>').then(function () {
            WP.api('app_install', { app: id }).then(function (r) {
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
        document.getElementById('task-name').textContent = '#task-' + id;
        var log = document.getElementById('task-log');
        log.textContent = r.log;
        log.scrollTop = log.scrollHeight;
        if (r.task.status === 'done') { clearInterval(timer); WP.toast('Installation finished.', 'ok'); load(); }
        if (r.task.status === 'failed') { clearInterval(timer); WP.toast('Installation failed. Check the log.', 'err'); }
      });
    }, 1500);
  };

  document.getElementById('task-close').addEventListener('click', function () {
    document.getElementById('task-box').classList.add('hidden');
  });

  load();
})();
</script>
<?php layout_bottom(); ?>
