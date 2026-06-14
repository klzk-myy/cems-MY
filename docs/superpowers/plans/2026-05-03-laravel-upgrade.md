# Laravel 10 → 11 Upgrade Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Upgrade Laravel from 10.50.2 to 11.x, including all compatible dependencies.

**Architecture:** Standard Laravel upgrade path. Replace legacy bootstrap/app.php kernel binding pattern with Laravel 11's new `bootstrap/app.php` structure that uses service providers and middleware configurations. Update HTTP Kernel to use the new middleware structure. Ensure all routes and configuration are compatible.

**Tech Stack:** Laravel 11.x, PHP 8.2+, Laravel Sanctum 4.x, Laravel Horizon 6.x, barryvdh/laravel-dompdf 4.x

---

## Phase 1: Assessment & Preparation

### Task 1: Audit Compatibility

**Files:**
- Check: `composer.json`
- Check: `app/Http/Kernel.php`
- Check: `app/Console/Kernel.php`
- Check: `app/Exceptions/Handler.php`

- [ ] **Step 1: Run composer outdated to see current package versions**

```bash
composer outdated --format=json 2>/dev/null | jq '.packages[] | select(.name | contains("laravel"))'
```

- [ ] **Step 2: Check for Laravel 11 compatible versions**

```bash
composer show laravel/framework --available 2>/dev/null | grep "^versions" | tail -5
```

- [ ] **Step 3: Check PHP version compatibility for Laravel 11**

```bash
php -v
```
Laravel 11 requires PHP 8.2+. Verify current PHP version.

- [ ] **Step 4: Run test suite to establish baseline**

```bash
php artisan test --without-tty 2>&1 | tail -20
```

Expected: Tests should pass before upgrade begins. Note any failures for post-upgrade comparison.

- [ ] **Step 5: Commit baseline**

```bash
git add -A && git commit -m "chore: baseline before Laravel 11 upgrade"
```

---

## Phase 2: Update Dependencies

### Task 2: Update Composer Dependencies

**Files:**
- Modify: `composer.json`

- [ ] **Step 1: Update laravel/framework to ^11.0**

Run: `composer require laravel/framework:^11.0 --no-interaction`
Expected: Downloads Laravel 11.x, removes old package, shows updated dependencies

- [ ] **Step 2: Update laravel/sanctum to ^4.0**

Run: `composer require laravel/sanctum:^4.0 --no-interaction`
Expected: Sanctum 4.x for Laravel 11 compatibility

- [ ] **Step 3: Update laravel/horizon to ^6.0**

Run: `composer require laravel/horizon:^6.0 --no-interaction`
Expected: Horizon 6.x for Laravel 11 compatibility

- [ ] **Step 4: Update barryvdh/laravel-dompdf to ^4.0**

Run: `composer require barryvdh/laravel-dompdf:^4.0 --no-interaction`
Expected: DomPDF 4.x for Laravel 11 compatibility

- [ ] **Step 5: Update other Laravel packages**

Check each package for Laravel 11 compatible versions. Run individual updates as needed:

```bash
composer require laravel/tinker:^2.9 --no-interaction
composer require spatie/laravel-backup:^9.0 --no-interaction
composer require laravel/pint:^1.0 --no-interaction --dev
composer require laravel/sail:^2.0 --no-interaction --dev
```

- [ ] **Step 6: Run composer update to resolve all dependencies**

Run: `composer update --no-interaction`
Expected: All packages updated to Laravel 11 compatible versions

- [ ] **Step 7: Verify installation**

Run: `php artisan --version`
Expected: Laravel Framework 11.x.x

- [ ] **Step 8: Commit dependency updates**

```bash
git add composer.json composer.lock && git commit -m "chore: update dependencies for Laravel 11"
```

---

## Phase 3: Update Application Bootstrap

### Task 3: Update bootstrap/app.php (Laravel 11 Pattern)

**Files:**
- Modify: `bootstrap/app.php`

Laravel 11 replaces the Kernel binding pattern with a new Application bootstrap that configures middleware directly.

- [ ] **Step 1: Replace bootstrap/app.php with Laravel 11 structure**

Backup and replace the entire file:

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api_v1.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\LogRequests::class,
            \App\Http\Middleware\QueryPerformanceMonitor::class,
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\IpBlocker::class,
            \App\Http\Middleware\StrictRateLimit::class,
            \App\Http\Middleware\DataBreachDetection::class,
        ]);

        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'role.any' => \App\Http\Middleware\CheckRoleAny::class,
            'branch.access' => \App\Http\Middleware\CheckBranchAccess::class,
            'mfa.enabled' => \App\Http\Middleware\EnsureMfaEnabled::class,
            'mfa.verified' => \App\Http\Middleware\EnsureMfaVerified::class,
            'session.timeout' => \App\Http\Middleware\SessionTimeout::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
```

- [ ] **Step 2: Verify bootstrap/app.php is valid**

Run: `php artisan about 2>&1 | head -20`
Expected: No errors, application boots

- [ ] **Step 3: Commit**

```bash
git add bootstrap/app.php && git commit -m "refactor: adopt Laravel 11 bootstrap pattern"
```

---

### Task 4: Remove Legacy Kernel Files

**Files:**
- Delete: `app/Http/Kernel.php` (migrated to bootstrap/app.php)
- Delete: `app/Console/Kernel.php` (Laravel 11 uses defaults)

- [ ] **Step 1: Read Kernel.php to verify middleware registrations**

Check `app/Http/Kernel.php` - record all middleware aliases registered here to migrate to `bootstrap/app.php`

Check `app/Console/Kernel.php` - record any custom commands or schedule configurations

- [ ] **Step 2: Delete app/Http/Kernel.php**

```bash
rm app/Http/Kernel.php
```

- [ ] **Step 3: Delete app/Console/Kernel.php**

```bash
rm app/Console/Kernel.php
```

- [ ] **Step 4: Check if app/Providers contains kernel references**

```bash
grep -r "Kernel" /www/wwwroot/local.host/app/Providers/ 2>/dev/null
```

If found, update those files to remove kernel references.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "refactor: remove legacy Kernel classes (Laravel 11)"
```

---

### Task 5: Update Exception Handler

**Files:**
- Modify: `app/Exceptions/Handler.php`

Laravel 11 exception handler uses a different pattern with `$middlewareAlias` property instead of `$middleware`.

- [ ] **Step 1: Read current Handler.php**

```bash
cat app/Exceptions/Handler.php
```

- [ ] **Step 2: Update Handler.php for Laravel 11**

Replace the content with Laravel 11 compatible structure:

```php
<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Resource not found'], 404);
            }
        });

        $this->renderable(function (ValidationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });
    }
}
```

- [ ] **Step 3: Test exception handler**

Run: `php artisan route:list 2>&1 | head -10`
Expected: Routes listed without errors

- [ ] **Step 4: Commit**

```bash
git add app/Exceptions/Handler.php && git commit -m "refactor: update exception handler for Laravel 11"
```

---

## Phase 4: Routes & Configuration Updates

### Task 6: Update Routes Structure

**Files:**
- Check: `routes/web.php`
- Check: `routes/api_v1.php`

Laravel 11 deprecates `Route::controller()` and some Route facade methods.

- [ ] **Step 1: Verify routes/web.php compatibility**

```bash
grep -E "Route::controller|Route::group.*prefix" routes/web.php | head -10
```

If `Route::controller()` found, note for migration to explicit route definitions.

- [ ] **Step 2: Verify routes/api_v1.php compatibility**

```bash
grep -E "Route::controller|api:" routes/api_v1.php | head -10
```

- [ ] **Step 3: Check route list still works**

```bash
php artisan route:list --compact 2>&1 | head -30
```

Expected: All routes listed without errors

- [ ] **Step 4: Commit if changes needed**

```bash
git add routes/web.php routes/api_v1.php && git commit -m "fix: update routes for Laravel 11 compatibility"
```

---

### Task 7: Update Config Files for Laravel 11

**Files:**
- Check: `config/app.php` (providers array changes)
- Check: `config/sanctum.php`

Laravel 11 removes the `config/app.php` `providers` array and uses auto-discovery via `bootstrap/providers.php`.

- [ ] **Step 1: Create bootstrap/providers.php**

```bash
cat > bootstrap/providers.php << 'EOF'
<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    App\Providers\RouteServiceProvider::class,
];
EOF
```

- [ ] **Step 2: Update config/app.php - remove providers and aliases arrays**

Remove the `providers` and `aliases` arrays from `config/app.php` as they are deprecated in Laravel 11.

- [ ] **Step 3: Update config/sanctum.php for Laravel 11**

Laravel 11 Sanctum configuration uses `stateful` instead of `frontend` for the stateful domains.

```bash
cat config/sanctum.php
```

Verify the config follows Sanctum 4.x pattern.

- [ ] **Step 4: Clear config cache**

```bash
php artisan config:clear && php artisan config:cache 2>&1
```

Expected: No errors

- [ ] **Step 5: Commit**

```bash
git add bootstrap/providers.php config/app.php config/sanctum.php && git commit -m "refactor: Laravel 11 providers and config structure"
```

---

## Phase 5: Database & Service Provider Updates

### Task 8: Create bootstrap/providers.php and Verify Service Providers

**Files:**
- Create: `bootstrap/providers.php`
- Check: `app/Providers/AppServiceProvider.php`
- Check: `app/Providers/RouteServiceProvider.php`

- [ ] **Step 1: Verify AppServiceProvider compatibility**

Check for any `$register` or `$boot` overrides that might need updating.

- [ ] **Step 2: Verify RouteServiceProvider compatibility**

Laravel 11 RouteServiceProvider may need `$middleware` property updated.

- [ ] **Step 3: Clear application cache**

```bash
php artisan about 2>&1 | grep -E "PHP|Laravel|Arch"
```

Expected: Application info displays correctly

- [ ] **Step 4: Commit if providers were updated**

```bash
git add app/Providers/ bootstrap/providers.php && git commit -m "refactor: update service providers for Laravel 11"
```

---

## Phase 6: Test & Verify

### Task 9: Run Full Test Suite

**Files:**
- Run: All tests

- [ ] **Step 1: Clear all caches**

```bash
php artisan config:clear && php artisan route:clear && php artisan cache:clear 2>&1
```

- [ ] **Step 2: Run full test suite**

```bash
php artisan test --without-tty 2>&1
```

Expected: All tests pass. Note any failures and fix individually.

- [ ] **Step 3: Run specific high-priority tests**

Test key workflows:
```bash
php artisan test --filter=TransactionWorkflowTest
php artisan test --filter=AccountingWorkflowTest
php artisan test --filter=MathServiceTest
```

- [ ] **Step 4: Verify routes are registered**

```bash
php artisan route:list --compact 2>&1 | wc -l
```

Count should match pre-upgrade count.

- [ ] **Step 5: Commit passing tests**

```bash
git add -A && git commit -m "test: Laravel 11 upgrade - all tests passing"
```

---

### Task 10: Final Verification

- [ ] **Step 1: Verify artisan commands work**

```bash
php artisan list 2>&1 | head -30
```

Expected: Full command list without errors

- [ ] **Step 2: Verify Horizon (if installed) works**

```bash
php artisan horizon:status 2>&1
```

Expected: Horizon status response

- [ ] **Step 3: Check logs for errors**

```bash
tail -20 storage/logs/laravel.log 2>/dev/null | grep -i error
```

Expected: No errors

- [ ] **Step 4: Run Pint for code style**

```bash
./vendor/bin/pint --test 2>&1 | tail -10
```

Expected: No style violations

- [ ] **Step 5: Final commit**

```bash
git add -A && git commit -m "chore: complete Laravel 11 upgrade"
```

---

## Rollback Plan

If upgrade fails catastrophically:

```bash
git revert HEAD
git checkout HEAD~1 composer.json composer.lock
composer install --no-interaction
```

This restores the pre-upgrade state.

---

## Plan Complete

Saved to: `docs/superpowers/plans/2026-05-03-laravel-upgrade.md`

**Two execution options:**

**1. Subagent-Driven (recommended)** - I dispatch a fresh subagent per task, review between tasks, fast iteration

**2. Inline Execution** - Execute tasks in this session using executing-plans, batch execution with checkpoints

**Which approach?**
