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
<link crossorigin="" href="https://fonts.gstatic.com/" rel="preconnect" />
<link as="style" href="https://fonts.googleapis.com/css2?display=swap&amp;family=Inter%3Awght%40400%3B500%3B600%3B700&amp;family=Noto+Sans%3Awght%40400%3B500%3B700%3B900" onload="this.rel='stylesheet'" rel="stylesheet" />
<script src="https://cdn.tailwindcss.com?plugins=forms"></script>
<script>
tailwind.config = {
  theme: {
    extend: {
      colors: {
        primary: { 50:'#eef7ff',100:'#d9edff',200:'#bce1ff',300:'#8ed0ff',400:'#5ab8ff',500:'#309eff',600:'#0d7ff2',700:'#0269d3',800:'#0355ad',900:'#06478a' }
      }
    }
  }
};
</script>
<link rel="stylesheet" href="assets/panel.css?v=<?php echo PANEL_VERSION; ?>" />
</head>
<body class="bg-gray-50" style='font-family: Inter, "Noto Sans", sans-serif;'>
  <div class="flex min-h-screen flex-col items-center justify-center px-6 py-8">
    <a class="mb-6 flex items-center gap-3 text-2xl font-semibold text-gray-900" href="#">
      <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary-600 text-white">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
          <path d="M42.4379 44C42.4379 44 36.0744 33.9038 41.1692 24C46.8624 12.9336 42.2078 4 42.2078 4L7.01134 4C7.01134 4 11.6577 12.932 5.96912 23.9969C0.876273 33.9029 7.27094 44 7.27094 44L42.4379 44Z" fill="currentColor"></path>
        </svg>
      </div>
      <span class="font-bold">WebPanel Setup</span>
    </a>
    <div class="w-full rounded-lg bg-white shadow-md sm:max-w-md">
      <div class="space-y-4 p-6 sm:p-8 md:space-y-6">
        <h1 class="text-xl font-bold leading-tight tracking-tight text-gray-900 md:text-2xl">Create your admin account</h1>
        <p class="text-sm text-gray-500 -mt-2">This panel manages your whole server &mdash; use a strong password.</p>
        <?php if ($err): ?><div class="alert alert-error"><?php echo e($err); ?></div><?php endif; ?>
        <form method="post" class="space-y-4 md:space-y-6">
          <div>
            <label class="mb-2 block text-sm font-medium text-gray-900" for="username">Username</label>
            <input class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-gray-900 focus:border-primary-600 focus:ring-primary-600 sm:text-sm" id="username" name="username" type="text" value="<?php echo e($user ?? 'admin'); ?>" required autofocus />
          </div>
          <div>
            <label class="mb-2 block text-sm font-medium text-gray-900" for="password">Password</label>
            <input class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-gray-900 focus:border-primary-600 focus:ring-primary-600 sm:text-sm" id="password" name="password" type="password" placeholder="min. 8 characters" required />
          </div>
          <div>
            <label class="mb-2 block text-sm font-medium text-gray-900" for="password2">Repeat password</label>
            <input class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-gray-900 focus:border-primary-600 focus:ring-primary-600 sm:text-sm" id="password2" name="password2" type="password" required />
          </div>
          <button class="w-full rounded-lg bg-primary-600 px-5 py-2.5 text-center text-sm font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-4 focus:ring-primary-300" type="submit">
            Create account
          </button>
        </form>
      </div>
    </div>
    <p class="mt-6 text-sm text-gray-500">The installer is shown only until data/config.php exists.</p>
  </div>
</body>
</html>
