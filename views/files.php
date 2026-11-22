<?php
// WebPanel - file manager: file tree sidebar + browse/edit/upload/delete.

if (!defined('PANEL')) { exit('Direct access denied.'); }
require PANEL_ROOT . '/views/layout.php';

$editPath = isset($_GET['edit']) ? (string)$_GET['edit'] : '';

/** Compute ancestor directories (native separators) so the tree can auto-expand to a path. */
function fm_ancestors($path) {
    $path = trim((string)$path);
    if ($path === '') { return array(); }
    $win = (bool)preg_match('/^[A-Za-z]:/', $path);
    $sep = $win ? '\\' : '/';
    $norm = $win ? str_replace('/', '\\', $path) : str_replace('\\', '/', $path);
    $parts = array();
    foreach (explode($sep, $norm) as $s) { if ($s !== '') { $parts[] = $s; } }
    if ($win) { array_shift($parts); }
    $root = $win ? strtoupper(substr($norm, 0, 1)) . ':\\' : '/';
    $out = array($root);
    $cur = $root;
    foreach ($parts as $p) {
        $cur = rtrim($cur, '/\\') . $sep . $p;
        $out[] = $cur;
    }
    return $out;
}

$focusPath = $editPath !== '' ? $editPath : Filesys::default_path();
$ancestors = fm_ancestors($focusPath);

layout_top('File Manager', 'files');
?>
<div class="fm-layout">
  <aside class="fm-tree card">
    <div class="card-head">
      <h3>Files</h3>
      <button id="tree-root" class="btn btn-xs" title="Go to default folder">Home</button>
    </div>
    <div id="tree" class="tree"><div class="loading">Loading&hellip;</div></div>
  </aside>

  <div class="fm-main">
<?php if ($editPath !== ''): ?>
    <div class="card">
      <div class="card-head">
        <h3>Editing: <code class="mono" id="edit-path"><?php echo e($editPath); ?></code></h3>
        <div class="btn-group">
          <button id="edit-back" class="btn">Back</button>
          <button id="edit-save" class="btn btn-primary">Save (Ctrl+S)</button>
        </div>
      </div>
      <div id="edit-err"></div>
      <div class="editor-wrap">
        <textarea id="editor" spellcheck="false"></textarea>
      </div>
    </div>
<?php else: ?>
    <div class="card">
      <div class="file-toolbar">
        <div class="path-box">
          <input id="path-input" type="text" class="mono" spellcheck="false" />
          <button id="path-go" class="btn">Go</button>
        </div>
        <div class="btn-group">
          <button id="btn-upload" class="btn">Upload</button>
          <button id="btn-newfile" class="btn">New file</button>
          <button id="btn-newdir" class="btn">New folder</button>
        </div>
      </div>

      <table class="table files">
        <thead><tr>
          <th>Name</th><th class="w80">Perms</th><th class="w100">Owner</th><th class="w110">Size</th><th class="w150">Modified</th><th class="w220">Actions</th>
        </tr></thead>
        <tbody id="fs-list"><tr><td colspan="6" class="loading">Loading&hellip;</td></tr></tbody>
      </table>
      <p class="muted" style="margin-top:10px">Tip: use the file tree on the left, or type any server path above (full root access).</p>
    </div>
    <input type="file" id="file-input" style="display:none" />
<?php endif; ?>
  </div>
</div>

<?php if ($editPath !== ''): ?>
<link rel="stylesheet" href="assets/codemirror/codemirror.min.css?v=<?php echo PANEL_VERSION; ?>" />
<link rel="stylesheet" href="assets/codemirror/addon/dialog/dialog.min.css?v=<?php echo PANEL_VERSION; ?>" />
<script src="assets/codemirror/codemirror.min.js?v=<?php echo PANEL_VERSION; ?>"></script>
<script src="assets/codemirror/mode/clike/clike.min.js?v=<?php echo PANEL_VERSION; ?>"></script>
<script src="assets/codemirror/mode/xml/xml.min.js?v=<?php echo PANEL_VERSION; ?>"></script>
<script src="assets/codemirror/mode/css/css.min.js?v=<?php echo PANEL_VERSION; ?>"></script>
<script src="assets/codemirror/mode/javascript/javascript.min.js?v=<?php echo PANEL_VERSION; ?>"></script>
<script src="assets/codemirror/mode/htmlmixed/htmlmixed.min.js?v=<?php echo PANEL_VERSION; ?>"></script>
<script src="assets/codemirror/mode/php/php.min.js?v=<?php echo PANEL_VERSION; ?>"></script>
<script src="assets/codemirror/mode/sql/sql.min.js?v=<?php echo PANEL_VERSION; ?>"></script>
<script src="assets/codemirror/mode/shell/shell.min.js?v=<?php echo PANEL_VERSION; ?>"></script>
<script src="assets/codemirror/addon/edit/closebrackets.min.js?v=<?php echo PANEL_VERSION; ?>"></script>
<script src="assets/codemirror/addon/edit/matchbrackets.min.js?v=<?php echo PANEL_VERSION; ?>"></script>
<script src="assets/codemirror/addon/edit/closetag.min.js?v=<?php echo PANEL_VERSION; ?>"></script>
<script src="assets/codemirror/addon/selection/active-line.min.js?v=<?php echo PANEL_VERSION; ?>"></script>
<script src="assets/codemirror/addon/search/searchcursor.min.js?v=<?php echo PANEL_VERSION; ?>"></script>
<script src="assets/codemirror/addon/search/search.min.js?v=<?php echo PANEL_VERSION; ?>"></script>
<script src="assets/codemirror/addon/dialog/dialog.min.js?v=<?php echo PANEL_VERSION; ?>"></script>
<script>
(function () {
  var path = <?php echo json_encode($editPath); ?>;
  var err = document.getElementById('edit-err');

  function modeFor(name) {
    var n = String(name || '').toLowerCase();
    if (/\.php$|\.phtml$/.test(n)) return 'application/x-httpd-php';
    if (/\.html?$/.test(n)) return 'htmlmixed';
    if (/\.css$/.test(n)) return 'css';
    if (/\.js$|\.mjs$/.test(n)) return 'javascript';
    if (/\.json$/.test(n)) return { name: 'javascript', json: true };
    if (/\.sql$/.test(n)) return 'text/x-sql';
    if (/\.(sh|bash)$/.test(n)) return 'shell';
    if (/\.(md|txt|log|conf|ini|cnf|env)$/.test(n)) return 'text/plain';
    if (/\.xml$|\.svg$/.test(n)) return 'xml';
    if (/\.ya?ml$/.test(n)) return 'yaml';
    if (/\.(c|h|cpp|java|go|rs|py)$/.test(n)) return 'clike';
    return 'text/plain';
  }

  var cm = CodeMirror.fromTextArea(document.getElementById('editor'), {
    mode: modeFor(path),
    lineNumbers: true,
    indentUnit: 4,
    tabSize: 4,
    indentWithTabs: false,
    lineWrapping: false,
    matchBrackets: true,
    autoCloseBrackets: true,
    autoCloseTags: true,
    styleActiveLine: true,
    viewportMargin: Infinity
  });

  WP.api('fs_read', { path: path }, 'GET').then(function (d) {
    if (!d.ok) {
      err.innerHTML = '<div class="alert alert-error">' + WP.esc(d.error) + '</div>';
      cm.setOption('readOnly', true);
      return;
    }
    cm.setValue(d.data.content);
    cm.clearHistory();
    cm.refresh();
  });

  var save = function () {
    WP.api('fs_save', { path: path, content: cm.getValue() }).then(function (r) {
      if (r.ok && r.result[0]) { WP.toast(r.result[1], 'ok'); }
      else { WP.toast(r.result && r.result[1] ? r.result[1] : 'Save failed', 'err'); }
    });
  };

  document.getElementById('edit-save').addEventListener('click', save);
  document.getElementById('edit-back').addEventListener('click', function () {
    location.href = 'index.php?page=files';
  });
  cm.setOption('extraKeys', {
    'Ctrl-S': save,
    'Cmd-S': save,
    'Ctrl-F': 'findPersistent',
    'Ctrl-H': 'replace'
  });
})();
</script>
<?php endif; ?>

<script>
(function () {
  var treeEl = document.getElementById('tree');
  var nodes = {};               // path -> {row, kids}
  var ancestors = <?php echo json_encode($ancestors); ?>;
  var activePath = <?php echo json_encode($focusPath); ?>;

  function createNode(entry) {
    var node = document.createElement('div');
    node.className = 'tnode';

    var row = document.createElement('div');
    row.className = 'trow';
    if (entry.full === activePath) { row.classList.add('active'); }

    var caret = document.createElement('span');
    caret.className = 'tcaret' + (entry.is_dir ? '' : ' spacer');
    if (entry.is_dir) { caret.textContent = '\u25B8'; }

    var icon = document.createElement('span');
    icon.className = 'tico';
    icon.textContent = entry.is_dir ? '\uD83D\uDCC1' : '\uD83D\uDCC4';

    var name = document.createElement('span');
    name.className = 'tname';
    name.textContent = entry.name;
    name.title = entry.full;

    row.appendChild(caret);
    row.appendChild(icon);
    row.appendChild(name);

    var kids = document.createElement('div');
    kids.className = 'tkids hidden';

    node.appendChild(row);
    node.appendChild(kids);

    var rec = { row: row, kids: kids };
    nodes[entry.full] = rec;

    row.addEventListener('click', function () {
      if (entry.is_dir) {
        toggle(entry.full, row, kids);
      } else {
        location.href = 'index.php?page=files&edit=' + encodeURIComponent(entry.full);
      }
    });
    return node;
  }

  function toggle(path, row, kids) {
    if (kids.classList.contains('hidden')) {
      row.querySelector('.tcaret').textContent = '\u25BE';
      kids.classList.remove('hidden');
      loadKids(kids, path);
    } else {
      row.querySelector('.tcaret').textContent = '\u25B8';
      kids.classList.add('hidden');
    }
  }

  function loadKids(kids, path) {
    if (kids.dataset.loaded) { return Promise.resolve(); }
    kids.dataset.loaded = '1';
    return WP.api('fs_tree', { path: path }, 'GET').then(function (d) {
      if (!d.ok) {
        kids.innerHTML = '<div class="tempty">Denied</div>';
        return;
      }
      var frag = document.createDocumentFragment();
      d.data.entries.forEach(function (e) {
        frag.appendChild(createNode(e));
      });
      if (!d.data.entries.length) { kids.innerHTML = '<div class="tempty">Empty</div>'; }
      else { kids.appendChild(frag); }
    });
  }

  function expandChain(i) {
    if (i >= ancestors.length) { return; }
    var p = ancestors[i];
    var rec = nodes[p];
    if (!rec) { return; }
    rec.kids.classList.remove('hidden');
    rec.row.querySelector('.tcaret').textContent = '\u25BE';
    loadKids(rec.kids, p).then(function () {
      expandChain(i + 1);
    });
  }

  // Home button: reload the tree from default root
  document.getElementById('tree-root').addEventListener('click', function () {
    location.href = 'index.php?page=files';
  });

  // Load root children, then auto-expand toward the focused path
  loadKids(treeEl, ancestors[0] || '').then(function () {
    expandChain(1);
  });
})();
</script>

<?php if ($editPath === ''): ?>
<script>
(function () {
  var current = '';
  var fsList = document.getElementById('fs-list');
  var pathInput = document.getElementById('path-input');
  var fmt = function (b) {
    if (b === null || b === undefined) return '-';
    var u = ['B','KB','MB','GB'], i = 0, v = Number(b);
    while (v >= 1024 && i < u.length - 1) { v /= 1024; i++; }
    return (i === 0 ? v + ' B' : v.toFixed(1) + ' ' + u[i]);
  };

  var load = function (path) {
    WP.api('fs_list', { path: path }, 'GET').then(function (d) {
      if (!d.ok) {
        WP.toast(d.error, 'err');
        pathInput.value = current;
        return;
      }
      current = d.data.path;
      pathInput.value = current;
      fsList.innerHTML = '';
      var tr;
      if (d.data.parent !== null) {
        tr = document.createElement('tr');
        tr.innerHTML = '<td><a href="#" class="link" data-nav="' + WP.esc(d.data.parent) + '">&larr; ..</a></td><td></td><td></td><td></td><td></td><td></td>';
        fsList.appendChild(tr);
      }
      d.data.entries.forEach(function (f) {
        tr = document.createElement('tr');
        var icon = f.is_dir ? '&#128193;' : '&#128196;';
        var actions = '';
        if (f.is_dir) {
          actions = '<a href="#" class="link" data-nav="' + WP.esc(f.full) + '">Open</a>';
        } else {
          actions = '<a href="api.php?a=fs_download&csrf=' + encodeURIComponent(window.PANEL.csrf) + '&path=' + encodeURIComponent(f.full) + '" class="link">Download</a>';
          if (f.editable) {
            actions += ' <a href="index.php?page=files&edit=' + encodeURIComponent(f.full) + '" class="link">Edit</a>';
          }
        }
        actions += ' <a href="#" class="link" data-rn="' + WP.esc(f.full) + '">Rename</a>';
        actions += ' <a href="#" class="link" data-ch="' + WP.esc(f.full) + '" data-p="' + WP.esc(f.perm) + '">Chmod</a>';
        actions += ' <a href="#" class="link danger" data-del="' + WP.esc(f.full) + '">Delete</a>';
        var mtime = new Date(f.mtime * 1000);
        tr.innerHTML =
          '<td>' + icon + ' ' + (f.is_dir ? '<a href="#" class="link strong" data-nav="' + WP.esc(f.full) + '">' + WP.esc(f.name) + '/</a>' : '<span>' + WP.esc(f.name) + '</span>') + '</td>' +
          '<td class="mono">' + WP.esc(f.perm) + '</td>' +
          '<td>' + WP.esc(f.owner) + '</td>' +
          '<td>' + fmt(f.size) + '</td>' +
          '<td class="muted">' + mtime.toLocaleString() + '</td>' +
          '<td>' + actions + '</td>';
        fsList.appendChild(tr);
      });
      if (!d.data.entries.length && d.data.parent === null) {
        fsList.innerHTML = '<tr><td colspan="6" class="muted">Empty directory.</td></tr>';
      }
    });
  };

  fsList.addEventListener('click', function (ev) {
    var t = ev.target.closest('a');
    if (!t) return;
    ev.preventDefault();
    if (t.dataset.nav) { load(t.dataset.nav); return; }
    if (t.dataset.rn) {
      var p = t.dataset.rn;
      WP.prompt('New name:', function (val) {
        if (!val || val === p) return;
        var parts = p.split(/[\\/]/);
        parts[parts.length - 1] = val;
        var np = parts.join('/');
        if (p.indexOf('\\') !== -1) np = parts.join('\\');
        WP.api('fs_rename', { path: p, new_path: np }).then(function (r) {
          if (r.ok && r.result[0]) { WP.toast(r.result[1], 'ok'); load(current); }
          else { WP.toast(r.result && r.result[1] ? r.result[1] : 'Failed', 'err'); }
        });
      }, p);
      return;
    }
    if (t.dataset.ch) {
      var chp = t.dataset.ch;
      WP.prompt('Permissions (octal, e.g. 0755):', function (val) {
        if (!val) return;
        WP.api('fs_chmod', { path: chp, mode: val }).then(function (r) {
          if (r.ok && r.result[0]) { WP.toast(r.result[1], 'ok'); load(current); }
          else { WP.toast(r.result && r.result[1] ? r.result[1] : 'Failed', 'err'); }
        });
      }, t.dataset.p || '0755');
      return;
    }
    if (t.dataset.del) {
      var dp = t.dataset.del;
      WP.confirm('Delete <b>' + WP.esc(dp) + '</b>?<br><span class="muted">This cannot be undone.</span>').then(function () {
        WP.api('fs_delete', { path: dp }).then(function (r) {
          if (r.ok && r.result[0]) { WP.toast(r.result[1], 'ok'); load(current); }
          else { WP.toast(r.result && r.result[1] ? r.result[1] : 'Failed', 'err'); }
        });
      });
    }
  });

  document.getElementById('path-go').addEventListener('click', function () {
    load(pathInput.value.trim() || current);
  });
  pathInput.addEventListener('keydown', function (ev) {
    if (ev.key === 'Enter') { load(pathInput.value.trim() || current); }
  });

  var action = function () {
    return function (r) {
      if (r.ok && r.result[0]) { WP.toast(r.result[1], 'ok'); load(current); }
      else { WP.toast(r.result && r.result[1] ? r.result[1] : 'Failed', 'err'); }
    };
  };

  document.getElementById('btn-newfile').addEventListener('click', function () {
    WP.prompt('File name:', function (val) {
      if (!val) return;
      WP.api('fs_newfile', { dir: current, name: val }).then(action());
    });
  });
  document.getElementById('btn-newdir').addEventListener('click', function () {
    WP.prompt('Folder name:', function (val) {
      if (!val) return;
      WP.api('fs_newdir', { dir: current, name: val }).then(action());
    });
  });

  var fileInput = document.getElementById('file-input');
  document.getElementById('btn-upload').addEventListener('click', function () { fileInput.click(); });
  fileInput.addEventListener('change', function () {
    if (!fileInput.files.length) return;
    var fd = new FormData();
    fd.append('file', fileInput.files[0]);
    fd.append('dir', current);
    fd.append('csrf', window.PANEL.csrf);
    fetch(window.PANEL.api + '?a=fs_upload', { method: 'POST', body: fd }).then(function (r) { return r.json(); }).then(function (r) {
      if (r.ok && r.result[0]) { WP.toast(r.result[1], 'ok'); load(current); }
      else { WP.toast(r.result && r.result[1] ? r.result[1] : 'Upload failed', 'err'); }
    });
    fileInput.value = '';
  });

  load('');
})();
</script>
<?php endif; ?>

<?php layout_bottom(); ?>
