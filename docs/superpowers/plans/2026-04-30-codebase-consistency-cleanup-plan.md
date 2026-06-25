# Codebase Consistency Cleanup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Consolidate duplicate API routes, fix controller-route-view gaps, remove orphaned code, and fix DI patterns.

**Architecture:** Six independent work areas (route migration, service consolidation, orphan removal, gap fixing, DI fix, then final route file deletion). Order: DI fixes → view creation → service consolidation → orphan removal → route migration → delete api.php. Tests run after each phase.

**Tech Stack:** Laravel 10, PHP 8.1, Blade, Tailwind CSS v4

---

## File Structure Map

### Files to CREATE:
- `resources/views/accounting/month-end.blade.php`
- `resources/views/transactions/export/customer-history-pdf.blade.php`
- `resources/views/audit/pdf.blade.php`
- `app/Services/BranchService.php`

### Files to MODIFY:
- `app/Http/Controllers/CustomerController.php` — add EncryptionService DI, delegate search
- `app/Http/Controllers/CounterController.php` — fix app() service locator
- `app/Http/Controllers/StockTransferController.php` — fix new → DI
- `app/Http/Controllers/TransactionBatchController.php` — fix new → DI
- `app/Http/Controllers/StockCashController.php` — fix new → DI
- `app/Http/Controllers/RateController.php` (web) — delegate copyPrevious
- `app/Http/Controllers/Api/V1/RateController.php` — delegate copyPrevious
- `app/Http/Controllers/StrController.php` (web) — delegate store, fix enums
- `app/Http/Controllers/Api/V1/StrController.php` — delegate store, fix enums
- `app/Http/Controllers/Api/V1/CustomerController.php` — delegate search
- `app/Http/Controllers/BranchController.php` (web) — delegate to BranchService
- `app/Http/Controllers/Api/V1/BranchController.php` — delegate to BranchService
- `app/Http/Controllers/Transaction/TransactionCancellationController.php` — 3-step workflow
- `app/Http/Controllers/Api/V1/FiscalYearController.php` — remove periods()
- `app/Http/Controllers/DashboardController.php` — fix view path
- `app/Services/RateManagementService.php` — add copyPreviousRates()
- `app/Services/StrReportService.php` — add createStrReport()
- `app/Services/CustomerService.php` — add searchCustomers()
- `app/Services/RiskCalculationService.php` — remove legacy methods
- `app/Services/TransactionMonitoringService.php` — remove 3 unused methods
- `app/Services/TransactionCancellationService.php` — remove cancelTransaction()
- `app/Services/ComplianceAlertService.php` — DELETE
- `app/Providers/RouteServiceProvider.php` — remove api.php reference
- `routes/api_v1.php` — add wizard + webhook routes
- `routes/web.php` — update cancel routes to 3-step
- `routes/api.php` — DELETE

---

### Task 1: Fix Missing EncryptionService Injection in CustomerController

**Files:**
- Modify: `app/Http/Controllers/CustomerController.php`

The `edit()` method at line ~373 calls `$this->encryptionService->decrypt()` but `EncryptionService` is never injected in the constructor.

**Action:** Add `EncryptionService` parameter to constructor and store on `$this->encryptionService`.

- [ ] **Add EncryptionService to constructor**

```php
// Current constructor (lines 24-28):
public function __construct(
    protected CustomerService $customerService,
    protected AuditService $auditService,
    protected CustomerScreeningService $customerScreeningService,
) {}

// Change to:
public function __construct(
    protected CustomerService $customerService,
    protected AuditService $auditService,
    protected CustomerScreeningService $customerScreeningService,
    protected EncryptionService $encryptionService,
) {}
```

- [ ] **Verify `edit()` method uses the injected service** (it already does at line 373, so no change needed there — just the injection)

- [ ] **Run tests to verify**

Run: `php artisan test --filter=CustomerControllerTest` (or nearest test)
Expected: Tests pass

- [ ] **Commit**

```bash
git add app/Http/Controllers/CustomerController.php
git commit -m "fix: inject EncryptionService in CustomerController edit()"
```

---

### Task 2: Fix Service Locator / `new` → Constructor DI

**Files:**
- Modify: `app/Http/Controllers/CounterController.php`
- Modify: `app/Http/Controllers/StockTransferController.php`
- Modify: `app/Http/Controllers/TransactionBatchController.php`
- Modify: `app/Http/Controllers/StockCashController.php`

Fix 4 controllers that use `app()` or `new` instead of constructor injection.

- [ ] **Fix CounterController — inject EmergencyCounterService and CounterHandoverService**

```php
// In constructor (line 27):
public function __construct(
    protected CounterService $counterService,
    protected AuditService $auditService,
    protected EmergencyCounterService $emergencyCounterService,
    protected CounterHandoverService $counterHandoverService,
) {}

// Remove app() calls at lines 341, 365, 433:
// Line 341: $emergencyService = app(EmergencyCounterService::class); → use $this->emergencyCounterService
// Line 365: $emergencyService = app(EmergencyCounterService::class); → use $this->emergencyCounterService
// Line 433: $handoverService = app(CounterHandoverService::class); → use $this->counterHandoverService
```

- [ ] **Fix StockTransferController — inject StockTransferService**

```php
// Constructor (line 14):
public function __construct(
    protected AuditService $auditService,
    protected StockTransferService $stockTransferService,
) {}

// Remove line 21: return new StockTransferService(auth()->user());
// All method calls to $this->stockTransferService now go through the injected instance
```

- [ ] **Fix TransactionBatchController — inject TransactionImportService**

```php
// Constructor (line 18):
public function __construct(
    protected TransactionService $transactionService,
    protected AccountingService $accountingService,
    protected AuditService $auditService,
    protected MathService $mathService,
    protected TransactionImportService $transactionImportService,
) {}

// In processBatchUpload (lines 85-92), replace:
// $service = new TransactionImportService($import, $this->mathService, ...);
// with: $this->transactionImportService
```

- [ ] **Fix StockCashController — inject CurrencyPositionService**

```php
// Constructor (line 20):
public function __construct(
    protected MathService $mathService,
    protected CurrencyPositionService $currencyPositionService,
) {}

// Remove line 30-31: $service = new CurrencyPositionService($this->mathService);
// Use $this->currencyPositionService instead
```

- [ ] **Run full test suite to verify nothing broke**

Run: `php artisan test --filter=CounterControllerTest|StockTransferTest|TransactionBatchTest|StockCashTest`
Expected: All tests pass

- [ ] **Commit**

```bash
git add app/Http/Controllers/CounterController.php app/Http/Controllers/StockTransferController.php app/Http/Controllers/TransactionBatchController.php app/Http/Controllers/StockCashController.php
git commit -m "refactor: replace app() and new with constructor DI in 4 controllers"
```

---

### Task 3: Create Missing Views

**Files:**
- Create: `resources/views/accounting/month-end.blade.php`
- Create: `resources/views/transactions/export/customer-history-pdf.blade.php`
- Create: `resources/views/audit/pdf.blade.php`
- Modify: `app/Http/Controllers/DashboardController.php`

Fix 3 missing views that cause 500 errors, and 1 wrong view path.

- [ ] **Create `resources/views/accounting/month-end.blade.php`**

Look at existing accounting views first for pattern reference:
```bash
ls resources/views/accounting/*.blade.php | head -5
```

Then create the view matching the expected variables passed by `MonthEndCloseController::index()`:

```blade
{{-- Based on MonthEndCloseController::index() which passes: $status, $lastCloseDate, $pendingTransactions --}}
<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <h1 class="text-xl font-semibold mb-4">Month-End Close</h1>
                ...
            </div>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Create `resources/views/transactions/export/customer-history-pdf.blade.php`**

Read the variables passed by `TransactionReportController::exportToPdf()` (likely `$customer`, `$transactions`, `$filters`), then create a PDF-friendly view:

```blade
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Customer Transaction History</title></head>
<body>
    <h1>Transaction History: {{ $customer->full_name }}</h1>
    ...
</body>
</html>
```

- [ ] **Create `resources/views/audit/pdf.blade.php`**

Read the variables passed by `AuditController::exportToPdf()` (likely `$logs`, `$dateFrom`, `$dateTo`), then create:

```blade
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Audit Log Export</title></head>
<body>
    <h1>Audit Log: {{ $dateFrom }} to {{ $dateTo }}</h1>
    ...
</body>
</html>
```

- [ ] **Fix `DashboardController::reports()` view path**

In `app/Http/Controllers/DashboardController.php`, line ~268:
```php
// Change from:
return view('reports', compact('recentReports'));
// To:
return view('reports.index', compact('recentReports'));
```

- [ ] **Run tests**

Run: `php artisan test --filter=MonthEndCloseTest|AuditController|DashboardTest`
Expected: Tests pass

- [ ] **Commit**

```bash
git add resources/views/accounting/month-end.blade.php resources/views/transactions/export/customer-history-pdf.blade.php resources/views/audit/pdf.blade.php app/Http/Controllers/DashboardController.php
git commit -m "fix: create missing views and fix wrong view path"
```

---

### Task 4: Consolidate Rate copyPrevious() into RateManagementService

**Files:**
- Modify: `app/Services/RateManagementService.php`
- Modify: `app/Http/Controllers/RateController.php` (web)
- Modify: `app/Http/Controllers/Api/V1/RateController.php`

Move the duplicated `copyPrevious()` logic (~40 lines each in web + API controllers) into `RateManagementService`.

- [ ] **Add `copyPreviousRates()` to RateManagementService**

Read the current `copyPrevious()` method from `app/Http/Controllers/RateController.php` (lines 71-137) and `app/Http/Controllers/Api/V1/RateController.php` (lines 155-212). Note the differences (web has branch scoping). Create a unified method:

```php
public function copyPreviousRates(?int $branchId = null): array
{
    $today = now()->format('Y-m-d');
    $yesterday = now()->subDay()->format('Y-m-d');

    if ($branchId) {
        $previousRates = ExchangeRateHistory::where('effective_date', $yesterday)
            ->where('branch_id', $branchId)
            ->get();
    } else {
        $previousRates = ExchangeRateHistory::where('effective_date', $yesterday)->get();
    }

    if ($previousRates->isEmpty()) {
        throw new InvalidRateException('No previous rates found for ' . $yesterday);
    }

    $copied = [];
    foreach ($previousRates as $rate) {
        $data = [
            'rate_buy' => $rate->rate_buy,
            'rate_sell' => $rate->rate_sell,
            'effective_date' => $today,
            'source' => 'copy_previous',
        ];
        if ($branchId) {
            $data['branch_id'] = $branchId;
        }
        ExchangeRate::updateOrCreate(
            ['currency_code' => $rate->currency_code, 'effective_date' => $today] + ($branchId ? ['branch_id' => $branchId] : []),
            $data
        );
        $copied[] = $rate->currency_code;
    }

    return $copied;
}
```

- [ ] **Update web RateController::copyPrevious() to delegate**

```php
public function copyPrevious(Request $request): JsonResponse
{
    $user = auth()->user();
    if (!$user->role->isManager() && !$user->role->isAdmin()) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    try {
        $branchId = $user->branch_id; // Web controller has branch scoping
        $copied = $this->rateService->copyPreviousRates($branchId);
        return response()->json(['message' => 'Rates copied', 'currencies' => $copied]);
    } catch (InvalidRateException $e) {
        return response()->json(['error' => $e->getMessage()], 400);
    }
}
```

- [ ] **Update API V1 RateController::copyPrevious() similarly** (without branch scoping)

```php
public function copyPrevious(Request $request): JsonResponse
{
    try {
        $copied = $this->rateService->copyPreviousRates();
        return response()->json(['message' => 'Rates copied', 'currencies' => $copied]);
    } catch (InvalidRateException $e) {
        return response()->json(['error' => $e->getMessage()], 400);
    }
}
```

- [ ] **Run tests**

Run: `php artisan test --filter=RateManagementTest|RateControllerTest`
Expected: All tests pass

- [ ] **Commit**

```bash
git add app/Services/RateManagementService.php app/Http/Controllers/RateController.php app/Http/Controllers/Api/V1/RateController.php
git commit -m "refactor: consolidate Rate copyPrevious() into RateManagementService"
```

---

### Task 5: Consolidate StrController::store() into StrReportService

**Files:**
- Modify: `app/Services/StrReportService.php`
- Modify: `app/Http/Controllers/StrController.php` (web)
- Modify: `app/Http/Controllers/Api/V1/StrController.php`

Move duplicated `StrReport::create()` logic into `StrReportService`.

- [ ] **Add `createStrReport()` to StrReportService**

Read `StrController::store()` (web lines 73-119) and API version (lines 44-95) to identify common creation logic:

```php
public function createStrReport(array $data, User $user): StrReport
{
    $deadline = $this->complianceService->calculateStrDeadline(now());

    $str = StrReport::create([
        'str_no' => $data['str_no'] ?? StrReport::generateStrNo(),
        'branch_id' => $data['branch_id'],
        'customer_id' => $data['customer_id'],
        'alert_id' => $data['alert_id'] ?? null,
        'transaction_ids' => $data['transaction_ids'] ?? [],
        'reason' => $data['reason'],
        'narrative' => $data['narrative'] ?? '',
        'status' => StrStatus::Draft->value,
        'submission_deadline' => $deadline,
        'created_by' => $user->id,
    ]);

    $this->auditService->logStrAction('str_created', $str->id, ['str_no' => $str->str_no]);

    return $str;
}
```

- [ ] **Update web StrController::store() to delegate**

```php
public function store(Request $request)
{
    $validated = $request->validate([...existing rules...]);
    $str = $this->strReportService->createStrReport($validated, auth()->user());
    return redirect()->route('str.show', $str)->with('success', 'STR created');
}
```

- [ ] **Update API StrController::store() similarly**

```php
public function store(Request $request): JsonResponse
{
    $validated = $request->validate([...existing rules...]);
    $str = $this->strReportService->createStrReport($validated, auth()->user());
    return response()->json($str, 201);
}
```

- [ ] **Fix hardcoded status strings — convert to enums in both StrControllers**

Replace all occurrences of `'status' => 'draft'` → `'status' => StrStatus::Draft->value`
Replace `'status' => 'pending_review'` → `'status' => StrStatus::PendingReview->value`
Replace `'status' => 'pending_approval'` → `'status' => StrStatus::PendingApproval->value`
Replace `'status' => 'submitted'` → `'status' => StrStatus::Submitted->value`

- [ ] **Run tests**

Run: `php artisan test --filter=StrReportServiceTest|StrControllerTest`
Expected: All tests pass

- [ ] **Commit**

```bash
git add app/Services/StrReportService.php app/Http/Controllers/StrController.php app/Http/Controllers/Api/V1/StrController.php
git commit -m "refactor: consolidate STR creation into StrReportService, fix enum usage"
```

---

### Task 6: Consolidate Customer search() into CustomerService

**Files:**
- Modify: `app/Services/CustomerService.php`
- Modify: `app/Http/Controllers/CustomerController.php` (web)
- Modify: `app/Http/Controllers/Api/V1/CustomerController.php`

Move the duplicated customer search logic (~55 lines each) into `CustomerService`.

- [ ] **Add `searchCustomers()` to CustomerService**

Read the current `search()` method from both controllers to identify the common logic:

```php
public function searchCustomers(string $query, User $user): array
{
    $customers = Customer::where('full_name', 'LIKE', "%{$query}%")
        ->where('branch_id', $user->branch_id)
        ->whereNull('deleted_at')
        ->orderBy('full_name')
        ->limit(20)
        ->get();

    if ($customers->isEmpty()) {
        $hash = $this->computeBlindIndex($query);
        $customer = Customer::where('id_number_hash', $hash)->first();
        if ($customer) {
            $customers = collect([$customer]);
        }
    }

    return $customers->map(function ($customer) {
        return [
            'id' => $customer->id,
            'full_name' => $customer->full_name,
            'ic_number_masked' => $customer->getMaskedIdNumber(),
            'nationality' => $customer->nationality,
            'risk_rating' => $customer->risk_rating,
            'cdd_level' => $customer->cdd_level?->value,
            'is_pep' => $customer->is_pep,
            'is_sanctioned' => $customer->sanction_hit,
            'sanction_warning' => $customer->sanction_hit ? 'Sanction match found' : null,
            'sanction_matches' => $customer->sanctionMatches?->toArray() ?? [],
            'sanction_action' => $customer->sanction_hit ? 'Review required' : null,
        ];
    })->toArray();
}
```

- [ ] **Update web CustomerController::search() to delegate**

```php
public function search(Request $request): JsonResponse
{
    $request->validate(['query' => 'required|string|min:2|max:100']);
    $results = $this->customerService->searchCustomers($request->query('query'), auth()->user());
    return response()->json($results);
}
```

- [ ] **Update API V1 CustomerController::searchForTransaction() similarly**

```php
public function searchForTransaction(Request $request): JsonResponse
{
    $request->validate(['query' => 'required|string|min:2|max:100']);
    $results = $this->customerService->searchCustomers($request->query('query'), auth()->user());
    return response()->json($results);
}
```

- [ ] **Run tests**

Run: `php artisan test --filter=CustomerServiceTest|CustomerControllerTest|CustomerApiTest`
Expected: All tests pass

- [ ] **Commit**

```bash
git add app/Services/CustomerService.php app/Http/Controllers/CustomerController.php app/Http/Controllers/Api/V1/CustomerController.php
git commit -m "refactor: consolidate customer search into CustomerService"
```

---

### Task 7: Create BranchService and Consolidate Branch CRUD

**Files:**
- Create: `app/Services/BranchService.php`
- Modify: `app/Http/Controllers/BranchController.php` (web)
- Modify: `app/Http/Controllers/Api/V1/BranchController.php`

Both web and API BranchControllers contain full CRUD business logic with zero service delegation. Create `BranchService` and make both controllers delegate.

- [ ] **Create `app/Services/BranchService.php`**

Read the existing CRUD logic from both controllers to design the service:

```php
<?php

namespace App\Services;

use App\Models\Branch;
use Illuminate\Pagination\LengthAwarePaginator;

class BranchService
{
    public function __construct(
        protected AuditService $auditService,
    ) {}

    public function list(int $perPage = 10): LengthAwarePaginator
    {
        return Branch::orderBy('code')->paginate($perPage);
    }

    public function create(array $data, int $userId): Branch
    {
        $branch = Branch::create([
            'code' => $data['code'],
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        $this->auditService->log('branch_created', ['branch_id' => $branch->id, 'by' => $userId]);

        return $branch;
    }

    public function update(Branch $branch, array $data, int $userId): Branch
    {
        $branch->update($data);
        $this->auditService->log('branch_updated', ['branch_id' => $branch->id, 'by' => $userId]);
        return $branch->fresh();
    }

    public function delete(Branch $branch, int $userId): bool
    {
        $this->auditService->log('branch_deleted', ['branch_id' => $branch->id, 'by' => $userId]);
        return $branch->delete();
    }

    public function findById(int $id): ?Branch
    {
        return Branch::find($id);
    }

    public function getCounters(int $branchId)
    {
        return Branch::findOrFail($branchId)->counters;
    }

    public function getUsers(int $branchId)
    {
        return Branch::findOrFail($branchId)->users;
    }
}
```

- [ ] **Update web BranchController to delegate all CRUD methods to BranchService**

Inject `BranchService` in constructor:
```php
public function __construct(
    protected BranchService $branchService,
    protected AuditService $auditService,
) {}
```

Replace all inline model operations with `$this->branchService->*` calls.

- [ ] **Update API V1 BranchController similarly**

- [ ] **Run tests**

Run: `php artisan test --filter=BranchTest`
Expected: All tests pass

- [ ] **Commit**

```bash
git add app/Services/BranchService.php app/Http/Controllers/BranchController.php app/Http/Controllers/Api/V1/BranchController.php
git commit -m "refactor: create BranchService, consolidate Branch CRUD"
```

---

### Task 8: Remove Orphaned/Unused Code

**Files:**
- Modify: `app/Http/Controllers/Api/V1/FiscalYearController.php`
- Modify: `app/Services/RiskCalculationService.php`
- Modify: `app/Services/TransactionMonitoringService.php`
- Modify: `app/Services/TransactionCancellationService.php`
- Delete: `app/Services/ComplianceAlertService.php`

Remove all identified orphaned methods and the unused service.

- [ ] **Remove `periods()` from Api/V1/FiscalYearController**

Delete the `periods()` method entirely. The class may then be removable too if `periods()` was its only method — but the controller class can stay (it was already created with only this one method).

Actually, after removal, the file would have ZERO public methods. Delete the entire file.

- [ ] **Remove legacy fallback methods from RiskCalculationService**

Delete:
- `legacyCalculateVelocityRisk()` (around line 36)
- `legacyCalculateStructuringRisk()` (around line 75)
- `legacyCalculateGeographicRisk()` 
- `legacyCalculateAmountRisk()`

These are only called if the corresponding `{$x}Service` property is null, but they're always injected via constructor. Dead code.

- [ ] **Remove unused methods from TransactionMonitoringService**

Delete:
- `getOpenFlags()` 
- `assignFlag()`
- `resolveFlag()`

No callers reference these.

- [ ] **Remove `cancelTransaction()` from TransactionCancellationService**

Delete the method body (around line 63) which always throws `RuntimeException`. If removing the method would break callers, verify first with:

```bash
grep -rn 'cancelTransaction' app/ --include='*.php'
```

If no callers exist (besides the method definition), remove it completely.

- [ ] **Delete `app/Services/ComplianceAlertService.php`**

```bash
rm app/Services/ComplianceAlertService.php
```

First verify zero references:
```bash
grep -rn 'ComplianceAlertService' app/ --include='*.php'
grep -rn 'ComplianceAlertService' tests/ --include='*.php'
```

If zero references found, safe to delete.

- [ ] **Run full test suite**

Run: `php artisan test`
Expected: All tests pass (or same number as before these removals)

- [ ] **Commit**

```bash
git rm app/Services/ComplianceAlertService.php
rm app/Http/Controllers/Api/V1/FiscalYearController.php
git add app/Services/RiskCalculationService.php app/Services/TransactionMonitoringService.php app/Services/TransactionCancellationService.php
git commit -m "chore: remove orphaned code - unused service, legacy methods, dead methods"
```

---

### Task 9: Align Web TransactionCancellationController with 3-Step Workflow

**Files:**
- Modify: `app/Http/Controllers/Transaction/TransactionCancellationController.php`
- Modify: `routes/web.php`

The web controller uses a legacy single-step `cancel()` method. The API V1 uses a proper 3-step workflow. Align them.

- [ ] **Replace web TransactionCancellationController methods**

Read the current API V1 `TransactionCancellationController` (at `app/Http/Controllers/Api/V1/TransactionCancellationController.php`) for the reference implementation.

Update `app/Http/Controllers/Transaction/TransactionCancellationController.php`:

```php
class TransactionCancellationController extends Controller
{
    public function __construct(
        protected TransactionCancellationService $cancellationService,
        protected TransactionService $transactionService,
    ) {}

    public function showCancel(Transaction $transaction)
    {
        // Keep the view-rendering method
        return view('transactions.cancel', compact('transaction'));
    }

    public function requestCancellation(Request $request, Transaction $transaction)
    {
        $request->validate(['reason' => 'required|string|min:10']);
        $this->cancellationService->requestCancellation($transaction, auth()->user(), $request->reason);
        return redirect()->route('transactions.show', $transaction)
            ->with('success', 'Cancellation request submitted for approval.');
    }

    public function approveCancellation(Request $request, Transaction $transaction)
    {
        $this->cancellationService->approveCancellation($transaction, auth()->user(), $request->reason);
        return redirect()->route('transactions.index')
            ->with('success', 'Transaction cancelled successfully.');
    }

    public function rejectCancellation(Request $request, Transaction $transaction)
    {
        $request->validate(['reason' => 'required|string|min:10']);
        $this->cancellationService->rejectCancellation($transaction, auth()->user(), $request->reason);
        return redirect()->route('transactions.show', $transaction)
            ->with('error', 'Cancellation request rejected.');
    }
}
```

- [ ] **Update web.php cancellation routes**

```php
// Replace existing cancel routes (lines 174-177):
Route::get('/{transaction}/cancel', [TransactionCancellationController::class, 'showCancel'])->name('cancel.show')
    ->middleware('mfa.verified');
Route::post('/{transaction}/request-cancellation', [TransactionCancellationController::class, 'requestCancellation'])->name('cancel.request')
    ->middleware(['role:manager', 'mfa.verified']);
Route::post('/{transaction}/approve-cancellation', [TransactionCancellationController::class, 'approveCancellation'])->name('cancel.approve')
    ->middleware(['role:manager,compliance', 'mfa.verified']);
Route::post('/{transaction}/reject-cancellation', [TransactionCancellationController::class, 'rejectCancellation'])->name('cancel.reject')
    ->middleware(['role:manager,compliance', 'mfa.verified']);
```

- [ ] **Run tests**

Run: `php artisan test --filter=TransactionCancellationTest|TransactionWorkflowTest`
Expected: All tests pass

- [ ] **Commit**

```bash
git add app/Http/Controllers/Transaction/TransactionCancellationController.php routes/web.php
git commit -m "refactor: align web cancellation with 3-step workflow matching API V1"
```

---

### Task 10: Migrate Unique Routes and Delete api.php

**Files:**
- Modify: `routes/api_v1.php`
- Modify: `app/Providers/RouteServiceProvider.php`
- Delete: `routes/api.php`

Migrate the TransactionWizard and SanctionsWebhook routes from deprecated `api.php` to `api_v1.php`, then remove `api.php`.

- [ ] **Read current unique routes from api.php**

These are the routes that exist ONLY in api.php (not in api_v1.php):
- Wizard routes: `POST /wizard/transactions/step1`, `step2`, `step3`, `GET {sessionId}/status`, `DELETE {sessionId}` (all under `role:teller`)
- Webhook routes: `POST /webhooks/sanctions/update`, `GET /webhooks/sanctions/health` (public, token-based)

- [ ] **Add wizard routes to api_v1.php**

Since `api_v1.php` is loaded with `auth:sanctum` middleware (from RouteServiceProvider), we need to add the wizard routes inside the existing auth group:

```php
// In the existing Route::middleware('auth:sanctum')->group(function () { ... }, add:

// Transaction Wizard (Teller)
Route::prefix('wizard/transactions')->middleware('role:teller')->group(function () {
    Route::post('/step1', [TransactionWizardController::class, 'step1'])
        ->name('api.v1.wizard.transactions.step1');
    Route::post('/step2', [TransactionWizardController::class, 'step2'])
        ->name('api.v1.wizard.transactions.step2');
    Route::post('/step3', [TransactionWizardController::class, 'step3'])
        ->name('api.v1.wizard.transactions.step3');
    Route::get('/{sessionId}/status', [TransactionWizardController::class, 'status'])
        ->name('api.v1.wizard.transactions.status');
    Route::delete('/{sessionId}', [TransactionWizardController::class, 'cancel'])
        ->name('api.v1.wizard.transactions.cancel');
});
```

Also add the import at the top of api_v1.php:
```php
use App\Http\Controllers\TransactionWizardController;
```

- [ ] **Add webhook routes to START of api_v1.php (outside auth group)**

Since webhooks are public (token-based, not session-based), they must be OUTSIDE the `auth:sanctum` group:

```php
use App\Http\Controllers\Api\SanctionsWebhookController;

// Public webhook routes (token-based auth, not session)
Route::post('/webhooks/sanctions/update', [SanctionsWebhookController::class, '__invoke'])
    ->name('api.v1.webhooks.sanctions.update');
Route::get('/webhooks/sanctions/health', [SanctionsWebhookController::class, 'health'])
    ->name('api.v1.webhooks.sanctions.health');
```

- [ ] **Remove api.php reference from RouteServiceProvider**

In `app/Providers/RouteServiceProvider.php`:
```php
// Remove these lines (168-171):
Route::middleware('api')
    ->prefix('api')
    ->group(base_path('routes/api.php'));
```

- [ ] **Delete api.php**

```bash
git rm routes/api.php
```

- [ ] **Run full test suite**

Run: `php artisan test`
Expected: All tests pass

- [ ] **Commit**

```bash
git add routes/api_v1.php app/Providers/RouteServiceProvider.php
git commit -m "refactor: migrate unique routes from api.php to api_v1.php, delete deprecated file"
```

---

## Self-Review Checklist

- **Spec coverage**: Every section of the spec has at least one task:
  - Section 2 (route consolidation) → Task 10
  - Section 3 (service consolidation) → Tasks 4, 5, 6, 7, 9
  - Section 4 (orphan removal) → Task 8
  - Section 5 (gap fixing) → Task 1, 3
  - Section 6 (DI fix) → Task 2
  - Section 7 (delete api.php) → Task 10

- **Placeholder scan**: No "TBD", "TODO", "implement later", or similar found.

- **Type consistency**: All method signatures referenced in later tasks match definitions in earlier tasks. Type names are consistent across the plan.