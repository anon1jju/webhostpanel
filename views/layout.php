<?php
// WebPanel - shared layout (sidebar + topbar). Views call layout_top()/layout_bottom().

if (!defined('PANEL')) { exit('Direct access denied.'); }

function layout_top($title, $active) {
    $panelName = config_get('panel_name', 'WebPanel');
    $user = $_SESSION['panel_user'] ?? 'admin';
    $nav = array(
        'dashboard'  => array('Dashboard', 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75'),
        'websites'   => array('Websites', 'M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m-18.432 0A8.959 8.959 0 013 12c0-.778.099-1.533.284-2.253'),
        'databases'  => array('Databases', 'M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 5.625c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125'),
        'files'      => array('File Manager', 'M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z'),
        'extensions' => array('PHP Extensions', 'M6.429 9.75L2.25 12l4.179 2.25m0-4.5l5.571 3 5.571-3m-11.142 0L2.25 7.5 12 2.25l9.75 5.25-4.179 2.25m0 0L21.75 12l-4.179 2.25m0 0l4.179 2.25L12 21.75 2.25 16.5l4.179-2.25m11.142 0l-5.571 3-5.571-3'),
        'apps'       => array('App Store', 'M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z'),
        'settings'   => array('Settings', 'M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75'),
    );
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?php echo e($title); ?> &middot; <?php echo e($panelName); ?></title>
<link rel="icon" href="data:image/x-icon;base64," type="image/x-icon" />
<link rel="stylesheet" href="assets/panel.css?v=<?php echo PANEL_VERSION; ?>" />
<script>window.PANEL = { csrf: <?php echo json_encode(csrf_token()); ?>, api: 'api.php' };</script>
<script src="assets/panel.js?v=<?php echo PANEL_VERSION; ?>"></script>
</head>
<body>
<div class="app">
  <aside id="sidebar" class="sidebar">
    <div class="sidebar-head">
      <div class="brand">
        <div class="brand-dot"></div>
        <span class="brand-name"><?php echo e($panelName); ?></span>
      </div>
      <button id="close-menu" class="icon-btn sidebar-close" title="Close menu">
        <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      </button>
    </div>
    <nav class="nav">
      <?php foreach ($nav as $key => $item): ?>
      <a class="nav-item<?php echo $active === $key ? ' active' : ''; ?>" href="index.php<?php echo $key === 'dashboard' ? '' : '?page=' . $key; ?>">
        <svg class="nav-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="<?php echo e($item[1]); ?>"/></svg>
        <span><?php echo e($item[0]); ?></span>
      </a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-foot">
      <div class="foot-row">
        <span class="dot <?php echo is_root() ? 'ok' : 'warn'; ?>"></span>
        <span class="foot-txt"><?php echo is_linux() ? (is_root() ? 'Running as root' : 'Non-root (sudo)') : 'Windows test mode'; ?></span>
      </div>
      <a class="nav-item" href="index.php?page=logout">
        <svg class="nav-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/></svg>
        <span>Sign out</span>
      </a>
    </div>
  </aside>
  <div id="overlay" class="overlay hidden"></div>

  <main class="main">
    <header class="topbar">
      <div class="topbar-left">
        <button id="menu-toggle" class="icon-btn menu-toggle" title="Menu">
          <svg viewBox="0 0 24 24"><path d="M3 12h18M3 6h18M3 18h18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        </button>
        <div>
          <h1 class="page-title"><?php echo e($title); ?></h1>
          <p class="page-sub"><?php echo e(Sys::hostname()); ?> &middot; <?php echo e(Sys::os()); ?></p>
        </div>
      </div>
      <div class="topbar-right">
        <span class="user-chip"><?php echo e(strtoupper(substr($user, 0, 1))); ?></span>
        <span class="user-name"><?php echo e($user); ?></span>
      </div>
    </header>

    <?php if ($msg = flash_get('success')): ?>
    <div class="alert alert-success"><?php echo e($msg); ?></div>
    <?php endif; ?>
    <?php if ($msg = flash_get('error')): ?>
    <div class="alert alert-error"><?php echo e($msg); ?></div>
    <?php endif; ?>

    <section class="content">
    <?php
}

function layout_bottom() {
    ?>
    </section>
  </main>
</div>

<div id="modal-root"></div>
<div id="toast-root" class="toast-root"></div>
</body>
</html>
<?php
}
