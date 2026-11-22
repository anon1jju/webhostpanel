<?php
// WebPanel - dashboard: live system stats, services, disks, processes.
if (!defined('PANEL')) { exit('Direct access denied.'); }
require PANEL_ROOT . '/views/layout.php';

layout_top('Dashboard', 'dashboard');
?>
<div class="stats-grid" id="stats-grid">
  <div class="card stat-card">
    <div class="stat-head">CPU Usage</div>
    <div class="stat-val" id="st-cpu">--</div>
    <div class="bar"><div class="bar-fill bar-blue" id="bar-cpu" style="width:0%"></div></div>
    <div class="stat-sub" id="st-load">Load: --</div>
  </div>
  <div class="card stat-card">
    <div class="stat-head">Memory</div>
    <div class="stat-val" id="st-mem">--</div>
    <div class="bar"><div class="bar-fill bar-green" id="bar-mem" style="width:0%"></div></div>
    <div class="stat-sub" id="st-mem-sub">--</div>
  </div>
  <div class="card stat-card">
    <div class="stat-head">Disk (system)</div>
    <div class="stat-val" id="st-disk">--</div>
    <div class="bar"><div class="bar-fill bar-amber" id="bar-disk" style="width:0%"></div></div>
    <div class="stat-sub" id="st-disk-sub">--</div>
  </div>
  <div class="card stat-card">
    <div class="stat-head">Network</div>
    <div class="stat-val" id="st-net">--</div>
    <div class="stat-sub" id="st-net-sub">RX -- &middot; TX --</div>
    <div class="stat-sub">Uptime: <span id="st-uptime">--</span></div>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-head">
      <h3>Services</h3>
      <span class="muted">auto-refresh</span>
    </div>
    <div id="services-list" class="svc-list">
      <div class="loading">Loading&hellip;</div>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h3>Server Information</h3></div>
    <table class="table kv" id="server-info">
      <tr><td>Hostname</td><td id="si-host">--</td></tr>
      <tr><td>OS</td><td id="si-os">--</td></tr>
      <tr><td>Kernel / Arch</td><td id="si-kernel">--</td></tr>
      <tr><td>CPU</td><td id="si-cpu">--</td></tr>
      <tr><td>PHP (panel)</td><td id="si-php">--</td></tr>
      <tr><td>Web root</td><td id="si-www">--</td></tr>
      <tr><td>Websites mode</td><td id="si-mode">--</td></tr>
      <tr><td>Panel version</td><td id="si-ver">--</td></tr>
    </table>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-head"><h3>Disk Usage</h3></div>
    <table class="table">
      <thead><tr><th>Mount</th><th>Filesystem</th><th>Used / Total</th><th class="w120">Usage</th></tr></thead>
      <tbody id="disks-list"><tr><td colspan="4" class="loading">Loading&hellip;</td></tr></tbody>
    </table>
  </div>

  <div class="card">
    <div class="card-head"><h3>Top Processes</h3></div>
    <table class="table">
      <thead><tr><th>PID</th><th>User</th><th>Name</th><th>CPU</th><th>MEM</th></tr></thead>
      <tbody id="procs-list"><tr><td colspan="5" class="loading">Loading&hellip;</td></tr></tbody>
    </table>
  </div>
</div>

<script>
(function () {
  var fmt = function (b) {
    if (b === null || b === undefined) return '-';
    var u = ['B','KB','MB','GB','TB'], i = 0, v = Number(b);
    while (v >= 1024 && i < u.length - 1) { v /= 1024; i++; }
    return (i === 0 ? v : v.toFixed(1)) + ' ' + u[i];
  };
  var fmtUp = function (s) {
    var d = Math.floor(s / 86400), h = Math.floor((s % 86400) / 3600), m = Math.floor((s % 3600) / 60);
    var p = [];
    if (d) p.push(d + 'd');
    if (h) p.push(h + 'h');
    if (m) p.push(m + 'm');
    return p.length ? p.join(' ') : Math.floor(s % 60) + 's';
  };
  var render = function (d) {
    var cpu = d.cpu || 0;
    document.getElementById('st-cpu').textContent = cpu.toFixed(1) + '%';
    document.getElementById('bar-cpu').style.width = Math.min(100, cpu) + '%';
    document.getElementById('st-load').textContent = 'Load: ' + d.load.map(function (x) { return x.toFixed(2); }).join(' / ');

    var m = d.mem;
    document.getElementById('st-mem').textContent = (m.pct || 0).toFixed(1) + '%';
    document.getElementById('bar-mem').style.width = Math.min(100, m.pct || 0) + '%';
    document.getElementById('st-mem-sub').textContent = fmt(m.used) + ' / ' + fmt(m.total) + (m.swap_total ? ('  (swap ' + (m.swap_pct||0).toFixed(0) + '%)') : '');

    var dp = d.disk_primary;
    if (dp) {
      document.getElementById('st-disk').textContent = dp.pct + '%';
      document.getElementById('bar-disk').style.width = dp.pct + '%';
      document.getElementById('st-disk-sub').textContent = fmt(dp.used) + ' / ' + fmt(dp.total) + ' (' + dp.mount + ')';
    }
    document.getElementById('st-net').textContent = fmt(d.net.rx) + '/s';
    document.getElementById('st-net-sub').textContent = 'RX ' + fmt(d.net.rx) + '/s \u00b7 TX ' + fmt(d.net.tx) + '/s';
    document.getElementById('st-uptime').textContent = fmtUp(d.server.uptime);

    document.getElementById('si-host').textContent = d.server.hostname;
    document.getElementById('si-os').textContent = d.server.os;
    document.getElementById('si-kernel').textContent = d.server.kernel + ' / ' + d.server.arch;
    document.getElementById('si-cpu').textContent = d.server.cpu_model + ' (' + d.server.cpu_cores + ' cores)';
    document.getElementById('si-php').textContent = 'PHP ' + d.server.php;
    document.getElementById('si-www').textContent = d.server.www_root;
    document.getElementById('si-mode').textContent = d.server.websites_mode;
    document.getElementById('si-ver').textContent = 'v' + d.server.panel_version;

    var svc = document.getElementById('services-list');
    svc.innerHTML = '';
    d.services.forEach(function (s) {
      var row = document.createElement('div');
      row.className = 'svc-row';
      var cls = s.status === 'active' ? 'ok' : (s.status === 'missing' ? 'missing' : (s.status === 'inactive' ? 'off' : 'unknown'));
      row.innerHTML =
        '<span class="svc-name">' + s.name + '</span>' +
        '<span class="pill ' + cls + '">' + s.status + '</span>' +
        (s.status !== 'missing'
          ? '<span class="svc-actions">' +
            '<button class="btn btn-xs" data-svc="' + s.name + '" data-act="start"' + (s.status === 'active' ? ' disabled' : '') + '>Start</button>' +
            '<button class="btn btn-xs" data-svc="' + s.name + '" data-act="stop"' + (s.status !== 'active' ? ' disabled' : '') + '>Stop</button>' +
            '<button class="btn btn-xs" data-svc="' + s.name + '" data-act="restart"' + (s.status !== 'active' ? ' disabled' : '') + '>Restart</button>' +
            '</span>'
          : '<span class="muted">not installed</span>');
      svc.appendChild(row);
    });
    svc.querySelectorAll('button[data-svc]').forEach(function (b) {
      b.addEventListener('click', function () {
        WP.api('service', { name: b.dataset.svc, action: b.dataset.act }).then(function (r) {
          if (r.ok && r.result && r.result[0]) { WP.toast(r.result[1], 'ok'); }
          else { WP.toast(r.result && r.result[1] ? r.result[1] : 'Action failed', 'err'); }
          poll();
        });
      });
    });

    var diskTb = document.getElementById('disks-list');
    diskTb.innerHTML = '';
    d.disks.forEach(function (dd) {
      var tr = document.createElement('tr');
      tr.innerHTML = '<td>' + WP.esc(dd.mount) + '</td><td>' + WP.esc(dd.fs) + '</td>' +
        '<td>' + fmt(dd.used) + ' / ' + fmt(dd.total) + '</td>' +
        '<td><div class="bar"><div class="bar-fill ' + (dd.pct > 85 ? 'bar-red' : dd.pct > 70 ? 'bar-amber' : 'bar-blue') + '" style="width:' + dd.pct + '%"></div></div><span class="muted">' + dd.pct + '%</span></td>';
      diskTb.appendChild(tr);
    });

    var procTb = document.getElementById('procs-list');
    procTb.innerHTML = '';
    d.processes.forEach(function (p) {
      var tr = document.createElement('tr');
      tr.innerHTML = '<td>' + p.pid + '</td><td>' + WP.esc(p.user) + '</td><td>' + WP.esc(p.name) + '</td><td>' + p.cpu + '%</td><td>' + p.mem + '%</td>';
      procTb.appendChild(tr);
    });
    if (!d.processes.length) { procTb.innerHTML = '<tr><td colspan="5" class="muted">No data</td></tr>'; }
  };

  var poll = function () {
    WP.api('stats', {}, 'GET').then(function (d) {
      if (d.ok) { render(d); }
    }).catch(function () {});
  };
  poll();
  setInterval(poll, 3000);
})();
</script>
<?php layout_bottom(); ?>
