#!/usr/bin/env bash
#
# scripts/install-deps.sh — install all CEMS-MY server dependencies.
#
# Auto-detects:
#   - Architecture: x86_64 / aarch64 (ARM)
#   - OS family:    Ubuntu (apt) / AlmaLinux / Oracle Linux (dnf)
#
# Installs: nginx, MariaDB 10.11 LTS, Redis, PHP 8.3 + extensions, Composer, Node.js 22.
#
# Usage:
#   sudo bash scripts/install-deps.sh
#
set -euo pipefail

PHP_VERSION="8.3"
NODE_MAJOR="22"
MARIADB_VERSION="10.11"   # latest 10.x LTS

PHP_EXTENSIONS=(
    cli fpm mysql redis gd zip mbstring xml bcmath intl curl opcache sqlite3
)

# Remi/RHEL package names differ for the MySQL driver.
PHP_EXTENSIONS_RHEL=(
    cli fpm mysqlnd redis gd zip mbstring xml bcmath intl curl opcache sqlite3
)

log()  { printf '\033[1;34m==>\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33mWARN:\033[0m %s\n' "$*"; }
die()  { printf '\033[1;31mERROR:\033[0m %s\n' "$*" >&2; exit 1; }

# ---------------------------------------------------------------------------
# Detection
# ---------------------------------------------------------------------------

[[ $EUID -eq 0 ]] || die "Run as root: sudo bash $0"

ARCH="$(uname -m)"
case "$ARCH" in
    x86_64|amd64)   ARCH="x86_64" ;;
    aarch64|arm64)  ARCH="aarch64" ;;
    *) die "Unsupported architecture: $ARCH" ;;
esac

[[ -r /etc/os-release ]] || die "Cannot detect OS: /etc/os-release missing"
# shellcheck disable=SC1091
. /etc/os-release

OS_ID="${ID:-unknown}"
OS_MAJOR="${VERSION_ID%%.*}"

case "$OS_ID" in
    ubuntu|debian)           FAMILY="debian" ;;
    almalinux|ol|oraclelinux|rocky|rhel|centos) FAMILY="rhel" ;;
    *) die "Unsupported OS: ${OS_ID} (supported: ubuntu, almalinux, ol)" ;;
esac

log "Detected: ${PRETTY_NAME:-$OS_ID} | arch: $ARCH | family: $FAMILY"

# ---------------------------------------------------------------------------
# Debian family (Ubuntu)
# ---------------------------------------------------------------------------

install_debian() {
    export DEBIAN_FRONTEND=noninteractive

    apt-get update
    apt-get install -y software-properties-common ca-certificates curl gnupg unzip git

    # PHP 8.3 via ondrej PPA (native on Ubuntu 24.04+, needed on older releases)
    if ! apt-cache show "php${PHP_VERSION}-fpm" >/dev/null 2>&1; then
        add-apt-repository -y ppa:ondrej/php
        apt-get update
    fi

    log "Installing PHP ${PHP_VERSION} + extensions"
    apt-get install -y \
        $(printf 'php%s-%s ' "$PHP_VERSION" "${PHP_EXTENSIONS[@]}")

    log "Installing nginx, MariaDB ${MARIADB_VERSION} LTS, Redis"
    install_mariadb_repo
    apt-get install -y nginx mariadb-server redis-server

    install_nodesource_deb
    install_composer
}

# ---------------------------------------------------------------------------
# RHEL family (AlmaLinux / Oracle Linux)
# ---------------------------------------------------------------------------

install_rhel() {
    dnf install -y dnf-utils ca-certificates curl gnupg2 unzip git

    # Remi repo carries PHP 8.3 for EL8/EL9 on both x86_64 and aarch64.
    if ! dnf module list php 2>/dev/null | grep -q "${PHP_VERSION}" \
        && ! rpm -q "remi-release" >/dev/null 2>&1; then
        log "Enabling Remi repository for PHP ${PHP_VERSION}"
        dnf install -y \
            "https://rpms.remirepo.net/enterprise/remi-release-${OS_MAJOR}.rpm" \
            || die "Remi repo install failed for EL${OS_MAJOR}"
    fi
    dnf module reset php -y >/dev/null 2>&1 || true
    dnf module enable "php:remi-${PHP_VERSION}" -y

    log "Installing PHP ${PHP_VERSION} + extensions"
    dnf install -y \
        $(printf 'php-%s ' "${PHP_EXTENSIONS_RHEL[@]}")

    log "Installing nginx"
    dnf install -y nginx

    log "Installing MariaDB ${MARIADB_VERSION} LTS"
    install_mariadb_repo
    dnf install -y MariaDB-server

    log "Installing Redis"
    dnf install -y redis || {
        warn "redis package unavailable; enabling redis:6 module"
        dnf module enable redis:6 -y && dnf install -y redis
    }

    install_nodesource_rpm
    install_composer
}

# ---------------------------------------------------------------------------
# Shared installers
# ---------------------------------------------------------------------------

install_mariadb_repo() {
    if command -v mariadb >/dev/null \
        && mariadb --version | grep -q "${MARIADB_VERSION}"; then
        log "MariaDB ${MARIADB_VERSION} already installed"
        return
    fi
    # Official repo setup supports apt + dnf distros and x86_64/aarch64.
    log "Configuring MariaDB ${MARIADB_VERSION} repository"
    curl -fsSL https://r.mariadb.com/downloads/mariadb_repo_setup \
        | bash -s -- --mariadb-server-version="mariadb-${MARIADB_VERSION}"
    if [[ "$FAMILY" == "debian" ]]; then
        apt-get update
    fi
}

install_nodesource_deb() {
    if command -v node >/dev/null && [[ "$(node -v)" == v${NODE_MAJOR}.* ]]; then
        log "Node.js ${NODE_MAJOR}.x already installed"
        return
    fi
    log "Installing Node.js ${NODE_MAJOR} (NodeSource)"
    curl -fsSL "https://deb.nodesource.com/setup_${NODE_MAJOR}.x" | bash -
    apt-get install -y nodejs
}

install_nodesource_rpm() {
    if command -v node >/dev/null && [[ "$(node -v)" == v${NODE_MAJOR}.* ]]; then
        log "Node.js ${NODE_MAJOR}.x already installed"
        return
    fi
    log "Installing Node.js ${NODE_MAJOR} (NodeSource)"
    curl -fsSL "https://rpm.nodesource.com/setup_${NODE_MAJOR}.x" | bash -
    dnf install -y nodejs
}

install_composer() {
    if command -v composer >/dev/null; then
        log "Composer already installed"
        return
    fi
    log "Installing Composer"
    local installer
    installer="$(mktemp)"
    curl -fsSL https://getcomposer.org/installer -o "$installer"
    php "$installer" --install-dir=/usr/local/bin --filename=composer
    rm -f "$installer"
}

# ---------------------------------------------------------------------------
# Enable services
# ---------------------------------------------------------------------------

enable_services() {
    systemctl enable --now nginx php${PHP_VERSION}-fpm 2>/dev/null \
        || systemctl enable --now nginx php-fpm

    for svc in mariadb mysqld mysql redis redis-server; do
        if systemctl list-unit-files "${svc}.service" >/dev/null 2>&1 \
            && systemctl cat "${svc}.service" >/dev/null 2>&1; then
            systemctl enable --now "$svc"
        fi
    done
}

# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

case "$FAMILY" in
    debian) install_debian ;;
    rhel)   install_rhel ;;
esac

enable_services

log "Verifying installations"
php -v | head -1
composer --version 2>/dev/null | head -1 || true
node -v
mariadb --version 2>/dev/null || mysql --version
redis-server --version
nginx -v 2>&1

cat <<'DONE'

========================================================================
 CEMS-MY dependencies installed.

 Next steps (see README.md "Deployment"):
   1. Clone the repo and set permissions
   2. Create the MariaDB database + user
   3. Configure .env
   4. Configure the nginx vhost (docroot = <repo>/public)
   5. Install the scheduler cron + queue worker systemd unit
========================================================================
DONE
