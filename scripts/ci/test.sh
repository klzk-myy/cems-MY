#!/usr/bin/env bash
set -euo pipefail

# scripts/ci/test.sh
# Run PHPUnit test suites locally.

source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

require_command php composer

cd "$REPO_ROOT"

RESULTS_DIR="$REPO_ROOT/storage/test-results"
mkdir -p "$RESULTS_DIR"

if [[ ! -f vendor/autoload.php ]] || [[ "${FORCE_COMPOSER_INSTALL:-0}" == "1" ]]; then
  log_info "Installing dependencies..."
  composer install --prefer-dist --no-progress --no-interaction
else
  log_info "Vendor autoload found; skipping composer install"
fi

log_info "Setting directory permissions..."
chmod -R 775 storage bootstrap/cache || log_warn "Could not set some directory permissions"
find storage bootstrap/cache -type f -exec chmod 664 {} + || log_warn "Could not set some file permissions"

log_info "Running unit tests..."
php artisan test --testsuite=Unit 2>&1 | tee "$RESULTS_DIR/unit-test-output.txt"

log_info "Running feature tests..."
php artisan test --testsuite=Feature 2>&1 | tee "$RESULTS_DIR/feature-test-output.txt"

log_success "Test stage passed"
