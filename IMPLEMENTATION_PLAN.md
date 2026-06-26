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
- `tests/Feature/Audit/AppKeyTest.php`
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

## Definition of Done (Part 1)

- All Important issues resolved.
- Full test suite passes (`1181 passed, 0 failed`).
- `git log` is clean and no `.env` backup files exist in history.
- `.gitignore` only ignores intended ephemeral directories.
- AGENTS.md / CLAUDE.md remain tracked and up to date.

---

# Part 2: Follow-up Fixes from Code Review

## Overview

A focused code review of the range `6213292d..HEAD` identified one critical configuration bug and several important maintainability/security issues. This section tracks the fixes.

## Issue CR-1: Encryption Salt Env Var Naming Mismatch (Critical)

### Problem
`config/app.php:156` maps the salt to `env('ENCRYPTION_SALT')`, but `.env.example`, error messages, and `IMPLEMENTATION_PLAN.md` all use `APP_ENCRYPTION_SALT`. Following the documented variable has no effect, so production deployments either crash on startup or use an unintended salt, making previously encrypted PII undecryptable after a config fix.

### Approach
1. Change `config/app.php:156` to `'encryption_salt' => env('APP_ENCRYPTION_SALT'),`.
2. Verify no other code references `ENCRYPTION_SALT` without the `APP_` prefix.
3. Add/update tests that confirm `config('app.encryption_salt')` reflects `APP_ENCRYPTION_SALT`.

### Files to Change
- `config/app.php`
- `tests/Unit/EncryptionServiceTest.php` or `tests/Feature/Audit/AppKeyTest.php`

### Verification
- `php artisan test --compact tests/Unit/EncryptionServiceTest.php`
- Verify `.env.example` and error messages are consistent.

---

## Issue CR-2: Rate Limiting Ignores IP-Blocking Enabled Flag (Important)

### Problem
`RateLimitService::recordFailedAttempt()` and `RateLimitService::blockIp()` run even when `config('security.ip_blocking.enabled')` is `false`. The flag is only honored in `isIpBlocked()`. Disabling IP blocking via config still populates block entries, so re-enabling the flag can instantly block IPs and the feature cannot be cleanly disabled.

### Approach
1. Add an early return in `blockIp()` when the feature is disabled.
2. Add an early return in `recordFailedAttempt()` (before incrementing the failed-attempts counter or auto-blocking) when the feature is disabled.
3. Update existing tests to cover the disabled-flag path.

### Files to Change
- `app/Services/System/RateLimitService.php`
- `tests/Feature/Audit/LoginRateLimitTest.php`
- `tests/Feature/StrictRateLimitTest.php`

### Verification
- `php artisan test --compact tests/Feature/Audit/LoginRateLimitTest.php tests/Feature/StrictRateLimitTest.php`

---

## Issue CR-3: Blocked-IP Index Is Not Concurrency-Safe (Important)

### Problem
`RateLimitService` maintains `BLOCKED_IPS_INDEX` as a cached array with read-modify-write logic. Across multiple workers/requests, concurrent `blockIp()` calls can overwrite each other and lose IPs, breaking admin monitoring and bulk-unblock workflows.

### Approach
1. Replace the cached array with a Redis set (`sadd`, `srem`, `smembers`).
2. Use atomic operations for add/remove.
3. Update `getBlockedIps()` to read from the set.
4. Keep a TTL on the set matching the max block duration.

### Files to Change
- `app/Services/System/RateLimitService.php`

### Verification
- `php artisan test --compact tests/Feature/Audit/LoginRateLimitTest.php tests/Feature/StrictRateLimitTest.php`
- Add a concurrency smoke test if feasible (e.g., two parallel attempts to block different IPs and assert both appear).

---

## Issue CR-4: Dashboard Cache Invalidation Outside DB Transaction (Important)

### Problem
In `TransactionService::approveTransaction()`, `$this->cacheTagsService->invalidate('dashboard')` runs after `DB::transaction()` has committed. If cache invalidation fails after the DB commit, the transaction is persisted but the dashboard cache remains stale; the invalidation also cannot be rolled back if the approval should fail atomically.

### Approach
1. Move the dashboard cache invalidation inside the transaction closure OR dispatch it via `DB::afterCommit()`.
2. If the cache service cannot operate inside the transaction, use `DB::afterCommit()` so it runs only after a successful commit and still participates in the logical unit of work.

### Files to Change
- `app/Services/Transaction/TransactionService.php`

### Verification
- `php artisan test --compact tests/Feature/Audit/TransactionApprovalControllerTest.php tests/Unit/Transaction/TransactionServiceCacheTest.php`

---

## Issue CR-5: ThresholdService Silently Swallows DB Failures (Important)

### Problem
`ThresholdService::getPersistedValue()` catches all `\Exception` and falls back to returning `null` with only a warning log. `ThresholdService::get()` then falls back to config defaults. A database outage or schema issue is silently ignored, thresholds revert to defaults without alerting operators, and every threshold read queries the database.

### Approach
1. Catch specific query exceptions (e.g., `Illuminate\Database\QueryException`) instead of `\Exception`.
2. In production/staging, re-throw or log at `error`/`critical` level so monitoring catches it.
3. Cache persisted values for the request (or longer) to avoid repeated DB hits.
4. Keep the fallback to config defaults only for legitimate "no override" cases, not DB failures.

### Files to Change
- `app/Services/ThresholdService.php`

### Verification
- `php artisan test --compact tests/Unit/ThresholdServiceTest.php`
- Add a test that simulates a DB failure and asserts the exception/error behavior.

---

## Issue CR-6: Test-Environment Hack in bootstrap/app.php (Important)

### Problem
`bootstrap/app.php` mutates `$_ENV`/`$_SERVER`/`putenv()` to force `APP_ENV=testing` and `REDIS_PASSWORD=`. Application bootstrap should not contain environment-specific workarounds; this can leak testing state and is hard to discover.

### Approach
1. Move the `APP_ENV`/`REDIS_PASSWORD` overrides to `tests/CreatesApplication.php`.
2. Set them *before* requiring `bootstrap/app.php` so config files load with the testing environment.
3. Remove the testing override block from `bootstrap/app.php`.

### Files to Change
- `bootstrap/app.php`
- `tests/CreatesApplication.php`

### Verification
- `php artisan test --compact` (full suite) passes without the bootstrap workaround.

---

## Issue CR-7: AGENTS.md States Wrong Laravel Version (Important)

### Problem
`AGENTS.md` claims Laravel 10, but `composer.json` requires `laravel/framework ^11.0` and the code uses Laravel 11 APIs (`Application::configure`, `->withSchedule()`, etc.). This may cause agents to apply incorrect Laravel 10 conventions.

### Approach
1. Update `AGENTS.md` to list Laravel 11.
2. Review Laravel-specific guidance (e.g., middleware registration, `casts()` method availability, bootstrap/app.php conventions) and update to Laravel 11.

### Files to Change
- `AGENTS.md`

### Verification
- Manual review of `AGENTS.md` against `composer.json` and actual code patterns.

---

## Issue CR-8: Mass Deletion of Tracked Plans/Specs Docs (Important / Accepted)

### Problem / Decision
~35k lines of previously tracked `docs/superpowers/plans/*` and `docs/superpowers/specs/*` were removed and ignored. While these were stale Superpowers artifacts, they were previously tracked and may have historical value.

### Approach
1. Confirm with stakeholders that these docs are truly stale and not needed for compliance/audit history.
2. If historical value exists, restore them to an archive location (e.g., `docs/archive/superpowers/`) and update `.gitignore` to ignore only live ephemeral directories.
3. If removal is confirmed, document the decision in `AGENTS.md` or a short `docs/archive/README.md`.

### Files to Change
- Potentially restore deleted docs to `docs/archive/superpowers/`
- `.gitignore`
- `AGENTS.md` or archive README

### Verification
- `git log --stat` confirms archived docs are present or decision is documented.

---

## Issue CR-9: Duplicate Console Schedule Registration (Minor)

### Problem
The schedule is defined in both `app/Console/Kernel.php` and `bootstrap/app.php` (Laravel 11 style). This duplication means jobs may run twice if both registration paths are active.

### Approach
1. Remove `app/Console/Kernel.php` schedule definition and rely on `bootstrap/app.php` (`->withSchedule()`).
2. Or, if the Kernel is still needed for backward compatibility, ensure only one path registers schedules.

### Files to Change
- `app/Console/Kernel.php`
- `bootstrap/app.php` (if needed)

### Verification
- `php artisan schedule:list` shows each scheduled item exactly once.

---

## Issue CR-10: .env.example Permissions and Formatting (Minor)

### Problem
`.env.example` is created with executable permissions (`100755`) and may lack a trailing newline.

### Approach
1. `chmod 644 .env.example`.
2. Ensure the file ends with a newline.

### Files to Change
- `.env.example`

### Verification
- `git diff --stat` shows mode change; `tail -c 1 .env.example | xxd` confirms newline.

---

## Issue CR-11: Brittle Source-String Assertions in Tests (Minor)

### Problem
Several audit tests assert source-code strings rather than runtime behavior (e.g., checking file contents for `DB_PASSWORD` patterns, session config strings). They are deterministic but brittle and may break when code is refactored.

### Approach
1. Convert source-string assertions to runtime assertions where possible.
2. Where source inspection is the only viable test, centralize the parsing logic and document why.

### Files to Change
- `tests/Feature/Audit/*.php` as needed
- `tests/Feature/Auth/SessionConfigTest.php`

### Verification
- `php artisan test --compact tests/Feature/Audit tests/Feature/Auth`

---

## Issue CR-12: Missing Restart-Survival Encryption Test (Minor)

### Problem
`IMPLEMENTATION_PLAN.md` calls for a restart-survival encryption test, but none was added.

### Approach
1. Add a test that encrypts data, re-instantiates `EncryptionService` (simulating a restart), and decrypts successfully when `APP_ENCRYPTION_SALT` is set.
2. Add a test that verifies failure or derived-key behavior when salt is missing in the appropriate environments.

### Files to Change
- `tests/Unit/EncryptionServiceTest.php`

### Verification
- `php artisan test --compact tests/Unit/EncryptionServiceTest.php`

---

## Execution Order (Part 2)

1. Fix `config/app.php` encryption salt variable name (CR-1).
2. Add restart-survival encryption test (CR-12).
3. Update `AGENTS.md` Laravel version guidance (CR-7).
4. Harden `RateLimitService` IP-blocking flag and concurrency (CR-2, CR-3).
5. Move cache invalidation inside transaction / `DB::afterCommit()` (CR-4).
6. Improve `ThresholdService` exception handling and caching (CR-5).
7. Move test bootstrap overrides out of `bootstrap/app.php` (CR-6).
8. Resolve duplicate schedule registration (CR-9).
9. Fix `.env.example` permissions/formatting (CR-10).
10. Refactor brittle source-string assertions (CR-11).
11. Decide/archive deleted Superpowers docs (CR-8).
12. Run full suite: `php artisan test --compact`.
13. Commit fixes and push.

## Definition of Done (Part 2)

- CR-1 fixed and verified.
- All Important issues (CR-2..CR-8) resolved or explicitly accepted/documented.
- Full test suite passes (`1179+ passed, 0 failed`).
- `php artisan schedule:list` shows no duplicate schedules.
- `AGENTS.md` accurately reflects Laravel 11 and project conventions.
- No `env()` calls remain in `TrustProxies` or `RateLimitService`.
