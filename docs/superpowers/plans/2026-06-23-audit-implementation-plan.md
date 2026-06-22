# Implementation Plan: CEMS-MY Codebase Audit Fixes

**Date:** 2026-06-23
**Source:** Comprehensive audit via 4 parallel subagents (Models, Services, Controllers, Tests)
**Total Issues Identified:** 100+ across 4 layers
**Test Baseline:** 1035 tests passing

---

## Prioritization Framework

Issues are prioritized by **Severity** (Critical, High, Medium, Low) and **Blast Radius**:

- **Critical**: System crashes, security vulnerabilities, data loss risks
- **High**: Functional bugs, compliance violations, race conditions
- **Medium**: Code quality, maintainability, test gaps
- **Low**: Inconsistencies, missing types, minor bugs

---

## Phase 1: Critical Runtime Bugs (Fix Immediately — 1-2 days)

These cause immediate crashes or security exposure. Must be fixed before any deployment.

### 1.1 CaseManagementController undefined variable bug (CRITICAL)
**File:** `app/Http/Controllers/Compliance/CaseManagementController.php:52,70,74`
**Issue:** `$validated` used without assignment from `$request->validated()`
**Blast Radius:** All case creation and updates crash with `Undefined variable`
**Fix:**
```php
public function store(CreateCaseFromAlertsRequest $request): RedirectResponse
{
    $validated = $request->validated(); // ADD THIS LINE
    $case = $this->caseManagementService->createFromAlerts(
        $validated['alert_ids'],
        auth()->id()
    );
    // ...
}

public function update(UpdateCaseStatusRequest $request, ComplianceCase $case): RedirectResponse
{
    $validated = $request->validated(); // ADD THIS LINE
    // ...
}
```
**Test:** Verify store/update operations with valid data.

---

### 1.2 SetupController missing auth on resetSetup (CRITICAL)
**File:** `app/Http/Controllers/SetupController.php:195`
**Issue:** Any unauthenticated visitor can trigger `migrate:fresh` in non-production environments
**Blast Radius:** Complete database destruction in dev/staging
**Fix:**
```php
public function resetSetup(Application $app): JsonResponse
{
    if ($app->environment('production')) {
        return response()->json([...], 403);
    }

    // ADD AUTH CHECK
    if (!auth()->check() || !auth()->user()->hasRole(UserRole::Admin)) {
        abort(403, 'Unauthorized');
    }

    // ...
}
```
**Test:** Assert non-admin cannot access resetSetup endpoint.

---

### 1.3 Job signature mismatch: screenCustomer (CRITICAL)
**Files:** 
- `app/Jobs/ComplianceScreeningJob.php:31`
- `app/Jobs/RescreenHighRiskCustomersJob.php:37`

**Issue:** Both jobs call `$service->screenCustomer($customer, 'reason')` but method signature is `screenCustomer(Customer $customer)` (1 param)
**Blast Radius:** Jobs fail with `Too many arguments` error
**Fix:** Update `CustomerScreeningService::screenCustomer()` to accept optional `$reason` parameter OR adjust job calls to use logging context instead:

```php
// Option A: Extend service method signature
public function screenCustomer(Customer $customer, ?string $reason = null): void
{
    // use $reason for audit logging
}

// Option B: Remove second arg from job calls and rely on job's own logging
$service->screenCustomer($customer); // Remove second argument
```
**Test:** Run job tests; ensure no argument error.

---

### 1.4 CustomerController missing Cache import (CRITICAL)
**File:** `app/Http/Controllers/CustomerController.php:63`
**Issue:** Uses `Cache::remember()` but `Cache` facade not imported
**Blast Radius:** `Class 'App\Http\Controllers\Cache' not found` error
**Fix:**
```php
use Illuminate\Support\Facades\Cache; // ADD at top
```
**Test:** Visit quick-create endpoint; verify caching works.

---

### 1.5 TransactionImport missing BelongsTo import (CRITICAL)
**File:** `app/Models/TransactionImport.php:44`
**Issue:** `BelongsTo` return type not imported
**Blast Radius:** Fatal error on any code accessing `user()` relationship
**Fix:**
```php
use Illuminate\Database\Eloquent\Relations\BelongsTo; // ADD
```
**Test:** Load TransactionImport model and access user relationship.

---

## Phase 2: Security & Data Integrity (2-3 days)

### 2.1 Policy authorization bypass (HIGH → CRITICAL impact)
**Files:** 
- `app/Policies/TransactionPolicy.php:14-25`
- `app/Policies/CustomerPolicy.php:14-25`

**Issue:** `viewAny()` and `view()` return `true` unconditionally, violating branch isolation
**Risk:** Any authenticated user can view all transactions/customers across all branches
**Fix:** Implement branch scoping:

```php
// TransactionPolicy
public function viewAny(User $user): bool
{
    return $user->hasRole(UserRole::Admin, UserRole::Compliance);
}

public function view(User $user, Transaction $transaction): bool
{
    if ($user->hasRole(UserRole::Admin, UserRole::Compliance, UserRole::Manager)) {
        return true;
    }
    return $transaction->branch_id === $user->branch_id;
}

// CustomerPolicy (similar pattern)
```
**Test:** Extend `AuthorizationTest` to cover these policies properly.

---

### 2.2 Transaction confirmation race conditions (HIGH → CRITICAL)
**Files:**
- `app/Jobs/Audit/SealAuditHashJob.php:30-55` — deadlock risk
- `app/Services/Transaction/TransactionReversalService.php:202-253` — dual lock ordering
- `app/Services/Accounting/CurrencyPositionService.php:387-409` — cache-transaction race

**Issue:** Multiple lock acquisitions in inconsistent order create deadlocks or stale cache data
**Fix:**
- **SealAuditHashJob:** Reorder locks to always acquire in ascending ID order
- **TransactionReversalService:** Always lock foreign currency then MYR, or vice versa consistently
- **CurrencyPositionService:** Use `Cache::lock()` or atomic DB operations instead of cache+lock-for-update pattern

**Test:** Simulate concurrent job execution; verify no deadlocks.

---

### 2.3 Missing transaction wrappers (HIGH)
**Files:**
- `app/Services/CustomerScreeningService.php:162-183` (`handleConfirmedMatch`)
- `app/Listeners/TriggerSanctionsRescreening.php:176-208` (`placePendingTransactionsForComplianceReview`)
- `app/Services/Compliance/CaseManagementService.php:263-272` (`updateStatus`)

**Issue:** Multiple non-atomic operations; partial failure leaves inconsistent state
**Fix:** Wrap each in `DB::transaction()`:

```php
DB::transaction(function () use ($customer) {
    $customer->freeze();
    $this->blockCustomerTransactions($customer);
    $this->rejectCustomer($customer);
    $this->reportToBnmFiu($customer);
});
```
**Test:** Force an exception mid-flow; verify rollback.

---

### 2.4 UnifiedAlertController memory exhaustion (CRITICAL)
**File:** `app/Http/Controllers/Compliance/UnifiedAlertController.php:27-59`
**Issue:** `Alert::all()` and `Finding::all()` load entire tables, then `array_slice` paginates
**Risk:** OOM with large datasets
**Fix:** Use query builder pagination:

```php
$alerts = Alert::with([...])->paginate(25)->appends($request->query());
$findings = Finding::with([...])->paginate(25)->appends($request->query());
// Remove manual array_slice pagination
```
**Test:** Seed 1000 alerts; verify paginated response without memory issues.

---

### 2.5 Missing rate limiting (HIGH)
**Endpoints:**
- Web: transaction store, customer create, customer quick-create
- API: SanctionsWebhookController (relies only on token)

**Issue:** No throttling allows brute force or DoS
**Fix:** Add `throttle` middleware:

```php
// routes/web.php
Route::post('/transactions', ...)->middleware(['auth', 'throttle:60,1']);

// webhooks.php already has throttle on health; add to update endpoint if missing
```
**Test:** Use `StrictRateLimitTest` pattern; verify limit enforcement.

---

### 2.6 Business logic in controllers (MEDIUM → HIGH maintenance cost)
**Files:**
- `app/Http/Controllers/Transaction/TransactionApprovalController.php:212-265` (confirmation logic)
- `app/Http/Controllers/SetupController.php:375-409` (accounting journal creation)
- `app/Http/Controllers/CustomerController.php:378-404` (deletion guards)

**Issue:** Complex multi-step operations should be in services
**Fix:** Extract to services:
- `TransactionConfirmationService::confirm()`
- `SetupAccountingService::createInitialJournalEntries()`
- `CustomerService::deleteWithGuards()`

**Test:** Ensure existing controller tests still pass after delegation.

---

## Phase 3: Test Coverage Gaps (3-5 days)

### 3.1 FormRequest validation tests (CRITICAL coverage gap)
**Scope:** All 100+ FormRequest classes in `app/Http/Requests/`
**Current:** Zero tests
**Fix:** Write test per FormRequest covering:
- Valid data passes
- Required fields fail when missing
- Field types (string, int, date) validated
- Enum rules (e.g., `in:Individual,Organization,...`)
- Custom rules (`exists`, `unique`)

**Template:**
```php
public function test_valid_data_passes(): void
{
    $data = [
        'field' => 'value',
        // ...
    ];
    $request = new SomeRequest();
    $validator = Validator::make($data, $request->rules());
    $this->assertTrue($validator->passes());
}
```
**Estimate:** 2-3 minutes per FormRequest = ~4-5 hours total. Prioritize: StoreTransactionRequest, StoreCustomerRequest, all compliance requests.

---

### 3.2 Job execution tests (HIGH coverage gap)
**Missing tests for 16 of 19 jobs:**
- `SealAuditHashJob` (audit integrity — HIGH priority)
- `StructuringMonitorJob`, `VelocityMonitorJob` (compliance)
- `CurrencyFlowJob`, `CustomerLocationAnomalyJob`, `CounterfeitAlertJob`
- `ProcessTransactionRetry` (error recovery)
- `SanctionsRescreeningJob`
- `SendNotificationJob` (notifications)
- `MonthEndCloseJob`, `PeriodCloseJob`
- `BranchClosingJob`

**Fix:** Write feature tests that:
- Dispatch job
- Assert expected side effects (DB changes, events, logs)
- Test `failed()` method logging
- Use `Bus::fake()` for expensive jobs

**Prioritize:** `SealAuditHashJob` (audit tamper-evidence) and `ProcessTransactionRetry` (recovery) first.

---

### 3.3 Missing controller integration tests (HIGH)
**Critical controllers lacking tests:**
- `MfaController` (full setup/verify/recovery flow)
- `BudgetController`, `RevaluationController`
- `StockTransferController` (web)
- `StockCashController`
- `FiscalYearController`
- `JournalController`, `ReconciliationController`
- `RegulatoryReportController`, `AnalyticsController`
- `UserController` (manager/admin actions)
- `DashboardController` (web version)
- `CounterHandoverController` (web)

**Fix:** Write feature tests covering:
- GET routes render successfully
- POST routes with valid data create/update correctly
- Authorization middleware blocks unauthorized roles
- Validation errors return proper responses

**Pattern:** Use `$this->actingAs($user)->get('/path')` with role-specific users.

---

### 3.4 Missing STR generation tests (HIGH — BNM compliance)
**Scope:** Suspicious Transaction Report auto-generation, approval workflow, BNM reporting
**Current:** Config exists but no tests
**Fix:** Test:
- STR auto-generation when transaction triggers threshold
- STR approval by compliance officer
- BNM export format correctness
- Status transitions (draft → approved → submitted)

---

### 3.5 Bank reconciliation integration tests (MEDIUM)
**Current:** `BankReconciliationServiceTest` (unit) exists
**Missing:** Feature tests through `ReconciliationController` endpoints
**Fix:** Test full flow: import bank statement → match transactions → mark as reconciled → export report.

---

## Phase 4: Code Quality & Consistency (2-3 days)

### 4.1 Enum casting consistency (LOW → MEDIUM)
**Issue:** Many places use raw `->value` instead of enum instances when field is cast to enum
**Affected:**
- `BranchClosureWorkflow` (status)
- `EddDocumentRequest` (status updates)
- `ComplianceCase::close()` (resolution)
- Various model setters

**Fix:** Replace `SomeEnum::Value->value` with `SomeEnum::Value` when assigning to casted attributes

---

### 4.2 Money cast precision (MEDIUM)
**File:** `app/Models/BankReconciliation.php:69`
**Issue:** `getAmount()` casts `debit`/`credit` to `float` after MoneyCast, losing precision
**Fix:**
```php
public function getAmount(): string
{
    return $this->debit->value() - $this->credit->value(); // Use Money objects, not floats
}
```
**Test:** Verify large decimal amounts retain precision.

---

### 4.3 Model $with heavy eager loading (MEDIUM)
**Models with excessive `$with`:**
- `FlaggedTransaction::$with = ['transaction', 'customer', 'assignedTo', 'reviewer']` (4)
- `Alert::$with = ['flaggedTransaction', 'assignedTo', 'case']` (3)
- `ComplianceCase::$with = ['customer', 'assignee']` (2, ok but consider lazy)

**Fix:** Remove from `$with`, eager load explicitly in controller queries where needed:

```php
// In controller
$alerts = Alert::with(['flaggedTransaction.customer', 'assignedTo'])->paginate();
```
**Test:** Verify N+1 queries reduced via query log.

---

### 4.4 Model constructor service resolution (MEDIUM)
**Models:**
- `TellerAllocation` (MathService)
- `BranchPool` (MathService)
- `CurrencyPosition` (MathService)
- `AmlRule` (Transaction query in constructor)

**Issue:** `app(Service::class)` in constructor makes testing hard and creates hidden dependencies
**Fix:** Move to getter methods or use model observers/events instead. If service needed for computed attributes, resolve via `app()` inside the accessor, not constructor.

---

### 4.5 Accessor side effects (MEDIUM)
**File:** `app/Models/User.php:231-255`
**Issue:** `getNotificationPreference()` creates a record if none exists
**Fix:** Move creation logic to a service or observer. Accessor should only return existing value or null.

```php
public function getNotificationPreferenceAttribute(): ?UserNotificationPreference
{
    return $this->notificationPreference; // relationship, no creation
}
```
**Test:** Ensure user creation does not auto-create preference.

---

### 4.6 API response consistency (LOW)
**Issue:** Inconsistent error envelope across API controllers; some expose internal fields
**Fix:**
- Standardize all API errors to `{success: false, message: string, errors?: array}`
- Review `TransactionResource` and `CustomerResource` to hide internal fields (`idempotency_key`, `transition_history`, `id_number_encrypted`, `id_number_hash`)

---

### 4.7 Policy inconsistencies (LOW)
**Files:** `ThresholdAuditPolicy`, `SystemLogPolicy` use `$user->hasRole()` which may not exist on User model (uses enum `role` field)
**Fix:** Use consistent pattern: `$user->role->is(Admin|Compliance|Auditor)` OR add `hasRole()` method to User model that handles enums.

---

## Phase 5: Compliance & Audit (1-2 days)

### 5.1 STR (Suspicious Transaction Report) generation
- Implement auto-generation when transactions exceed thresholds
- Create STR approval workflow for compliance officers
- BNM report export (PDF/Excel) with required fields
- Audit trail for STR submissions

**Priority:** HIGH (BNM requirement)

---

### 5.2 Sanctions list import & matching workflow
- Feature test for full flow: import → screen customers → generate alerts → match hits
- Ensure no duplicates; handle updates gracefully

**Priority:** HIGH

---

### 5.3 Audit hash sealing verification
- Test `SealAuditHashJob` ensures sequential hash chain integrity
- Verify tamper detection: modifying an audited log breaks subsequent hashes

**Priority:** HIGH

---

### 5.4 Emergency counter close service test
- `EmergencyCounterService` has zero tests
- This is a BNM compliance feature for after-hours emergencies

**Priority:** HIGH

---

## Implementation Sequence

**Week 1:**
- Days 1-2: Phase 1 (Critical runtime bugs) — get system stable
- Day 3: Phase 2.1-2.2 (Policy auth, race conditions) — security
- Day 4-5: Phase 3.1 (FormRequest tests) — start coverage

**Week 2:**
- Days 1-2: Phase 2.3-2.6 (Transaction wrappers, business logic extraction)
- Day 3-4: Phase 3.2-3.3 (Job & controller tests) — critical coverage
- Day 5: Phase 4.1-4.3 (Enum consistency, money precision, eager loading)

**Week 3:**
- Days 1-2: Phase 3.4-3.5 (STR, sanctions workflow, audit sealing)
- Day 3: Phase 4.4-4.7 (remaining quality fixes)
- Day 4-5: Buffer for unforeseen issues, full regression test run
- Day 5: Final verification — run entire test suite, Pint formatting, commit

---

## Success Criteria

- ✅ All 1035 existing tests still pass
- ✅ No new runtime errors in smoke testing
- ✅ All critical bugs fixed and verified with tests
- ✅ FormRequest validation coverage: 100% (each has ≥1 test)
- ✅ Job test coverage: ≥80% (15/19 jobs tested)
- ✅ Controller integration coverage: all critical paths tested
- ✅ Pint passes with zero errors
- ✅ No deadlocks observed in concurrent test scenarios

---

## Risk Mitigation

- **Blast radius concerns:** Use feature flags or deploy to staging first for changes with wide impact (policy changes, eager loading)
- **Race conditions:** Simulate concurrency with parallel test processes (`paratest --processes=4`)
- **Financial correctness:** All monetary tests use `MathService` and large decimal fixtures to catch precision issues
- **Compliance:** Involve compliance officer to review STR, sanctions, audit sealing changes

---

## Notes

- This plan addresses **100+ issues** identified in the comprehensive audit
- Estimated total effort: **10-15 days** (can be parallelized across team members)
- All changes should be accompanied by tests; follow TDD for new code
- Run `vendor/bin/pint` before each commit
- Use `php artisan test --compact` with appropriate filters to validate each task
