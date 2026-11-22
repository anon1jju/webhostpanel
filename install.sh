#!/bin/sh
# =============================================================================
# WebPanel - one-line installer (aaPanel style)
#
#   Install from GitHub:
#     curl -sSL https://raw.githubusercontent.com/USER/REPO/main/install.sh | sudo bash
#
#   Or from inside the repo folder:
#     sudo sh install.sh
#
# Before using the GitHub method, edit the GITHUB_REPO variable below.
# =============================================================================
set -e

GITHUB_REPO="anon1jju/webhostpanel"   # your repo: USERNAME/webhostpanel
GITHUB_BRANCH="main"
INSTALL_DIR="/opt/webhostpanel"
PORT="8888"

echo "=============================================="
echo " WebPanel installer"
echo "=============================================="

if [ "$(id -u)" -ne 0 ]; then
    echo "[!] Please run as root:  sudo sh install.sh"
    exit 1
fi

# ---------------------------------------------------------------------------
# 1. Locate or download the panel source
# ---------------------------------------------------------------------------
SRC=""
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd 2>/dev/null || echo '.')"

if [ -f "$SCRIPT_DIR/index.php" ] && [ -f "$SCRIPT_DIR/inc/bootstrap.php" ]; then
    # Running from inside the repo folder -> use files as-is
    SRC="$SCRIPT_DIR"
    echo ">> Using source from: $SRC"
else
    echo ">> Downloading WebPanel from GitHub: $GITHUB_REPO ($GITHUB_BRANCH)..."
    SRC="/tmp/webpanel-src"
    TARBALL="/tmp/webpanel.tar.gz"
    rm -rf "$SRC" "$TARBALL"
    mkdir -p "$SRC"
    if ! curl -fsSL "https://github.com/$GITHUB_REPO/archive/refs/heads/$GITHUB_BRANCH.tar.gz" -o "$TARBALL"; then
        echo "[!] Download failed: repo not found or not public."
        echo "    GITHUB_REPO is set to: $GITHUB_REPO"
        echo "    URL: https://github.com/$GITHUB_REPO/archive/refs/heads/$GITHUB_BRANCH.tar.gz"
        exit 1
    fi
    if ! tar xzf "$TARBALL" -C "$SRC" --strip-components=1 2>/dev/null; then
        echo "[!] Downloaded file is not a valid tarball."
        exit 1
    fi
    rm -f "$TARBALL"
    if [ ! -f "$SRC/index.php" ]; then
        echo "[!] index.php not found in the downloaded archive. Check GITHUB_BRANCH."
        exit 1
    fi
fi

# ---------------------------------------------------------------------------
# 2. Copy to install directory (if different)
# ---------------------------------------------------------------------------
if [ "$SRC" != "$INSTALL_DIR" ]; then
    echo ">> Installing to: $INSTALL_DIR"
    mkdir -p "$INSTALL_DIR"
    if [ -f "$INSTALL_DIR/data/config.php" ]; then
        echo ">> Existing data/config.php found - keeping it."
        cp -a "$SRC/." "$INSTALL_DIR/"
    else
        rm -rf "$INSTALL_DIR"
        cp -a "$SRC" "$INSTALL_DIR"
    fi
fi

# ---------------------------------------------------------------------------
# 3. Install PHP + required extensions (Debian/Ubuntu)
# ---------------------------------------------------------------------------
if command -v apt-get >/dev/null 2>&1; then
    echo ">> Checking PHP..."
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -qq

    # Install PHP CLI first (extension checks below need it)
    if ! command -v php >/dev/null 2>&1; then
        echo ">> Installing PHP CLI..."
        apt-get install -y php-cli
    fi

    PACKAGES=""
    php -m 2>/dev/null | grep -qi '^pdo_sqlite$' || PACKAGES="$PACKAGES php-sqlite3"
    php -m 2>/dev/null | grep -qi '^mbstring$'   || PACKAGES="$PACKAGES php-mbstring"
    php -m 2>/dev/null | grep -qi '^curl$'       || PACKAGES="$PACKAGES php-curl"
    php -m 2>/dev/null | grep -qi '^zip$'        || PACKAGES="$PACKAGES php-zip"
    php -m 2>/dev/null | grep -qi '^xml$'        || PACKAGES="$PACKAGES php-xml"
    # openssl is compiled into PHP on Ubuntu - there is no php-openssl package
    if [ -n "$PACKAGES" ]; then
        echo ">> Installing: $PACKAGES"
        apt-get install -y $PACKAGES
    else
        echo ">> PHP and extensions OK."
    fi
else
    echo "[!] apt-get not found. Make sure PHP >= 7.4 with pdo_sqlite, mbstring, curl is installed."
fi

# ---------------------------------------------------------------------------
# 4. Make runtime dirs writable
# ---------------------------------------------------------------------------
mkdir -p "$INSTALL_DIR/data/tasks" "$INSTALL_DIR/data/cache" "$INSTALL_DIR/data/sites"
chmod 700 "$INSTALL_DIR/data"

# ---------------------------------------------------------------------------
# 5. systemd service (auto-start + restart)
# ---------------------------------------------------------------------------
PHP_BIN="$(command -v php)"
echo ">> Creating systemd service..."
cat > /etc/systemd/system/webpanel.service <<EOF
[Unit]
Description=WebPanel hosting control panel
After=network.target

[Service]
ExecStart=$PHP_BIN -S 0.0.0.0:$PORT -t $INSTALL_DIR
WorkingDirectory=$INSTALL_DIR
Restart=always
User=root

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable --now webpanel >/dev/null 2>&1 || true

IP="$(hostname -I 2>/dev/null | awk '{print $1}')"

echo ""
echo "=============================================="
echo " WebPanel installed!"
echo "=============================================="
echo " Open:  http://${IP:-YOUR-SERVER-IP}:$PORT"
echo ""
echo " First visit shows the Setup page - create your admin account there."
echo ""
echo " Service: systemctl status webpanel"
echo "     Log: journalctl -u webpanel -f"
echo ""
echo " SECURITY (do this now):"
echo "   Restrict the port to your IP only:"
echo "     ufw allow from YOUR_IP to any port $PORT"
echo "     ufw enable"
echo "   Or put it behind nginx + HTTPS (see README.md)."
echo "=============================================="
