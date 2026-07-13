#!/usr/bin/env bash
set -euo pipefail

# scripts/ci/deploy.sh
# Deploy the application to the target environment.

source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

ENVIRONMENT="${1:-staging}"
ENV_FILE="$REPO_ROOT/.env.deploy.$ENVIRONMENT"

cd "$REPO_ROOT"

load_env_file "$ENV_FILE"
assert_env DEPLOY_HOST DEPLOY_USER DEPLOY_PATH DEPLOY_APP_URL DEPLOY_BRANCH

SSH_OPTS="-o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null"
if [[ -n "${DEPLOY_SSH_KEY:-}" && -f "$DEPLOY_SSH_KEY" ]]; then
  SSH_OPTS="$SSH_OPTS -i $DEPLOY_SSH_KEY"
fi

deploy_target="$DEPLOY_USER@$DEPLOY_HOST"

log_info "Deploying to $ENVIRONMENT ($deploy_target:$DEPLOY_PATH)"

run_remote() {
  ssh $SSH_OPTS "$deploy_target" "$@"
}

run_remote "
  set -e
  cd $DEPLOY_PATH
  git pull origin $DEPLOY_BRANCH
  composer install --no-dev --prefer-dist --no-interaction
  php artisan migrate --force
  php artisan optimize:clear
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  php artisan optimize
  sudo systemctl reload php8.2-fpm || true
  sudo systemctl reload nginx || true
"

log_info "Waiting for services to settle..."
sleep 5

log_info "Verifying deployment at $DEPLOY_APP_URL/health..."
curl -fsS "$DEPLOY_APP_URL/health" || fail "Health check failed"

if [[ -n "${SLACK_WEBHOOK_URL:-}" ]]; then
  log_info "Sending Slack success notification..."
  curl -fsS -X POST -H 'Content-type: application/json' \
    --data "{\"text\":\"✅ CEMS-MY deployed to $ENVIRONMENT\"}" \
    "$SLACK_WEBHOOK_URL" || log_warn "Slack notification failed"
fi

log_success "Deployment to $ENVIRONMENT complete"
