#!/usr/bin/env bash
set -euo pipefail

# scripts/ci/lint.sh
# Run code quality checks locally.

source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

cd "$REPO_ROOT"

log_info "Validating composer files..."
composer validate --strict

if [[ ! -d vendor ]]; then
  log_info "Installing dependencies..."
  composer install --prefer-dist --no-progress --no-interaction
else
  log_info "Vendor directory found; skipping composer install"
fi

log_info "Running Laravel Pint..."
./vendor/bin/pint --test

log_info "Running PHP syntax check..."
syntax_log=$(mktemp)
trap 'rm -f "$syntax_log"' EXIT
find app -name "*.php" -exec php -l {} \; 2>&1 | { grep -v "No syntax errors" || true; } | head -20 | tee "$syntax_log"
if [[ -s "$syntax_log" ]]; then
  fail "PHP syntax errors detected"
fi

if [[ -f ./vendor/bin/phpstan ]]; then
  log_info "Running PHPStan..."
  ./vendor/bin/phpstan analyse --no-progress
else
  log_warn "PHPStan not installed; skipping"
fi

log_success "Lint stage passed"
