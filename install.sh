#!/usr/bin/env bash
#
# prepare-gc3-phishing-server.sh
# WARNING: This script is for authorized testing/red-team/lab environments ONLY
#          Do NOT run this on production servers or anywhere real users might land
#
# Purpose:
#   - Installs Apache2 + PHP (common modules)
#   - Creates /var/www/logs/
#   - Sets correct ownership & permissions
#   - Removes default Apache welcome page / index.html
#   - Creates a blank index.php as placeholder
#

set -euo pipefail

# ──────────────────────────────────────────────────────────────────────────────
#  Config
# ──────────────────────────────────────────────────────────────────────────────

WEB_USER="www-data"
WEB_GROUP="www-data"

LOG_DIR="/var/www/logs"
WEB_ROOT="/var/www/html"

PHP_VERSION="8.3"   # change to 8.2 / 8.1 / 7.4 if needed

# ──────────────────────────────────────────────────────────────────────────────
#  Colors & output helpers
# ──────────────────────────────────────────────────────────────────────────────

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

info()    { echo -e "${GREEN}[+] $1${NC}"; }
warn()    { echo -e "${YELLOW}[!] $1${NC}" >&2; }
error()   { echo -e "${RED}[-] $1${NC}" >&2; exit 1; }

# ──────────────────────────────────────────────────────────────────────────────
#  Must run as root
# ──────────────────────────────────────────────────────────────────────────────

if [[ $EUID -ne 0 ]]; then
    error "This script must be run as root (sudo)"
fi

# ──────────────────────────────────────────────────────────────────────────────
#  Update package lists
# ──────────────────────────────────────────────────────────────────────────────

info "Updating package lists..."
apt-get update -qq >/dev/null

# ──────────────────────────────────────────────────────────────────────────────
#  Install Apache + PHP + common modules
# ──────────────────────────────────────────────────────────────────────────────

info "Installing Apache2 + PHP ${PHP_VERSION}..."

apt-get install -y --no-install-recommends \
    apache2 \
    libapache2-mod-php${PHP_VERSION} \
    php${PHP_VERSION} \
    php${PHP_VERSION}-cli \
    php${PHP_VERSION}-common \
    php${PHP_VERSION}-curl \
    php${PHP_VERSION}-mbstring \
    php${PHP_VERSION}-xml \
    php${PHP_VERSION}-zip \
    php${PHP_VERSION}-gd \
    php${PHP_VERSION}-mysql \
    php${PHP_VERSION}-opcache \
  || error "Package installation failed"

# Enable PHP module (usually auto-enabled, but make sure)
a2enmod php${PHP_VERSION} rewrite headers >/dev/null 2>&1 || true

# Disable default configs we don't need
a2disconf other-vhosts-access-log 2>/dev/null || true

# ──────────────────────────────────────────────────────────────────────────────
#  Create log directory + set permissions
# ──────────────────────────────────────────────────────────────────────────────

info "Creating log directory: ${LOG_DIR}"

mkdir -p "${LOG_DIR}"
chown "${WEB_USER}:${WEB_GROUP}" "${LOG_DIR}"
chmod 750 "${LOG_DIR}"

# Create the log file with correct permissions (optional but recommended)
touch "${LOG_DIR}/logins.txt"
chown "${WEB_USER}:${WEB_GROUP}" "${LOG_DIR}/logins.txt"
chmod 640 "${LOG_DIR}/logins.txt"

# ──────────────────────────────────────────────────────────────────────────────
#  Clean default Apache files
# ──────────────────────────────────────────────────────────────────────────────

info "Removing default Apache files from ${WEB_ROOT}"

# Remove default index.html, apache2-default, etc.
rm -f "${WEB_ROOT}/index.html" \
      "${WEB_ROOT}/index.nginx-debian.html" \
      "${WEB_ROOT}/apache2-default" \
      "${WEB_ROOT}/50x.html" 2>/dev/null || true

# Optional: remove default Apache placeholder page completely
rm -rf "${WEB_ROOT}/html" 2>/dev/null || true

# Create empty index.php so directory listing doesn't happen if mod_dir is off
echo '<?php // GC3 Remote Access - placeholder' > "${WEB_ROOT}/index.php"
chown "${WEB_USER}:${WEB_GROUP}" "${WEB_ROOT}/index.php"
chmod 644 "${WEB_ROOT}/index.php"

# ──────────────────────────────────────────────────────────────────────────────
#  Restart services
# ──────────────────────────────────────────────────────────────────────────────

info "Restarting Apache..."

systemctl restart apache2 || error "Failed to restart Apache"

# ──────────────────────────────────────────────────────────────────────────────
#  Final status
# ──────────────────────────────────────────────────────────────────────────────

if systemctl is-active --quiet apache2; then
    info "Apache is running"
else
    warn "Apache failed to start — check 'journalctl -u apache2' or 'apache2ctl configtest'"
fi

echo ""
info "Setup complete."
echo "Log directory  : ${LOG_DIR}/logins.txt"
echo "Web root       : ${WEB_ROOT}"
echo "PHP version    : $(php -v | head -n1)"
echo ""
warn "Remember to place your login page (e.g. index.php) in ${WEB_ROOT}"
warn "Current directory listing is still enabled unless you disable it in config"
echo ""

exit 0
