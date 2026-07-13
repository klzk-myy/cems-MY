#!/usr/bin/env bash
set -euo pipefail

# scripts/ci/common.sh
# Shared helpers for local CI/CD scripts.

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
export REPO_ROOT

log_info() { printf '%b\n' "\033[34m[INFO]\033[0m  $*"; }
log_success() { printf '%b\n' "\033[32m[OK]\033[0m    $*"; }
log_warn() { printf '%b\n' "\033[33m[WARN]\033[0m  $*"; }
log_error() { printf '%b\n' "\033[31m[ERROR]\033[0m $*"; }

fail() {
  log_error "$*"
  exit 1
}

require_command() {
  for cmd in "$@"; do
    if ! command -v "$cmd" >/dev/null 2>&1; then
      fail "Required command not found: $cmd"
    fi
  done
}

load_env_file() {
  local file="$1"
  if [[ -f "$file" ]]; then
    log_info "Loading environment from $file"
    set -a
    # shellcheck source=/dev/null
    source "$file"
    set +a
  else
    log_warn "Environment file not found: $file"
  fi
}

assert_env() {
  for var in "$@"; do
    if [[ -z "${!var:-}" ]]; then
      fail "Required environment variable is not set: $var"
    fi
  done
}

run_step() {
  local name="$1"
  shift
  log_info "Running: $name"
  if "$@"; then
    log_success "$name"
  else
    fail "$name failed"
  fi
}
