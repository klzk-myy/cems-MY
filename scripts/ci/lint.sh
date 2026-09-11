#!/usr/bin/env bash
set -euo pipefail

# scripts/ci/lint.sh
# Run code quality checks locally.

source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

cd "$REPO_ROOT"

export APP_ENV="${APP_ENV:-testing}"
export APP_KEY="${APP_KEY:-base64:WlbBhnWV/8WIwLujIHnV4WqVBGA6jVh/6mAt4gt+NN8=}"

log_info "Validating composer files..."
composer validate --strict

if [[ ! -f vendor/autoload.php ]]; then
  log_info "Installing dependencies..."
  composer install --prefer-dist --no-progress --no-interaction
else
  log_info "Vendor autoload found; skipping composer install"
fi

log_info "Running Laravel Pint..."
./vendor/bin/pint --test

log_info "Running PHP syntax check across app, config, database, routes, tests..."
syntax_log=$(mktemp)
trap 'rm -f "$syntax_log"' EXIT
find app config database routes tests -type f -name "*.php" -print0 | xargs -0 -n 50 php -l 2>&1 | \
  grep -v "^No syntax errors detected" | \
  grep -v "^PHP Warning:  Module" | \
  grep -v "^PHP Deprecated:" | \
  grep -v "^Deprecated:" | \
  head -30 | tee "$syntax_log" || true

if [[ -s "$syntax_log" ]]; then
  fail "PHP syntax errors detected:\n$(cat "$syntax_log")"
fi

if [[ -f ./vendor/bin/phpstan ]]; then
  log_info "Running PHPStan..."
  ./vendor/bin/phpstan analyse --no-progress --memory-limit=2G
else
  log_warn "PHPStan not installed; skipping"
fi

log_success "Lint stage passed"
