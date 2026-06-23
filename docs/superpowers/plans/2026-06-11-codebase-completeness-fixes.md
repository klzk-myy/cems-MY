# Codebase Completeness Fixes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix all incomplete and broken areas identified in the 2026-06-11 codebase analysis: critical eager-loading recursion, broken `/compliance` route, skipped tests, orphaned views, and the open 2026-05-19 code-consolidation plan.

**Architecture:** Apply minimal, test-driven fixes first (bugs and tests), then clean up orphaned views, then execute the existing consolidation plan incrementally with frequent commits and test runs.

**Tech Stack:** PHP 8.3, Laravel 10, PHPUnit 10, GitNexus, Laravel Pint

---

## Phase 1: Critical Bug Fixes

### Task 1.1: Break `Customer` ↔ `CustomerDocument` Eager-Loading Cycle

**Files:**
- Modify: `app/Models/CustomerDocument.php:36`
- Test: `tests/Unit/EddServiceTest.php`

**Problem:** `Customer` auto-loads `documents`, and `CustomerDocument` auto-loads `customer`, causing infinite recursion and memory exhaustion.

- [ ] **Step 1: Run the failing test to confirm the bug**

Run: `php -d memory_limit=1G artisan test --compact --filter=test_edd_record_complete_requires_all_documents tests/Unit/EddServiceTest.php`

Expected: `Fatal error: Allowed memory size exhausted`

- [ ] **Step 2: Remove the recursive eager load from `CustomerDocument`**

Change `app/Models/CustomerDocument.php:36` from:

```php
protected $with = ['customer', 'uploader', 'verifier'];
```

to:

```php
protected $with = ['uploader', 'verifier'];
```

Rationale: `Customer` is the more central model loaded in many contexts; removing `customer` from the document side prevents the cycle while preserving document metadata eager loads.

- [ ] **Step 3: Run the failing test again**

Run: `php -d memory_limit=1G artisan test --compact --filter=test_edd_record_complete_requires_all_documents tests/Unit/EddServiceTest.php`

Expected: PASS

- [ ] **Step 4: Run all `EddServiceTest` tests**

Run: `php -d memory_limit=1G artisan test --compact tests/Unit/EddServiceTest.php`

Expected: 3 passed

- [ ] **Step 5: Run the full unit test suite**

Run: `php -d memory_limit=2G artisan test --compact tests/Unit`

Expected: Suite completes without memory exhaustion.

- [ ] **Step 6: Commit**

```bash
git add app/Models/CustomerDocument.php
git commit -m "fix(models): break Customer <-> CustomerDocument eager-loading cycle"
```

---

### Task 1.2: Fix Broken `/compliance` Route

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php:144`
- Test: `tests/Feature/AuthenticationTest.php`

**Problem:** `DashboardController::compliance()` returns `pages.compliance.index`, but that view was deleted in P0 cleanup.

- [ ] **Step 1: Run the failing tests to confirm**

Run: `php artisan test --compact tests/Feature/AuthenticationTest.php`

Expected: 2 failures — `View [pages.compliance.index] not found.`

- [ ] **Step 2: Identify the correct replacement view**

Check available compliance views and route conventions. The existing compliance UI uses:
- `compliance.flagged` route → `DashboardController::compliance()`
- `compliance/alerts` → `Compliance\AlertTriageController`
- `compliance/cases` → `Compliance\CaseManagementController`
- `compliance/findings` → `Compliance\FindingController`

The simplest fix is to redirect the `/compliance` landing page to the existing flagged-transactions list, which is the natural compliance dashboard entry point.

- [ ] **Step 3: Update `DashboardController::compliance()`**

Change `app/Http/Controllers/DashboardController.php:144` from:

```php
return view('pages.compliance.index', compact('flags', 'stats'));
```

to:

```php
return redirect()->route('compliance.flagged');
```

Also remove the now-unused `$flags` and `$stats` query/build code if it becomes dead, or keep it if `compliance.flagged` uses the same method. Since `compliance.flagged` maps to the same `compliance()` method, replace the method body with the redirect.

- [ ] **Step 4: Run the previously failing tests**

Run: `php artisan test --compact tests/Feature/AuthenticationTest.php`

Expected: 25 passed, 0 failed

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/DashboardController.php
git commit -m "fix(routes): redirect /compliance to existing flagged route after view deletion"
```

---

## Phase 2: Stabilize Skipped Tests

### Task 2.1: Fix `LedgerServiceTest` Skips

**Files:**
- Modify: `tests/Unit/LedgerServiceTest.php`
- Modify: `app/Services/LedgerService.php` if dependency wiring is missing

**Problem:** All 8 test methods are skipped with "Requires full service setup with DI - integration test".

- [ ] **Step 1: Read `LedgerService` constructor and `LedgerServiceTest`**

Run:
```bash
head -80 app/Services/LedgerService.php
cat tests/Unit/LedgerServiceTest.php
```

- [ ] **Step 2: Resolve the service via the container or instantiate with dependencies**

If `LedgerService` has constructor dependencies, instantiate via `app(LedgerService::class)` or `resolve()` in `setUp()`.

- [ ] **Step 3: Remove skips and run the tests**

Run: `php artisan test --compact tests/Unit/LedgerServiceTest.php`

Expected: All tests pass or produce real failures to fix.

- [ ] **Step 4: Fix any real failures**

Address failures iteratively; add missing factories/seeds if needed.

- [ ] **Step 5: Commit**

```bash
git add tests/Unit/LedgerServiceTest.php app/Services/LedgerService.php
git commit -m "test: re-enable LedgerServiceTest with container-resolved service"
```

---

### Task 2.2: Fix `BudgetServiceTest` Seed Dependencies

**Files:**
- Modify: `tests/Unit/BudgetServiceTest.php`

**Problem:** 8 tests skip when chart-of-accounts seed data is missing.

- [ ] **Step 1: Read the test file and identify required accounts**

Run: `cat tests/Unit/BudgetServiceTest.php`

- [ ] **Step 2: Add the enhanced chart-of-accounts seeder to setUp or a seeding trait**

In `setUp()`, after `parent::setUp()`:

```php
$this->seed(\Database\Seeders\EnhancedChartOfAccountsSeeder::class);
```

- [ ] **Step 3: Run the tests**

Run: `php artisan test --compact tests/Unit/BudgetServiceTest.php`

Expected: All tests pass.

- [ ] **Step 4: Commit**

```bash
git add tests/Unit/BudgetServiceTest.php
git commit -m "test: seed chart of accounts in BudgetServiceTest to avoid skips"
```

---

### Task 2.3: Handle `LoadTestTest` k6 Dependency

**Files:**
- Modify: `tests/Feature/LoadTestTest.php`

**Problem:** Test is skipped when k6 is not installed.

- [ ] **Step 1: Decide if k6 should be installed or test should be moved**

If load tests are part of CI, ensure k6 is available. If not, consider moving the test to a separate `tests/Load/` directory so it does not count as a skipped PHPUnit test.

- [ ] **Step 2: Apply the chosen approach**

Option A — keep skip logic but move to non-blocking location:
```bash
mkdir -p tests/Load
mv tests/Feature/LoadTestTest.php tests/Load/LoadTestTest.php
```

Update namespace to `Tests\Load`.

Option B — install k6 if environment supports it.

- [ ] **Step 3: Verify PHPUnit no longer reports the skip**

Run: `php artisan test --compact tests/Feature`

Expected: 0 skipped in feature suite.

- [ ] **Step 4: Commit**

```bash
git add tests/Load/LoadTestTest.php tests/Feature/LoadTestTest.php
git commit -m "chore(tests): move load test out of feature suite to avoid k6 skip noise"
```

---

## Phase 3: Orphaned Views Cleanup

### Task 3.1: Review and Remove Orphaned Views

**Files:**
- Delete: confirmed unused views under `resources/views/`
- Test: `php scripts/find-orphaned-views.php`

**Problem:** 11 non-component Blade views are flagged as unused.

- [ ] **Step 1: Verify each view has zero references**

For each of the 11 views, run:
```bash
grep -R "view_name" app/ resources/views/ routes/ --include="*.php"
```

Confirm no matches (excluding the file itself).

- [ ] **Step 2: Delete confirmed unused views**

List:
- `resources/views/customers/kyc.blade.php`
- `resources/views/accounting/month-end.blade.php`
- `resources/views/compliance/edd-templates/index.blade.php`
- `resources/views/compliance/edd-templates/show.blade.php`
- `resources/views/compliance/reporting/schedule.blade.php`
- `resources/views/auth/change-password.blade.php`
- `resources/views/transactions/customer-history.blade.php`
- `resources/views/pages/performance.blade.php`
- `resources/views/pages/audit/index.blade.php`
- `resources/views/pages/branches/index.blade.php`
- `resources/views/pages/rates/index.blade.php`

Note: `auth.change-password` may be rendered via a route and `customers.kyc` may be dynamically routed — verify before deleting.

- [ ] **Step 3: Re-run the orphaned-views scanner**

Run: `php scripts/find-orphaned-views.php`

Expected: Only the 9 known component false positives remain.

- [ ] **Step 4: Run tests**

Run: `php artisan test --compact`

Expected: All tests still pass.

- [ ] **Step 5: Commit**

```bash
git rm <confirmed-unused-views>
git commit -m "chore(views): remove verified orphaned Blade views"
```

---

## Phase 4: Resume 2026-05-19 Code Consolidation Plan

The existing plan `docs/superpowers/plans/2026-05-19-code-consolidation.md` defines six phases. Execute them in the recommended order.

### Task 4.1: Logging Consolidation

**Files:**
- Modify: `app/Services/AuditService.php`
- Modify: `app/Services/ComprehensiveLogService.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Create: `tests/Unit/Services/LoggingConsolidationTest.php`

- [ ] **Step 1: Inventory methods in both services**
- [ ] **Step 2: Move unique methods from `ComprehensiveLogService` to `AuditService`**
- [ ] **Step 3: Add `#[Deprecated]` to `ComprehensiveLogService`**
- [ ] **Step 4: Register alias in `AppServiceProvider`**
- [ ] **Step 5: Replace consumers or rely on alias**
- [ ] **Step 6: Write `LoggingConsolidationTest`**
- [ ] **Step 7: Run tests and commit**

---

### Task 4.2: Customer Retrieval Unification

**Files:**
- Create: `app/Repositories/CustomerRepository.php`
- Modify: `app/Services/CustomerService.php`
- Create: `tests/Unit/Repositories/CustomerRepositoryTest.php`

- [ ] **Step 1: Create `CustomerRepository` with retrieval methods**
- [ ] **Step 2: Update `CustomerService` to delegate**
- [ ] **Step 3: Replace scattered `Customer::find()` calls in services**
- [ ] **Step 4: Write repository tests**
- [ ] **Step 5: Run tests and commit**

---

### Task 4.3: Form Request Boilerplate Reduction

**Files:**
- Create: `app/Http/Requests/AuthorizedFormRequest.php`
- Modify: 40+ form request classes

- [ ] **Step 1: Create base `AuthorizedFormRequest`**
- [ ] **Step 2: Migrate form requests in batches of 5–10**
- [ ] **Step 3: Run request-specific tests after each batch**
- [ ] **Step 4: Commit**

---

### Task 4.4: Split `TransactionCancellationService`

**Files:**
- Modify: `app/Services/TransactionCancellationService.php`
- Create: `app/Services/TransactionReversalService.php`
- Create: `app/Services/StockReleaseService.php`
- Create: `tests/Unit/Services/TransactionReversalServiceTest.php`

- [ ] **Step 1: Identify natural split points**
- [ ] **Step 2: Create `TransactionReversalService`**
- [ ] **Step 3: Create `StockReleaseService`**
- [ ] **Step 4: Update `TransactionCancellationService` to compose new services**
- [ ] **Step 5: Write tests for extracted services**
- [ ] **Step 6: Run cancellation/reversal tests and commit**

---

### Task 4.5: Validation Centralization

**Files:**
- Create: `app/Http/Traits/ValidatorMethods.php`
- Modify: `app/Services/SanctionsDownloadService.php`
- Modify: `app/Services/TransactionService.php`

- [ ] **Step 1: Inventory validation methods**
- [ ] **Step 2: Create `ValidatorMethods` trait**
- [ ] **Step 3: Apply trait and remove duplicated methods**
- [ ] **Step 4: Run tests and commit**

---

### Task 4.6: Sanctions Workflow Consolidation

**Files:**
- Create: `app/Services/SanctionsOrchestrationService.php`
- Modify: `app/Services/SanctionsDownloadService.php`
- Modify: `app/Services/SanctionsImportService.php`
- Modify: `app/Http/Controllers/Compliance/SanctionListController.php`

- [ ] **Step 1: Map current sanctions workflow**
- [ ] **Step 2: Create `SanctionsOrchestrationService`**
- [ ] **Step 3: Update controller to use orchestration service**
- [ ] **Step 4: Write tests**
- [ ] **Step 5: Run tests and commit**

---

## Final Verification

- [ ] **Run full test suite:** `php artisan test --compact`
- [ ] **Run Laravel Pint:** `vendor/bin/pint --dirty --format agent`
- [ ] **Run orphaned scanners:** `php scripts/find-orphaned-views.php`, `php scripts/find-orphaned-db.php`, `bash scripts/find-orphaned-assets.sh`
- [ ] **Run Phase 1 verification script:** `php scripts/verify-phase1.php`
- [ ] **Git status review:** ensure only intended changes are staged

---

## Execution Notes

- Start with Phase 1; it unblocks the rest of the test suite.
- Each task should be committed independently.
- If a consolidation task proves too large or risky, pause and report before continuing.
