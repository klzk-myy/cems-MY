#!/usr/bin/env bash
set -euo pipefail

# scripts/ci/security.sh
# Run security audit locally.

source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

require_command curl composer

cd "$REPO_ROOT"

if [[ ! -f vendor/autoload.php ]] || [[ "${FORCE_COMPOSER_INSTALL:-0}" == "1" ]]; then
  log_info "Installing dependencies..."
  composer install --prefer-dist --no-progress --no-interaction
else
  log_info "Vendor autoload found; skipping composer install"
fi

log_info "Running composer audit..."
composer audit --format=table

TRUFFLEHOG_VERSION="${TRUFFLEHOG_VERSION:-3.95.9}"
TRUFFLEHOG_DIR="${REPO_ROOT}/.tmp/trufflehog"
TRUFFLEHOG_BIN="${TRUFFLEHOG_DIR}/trufflehog"

if [[ ! -x "$TRUFFLEHOG_BIN" ]]; then
  log_info "Installing TruffleHog v${TRUFFLEHOG_VERSION}..."

  OS=$(uname -s | tr '[:upper:]' '[:lower:]')
  ARCH=$(uname -m)
  case "$ARCH" in
    x86_64) ARCH="amd64" ;;
    aarch64|arm64) ARCH="arm64" ;;
    *) fail "Unsupported architecture: $ARCH" ;;
  esac
  case "$OS" in
    linux|darwin) ;;
    *) fail "Unsupported OS: $OS" ;;
  esac

  base="trufflehog_${TRUFFLEHOG_VERSION}_${OS}_${ARCH}"
  tmp_dir=$(mktemp -d)
  trap 'rm -rf "$tmp_dir"' EXIT

  mkdir -p "$TRUFFLEHOG_DIR"

  curl -fsSL "https://github.com/trufflesecurity/trufflehog/releases/download/v${TRUFFLEHOG_VERSION}/${base}.tar.gz" \
    -o "${tmp_dir}/${base}.tar.gz"

  curl -fsSL "https://github.com/trufflesecurity/trufflehog/releases/download/v${TRUFFLEHOG_VERSION}/trufflehog_${TRUFFLEHOG_VERSION}_checksums.txt" \
    -o "${tmp_dir}/checksums.txt"

  (cd "$tmp_dir" && sha256sum -c <(grep -F "${base}.tar.gz" checksums.txt))

  tar -xzf "${tmp_dir}/${base}.tar.gz" -C "$TRUFFLEHOG_DIR" trufflehog
  chmod +x "$TRUFFLEHOG_BIN"
fi

log_info "Running TruffleHog secret scan (advisory)..."
"$TRUFFLEHOG_BIN" filesystem . --only-verified --exclude-paths=.trufflehogignore --no-update || {
  log_warn "TruffleHog reported findings or exited non-zero; treating as advisory only"
}

log_success "Security stage passed"
