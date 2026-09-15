#!/usr/bin/env bash
set -euo pipefail

# scripts/ci/deploy.sh
# Deploy the application to the target environment.

source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

ENVIRONMENT="${1:?Usage: $0 <environment>}"
ENV_FILE="$REPO_ROOT/.env.deploy.$ENVIRONMENT"

cd "$REPO_ROOT"

load_env_file "$ENV_FILE"
assert_env DEPLOY_HOST DEPLOY_USER DEPLOY_PATH DEPLOY_APP_URL DEPLOY_BRANCH

SSH_OPTS=(-o StrictHostKeyChecking=accept-new -o UserKnownHostsFile=/dev/null)
if [[ -n "${DEPLOY_SSH_KEY:-}" && -f "$DEPLOY_SSH_KEY" ]]; then
  SSH_OPTS+=( -i "$DEPLOY_SSH_KEY" )
fi

deploy_target="$DEPLOY_USER@$DEPLOY_HOST"

# Local mode: deploy to a directory on this machine without SSH.
# Enabled explicitly via DEPLOY_LOCAL=true or when DEPLOY_HOST is loopback.
DEPLOY_LOCAL="${DEPLOY_LOCAL:-false}"
if [[ "$DEPLOY_HOST" == "localhost" || "$DEPLOY_HOST" == "127.0.0.1" ]]; then
  DEPLOY_LOCAL=true
fi

if [[ "$DEPLOY_LOCAL" == "true" ]]; then
  log_info "Deploying to $ENVIRONMENT (local: $DEPLOY_PATH)"

  run_remote() {
    bash -c "$*"
  }
else
  log_info "Deploying to $ENVIRONMENT ($deploy_target:$DEPLOY_PATH)"

  run_remote() {
    ssh "${SSH_OPTS[@]}" "$deploy_target" "$@"
  }
fi

# Capture current commit for rollback capability
PREV_COMMIT=$(run_remote "cd $(printf '%q' "$DEPLOY_PATH") && git rev-parse HEAD" 2>/dev/null || true)
if [[ -n "$PREV_COMMIT" ]]; then
  log_info "Previous commit before deployment: $PREV_COMMIT"
fi

run_remote "DEPLOY_PATH=$(printf '%q' "$DEPLOY_PATH") DEPLOY_BRANCH=$(printf '%q' "$DEPLOY_BRANCH") bash -s" <<'REMOTE'
  set -euo pipefail
  cd "$DEPLOY_PATH"

  # Put application in maintenance mode during update to avoid mid-deploy 500 errors
  php artisan down --render="errors::503" --retry=60 || true

  git fetch origin "$DEPLOY_BRANCH"
  git reset --hard "origin/$DEPLOY_BRANCH"
  composer install --no-dev --optimize-autoloader --prefer-dist --no-interaction

  if [[ -f package.json ]]; then
    if [[ -f package-lock.json ]]; then
      npm ci
    else
      npm install
    fi
    npm run build
  fi

  # database/migrations is retired; schema is recreated by SchemaSeeder
  # only on fresh installs (DatabaseSeeder refuses non-empty databases).
  php artisan db:seed --class=DatabaseSeeder || true
  php artisan optimize:clear
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  php artisan optimize

  # Warn when DB threshold overrides are active — they take precedence over
  # .env values, so env changes made for this deploy will not affect them.
  php artisan thresholds:check-overrides || echo "WARNING: active threshold DB overrides detected (see output above)"

  php artisan horizon:terminate || echo "WARNING: failed to terminate Horizon"
  sudo supervisorctl restart cems-worker:* || echo "WARNING: failed to restart queue workers"

  sudo systemctl reload php8.3-fpm || echo "WARNING: failed to reload php8.3-fpm"
  sudo systemctl reload nginx || sudo /etc/init.d/httpd reload || echo "WARNING: failed to reload web server"

  # Bring application out of maintenance mode
  php artisan up || true
REMOTE

log_info "Waiting for services to settle..."
sleep 5

log_info "Verifying deployment at $DEPLOY_APP_URL/up..."
if ! curl -fsS --connect-timeout 10 --max-time 30 "$DEPLOY_APP_URL/up"; then
  log_error "Health check failed at $DEPLOY_APP_URL/up"

  if [[ -n "$PREV_COMMIT" ]]; then
    log_warn "Attempting automated rollback to previous commit $PREV_COMMIT..."
    run_remote "DEPLOY_PATH=$(printf '%q' "$DEPLOY_PATH") PREV_COMMIT=$(printf '%q' "$PREV_COMMIT") bash -s" <<'ROLLBACK'
      cd "$DEPLOY_PATH"
      git reset --hard "$PREV_COMMIT"
      composer install --no-dev --optimize-autoloader --prefer-dist --no-interaction || true
      if [[ -f package.json ]]; then
        npm run build || true
      fi
      php artisan optimize:clear || true
      php artisan optimize || true
      php artisan up || true
      php artisan horizon:terminate || true
      sudo supervisorctl restart cems-worker:* || true
      sudo systemctl reload php8.3-fpm || true
      sudo systemctl reload nginx || sudo /etc/init.d/httpd reload || true
ROLLBACK
    log_info "Rollback to $PREV_COMMIT completed."
  fi

  if [[ -n "${SLACK_WEBHOOK_URL:-}" ]]; then
    curl -fsS --connect-timeout 10 --max-time 30 -X POST -H 'Content-type: application/json' \
      --data "{\"text\":\"❌ CEMS-MY deployment to $ENVIRONMENT FAILED (Health check /up failed; rollback attempted to ${PREV_COMMIT:-unknown})\"}" \
      "$SLACK_WEBHOOK_URL" || true
  fi

  fail "Deployment health check failed for $DEPLOY_APP_URL/up"
fi

if [[ -n "${SLACK_WEBHOOK_URL:-}" ]]; then
  log_info "Sending Slack success notification..."
  curl -fsS --connect-timeout 10 --max-time 30 -X POST -H 'Content-type: application/json' \
    --data "{\"text\":\"✅ CEMS-MY deployed successfully to $ENVIRONMENT\"}" \
    "$SLACK_WEBHOOK_URL" || log_warn "Slack notification failed"
fi

log_success "Deployment to $ENVIRONMENT complete"
