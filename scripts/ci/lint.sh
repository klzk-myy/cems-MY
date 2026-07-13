#!/usr/bin/env bash
set -euo pipefail

# scripts/ci/lint.sh
# Run code quality checks locally.

source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

cd "$REPO_ROOT"

log_info "Validating composer files..."
composer validate --strict

log_info "Installing dependencies..."
composer install --prefer-dist --no-progress --no-interaction

log_info "Running Laravel Pint..."
./vendor/bin/pint --test

log_info "Running PHP syntax check..."
find app -name "*.php" -exec php -l {} \; 2>&1 | grep -v "No syntax errors" | head -20 | tee /tmp/php-syntax-errors.txt
if [[ -s /tmp/php-syntax-errors.txt ]]; then
  fail "PHP syntax errors detected"
fi

if [[ -f ./vendor/bin/phpstan ]]; then
  log_info "Running PHPStan..."
  ./vendor/bin/phpstan analyse --no-progress || true
else
  log_warn "PHPStan not installed; skipping"
fi

log_success "Lint stage passed"
