# Implementation Plan: Fix Post-Merge Issues

## Overview

This plan addresses the Important and Minor issues identified in the code review after merging `audit-fixes-2026-06-26` into `main`. The Critical credential-leak issue has already been resolved via `git filter-branch`; this plan covers the remaining code-quality and maintainability issues.

## Issue 1: TrustProxies Directly Calls `env()`

### Problem
`App\Http\Middleware\TrustProxies` reads `TRUSTED_PROXIES` via `env()` in its constructor. Per project convention, `env()` should only be called inside config files. Additionally, when configuration caching is enabled, `env()` calls outside config files return `null`.

### Approach
1. Create `config/trustedproxy.php` that reads `env('TRUSTED_PROXIES')` and parses it into an array.
2. Update `App\Http\Middleware\TrustProxies` to use `config('trustedproxy.proxies')` instead of `env('TRUSTED_PROXIES')`.
3. Add `.env.example` entry for `TRUSTED_PROXIES=`.
4. Add/update tests to verify the middleware reads from config correctly.

### Files to Change
- `config/trustedproxy.php` (new)
- `app/Http/Middleware/TrustProxies.php`
- `.env.example`
- `tests/Feature/Infrastructure/TrustedProxiesTest.php` (update or add)

### Verification
- `php artisan test --compact tests/Feature/Infrastructure/TrustedProxiesTest.php`
- Run full suite: `php artisan test --compact`

---

## Issue 2: Audit Tests Are Brittle and Environment-Dependent

### Problem
Several audit tests parse `.env` directly with `file_get_contents(base_path('.env'))` and assert local-specific values such as `APP_DEBUG=false`, `DB_USERNAME=cems_app`, and password length >= 12. These tests fail on CI or other developers' machines where `.env` differs.

### Approach
1. Refactor each brittle test to assert against runtime configuration values (`config('app.debug')`, `config('database.connections.mysql.username')`, etc.) instead of parsing `.env`.
2. Where the test intent is to verify that a value is configured (not its specific local value), assert `config()` has a non-default value.
3. For tests that must inspect `.env`, make them skip when `.env` is missing or the value is not set.

### Files to Change
- `tests/Feature/Audit/DebugModeTest.php`
- `tests/Feature/Audit/DatabaseCredentialsTest.php`
- `tests/Feature/Audit/AppKeyRotationTest.php`
- `tests/Feature/Audit/EncryptionKeyTest.php`
- Any other audit test that reads `.env` directly.

### Verification
- Run each affected test file individually.
- Simulate a different `.env` (e.g., rename temporarily) and confirm tests either pass or skip gracefully.

---

## Issue 3: EncryptionService Auto-Generated Salt Risks Data Loss

### Problem
If `APP_ENCRYPTION_SALT` is not set, `EncryptionService` generates a random salt on every boot. Previously encrypted PII becomes undecryptable after the next restart.

### Approach
1. Require `APP_ENCRYPTION_SALT` to be configured in production/staging. In local/test environments, allow a deterministic fallback derived from `APP_KEY` only if explicitly opted-in via a new env flag (e.g., `ALLOW_DERIVED_ENCRYPTION_SALT=true`).
2. On boot, if `APP_ENCRYPTION_SALT` is missing:
   - In `local`/`testing`: log a warning and derive a stable salt from `APP_KEY` (only when opt-in flag is set).
   - In `production`/`staging`: throw a fatal exception to prevent startup.
3. Update `.env.example` with `APP_ENCRYPTION_SALT=` placeholder and documentation.
4. Add a test verifying that encryption/decryption survives a service restart / re-instantiation.

### Files to Change
- `app/Services/System/EncryptionService.php`
- `.env.example`
- `tests/Unit/System/EncryptionServiceTest.php` (new or update existing)

### Verification
- Test encryption/decryption with `APP_ENCRYPTION_SALT` set.
- Test that without the salt and without the opt-in flag, service throws or refuses to start in non-local env.

---

## Issue 4: RateLimitService Cache Store Inconsistency

### Problem
`RateLimitService` mostly uses `Cache::store()` (default cache driver), but `recordFailedAttempt()` still uses `Cache::store('redis')`. If the default cache driver is not Redis, rate-limit and IP-blocking state will not be shared across workers.

### Approach
1. Introduce a dedicated config key for the rate-limit cache store: `config('rate_limit.store', 'redis')`.
2. Add `config/ratelimit.php` (or extend existing config) reading `env('RATE_LIMIT_CACHE_STORE', 'redis')`.
3. Replace all `Cache::store()` calls in `RateLimitService` with `Cache::store(config('rate_limit.store'))`.
4. Add tests verifying that the configured store is used.

### Files to Change
- `config/ratelimit.php` (new or extend existing)
- `app/Services/System/RateLimitService.php`
- `.env.example`
- `tests/Unit/System/RateLimitServiceTest.php` (new or update existing)

### Verification
- Run `RateLimitService` tests with default config.
- Temporarily change `RATE_LIMIT_CACHE_STORE` and verify behavior follows.

---

## Issue 5: Poor WIP Commit Message

### Problem
The commit `WIP: uncommitted changes on audit-fixes-2026-06-26` bundles unrelated fixes, tests, and documentation. After history rewrite it no longer contains the secret files, but the message remains poor.

### Approach
1. Perform an interactive rebase from the merge-base to rewrite the WIP commit message to something descriptive, e.g.:
   - `fix: apply audit security and compliance fixes (session, rate limit, precision)`
2. Optionally split the commit into logical groups if the scope is too broad.

### Files to Change
- Git history only.

### Verification
- `git log --oneline` shows clean messages.

---

## Issue 6: `.gitignore` `**/docs/` Is Too Broad

### Problem
The pattern `**/docs/` ignores any directory named `docs` at any depth, which may hide legitimate documentation added later.

### Approach
1. Replace `**/docs/` with specific root-level ignores:
   - `/docs/superpowers/plans/`
   - `/docs/superpowers/specs/`
2. Keep `/docs/` tracked by default; only ignore the ephemeral Superpowers subdirectories.

### Files to Change
- `.gitignore`

### Verification
- `git check-ignore -v docs/ARCHITECTURE.md` reports no match.
- `git check-ignore -v docs/superpowers/plans/test.md` reports a match.

---

## Issue 7: Noisy Cleanup History

### Problem
There are multiple small `.gitignore`/removal commits that could be squashed for a cleaner history.

### Approach
1. Use interactive rebase to squash the following into one or two commits:
   - `.gitignore` updates for env backups, backup files, ephemeral docs
   - Removal of stale Superpowers plans/specs, migration backup, stashed `.env.example`
   - `.mimocode` / `.gitnexusignore` ignores
2. Keep the `docs: track .env.example, AGENTS.md, and CLAUDE.md` commit separate because it is semantically distinct.

### Files to Change
- Git history only.

### Verification
- `git log --oneline` shows a clean, logical commit sequence.

---

## Execution Order

1. Fix TrustProxies (`env()` -> config).
2. Refactor brittle audit tests.
3. Harden EncryptionService salt handling.
4. Unify RateLimitService cache store.
5. Tighten `.gitignore` for docs.
6. Rewrite commit messages and squash cleanup commits.
7. Run full test suite: `php artisan test --compact`.
8. Force-push rewritten history: `git push --force-with-lease origin main`.

## Definition of Done

- All Important issues resolved.
- Full test suite passes (`1181 passed, 0 failed`).
- `git log` is clean and no `.env` backup files exist in history.
- `.gitignore` only ignores intended ephemeral directories.
- AGENTS.md / CLAUDE.md remain tracked and up to date.
