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

log_info "Deploying to $ENVIRONMENT ($deploy_target:$DEPLOY_PATH)"

run_remote() {
  ssh "${SSH_OPTS[@]}" "$deploy_target" "$@"
}

run_remote "DEPLOY_PATH=$(printf '%q' "$DEPLOY_PATH") DEPLOY_BRANCH=$(printf '%q' "$DEPLOY_BRANCH") bash -s" <<'REMOTE'
  set -euo pipefail
  cd "$DEPLOY_PATH"
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

  php artisan migrate --force
  php artisan optimize:clear
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  php artisan optimize

  php artisan horizon:terminate || echo "WARNING: failed to terminate Horizon"
  sudo supervisorctl restart cems-worker:* || echo "WARNING: failed to restart queue workers"

  sudo systemctl reload php8.3-fpm || echo "WARNING: failed to reload php8.3-fpm"
  sudo systemctl reload nginx || echo "WARNING: failed to reload nginx"
REMOTE

log_info "Waiting for services to settle..."
sleep 5

log_info "Verifying deployment at $DEPLOY_APP_URL/health..."
curl -fsS --connect-timeout 10 --max-time 30 "$DEPLOY_APP_URL/health" || fail "Health check failed"

if [[ -n "${SLACK_WEBHOOK_URL:-}" ]]; then
  log_info "Sending Slack success notification..."
  curl -fsS --connect-timeout 10 --max-time 30 -X POST -H 'Content-type: application/json' \
    --data "{\"text\":\"✅ CEMS-MY deployed to $ENVIRONMENT\"}" \
    "$SLACK_WEBHOOK_URL" || log_warn "Slack notification failed"
fi

log_success "Deployment to $ENVIRONMENT complete"
