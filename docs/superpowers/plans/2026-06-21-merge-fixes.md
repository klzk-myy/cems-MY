# Git Merge Issues: Implementation Plan

**Created:** 2026-06-21  
**Goal:** Resolve all issues introduced or uncovered by the `git merge origin/main` operation.  
**Current State:** Merge completed (commit 8f5437cf), 2 tests failing in `tests/Feature/Views/DummyDataReplacementTest.php`.

---

## Issues Identified

### 1. DomainException Duplicate Method (FIXED ✓)

- **Symptom:** Fatal error "Cannot redeclare App\Exceptions\Domain\DomainException::getStatusCode()"
- **Root Cause:** Duplicate method definition lines 9-12 and 24-27 in `app/Exceptions/Domain/DomainException.php`
- **Fix Applied:** Removed lines 24-27 (duplicate)

### 2. Merge Conflicts (RESOLVED ✓)

- **Symptom:** Multiple content conflicts across 40+ files
- **Resolution:** Used `git merge -X theirs origin/main` to auto-resolve all conflicts favoring remote/main branch content
- **Result:** 257 files changed, 10561 insertions(+), 2712 deletions(-)

### 3. Test Failures (PENDING 🔧)

- **Failing Tests:**
  - `Tests\Feature\Views\DummyDataReplacementTest::fiscal_years_does_not_show_hardcoded_dates`
  - `Tests\Feature\Views\DummyDataReplacementTest::fiscal_years_renders_real_model_data`
- **Error:** `Target class [App\Http\Controllers\AccountingService] does not exist.`
- **Access Route:** `GET /accounting/fiscal-years` (route name `accounting.fiscal-years`)

---

## Root Cause Analysis (Test Failures)

The route `accounting.fiscal-years` maps to `AccountingController@fiscalYears`. The controller's constructor requires:

```php
public function __construct(
    protected PeriodCloseService $periodCloseService,
) {}
```

`PeriodCloseService` (in `app/Services/Accounting/PeriodCloseService.php`) requires:

```php
public function __construct(
    AccountingService $accountingService,
    MathService $mathService,
    AuditService $auditService,
) { ... }
```

All services exist:
- `app/Services/Accounting/AccountingService.php` (namespace `App\Services\Accounting\AccountingService`)
- `app/Services/System/MathService.php`
- `app/Services/AuditService.php`

**Mystery:** Container attempts to resolve `App\Http\Controllers\AccountingService` instead of `App\Services\Accounting\AccountingService`. This indicates a namespace resolution problem where an unqualified `AccountingService` type-hint in a file under `App\Http\Controllers` namespace is being resolved relative to that namespace.

**Plausible Causes:**
1. A controller in `App\Http\Controllers` has a constructor or method parameter typed as `AccountingService` without a corresponding `use App\Services\Accounting\AccountingService;`.
2. A service provider, middleware, or view composer registered under the `App\Http\Controllers` namespace has such an un-imported type-hint.
3. Stale caches (route, config, or Composer autoload) causing class map inconsistencies.
4. The `PeriodCloseService` file itself may have an incorrect `namespace` declaration (unlikely after verification).

---

## Implementation Steps

### Step 1: Clear All Caches & Regenerate Autoloader

Run these commands in the project root:

```bash
composer dump-autoload
php artisan cache:clear
php artisan route:clear
php artisan config:clear
php artisan view:clear
```

This eliminates stale class maps and container bindings.

### Step 2: Verify Service Class Namespaces

Confirm the following files have correct namespace declarations:

- `app/Services/Accounting/AccountingService.php` → `namespace App\Services\Accounting;`
- `app/Services/Accounting/PeriodCloseService.php` → `namespace App\Services\Accounting;`

If any discrepancy, correct immediately.

### Step 3: Search for Missing Imports in Controllers

Search for any occurrence of `AccountingService` in `app/Http/Controllers` that lacks a proper `use` statement. Specifically look for:

```php
// WRONG (in a file with namespace App\Http\Controllers):
public function __construct(AccountingService $service) { ... } // no use statement
```

Expected fix: Add `use App\Services\Accounting\AccountingService;` at the top.

Perform the search:

```bash
grep -r "AccountingService" app/Http/Controllers | grep -v "use App"
```

Manual review of any matches required.

### Step 4: Add Explicit Binding (Fallback)

If auto-wiring still fails after steps 1–3, add an explicit binding in `app/Providers/AppServiceProvider.php`:

```php
public function register(): void
{
    // Bind concrete class explicitly to avoid resolution issues
    $this->app->bind(
        \App\Services\Accounting\AccountingService::class,
        \App\Services\Accounting\AccountingService::class
    );

    // Or bind interface if type-hinted via interface
    $this->app->bind(
        \App\Services\Contracts\AccountingServiceInterface::class,
        \App\Services\Accounting\AccountingService::class
    );
}
```

### Step 5: Re-run Tests

```bash
php artisan test --compact --filter=DummyDataReplacementTest
```

Expected: Both tests pass (200 status, correct content).

### Step 6: Full Test Suite

```bash
php artisan test --compact
```

Ensure no regressions.

### Step 7: Verify Controller State Consistency

Check that `app/Http/Controllers/AccountingController.php` matches the intended post-refactor stub (only `periods` and `closePeriod` and fiscal years/revaluation methods). If accidental changes exist, reconcile with the merge commit:

```bash
git diff 8f5437cf app/Http/Controllers/AccountingController.php
```

If differences are not intentional, restore the correct version:

```bash
git checkout 8f5437cf -- app/Http/Controllers/AccountingController.php
```

Then re-run tests.

---

## Rollback Plan

If the binding issue cannot be resolved quickly:

1. Temporarily comment out the `PeriodCloseService` dependency in `AccountingController` and stub the `fiscalYears` method to return an empty view. This unblocks the route while debugging continues.
2. Or revert the merge, fix the DomainException duplicate on a clean branch, then re-merge with more careful conflict resolution (use `git mergetool` instead of `-X theirs`).

---

## Success Criteria

- ✅ No container binding resolution errors for `AccountingService`.
- ✅ `accounting.fiscal-years` route returns HTTP 200 and renders the Fiscal Years view.
- ✅ All 1035+ tests pass.
- ✅ Application functions normally in browser.

---

## Notes

- The merge itself (commit 8f5437cf) appears sound; the test failure is likely a side effect of stale caches or a subtle import issue introduced during conflict resolution.
- The `DomainException` duplicate was a pre-existing bug on the remote branch; it is now fixed.
- The `AccountingController` simplification (split into focused controllers) is intentional and should be preserved.
