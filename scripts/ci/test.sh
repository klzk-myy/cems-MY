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
unit_status=0
php artisan test --testsuite=Unit --compact 2>&1 | tee "$RESULTS_DIR/unit-test-output.txt" || unit_status=$?

log_info "Running feature tests..."
feat_status=0
php artisan test --testsuite=Feature --compact 2>&1 | tee "$RESULTS_DIR/feature-test-output.txt" || feat_status=$?

log_info "Running simulation smoke test..."
sim_status=0
php artisan test --compact tests/Http/Simulation/Support/OracleSmokeTest.php 2>&1 | tee "$RESULTS_DIR/simulation-smoke-output.txt" || sim_status=$?

if [[ $unit_status -ne 0 || $feat_status -ne 0 || $sim_status -ne 0 ]]; then
  fail "Test suite failures detected (Unit exit code: $unit_status, Feature exit code: $feat_status, Simulation exit code: $sim_status). Output saved in $RESULTS_DIR"
fi

log_success "All test suites passed"
