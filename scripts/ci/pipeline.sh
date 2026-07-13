#!/usr/bin/env bash
set -euo pipefail

# scripts/ci/pipeline.sh
# Run the full local CI/CD pipeline.

source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

ENVIRONMENT=""
SKIP_LINT=false
SKIP_SECURITY=false
SKIP_TEST=false
SKIP_DEPLOY=false

for arg in "$@"; do
  case "$arg" in
    --skip-lint) SKIP_LINT=true ;;
    --skip-security) SKIP_SECURITY=true ;;
    --skip-test) SKIP_TEST=true ;;
    --skip-deploy) SKIP_DEPLOY=true ;;
    staging|production)
      [[ -z "$ENVIRONMENT" ]] || fail "Only one environment may be specified (got: $ENVIRONMENT and $arg)"
      ENVIRONMENT="$arg"
      ;;
    --help)
      echo "Usage: $0 [staging|production] [--skip-lint] [--skip-security] [--skip-test] [--skip-deploy]"
      exit 0
      ;;
    *) fail "Unknown argument: $arg" ;;
  esac
done

if [[ "$SKIP_DEPLOY" == false && -z "$ENVIRONMENT" ]]; then
  fail "Deployment requested but no environment specified. Usage: $0 [staging|production] [--skip-deploy]"
fi

cd "$REPO_ROOT"

[[ "$SKIP_LINT" == true ]] || run_step "Lint" scripts/ci/lint.sh
[[ "$SKIP_SECURITY" == true ]] || run_step "Security" scripts/ci/security.sh
[[ "$SKIP_TEST" == true ]] || run_step "Test" scripts/ci/test.sh

if [[ "$SKIP_DEPLOY" == false ]]; then
  run_step "Deploy to $ENVIRONMENT" scripts/ci/deploy.sh "$ENVIRONMENT"
fi

log_success "Pipeline complete"
