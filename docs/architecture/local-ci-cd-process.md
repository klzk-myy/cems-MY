# Local CI/CD Process

This document describes the local CI/CD pipeline for the CEMS-MY Laravel application. The pipeline has been moved entirely out of GitHub Actions into version-controlled shell scripts under `scripts/ci/`.

## Overview

The pipeline runs on any workstation or server that has:

- PHP 8.3
- Composer
- Node.js and npm
- Bash
- SSH access to deployment targets (for deploys)

No GitHub secrets are required. Deployment credentials live in gitignored local files:

- `.env.deploy.staging`
- `.env.deploy.production`

Only `.env.deploy.*.example` templates are tracked in Git.

## File Responsibilities

| File | Responsibility |
|------|----------------|
| `scripts/ci/common.sh` | Shared helpers: colored logging, environment file loading, command checks, step runner |
| `scripts/ci/lint.sh` | `composer validate`, dependency install, Laravel Pint, PHP syntax check, optional PHPStan |
| `scripts/ci/security.sh` | `composer audit`, TruffleHog secret scan |
| `scripts/ci/test.sh` | Unit tests, feature tests, output saved to `storage/test-results/` |
| `scripts/ci/deploy.sh` | SSH-based deployment: pull code, install dependencies, build assets, migrate, cache, restart services, health check |
| `scripts/ci/pipeline.sh` | Orchestrates lint → security → test → deploy with optional skip flags |
| `Makefile` | Convenience entry points: `make ci`, `make test`, `make deploy ENV=staging` |
| `.env.deploy.staging.example` | Template for staging deployment variables |
| `.env.deploy.production.example` | Template for production deployment variables |

## How the Pipeline Runs

### Non-Deploy CI: `make ci`

Runs the three verification stages in order.

```bash
make ci
```

This is equivalent to:

```bash
make lint
make security
make test
```

### `make lint` → `scripts/ci/lint.sh`

1. Validates `composer.json` and `composer.lock`
2. Installs Composer dependencies only if `vendor/` is missing
3. Runs Laravel Pint in test mode (`./vendor/bin/pint --test`)
4. Runs `php -l` syntax check on all PHP files under `app/`
5. Runs PHPStan (`./vendor/bin/phpstan analyse --no-progress`)

### `make security` → `scripts/ci/security.sh`

1. Installs Composer dependencies only if `vendor/autoload.php` is missing
2. Runs `composer audit`
3. Installs TruffleHog locally into `.tmp/trufflehog` if it is not present
4. Verifies the TruffleHog tarball checksum before extraction
5. Runs a TruffleHog filesystem scan in advisory mode

### `make test` → `scripts/ci/test.sh`

1. Installs Composer dependencies only if `vendor/autoload.php` is missing
2. Sets storage and bootstrap/cache permissions
3. Runs `php artisan test --testsuite=Unit`
4. Runs `php artisan test --testsuite=Feature`
5. Saves output to `storage/test-results/unit-test-output.txt` and `storage/test-results/feature-test-output.txt`

## Deployment: `make deploy ENV=staging`

Runs the full pipeline including deployment.

```bash
make deploy ENV=staging
```

This invokes:

```bash
scripts/ci/pipeline.sh staging
```

### `scripts/ci/pipeline.sh`

- Parses arguments: `staging` or `production`, plus optional skip flags:
  - `--skip-lint`
  - `--skip-security`
  - `--skip-test`
  - `--skip-deploy`
- Runs each stage unless skipped
- Calls `scripts/ci/deploy.sh <environment>` for the deploy step

### `scripts/ci/deploy.sh`

1. Loads `.env.deploy.<environment>`
2. Asserts required variables are set:
   - `DEPLOY_HOST`
   - `DEPLOY_USER`
   - `DEPLOY_PATH`
   - `DEPLOY_APP_URL`
   - `DEPLOY_BRANCH`
3. Builds SSH options; adds `-i <key>` if `DEPLOY_SSH_KEY` is configured
4. SSHes to the target server and runs:

```bash
git fetch origin <DEPLOY_BRANCH>
git reset --hard origin/<DEPLOY_BRANCH>
composer install --no-dev --optimize-autoloader --prefer-dist --no-interaction

if [ -f package-lock.json ]; then
    npm ci
else
    npm install
fi
npm run build

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
```

5. Waits 5 seconds for services to settle
6. Performs a health check against `DEPLOY_APP_URL/health`
7. Sends a Slack notification if `SLACK_WEBHOOK_URL` is configured

## Deployment Configuration

Create real deployment credentials from the templates:

```bash
cp .env.deploy.staging.example .env.deploy.staging
# Edit .env.deploy.staging with real values
```

Example `.env.deploy.staging`:

```bash
DEPLOY_HOST=192.0.2.10
DEPLOY_USER=deploy
DEPLOY_PATH=/var/www/cems-my
DEPLOY_APP_URL=https://staging.cems-my.local
DEPLOY_BRANCH=main
DEPLOY_SSH_KEY=/home/you/.ssh/id_rsa_cems_staging
SLACK_WEBHOOK_URL=https://hooks.slack.com/services/...
```

`.env.deploy.*` is gitignored and will never be committed.

## Make Targets Reference

```bash
make help                  # Show all available targets
make lint                  # Run code quality checks
make security              # Run security audit
make test                  # Run unit and feature tests
make ci                    # Run lint + security + test (no deploy)
make deploy ENV=staging    # Run full pipeline and deploy to staging
make deploy-staging        # Equivalent to make deploy ENV=staging
make deploy-production     # Equivalent to make deploy ENV=production
```

## Comparison: GitHub Actions vs. Local CI/CD

| Aspect | GitHub Actions (old) | Local CI/CD (current) |
|--------|----------------------|-----------------------|
| Secrets | Stored in GitHub (`SSH_PRIVATE_KEY`, `SERVER_HOST`, etc.) | Stored in local `.env.deploy.*` files |
| Execution | GitHub-hosted runners | Local workstation or server |
| Offline use | Requires GitHub and internet | Works offline after dependencies are installed |
| Deployment logic | YAML workflow | Readable shell script |
| Trigger | Push to `main`/`develop` | Manual `make` invocation |

The old workflow files `.github/workflows/ci.yml` and `.github/workflows/staging-deploy.yml` have been removed.

## Operational Notes

- TruffleHog runs in advisory mode. It logs warnings but does not fail the security stage. To make it blocking, change the exit handling in `scripts/ci/security.sh`.
- Service reloads (`php8.3-fpm`, `nginx`, `supervisorctl`) assume the deploy user has passwordless `sudo` on the target server.
- SSH uses `StrictHostKeyChecking=accept-new`. For stricter security, pin the target host key in a `known_hosts` file and update `scripts/ci/deploy.sh` to use it.
- Actual staging and production deploys, plus smoke tests, are still manual steps after creating the appropriate `.env.deploy.*` file.

## Smoke Test Checklist After Deploy

After a successful deployment, verify the application:

1. Create a customer
2. Create a transaction (Buy/Sell)
3. Approve a transaction ≥ RM 10,000
4. Generate MSB2 / LMCA reports
5. View the dashboard
6. Check `storage/logs/laravel.log` for errors
7. Verify Horizon and queue workers are running
