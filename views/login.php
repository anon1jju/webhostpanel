<?php
// WebPanel - sign in page (styled after the existing signin.html template).
if (!defined('PANEL')) { exit('Direct access denied.'); }
$panelName = config_get('panel_name', 'WebPanel');
$err = flash_get('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Sign In &middot; <?php echo e($panelName); ?></title>
<link rel="icon" href="data:image/x-icon;base64," type="image/x-icon" />
<link rel="stylesheet" href="assets/panel.css?v=<?php echo PANEL_VERSION; ?>" />
</head>
<body class="auth-body">
  <div class="auth-wrap">
    <div class="auth-brand">
      <div class="brand-logo">
        <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M42.4 44c0 0-6.3-10-1.2-19.9 5.6-11.1 1-20.1 1-20.1H7c0 0 4.6 8.9-1 20C.9 34 7.3 44 7.3 44h35.1z" fill="currentColor"></path></svg>
      </div>
      <h1><?php echo e($panelName); ?></h1>
    </div>
    <div class="auth-card">
      <h2>Sign in to your account</h2>
      <?php if ($err): ?><div class="alert alert-error"><?php echo e($err); ?></div><?php endif; ?>
      <form method="post" action="index.php?page=login" class="auth-form">
        <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>" />
        <div class="field">
          <label for="username">Username</label>
          <input id="username" name="username" type="text" placeholder="admin" required autofocus />
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input id="password" name="password" type="password" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" required />
        </div>
        <button type="submit" class="btn btn-primary btn-block">Sign in</button>
      </form>
    </div>
    <p class="auth-foot"><?php echo e($panelName); ?> v<?php echo PANEL_VERSION; ?> &middot; <?php echo e(Sys::hostname()); ?></p>
  </div>
</body>
</html>
