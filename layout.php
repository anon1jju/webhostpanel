<?php
// WebPanel - shared layout (sidebar + topbar) styled with Tailwind (same design
// language as the provided dashboard.html template).

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
<link crossorigin="" href="https://fonts.gstatic.com/" rel="preconnect" />
<link as="style" href="https://fonts.googleapis.com/css2?display=swap&amp;family=Inter%3Awght%40400%3B500%3B700%3B900&amp;family=Noto+Sans%3Awght%40400%3B500%3B700%3B900" onload="this.rel='stylesheet'" rel="stylesheet" />
<script src="https://cdn.tailwindcss.com?plugins=forms"></script>
<script>
tailwind.config = {
  theme: {
    extend: {
      colors: {
        primary: '#0d7ff2',
        'primary-dark': '#0269d3',
        'primary-light': '#eef7ff',
        secondary: '#f0f8ff',
        accent: '#e2e8f0'
      }
    }
  }
};
</script>
<link rel="stylesheet" href="assets/panel.css?v=<?php echo PANEL_VERSION; ?>" />
<script>window.PANEL = { csrf: <?php echo json_encode(csrf_token()); ?>, api: 'api.php' };</script>
<script src="assets/panel.js?v=<?php echo PANEL_VERSION; ?>"></script>
</head>
<body class="bg-slate-50" style="font-family: 'Inter', 'Noto Sans', sans-serif;">
<div class="relative min-h-screen lg:flex">
  <aside id="sidebar" class="sidebar fixed inset-y-0 left-0 z-40 w-64 flex-shrink-0 bg-white p-6 border-r border-accent flex flex-col justify-between transform -translate-x-full lg:relative lg:translate-x-0">
    <div>
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <div class="w-8 h-8 bg-primary rounded-full"></div>
          <h1 class="text-xl font-bold text-slate-900"><?php echo e($panelName); ?></h1>
        </div>
        <button id="close-menu" class="lg:hidden text-slate-500 hover:text-slate-900">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </button>
      </div>
      <nav class="flex flex-col gap-2 mt-8">
        <?php foreach ($nav as $key => $item): ?>
        <a class="flex items-center gap-3 px-3 py-2 rounded-md<?php echo $active === $key ? ' bg-secondary text-primary font-medium' : ' text-slate-500 hover:bg-secondary hover:text-primary'; ?> transition-colors" href="index.php<?php echo $key === 'dashboard' ? '' : '?page=' . $key; ?>">
          <svg class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="<?php echo e($item[1]); ?>"/></svg>
          <span class="text-sm"><?php echo e($item[0]); ?></span>
        </a>
        <?php endforeach; ?>
      </nav>
    </div>
    <div class="flex flex-col gap-2">
      <div class="flex items-center gap-2 px-3 py-2">
        <span class="dot <?php echo is_root() ? 'ok' : 'warn'; ?>"></span>
        <span class="text-xs text-slate-500"><?php echo is_linux() ? (is_root() ? 'Running as root' : 'Non-root (sudo)') : 'Windows test mode'; ?></span>
      </div>
      <a class="flex items-center gap-3 px-3 py-2 rounded-md text-slate-500 hover:bg-secondary hover:text-primary transition-colors" href="index.php?page=logout">
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/></svg>
        <span class="text-sm">Sign out</span>
      </a>
    </div>
  </aside>
  <div id="overlay" class="fixed inset-0 bg-black/60 z-30 hidden lg:hidden"></div>

  <main class="flex-1 min-w-0 p-6 sm:p-8">
    <header class="flex items-center justify-between mb-8 gap-4">
      <div class="flex items-center gap-4">
        <button id="menu-toggle" class="p-2 rounded-md text-slate-500 hover:bg-secondary lg:hidden">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
          </svg>
        </button>
        <div>
          <h1 class="text-3xl font-black text-slate-900"><?php echo e($title); ?></h1>
          <p class="text-sm text-slate-500"><?php echo e(Sys::hostname()); ?> &middot; <?php echo e(Sys::os()); ?></p>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-full bg-primary-light text-primary flex items-center justify-center font-bold text-sm"><?php echo e(strtoupper(substr($user, 0, 1))); ?></div>
        <span class="hidden sm:block text-sm font-semibold text-slate-700"><?php echo e($user); ?></span>
      </div>
    </header>

    <?php if ($msg = flash_get('success')): ?>
    <div class="alert alert-success mb-4"><?php echo e($msg); ?></div>
    <?php endif; ?>
    <?php if ($msg = flash_get('error')): ?>
    <div class="alert alert-error mb-4"><?php echo e($msg); ?></div>
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
