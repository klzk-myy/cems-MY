# Code Quality Fixes — Implementation Plan

## Overview

Fix all remaining code quality issues across the CEMS-MY codebase. Issues identified by comprehensive audit of Controllers, Middleware, Jobs, Services, and Traits.

---

## Task 1: Fix StockCashController Missing Dependency (CRITICAL)

**File:** `app/Http/Controllers/StockCashController.php`

**Problem:** `openTill()` method calls `$this->auditService->log()` (line 135) but `AuditService` is not injected in the constructor (only `MathService`, `CurrencyPositionService`, `TillService`). This will throw a runtime error.

**Fix:** Add `AuditService` to the constructor via property promotion.

**Tests:** `php artisan test --compact --filter=StockCash`

---

## Task 2: Replace abort() with JSON Responses in API Controllers

**Problem:** Several API controllers use `abort()` which returns HTML error pages instead of JSON responses, breaking API clients.

**Files:**
- `app/Http/Controllers/Api/V1/BranchClosingController.php` — lines 26, 53, 83, 116 (4× `abort(403)`)
- `app/Http/Controllers/Api/V1/Compliance/RiskController.php` — line 135 (1× `abort(404)`)

**Fix:** Replace each `abort(403, 'message')` with `return response()->json(['success' => false, 'message' => '...'], 403)` and `abort(404)` with 404 JSON response.

**Tests:** `php artisan test --compact --filter=BranchClosing`

---

## Task 3: Remove Duplicate CheckRoleAny Middleware

**Problem:** `CheckRole.php` and `CheckRoleAny.php` are functionally identical — both implement OR semantics (user needs ANY of the specified roles). `CheckRoleAny`'s own docblock says "Both CheckRole and CheckRoleAny implement OR semantics."

**Fix:**
1. Verify which middleware is referenced in route definitions
2. If `CheckRoleAny` is used, replace all references with `CheckRole` and delete `CheckRoleAny`
3. If both are used, consolidate into `CheckRole` and delete `CheckRoleAny`

**Tests:** `php artisan test --compact --filter="Role|Middleware|Auth"`

---

## Task 4: Add Missing Return Types to Middleware

**Files:**
- `app/Http/Middleware/CheckRole.php:17` — `handle()` missing `Response` return type
- `app/Http/Middleware/CheckRoleAny.php:23` — `handle()` missing `Response` return type (if not deleted in Task 3)
- `app/Http/Middleware/PerformanceTrackingMiddleware.php:16` — `handle()` missing `Response` return type

**Fix:** Add `: Response` return type to each `handle()` method. Import `Symfony\Component\HttpFoundation\Response` if not already imported.

**Tests:** `php artisan test --compact`

---

## Task 5: Add Missing Return Types to DashboardController Helpers

**File:** `app/Http/Controllers/DashboardController.php`

**Methods missing `: void` return type:**
- `ensureComplianceOfficerAccess()` (line 265)
- `ensureManagerAccess()` (line 275)
- `ensureCanViewReports()` (line 287)

**Fix:** Add `: void` return type to each method.

**Tests:** `php artisan test --compact --filter=Dashboard`

---

## Task 6: Fix EnsureMfaVerified Middleware — Constructor Injection

**File:** `app/Http/Middleware/EnsureMfaVerified.php`

**Problem:** Line 26 uses `app(MfaService::class)` service locator instead of constructor injection.

**Fix:** Inject `MfaService` via constructor property promotion. Remove the `app()` call.

**Tests:** `php artisan test --compact --filter=Mfa`

---

## Task 7: Remove Empty Try/Catch Blocks in Jobs

**Files:**
- `app/Jobs/ComplianceScreeningJob.php:31-35` — `try { ... } catch (\Exception $e) { throw $e; }`
- `app/Jobs/SendNotificationJob.php:110-112` — `catch (\Exception $e) { throw $e; }`

**Problem:** Catching an exception and immediately rethrowing it serves no purpose — it's dead code that adds noise.

**Fix:** Remove the empty try/catch wrappers, keeping the enclosed code.

**Tests:** `php artisan test --compact --filter="ComplianceScreening|SendNotification"`

---

## Task 8: Extract Inline Validation into Form Requests

**Files with inline `$request->validate()`:**
- `app/Http/Controllers/Api/V1/RateController.php:83-85` — `copyPrevious()` method
- `app/Http/Controllers/Transaction/TransactionApprovalController.php:203-206` — `confirm()` method

**Fix:** Create Form Request classes for each, extract validation rules, update controller methods.

**Tests:** `php artisan test --compact --filter="Rate|TransactionApproval"`

---

## Task 9: Extract Duplicate Cancellation Logic into Trait

**Problem:** `canBeCancelled()`, `canRequestCancellation()`, `canApproveCancellation()` are duplicated character-for-character between:
- `app/Http/Controllers/Transaction/TransactionCancellationController.php`
- `app/Http/Controllers/Api/V1/TransactionCancellationController.php`

**Fix:** Create `app/Http/Concerns/CancellableTransaction.php` trait with shared methods. Both controllers use the trait.

**Tests:** `php artisan test --compact --filter=Cancellation`

---

## Task 10: Extract Duplicate Branch Auth Check into Trait

**Problem:** Branch authorization check (`$user->role !== UserRole::Admin && (int) $branchId !== $user->branch_id`) is repeated 4 times in `Api/V1/BranchClosingController.php` (lines 25-27, 52-54, 82-84, 115-117).

**Fix:** Create `app/Http/Concerns/BranchScoped.php` trait with `authorizeBranchAccess(int $branchId): void` method. Apply to BranchClosingController API.

**Tests:** `php artisan test --compact --filter=BranchClosing`

---

## Task 11: Extract Duplicate Compliance Finding Logic into Trait

**Problem:** Query filtering (status, severity, type, date range) and stats aggregation (5 identical queries) are duplicated between:
- `app/Http/Controllers/Compliance/FindingController.php`
- `app/Http/Controllers/Api/V1/Compliance/FindingController.php`

**Fix:** Create `app/Http/Concerns/FiltersComplianceFindings.php` trait with shared query builder and stats methods. Both controllers use the trait.

**Tests:** `php artisan test --compact --filter=Finding`

---

## Task 12: Extract Duplicate Sanction Normalization into Trait

**Problem:** `normalizeEntityName()` logic (soundex, metaphone, strtolower, preg_replace) is duplicated in both:
- `app/Http/Controllers/Compliance/SanctionListController.php`
- `app/Http/Controllers/Api/V1/SanctionListController.php`

**Fix:** Create `app/Http/Concerns/SanctionEntryNormalizer.php` trait with the normalization method. Both controllers use the trait.

**Tests:** `php artisan test --compact --filter=Sanction`

---

## Task 13: Fix TestQueryLogController — Extend Base Controller

**File:** `app/Http/Controllers/TestQueryLogController.php`

**Problem:** Does not extend `Controller` base class. Missing `AuthorizesRequests` and `ValidatesRequests` traits.

**Fix:** Add `extends Controller` and ensure proper structure.

**Tests:** `php artisan test --compact --filter=TestQueryLog`

---

## Task 14: Fix CounterApiController Visibility

**File:** `app/Http/Controllers/Api/V1/CounterApiController.php:15`

**Problem:** Uses `private CounterService $counterService` — all other controllers use `protected`.

**Fix:** Change `private` to `protected` for consistency.

**Tests:** `php artisan test --compact --filter=Counter`

---

## Execution Order

| Batch | Tasks | Rationale |
|-------|-------|-----------|
| **Batch 1: Critical Bug Fixes** | 1, 2 | Runtime errors and broken API responses |
| **Batch 2: Middleware Cleanup** | 3, 4, 6 | Remove duplicates, add types, fix DI |
| **Batch 3: Controller Type Safety** | 5, 13, 14 | Add missing return types, fix base class |
| **Batch 4: Dead Code Removal** | 7, 8 | Remove empty try/catch, extract inline validation |
| **Batch 5: Trait Extraction** | 9, 10, 11, 12 | Deduplicate shared logic across Web/API controllers |

## Verification

After each task: `vendor/bin/pint --dirty --format agent && php artisan test --compact --filter=<affected>`

Final: `php artisan test --compact` to verify no regressions.
