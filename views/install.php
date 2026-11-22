<?php
// WebPanel - first-run installer: create the admin account + config.
if (!defined('PANEL')) { exit('Direct access denied.'); }

$err = '';
if (is_post()) {
    $user = trim((string)($_POST['username'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');
    $pass2 = (string)($_POST['password2'] ?? '');
    if (!preg_match('/^[a-zA-Z0-9_\-]{3,32}$/', $user)) {
        $err = 'Username must be 3-32 characters (letters, numbers, _ or -).';
    } elseif (strlen($pass) < 8) {
        $err = 'Password must be at least 8 characters.';
    } elseif ($pass !== $pass2) {
        $err = 'Passwords do not match.';
    } else {
        $config = array(
            'panel_name' => 'WebPanel',
            'admin_user' => $user,
            'admin_pass' => password_hash($pass, PASSWORD_DEFAULT),
            'timezone'   => 'Asia/Jakarta',
            'www_root'   => is_linux() ? '/var/www' : str_replace('/', DIRECTORY_SEPARATOR, PANEL_ROOT . '/data/sites/www'),
            'db_host'    => '', 'db_port' => '3306', 'db_user' => 'root', 'db_pass' => '',
            'db_socket'  => '', 'le_email'  => '',
        );
        if (!panel_save_config($config)) {
            $err = 'Could not write data/config.php. Check that the data/ folder is writable.';
        } else {
            $_SESSION['panel_admin'] = true;
            $_SESSION['panel_user'] = $user;
            redirect('index.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Setup &middot; WebPanel</title>
<link rel="icon" href="data:image/x-icon;base64," type="image/x-icon" />
<link rel="stylesheet" href="assets/panel.css?v=<?php echo PANEL_VERSION; ?>" />
</head>
<body class="auth-body">
  <div class="auth-wrap">
    <div class="auth-brand">
      <div class="brand-logo">
        <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M42.4 44c0 0-6.3-10-1.2-19.9 5.6-11.1 1-20.1 1-20.1H7c0 0 4.6 8.9-1 20C.9 34 7.3 44 7.3 44h35.1z" fill="currentColor"></path></svg>
      </div>
      <h1>WebPanel Setup</h1>
    </div>
    <div class="auth-card">
      <h2>Create your admin account</h2>
      <p class="muted" style="margin-top:-8px">This panel manages your whole server &mdash; use a strong password.</p>
      <?php if ($err): ?><div class="alert alert-error"><?php echo e($err); ?></div><?php endif; ?>
      <form method="post" class="auth-form">
        <div class="field">
          <label for="username">Username</label>
          <input id="username" name="username" type="text" value="<?php echo e($user ?? 'admin'); ?>" required autofocus />
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input id="password" name="password" type="password" placeholder="min. 8 characters" required />
        </div>
        <div class="field">
          <label for="password2">Repeat password</label>
          <input id="password2" name="password2" type="password" required />
        </div>
        <button type="submit" class="btn btn-primary btn-block">Create account</button>
      </form>
    </div>
    <p class="auth-foot">The installer is shown only until data/config.php exists.</p>
  </div>
</body>
</html>
