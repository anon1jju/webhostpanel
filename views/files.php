<?php
// WebPanel - file manager: browse, edit, upload, create, rename, delete, chmod.
if (!defined('PANEL')) { exit('Direct access denied.'); }
require PANEL_ROOT . '/views/layout.php';

$editPath = isset($_GET['edit']) ? (string)$_GET['edit'] : '';
if ($editPath !== '') {
    layout_top('File Manager', 'files');
    ?>
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
      // Ctrl+S / Cmd+S to save
      cm.setOption('extraKeys', {
        'Ctrl-S': save,
        'Cmd-S': save,
        'Ctrl-F': 'findPersistent',
        'Ctrl-H': 'replace'
      });
    })();
    </script>
    <?php
    layout_bottom();
    exit;
}

layout_top('File Manager', 'files');
?>
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
  <p class="muted" style="margin-top:10px">Tip: you can navigate anywhere on the server by typing a path in the box above (full root access).</p>
</div>

<input type="file" id="file-input" style="display:none" />

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

  var action = function (fn) {
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
<?php layout_bottom(); ?>
