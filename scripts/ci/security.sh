#!/usr/bin/env bash
set -euo pipefail

# scripts/ci/security.sh
# Run security audit locally.

source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

cd "$REPO_ROOT"

log_info "Installing dependencies..."
composer install --prefer-dist --no-progress --no-interaction

log_info "Running composer audit..."
composer audit --format=table

TRUFFLEHOG_VERSION="3.95.9"
TRUFFLEHOG_BIN="/usr/local/bin/trufflehog"

if [[ ! -x "$TRUFFLEHOG_BIN" ]]; then
  log_info "Installing TruffleHog v${TRUFFLEHOG_VERSION}..."
  curl -sL "https://github.com/trufflesecurity/trufflehog/releases/download/v${TRUFFLEHOG_VERSION}/trufflehog_${TRUFFLEHOG_VERSION}_linux_amd64.tar.gz" | \
    sudo tar -xz -C /usr/local/bin trufflehog
fi

log_info "Running TruffleHog secret scan (advisory)..."
"$TRUFFLEHOG_BIN" filesystem . --only-verified --exclude-paths=.trufflehogignore --no-update || {
  log_warn "TruffleHog reported findings or exited non-zero; treating as advisory only"
}

log_success "Security stage passed"
