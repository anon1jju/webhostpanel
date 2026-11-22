/* WebPanel - shared frontend helpers: API calls, toast, confirm/prompt modals,
   sidebar toggle. Vanilla JS, no dependencies. */
(function () {
  'use strict';

  var api = function (action, data, method) {
    var opts = {
      method: method || 'POST',
      headers: { 'X-CSRF': window.PANEL.csrf }
    };
    if (opts.method === 'POST') {
      var body = new URLSearchParams();
      body.append('csrf', window.PANEL.csrf);
      Object.keys(data || {}).forEach(function (k) {
        if (data[k] !== undefined && data[k] !== null) { body.append(k, data[k]); }
      });
      opts.headers['Content-Type'] = 'application/x-www-form-urlencoded';
      opts.body = body.toString();
    }
    var qs = '?a=' + encodeURIComponent(action);
    if (opts.method === 'GET' && data) {
      Object.keys(data).forEach(function (k) { qs += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(data[k]); });
    }
    return fetch(window.PANEL.api + qs, opts).then(function (r) {
      return r.json().catch(function () { return { ok: false, error: 'Bad response (' + r.status + ')' }; });
    });
  };

  var esc = function (s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  };

  var toast = function (msg, type) {
    var root = document.getElementById('toast-root');
    if (!root) { return; }
    var el = document.createElement('div');
    el.className = 'toast' + (type === 'ok' ? ' ok' : (type === 'err' ? ' err' : ''));
    el.textContent = msg;
    root.appendChild(el);
    setTimeout(function () {
      el.style.opacity = '0';
      el.style.transition = 'opacity .3s';
      setTimeout(function () { el.remove(); }, 320);
    }, 3500);
  };

  var modal = function (html, buttons) {
    var root = document.getElementById('modal-root');
    var backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop';
    var box = document.createElement('div');
    box.className = 'modal';
    var body = document.createElement('div');
    body.innerHTML = html;
    var actions = document.createElement('div');
    actions.className = 'modal-actions';
    box.appendChild(body);
    box.appendChild(actions);
    backdrop.appendChild(box);
    root.appendChild(backdrop);
    var close = function () { backdrop.remove(); };
    backdrop.addEventListener('click', function (ev) { if (ev.target === backdrop) { close(); } });
    buttons.forEach(function (b) {
      var btn = document.createElement('button');
      btn.className = 'btn' + (b.primary ? ' btn-primary' : (b.danger ? ' btn-danger' : ''));
      btn.textContent = b.label;
      btn.addEventListener('click', function () {
        if (b.onClick) { b.onClick(); }
        close();
      });
      actions.appendChild(btn);
    });
    return backdrop;
  };

  var confirm = function (msg) {
    return new Promise(function (resolve) {
      var m = modal('<h3>Are you sure?</h3><p>' + msg + '</p>', [
        { label: 'Cancel', onClick: function () { resolve(false); } },
        { label: 'Confirm', primary: true, onClick: function () { resolve(true); } }
      ]);
      m.addEventListener('modal-closed', function () { resolve(false); }, { once: true });
    });
  };

  var promptText = function (label, onOk, initial) {
    var inputId = 'modal-input-' + Math.random().toString(36).slice(2);
    modal('<h3>' + esc(label) + '</h3>' +
      '<p style="margin-bottom:10px"><input id="' + inputId + '" type="text" class="modal-input" style="width:100%;border:1px solid #e2e8f0;border-radius:8px;padding:9px 12px;font-size:14px" value="' + esc(initial || '') + '" /></p>', [
      { label: 'Cancel' },
      { label: 'OK', primary: true, onClick: function () {
          var v = document.getElementById(inputId).value;
          if (v !== null) { onOk(v); }
        } }
    ]);
    setTimeout(function () {
      var i = document.getElementById(inputId);
      if (i) { i.focus(); i.select(); }
    }, 60);
  };

  window.WP = {
    api: api,
    esc: esc,
    toast: toast,
    confirm: confirm,
    prompt: promptText
  };

  // ---------------------------------------------------------------- sidebar
  document.addEventListener('DOMContentLoaded', function () {
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('overlay');
    var menuToggle = document.getElementById('menu-toggle');
    var closeMenu = document.getElementById('close-menu');
    var open = function () {
      if (sidebar) { sidebar.classList.add('open'); }
      if (overlay) { overlay.classList.remove('hidden'); }
    };
    var close = function () {
      if (sidebar) { sidebar.classList.remove('open'); }
      if (overlay) { overlay.classList.add('hidden'); }
    };
    if (menuToggle) { menuToggle.addEventListener('click', open); }
    if (closeMenu) { closeMenu.addEventListener('click', close); }
    if (overlay) { overlay.addEventListener('click', close); }
  });
})();
