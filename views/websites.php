<?php
// WebPanel - websites page: add/remove/toggle nginx vhosts, SSL.
if (!defined('PANEL')) { exit('Direct access denied.'); }
require PANEL_ROOT . '/views/layout.php';

layout_top('Websites', 'websites');
?>
<div id="mode-banner"></div>

<div class="grid-side">
  <div class="card">
    <div class="card-head"><h3>Add Website</h3></div>
    <form id="add-form" class="form">
      <div class="field">
        <label>Domain</label>
        <input name="domain" type="text" placeholder="example.com" required />
      </div>
      <div class="field">
        <label>Web root <span class="muted">(leave empty: auto)</span></label>
        <input name="root" type="text" placeholder="/var/www/example.com" />
      </div>
      <div class="field">
        <label>PHP-FPM version</label>
        <select name="php" id="php-select"><option value="default">default</option></select>
      </div>
      <div class="field">
        <label class="check"><input name="ssl" type="checkbox" value="1" /> Create self-signed SSL certificate</label>
      </div>
      <button type="submit" class="btn btn-primary">Create website</button>
    </form>
  </div>

  <div class="card">
    <div class="card-head">
      <h3>Sites</h3>
      <button id="reload-btn" class="btn">Reload nginx</button>
    </div>
    <table class="table">
      <thead><tr><th>Domain</th><th>Root</th><th>PHP</th><th>SSL</th><th>Status</th><th class="w180">Actions</th></tr></thead>
      <tbody id="sites-list"><tr><td colspan="6" class="loading">Loading&hellip;</td></tr></tbody>
    </table>
  </div>
</div>

<div id="task-box" class="card hidden">
  <div class="card-head">
    <h3>Task output <span id="task-name"></span></h3>
    <button id="task-close" class="btn btn-xs">Close</button>
  </div>
  <pre id="task-log" class="log"></pre>
</div>

<script>
(function () {
  var list = function () {
    WP.api('website_list', {}, 'GET').then(function (d) {
      if (!d.ok) { WP.toast(d.error, 'err'); return; }
      var banner = document.getElementById('mode-banner');
      banner.innerHTML = d.mode === 'nginx'
        ? ''
        : '<div class="alert alert-warn">nginx config dirs not found &mdash; running in <b>virtual mode</b> (configs stored under <code>data/sites/</code>). On your VPS the real nginx vhosts will be managed automatically.</div>';

      var sel = document.getElementById('php-select');
      var cur = sel.value;
      sel.innerHTML = '';
      d.php_versions.forEach(function (v) {
        var o = document.createElement('option');
        o.value = v; o.textContent = v;
        sel.appendChild(o);
      });
      sel.value = (cur && d.php_versions.indexOf(cur) !== -1) ? cur : d.php_versions[0];

      var tb = document.getElementById('sites-list');
      tb.innerHTML = '';
      d.sites.forEach(function (s) {
        var tr = document.createElement('tr');
        tr.innerHTML =
          '<td><b>' + WP.esc(s.domain) + '</b></td>' +
          '<td class="mono">' + WP.esc(s.root) + '</td>' +
          '<td>' + WP.esc(s.php) + '</td>' +
          '<td>' + (s.ssl ? '<span class="pill ok">on</span>' : '<span class="pill off">off</span>') + '</td>' +
          '<td>' + (s.enabled ? '<span class="pill ok">enabled</span>' : '<span class="pill off">disabled</span>') + '</td>' +
          '<td><div class="btn-group">' +
            '<button class="btn btn-xs" data-act="toggle" data-d="' + WP.esc(s.domain) + '">' + (s.enabled ? 'Disable' : 'Enable') + '</button>' +
            '<button class="btn btn-xs" data-act="cert" data-d="' + WP.esc(s.domain) + '">SSL</button>' +
            '<button class="btn btn-xs btn-danger" data-act="del" data-d="' + WP.esc(s.domain) + '">Delete</button>' +
          '</div></td>';
        tb.appendChild(tr);
      });
      if (!d.sites.length) {
        tb.innerHTML = '<tr><td colspan="6" class="muted">No websites yet. Add your first site on the left.</td></tr>';
      }
      tb.querySelectorAll('button[data-act]').forEach(function (b) {
        b.addEventListener('click', function () {
          var domain = b.dataset.d;
          if (b.dataset.act === 'del') {
            WP.confirm('Delete website <b>' + WP.esc(domain) + '</b>? The web root folder will be kept.').then(function () {
              WP.api('website_remove', { domain: domain }).then(function (r) {
                handle(r);
              });
            });
          } else if (b.dataset.act === 'toggle') {
            WP.api('website_toggle', { domain: domain, enable: b.textContent === 'Enable' ? 1 : 0 }).then(handle);
          } else if (b.dataset.act === 'cert') {
            WP.confirm('Request a free Let\u2019s Encrypt certificate for <b>' + WP.esc(domain) + '</b>? (requires certbot)').then(function () {
              WP.api('website_certbot', { domain: domain }).then(function (r) {
                handle(r);
                if (r.ok && r.result && r.result[2] && r.result[2].task_id) { watchTask(r.result[2].task_id); }
              });
            });
          }
        });
      });
    });
  };

  var handle = function (r) {
    if (r.ok && r.result && r.result[0]) { WP.toast(r.result[1], 'ok'); list(); }
    else { WP.toast(r.result && r.result[1] ? r.result[1] : (r.error || 'Failed'), 'err'); }
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
        if (r.task.status === 'done') { clearInterval(timer); WP.toast('Task #' + id + ' finished.', 'ok'); list(); }
        if (r.task.status === 'failed') { clearInterval(timer); WP.toast('Task #' + id + ' failed.', 'err'); }
      });
    }, 1500);
  };

  document.getElementById('add-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var f = new FormData(ev.target);
    WP.api('website_add', {
      domain: f.get('domain'), root: f.get('root'), php: f.get('php'), ssl: f.get('ssl') ? 1 : 0
    }).then(function (r) {
      if (r.ok && r.result && r.result[0]) { WP.toast(r.result[1], 'ok'); ev.target.reset(); list(); }
      else { WP.toast(r.result && r.result[1] ? r.result[1] : 'Failed', 'err'); }
    });
  });

  document.getElementById('reload-btn').addEventListener('click', function () {
    WP.api('nginx_reload', {}).then(handle);
  });
  document.getElementById('task-close').addEventListener('click', function () {
    document.getElementById('task-box').classList.add('hidden');
  });

  list();
})();
</script>
<?php layout_bottom(); ?>
