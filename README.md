# WebPanel

Lightweight, fast web hosting control panel (aaPanel-style) built in **plain PHP + SQLite**.
No framework, no CDN assets, no Composer dependencies — every page loads in a few KB.

## Features

| Page | What it does |
|---|---|
| **Dashboard** | Live CPU / RAM / disk / network, load average, service status (start/stop/restart), top processes, server info. Auto-refreshes every 3 s via AJAX. |
| **Websites** | Create / disable / delete nginx vhosts with PHP-FPM version selection, self-signed SSL, Let's Encrypt (via certbot), nginx reload. |
| **Databases** | MySQL/MariaDB: create/drop databases & users, grant privileges, list sizes. |
| **File Manager** | Browse the whole server, edit text files, upload, create, rename, chmod, delete, download. |
| **PHP Extensions** | View loaded extensions, one-click install via apt (background task with live log). |
| **App Store** | One-click installs: nginx, Apache, MariaDB, MySQL, PHP (distro or 8.2/8.3 via ondrej PPA), phpMyAdmin, Redis, Memcached, Node.js, Composer, Git, Certbot, Fail2ban. |
| **Settings** | Panel name, timezone, MySQL root credentials, Let's Encrypt email, password change, database backup. |

## Requirements

- Linux VPS: Debian/Ubuntu (other distros work, but one-click installs need apt)
- PHP >= 7.4 CLI with `pdo_sqlite`, `mbstring`, `curl` (default on most systems)
- Run as **root** (or a user with passwordless sudo) so the panel can manage nginx/MySQL/services

## Install

**One-liner (aaPanel style) — after pushing to GitHub:**

1. Edit the `GITHUB_REPO="YOUR_GITHUB_USER/webhostpanel"` line at the top of `install.sh`.
2. Then on your VPS:

```bash
curl -sSL https://raw.githubusercontent.com/YOUR_GITHUB_USER/webhostpanel/main/install.sh | sudo bash
```

The installer downloads the panel to `/opt/webhostpanel`, installs PHP + extensions,
creates a systemd service, and starts it. Then open `http://SERVER-IP:8888`.

**Manual (files already on the server):**

```bash
cd /path/to/webhostpanel
sudo sh install.sh
```

On first visit the panel shows a **Setup page** where you create your own admin account
(no default password is ever written).

## Run

**Quickest (aaPanel-style, own port):**

```bash
sudo php -S 0.0.0.0:8888 -t /path/to/webhostpanel
```

Then open `http://YOUR-SERVER-IP:8888`.

**Behind nginx** (recommended, gives HTTPS): add this to a server block and run PHP-FPM:

```nginx
server {
    listen 80;
    server_name panel.yourdomain.com;
    root /path/to/webhostpanel;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    # never expose panel internals
    location ~ ^/(data|inc)/ { deny all; }
}
```

## Security notes

- `data/config.php` stores the admin password hash + MySQL root credentials with 0600 permissions. Keep it that way.
- The panel is **root-level**: anyone with the admin password controls the whole server. Put it behind a firewall / fail2ban / HTTPS and a strong password.
- Login is rate-limited (5 failed attempts = 10 minute lock per IP) and CSRF-protected.

## Files

```
index.php          front controller / router
api.php            JSON API (all AJAX endpoints)
install.sh         one-command Debian/Ubuntu setup
assets/panel.css   all styling, no CDN
assets/panel.js    small vanilla-JS helper (API, toasts, modals)
inc/               backend: bootstrap, auth, sysinfo, tasks, services,
                   websites, databases, filesys, pkgs
views/             pages: install, login, dashboard, websites, databases,
                   files, extensions, apps, settings
data/              runtime: panel.db (SQLite), config.php, task logs
*.html             the original design templates this panel was styled after
```
