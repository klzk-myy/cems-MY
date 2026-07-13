#!/usr/bin/env bash
set -euo pipefail

# scripts/ci/test.sh
# Run PHPUnit test suites locally.

source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

cd "$REPO_ROOT"

RESULTS_DIR="$REPO_ROOT/storage/test-results"
mkdir -p "$RESULTS_DIR"

log_info "Installing dependencies..."
composer install --prefer-dist --no-progress --no-interaction

log_info "Setting directory permissions..."
chmod -R 777 storage bootstrap/cache

log_info "Running unit tests..."
php artisan test --testsuite=Unit 2>&1 | tee "$RESULTS_DIR/unit-test-output.txt"

log_info "Running feature tests..."
php artisan test --testsuite=Feature 2>&1 | tee "$RESULTS_DIR/feature-test-output.txt"

log_success "Test stage passed"
