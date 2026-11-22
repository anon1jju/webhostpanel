<?php
// WebPanel - lightweight hosting control panel. Front controller.
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/sysinfo.php';
require __DIR__ . '/inc/tasks.php';

// First run: installer
if (!HAS_CONFIG) {
    require PANEL_ROOT . '/views/install.php';
    exit;
}

$page = isset($_GET['page']) ? preg_replace('/[^a-z0-9_\-]/', '', $_GET['page']) : '';

if ($page === 'logout') {
    panel_logout();
    redirect('index.php');
}

// Login handling
if (is_post() && $page === 'login') {
    $token = isset($_POST['csrf']) ? $_POST['csrf'] : '';
    if (!$token || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
        flash_set('error', 'Session expired. Please try again.');
    } else {
        list($ok, $msg) = attempt_login((string)($_POST['username'] ?? ''), (string)($_POST['password'] ?? ''));
        if ($ok) {
            redirect('index.php');
        }
        flash_set('error', $msg);
    }
    redirect('index.php?page=login');
}

if (!is_logged_in()) {
    require PANEL_ROOT . '/views/login.php';
    exit;
}

$views = array(
    ''           => 'dashboard',
    'dashboard'  => 'dashboard',
    'websites'   => 'websites',
    'databases'  => 'databases',
    'files'      => 'files',
    'extensions' => 'extensions',
    'apps'       => 'apps',
    'settings'   => 'settings',
);

$view = isset($views[$page]) ? $views[$page] : 'dashboard';
require PANEL_ROOT . '/views/' . $view . '.php';
