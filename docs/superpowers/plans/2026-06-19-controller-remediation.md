# Controller Remediation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix all controller issues: extract FormRequests, add authorization policies, replace generic exception handling with domain exceptions, split god controllers, fix N+1 queries, and standardize responses.

**Architecture:** Four-phase approach: (1) Foundation — FormRequest extraction for 21 controllers, (2) Authorization — add policies for unprotected resources, (3) Exception handling — replace generic catches with domain exceptions, (4) Structure — split god controllers and fix N+1 queries. Each phase is independently deployable.

**Tech Stack:** PHP 8.3, Laravel 11, PHPUnit 11, existing DomainException hierarchy (48 exceptions).

## Global Constraints

- PHP 8.3.30, Laravel 11, PHPUnit 11.5.55
- Follow existing code conventions (check sibling files)
- Use existing `AuthorizedFormRequest` base class for FormRequests
- Use existing `App\Exceptions\Domain\*Exception` hierarchy (48 exceptions)
- Use existing `App\Http\Resources\Api\V1\*Resource` pattern for API responses
- All tests must pass after each task
- Run `vendor/bin/pint --dirty --format agent` before every commit
- Run `php artisan test --compact --filter=<test>` after changes

---

## Phase 1: FormRequest Extraction (21 controllers)

### Task 1: Extract FormRequests for API Compliance Controllers

**Files:**
- Create: `app/Http/Requests/Api/V1/Compliance/StoreAlertRequest.php`
- Create: `app/Http/Requests/Api/V1/Compliance/UpdateAlertRequest.php`
- Create: `app/Http/Requests/Api/V1/Compliance/StoreEddRequest.php`
- Create: `app/Http/Requests/Api/V1/Compliance/UpdateEddRequest.php`
- Create: `app/Http/Requests/Api/V1/Compliance/StoreFindingRequest.php`
- Create: `app/Http/Requests/Api/V1/Compliance/UpdateFindingRequest.php`
- Create: `app/Http/Requests/Api/V1/Compliance/FilterDashboardRequest.php`
- Create: `app/Http/Requests/Api/V1/Compliance/StoreRiskRequest.php`
- Modify: `app/Http/Controllers/Api/V1/Compliance/AlertController.php`
- Modify: `app/Http/Controllers/Api/V1/Compliance/CaseController.php`
- Modify: `app/Http/Controllers/Api/V1/Compliance/DashboardController.php`
- Modify: `app/Http/Controllers/Api/V1/Compliance/EddController.php`
- Modify: `app/Http/Controllers/Api/V1/Compliance/FindingController.php`
- Modify: `app/Http/Controllers/Api/V1/Compliance/RiskController.php`

**Interfaces:**
- Consumes: Existing `AuthorizedFormRequest` base class
- Produces: 7 new FormRequest classes

- [ ] **Step 1: Read existing compliance controllers to extract validation rules**

Read each controller's `validate()` calls and extract the rules into FormRequest classes.

- [ ] **Step 2: Create FormRequest classes**

For each controller method with inline validation, create a FormRequest:

```php
<?php

namespace App\Http\Requests\Api\V1\Compliance;

use App\Http\Requests\AuthorizedFormRequest;

class StoreAlertRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'customer_id' => 'required|exists:customers,id',
            'alert_type' => 'required|string|in:SANCTION,PEP,RISK,TRANSACTION',
            'severity' => 'required|string|in:LOW,MEDIUM,HIGH,CRITICAL',
            'description' => 'required|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'Customer is required.',
            'customer_id.exists' => 'Customer not found.',
            'alert_type.required' => 'Alert type is required.',
            'severity.required' => 'Severity level is required.',
        ];
    }
}
```

- [ ] **Step 3: Update controllers to use FormRequests**

Replace `$request->validate([...])` with FormRequest type-hints:

```php
// Before
public function store(Request $request): JsonResponse
{
    $validated = $request->validate([...]);
    // ...
}

// After
public function store(StoreAlertRequest $request): JsonResponse
{
    $validated = $request->validated();
    // ...
}
```

- [ ] **Step 4: Run tests**

Run: `php artisan test --compact --filter=AlertController`
Run: `php artisan test --compact --filter=CaseController`
Run: `php artisan test --compact --filter=DashboardController`
Run: `php artisan test --compact --filter=EddController`
Run: `php artisan test --compact --filter=FindingController`
Run: `php artisan test --compact --filter=RiskController`
Expected: All tests pass

- [ ] **Step 5: Commit**

```bash
git add app/Http/Requests/Api/V1/Compliance/ app/Http/Controllers/Api/V1/Compliance/
git commit -m "refactor(exceptions): extract FormRequests for API Compliance controllers"
```

### Task 2: Extract FormRequests for API Transaction Controllers

**Files:**
- Create: `app/Http/Requests/Api/V1/StoreTransactionRequest.php`
- Create: `app/Http/Requests/Api/V1/ApproveTransactionRequest.php`
- Create: `app/Http/Requests/Api/V1/ScreeningRequest.php`
- Create: `app/Http/Requests/Api/V1/ImportBankStatementRequest.php`
- Create: `app/Http/Requests/Api/V1/RunReportRequest.php`
- Modify: `app/Http/Controllers/Api/V1/TransactionController.php`
- Modify: `app/Http/Controllers/Api/V1/TransactionApprovalController.php` (if exists)
- Modify: `app/Http/Controllers/Api/V1/ScreeningController.php`
- Modify: `app/Http/Controllers/Api/V1/ReportController.php`
- Modify: `app/Http/Controllers/TransactionBatchController.php`

- [ ] **Step 1: Read controllers and extract validation rules**

- [ ] **Step 2: Create FormRequest classes**

```php
<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\AuthorizedFormRequest;

class StoreTransactionRequest extends AuthorizedFormRequest
{
    public function rules(): array
    {
        return [
            'customer_id' => 'required|exists:customers,id',
            'currency_code' => 'required|exists:currencies,code',
            'transaction_type' => 'required|in:BUY,SELL',
            'amount' => 'required|numeric|min:0.01',
            'rate' => 'required|numeric|min:0',
            'branch_id' => 'required|exists:branches,id',
        ];
    }
}
```

- [ ] **Step 3: Update controllers**

- [ ] **Step 4: Run tests**

- [ ] **Step 5: Commit**

```bash
git add app/Http/Requests/Api/V1/ app/Http/Controllers/Api/V1/TransactionController.php app/Http/Controllers/Api/V1/ScreeningController.php app/Http/Controllers/Api/V1/ReportController.php app/Http/Controllers/TransactionBatchController.php
git commit -m "refactor(exceptions): extract FormRequests for API Transaction controllers"
```

### Task 3: Extract FormRequests for API Counter Controllers

**Files:**
- Create: `app/Http/Requests/Api/V1/Counter/HandoverCounterRequest.php`
- Create: `app/Http/Requests/Api/V1/Counter/OpenCounterRequest.php`
- Create: `app/Http/Requests/Api/V1/Counter/EmergencyCloseRequest.php`
- Create: `app/Http/Requests/Api/V1/Counter/StoreEodRequest.php`
- Create: `app/Http/Requests/Api/V1/TellerAllocationRequest.php`
- Modify: `app/Http/Controllers/Api/V1/CounterHandoverController.php`
- Modify: `app/Http/Controllers/Api/V1/CounterOpeningController.php`
- Modify: `app/Http/Controllers/Api/V1/EmergencyCounterController.php`
- Modify: `app/Http/Controllers/Api/V1/EodReconciliationController.php`
- Modify: `app/Http/Controllers/Api/V1/TellerAllocationController.php`

- [ ] **Step 1-5:** Same pattern as Tasks 1-2

### Task 4: Extract FormRequests for Web Controllers

**Files:**
- Create: `app/Http/Requests/StoreTransactionBatchRequest.php`
- Create: `app/Http/Requests/StoreTransactionApprovalRequest.php`
- Create: `app/Http/Requests/FilterDashboardRequest.php`
- Create: `app/Http/Requests/StoreSanctionListRequest.php`
- Create: `app/Http/Requests/LoginRequest.php`
- Modify: `app/Http/Controllers/Transaction/TransactionApprovalController.php`
- Modify: `app/Http/Controllers/DashboardController.php`
- Modify: `app/Http/Controllers/Compliance/CaseManagementController.php`
- Modify: `app/Http/Controllers/Compliance/RiskDashboardController.php`
- Modify: `app/Http/Controllers/Auth/LoginController.php`
- Modify: `app/Http/Controllers/CustomerController.php` (search, quickCreate methods)

- [ ] **Step 1-5:** Same pattern as Tasks 1-2

---

## Phase 2: Authorization Policies

### Task 5: Create Authorization Policies for Core Resources

**Files:**
- Create: `app/Policies/TransactionPolicy.php`
- Create: `app/Policies/CustomerPolicy.php`
- Create: `app/Policies/BranchPolicy.php`
- Create: `app/Policies/CounterPolicy.php`
- Create: `app/Policies/UserPolicy.php`
- Create: `app/Policies/JournalEntryPolicy.php`
- Modify: `app/Providers/AuthServiceProvider.php`

**Interfaces:**
- Produces: 6 Policy classes with `viewAny`, `view`, `create`, `update`, `delete` methods

- [ ] **Step 1: Create TransactionPolicy**

```php
<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TransactionPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Transaction $transaction): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Teller, UserRole::Manager, UserRole::Admin]);
    }

    public function update(User $user, Transaction $transaction): bool
    {
        return $user->role === UserRole::Admin || $transaction->user_id === $user->id;
    }

    public function delete(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }
}
```

- [ ] **Step 2: Create CustomerPolicy**

```php
<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Customer $customer): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Teller, UserRole::Manager, UserRole::Admin]);
    }

    public function update(User $user, Customer $customer): bool
    {
        return in_array($user->role, [UserRole::Manager, UserRole::Admin]);
    }

    public function delete(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }
}
```

- [ ] **Step 3: Create BranchPolicy, CounterPolicy, UserPolicy, JournalEntryPolicy**

Follow same pattern with role-based checks.

- [ ] **Step 4: Register policies in AuthServiceProvider**

```php
// app/Providers/AuthServiceProvider.php
protected $policies = [
    \App\Models\Transaction::class => \App\Policies\TransactionPolicy::class,
    \App\Models\Customer::class => \App\Policies\CustomerPolicy::class,
    \App\Models\Branch::class => \App\Policies\BranchPolicy::class,
    \App\Models\Counter::class => \App\Policies\CounterPolicy::class,
    \App\Models\User::class => \App\Policies\UserPolicy::class,
    \App\Models\JournalEntry::class => \App\Policies\JournalEntryPolicy::class,
];
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --compact --filter=Policy`
Expected: All tests pass

- [ ] **Step 6: Commit**

```bash
git add app/Policies/ app/Providers/AuthServiceProvider.php
git commit -m "feat(auth): add authorization policies for core resources"
```

### Task 6: Add Policy Checks to Controllers

**Files:**
- Modify: `app/Http/Controllers/TransactionController.php`
- Modify: `app/Http/Controllers/CustomerController.php`
- Modify: `app/Http/Controllers/Api/V1/TransactionController.php`
- Modify: `app/Http/Controllers/Api/V1/CustomerController.php`

- [ ] **Step 1: Add `$this->authorize()` calls to controller methods**

```php
// Before
public function store(StoreTransactionRequest $request): RedirectResponse
{
    $validated = $request->validated();
    // ...
}

// After
public function store(StoreTransactionRequest $request): RedirectResponse
{
    $this->authorize('create', Transaction::class);
    $validated = $request->validated();
    // ...
}
```

- [ ] **Step 2: Run tests**

- [ ] **Step 3: Commit**

---

## Phase 3: Exception Handling

### Task 7: Replace Generic Exception Catches with Domain Exceptions

**Files:**
- Modify: `app/Http/Controllers/Transaction/TransactionApprovalController.php`
- Modify: `app/Http/Controllers/Transaction/TransactionCancellationController.php`
- Modify: `app/Http/Controllers/TransactionController.php`
- Modify: `app/Http/Controllers/FiscalYearController.php`
- Modify: `app/Http/Controllers/CustomerController.php`
- Modify: `app/Http/Controllers/TransactionBatchController.php`
- Modify: `app/Http/Controllers/Api/V1/CounterApiController.php`
- Modify: `app/Http/Controllers/Api/V1/EodReconciliationController.php`
- Modify: `app/Http/Controllers/Api/V1/MonthEndCloseController.php`

**Interfaces:**
- Consumes: Existing 48 `App\Exceptions\Domain\*Exception` classes
- Produces: Updated controllers with specific exception handling

- [ ] **Step 1: Map generic catches to specific domain exceptions**

| Controller | Current Catch | Replace With |
|------------|---------------|--------------|
| TransactionApprovalController | `\Exception` | `InsufficientStockException`, `DuplicateTransactionException` |
| TransactionCancellationController | `\Exception` | `TransactionNotFoundException`, `InvalidTransactionStateException` |
| TransactionController | `\Exception` | `InvalidCurrencyException`, `InsufficientStockException` |
| FiscalYearController | `\Exception` | `FiscalYearNotFoundException`, `FiscalYearClosedException`, `ClosedPeriodException` |
| CustomerController | `\Exception` | `CddDocumentExpiredException`, `InvalidCustomerDataException` |
| TransactionBatchController | `\Exception` | `BatchImportFailedException` |

- [ ] **Step 2: Update exception handler to recognize DomainException status codes**

Read `app/Exceptions/Handler.php` and ensure `DomainException` is rendered with correct HTTP status:

```php
// In Handler::render()
if ($exception instanceof \App\Exceptions\Domain\DomainException) {
    if (request()->expectsJson()) {
        return response()->json([
            'success' => false,
            'message' => $exception->getMessage(),
        ], $exception->getStatusCode());
    }
    return back()->with('error', $exception->getMessage());
}
```

- [ ] **Step 3: Update controllers with specific catches**

```php
// Before
try {
    $result = $this->approvalService->approve($transaction, auth()->id());
    return back()->with('success', 'Transaction approved.');
} catch (\Exception $e) {
    Log::error('Approval failed', ['error' => $e->getMessage()]);
    return back()->with('error', 'Approval failed: '.$e->getMessage());
}

// After
try {
    $result = $this->approvalService->approve($transaction, auth()->id());
    return back()->with('success', 'Transaction approved.');
} catch (InsufficientStockException $e) {
    return back()->with('error', $e->getMessage());
} catch (DuplicateTransactionException $e) {
    return back()->with('error', $e->getMessage());
} catch (\Exception $e) {
    Log::error('Unexpected approval error', [
        'transaction_id' => $transaction->id,
        'error' => $e->getMessage(),
    ]);
    return back()->with('error', 'An unexpected error occurred. Please try again.');
}
```

- [ ] **Step 4: Run tests**

Run: `php artisan test --compact --filter=TransactionApproval`
Run: `php artisan test --compact --filter=TransactionCancellation`
Run: `php artisan test --compact --filter=TransactionController`
Expected: All tests pass

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/ app/Exceptions/Handler.php
git commit -m "refactor(exceptions): replace generic catches with domain exceptions"
```

---

## Phase 4: Structure & N+1 Fixes

### Task 8: Split AccountingController into Focused Controllers

**Files:**
- Create: `app/Http/Controllers/Accounting/JournalController.php`
- Create: `app/Http/Controllers/Accounting/BudgetController.php`
- Create: `app/Http/Controllers/Accounting/ReconciliationController.php`
- Create: `app/Http/Controllers/Accounting/ReportController.php`
- Modify: `app/Http/Controllers/AccountingController.php` (keep as facade)
- Modify: `routes/web.php`

**Interfaces:**
- Produces: 4 focused controllers extracted from AccountingController

- [ ] **Step 1: Create JournalController**

```php
<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreJournalEntryRequest;
use App\Http\Requests\Accounting\ReverseJournalEntryRequest;
use App\Models\JournalEntry;
use App\Services\Accounting\AccountingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class JournalController extends Controller
{
    public function __construct(
        protected AccountingService $accountingService,
    ) {}

    public function index(): View
    {
        $entries = JournalEntry::with(['lines', 'postedBy', 'creator', 'approver'])
            ->orderBy('entry_date', 'desc')
            ->paginate(25);

        return view('accounting.journal.index', compact('entries'));
    }

    public function show(JournalEntry $entry): View
    {
        $entry->load(['lines.account', 'postedBy', 'creator', 'approver']);
        return view('accounting.journal.show', compact('entry'));
    }

    public function store(StoreJournalEntryRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $entry = $this->accountingService->createJournalEntry(
            $validated['lines'],
            'Manual',
            null,
            $validated['description'],
            $validated['entry_date']
        );

        return redirect()->route('accounting.journal.show', $entry)
            ->with('success', 'Journal entry created successfully.');
    }

    public function reverse(ReverseJournalEntryRequest $request, JournalEntry $entry): RedirectResponse
    {
        $this->accountingService->reverseJournalEntry($entry->id, $request->validated()['reason']);
        return redirect()->route('accounting.journal.show', $entry)
            ->with('success', 'Journal entry reversed successfully.');
    }
}
```

- [ ] **Step 2: Create BudgetController, ReconciliationController, ReportController**

Follow same pattern, extracting relevant methods.

- [ ] **Step 3: Update routes**

```php
// routes/web.php
Route::prefix('accounting')->name('accounting.')->group(function () {
    Route::get('/journal', [JournalController::class, 'index'])->name('journal.index');
    Route::post('/journal', [JournalController::class, 'store'])->name('journal.store');
    Route::get('/journal/{entry}', [JournalController::class, 'show'])->name('journal.show');
    Route::post('/journal/{entry}/reverse', [JournalController::class, 'reverse'])->name('journal.reverse');

    Route::get('/budget', [BudgetController::class, 'index'])->name('budget.index');
    Route::post('/budget', [BudgetController::class, 'store'])->name('budget.store');
    // ... etc
});
```

- [ ] **Step 4: Keep AccountingController as facade for backward compatibility**

```php
<?php

namespace App\Http\Controllers;

/**
 * @deprecated Use specific controllers: JournalController, BudgetController, etc.
 */
class AccountingController extends Controller
{
    // Delegate to specific controllers or remove entirely
}
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --compact --filter=Accounting`
Expected: All tests pass

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Accounting/ routes/web.php
git commit -m "refactor(controllers): split AccountingController into focused controllers"
```

### Task 9: Split CustomerController and Fix N+1 Queries

**Files:**
- Create: `app/Http/Controllers/Customer/CustomerSearchController.php`
- Modify: `app/Http/Controllers/CustomerController.php`
- Modify: `app/Http/Controllers/Api/V1/CustomerController.php`
- Modify: `app/Http/Controllers/UserController.php`
- Modify: `app/Http/Controllers/TransactionController.php`

- [ ] **Step 1: Extract search/quickCreate to CustomerSearchController**

```php
<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Customer\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerSearchController extends Controller
{
    public function __construct(
        protected CustomerService $customerService,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|min:2',
        ]);

        $results = $this->customerService->searchCustomers($validated['query']);

        return response()->json([
            'success' => true,
            'results' => $results,
        ]);
    }

    public function quickCreate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'id_type' => 'required|in:MyKad,Passport,Others',
            'id_number' => 'required|string|max:50',
            'date_of_birth' => 'required|date|before:today',
            'nationality' => 'required|string|max:100',
        ]);

        $customer = $this->customerService->createCustomer($validated, auth()->id());

        return response()->json([
            'success' => true,
            'customer' => $customer,
        ]);
    }
}
```

- [ ] **Step 2: Fix N+1 queries in UserController**

```php
// Before
public function index(): View
{
    $users = User::paginate(20)->withQueryString();
    return view('pages.users.index', compact('users'));
}

// After
public function index(): View
{
    $users = User::with('branch')->paginate(20)->withQueryString();
    return view('pages.users.index', compact('users'));
}
```

- [ ] **Step 3: Fix N+1 queries in TransactionController**

```php
// Before
$transactions = $query->orderBy('created_at', 'desc')->paginate(50)->withQueryString();

// After
$transactions = $query->with(['customer', 'currency', 'branch', 'user'])
    ->orderBy('created_at', 'desc')
    ->paginate(50)
    ->withQueryString();
```

- [ ] **Step 4: Run tests**

Run: `php artisan test --compact --filter=CustomerController`
Run: `php artisan test --compact --filter=UserController`
Run: `php artisan test --compact --filter=TransactionController`
Expected: All tests pass

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Customer/ app/Http/Controllers/CustomerController.php app/Http/Controllers/UserController.php app/Http/Controllers/TransactionController.php
git commit -m "refactor(controllers): split CustomerController and fix N+1 queries"
```

### Task 10: Standardize API Responses with Resources

**Files:**
- Create: `app/Http/Resources/Api/V1/Compliance/AlertResource.php`
- Create: `app/Http/Resources/Api/V1/Compliance/CaseResource.php`
- Create: `app/Http/Resources/Api/V1/Compliance/FindingResource.php`
- Create: `app/Http/Resources/Api/V1/Compliance/EddResource.php`
- Modify: `app/Http/Controllers/Api/V1/Compliance/AlertController.php`
- Modify: `app/Http/Controllers/Api/V1/Compliance/CaseController.php`
- Modify: `app/Http/Controllers/Api/V1/Compliance/FindingController.php`
- Modify: `app/Http/Controllers/Api/V1/Compliance/EddController.php`

- [ ] **Step 1: Create AlertResource**

```php
<?php

namespace App\Http\Resources\Api\V1\Compliance;

use Illuminate\Http\Resources\Json\JsonResource;

class AlertResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'alert_type' => $this->alert_type,
            'severity' => $this->severity,
            'status' => $this->status,
            'description' => $this->description,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
```

- [ ] **Step 2: Create CaseResource, FindingResource, EddResource**

Follow same pattern.

- [ ] **Step 3: Update controllers to use Resources**

```php
// Before
return response()->json([
    'success' => true,
    'data' => $alerts,
]);

// After
return response()->json([
    'success' => true,
    'data' => AlertResource::collection($alerts),
]);
```

- [ ] **Step 4: Run tests**

- [ ] **Step 5: Commit**

```bash
git add app/Http/Resources/Api/V1/Compliance/ app/Http/Controllers/Api/V1/Compliance/
git commit -m "refactor(api): add API Resources for Compliance endpoints"
```

---

## Success Metrics

| Metric | Before | Target |
|--------|--------|--------|
| Controllers with inline validation | 21 | 0 |
| Generic exception catches | 30+ | <5 |
| Controllers with authorization | 12 | 20+ |
| N+1 query risks | 3+ | 0 |
| God controllers (>20 methods) | 5 | 0 |

## Commit Strategy

Each phase should be committed separately:
1. Phase 1: `refactor(exceptions): extract FormRequests for all controllers`
2. Phase 2: `feat(auth): add authorization policies for core resources`
3. Phase 3: `refactor(exceptions): replace generic catches with domain exceptions`
4. Phase 4: `refactor(controllers): split god controllers and fix N+1 queries`
