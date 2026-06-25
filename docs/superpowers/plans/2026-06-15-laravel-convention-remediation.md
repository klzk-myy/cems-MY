# Laravel Convention Remediation Implementation Plan

> **For agentic workers:** REQUIRED SUB-_SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remediate the Laravel convention violations identified in the codebase audit so the project consistently follows Laravel/AGENTS.md patterns, passes `php artisan routes:validate`, and remains fully testable.

**Architecture:** Apply targeted, incremental refactors grouped by violation category. Each category is isolated: controller signatures, Form Requests, API Resources, casts, Eloquent queries, eager loading, routes, Tailwind classes, comments, and constructors. Every task includes a regression test run so no phase breaks the existing 893-test suite.

**Tech Stack:** PHP 8.3, Laravel 11, PHPUnit 10, Tailwind CSS v4, Laravel Pint, Laravel Horizon, SQLite (tests).

---

## File map

| Category | Primary files touched |
|---|---|
| Controller return/param types | `app/Http/Controllers/**/*.php` |
| Form Requests | Create `app/Http/Requests/**/*.php`; modify controllers |
| API Resources | Create `app/Http/Resources/Api/V1/**/*.php`; modify `app/Http/Controllers/Api/V1/**/*.php` |
| Money casts | `app/Models/Transaction.php`, `app/Models/TellerAllocation.php`, `app/Models/CurrencyPosition.php`, `app/Models/TillBalance.php`, `app/Models/Customer.php` |
| Raw DB → Eloquent | `app/Http/Controllers/Api/V1/CustomerController.php`, `app/Http/Controllers/Api/V1/Compliance/RiskController.php`, `app/Services/Compliance/*.php`, `app/Services/AlertTriageService.php`, `app/Http/Controllers/SetupController.php` |
| Eager loading / N+1 | `app/Services/BranchClosingService.php`, `app/Services/CounterService.php`, `app/Services/TransactionRecoveryService.php`, `app/Http/Controllers/CustomerController.php` |
| Routes | `routes/web.php`, `routes/api_v1.php`; create `app/Http/Controllers/HomeController.php`, `app/Http/Controllers/Api/V1/CurrentUserController.php` |
| Tailwind v4 | `resources/views/users/show.blade.php`, `resources/views/components/alert.blade.php`, `resources/views/components/navigation.blade.php`, `resources/views/components/card-section.blade.php` |
| Comments / PHPDoc | `app/Http/Controllers/**/*.php` |
| Empty constructors | `app/Jobs/Sanctions/BaseSanctionsDownloadJob.php`, `app/Exceptions/Domain/SelfApprovalException.php`, `app/Services/BranchPoolService.php`, `app/Services/ExportService.php`, `app/Services/EncryptionService.php` |

---

## Phase 1: Controller signatures (return types + type hints)

### Task 1.1: Audit all controller methods without return types

**Files:**
- Modify: `app/Http/Controllers/SetupController.php`
- Modify: `app/Http/Controllers/StockTransferController.php`
- Modify: `app/Http/Controllers/StockCashController.php`
- Modify: `app/Http/Controllers/Auth/LoginController.php`
- Modify: `app/Http/Controllers/Report/AnalyticsController.php`
- Modify: `app/Http/Controllers/Report/RegulatoryReportController.php`
- Modify: all remaining `app/Http/Controllers/**/*.php`

- [ ] **Step 1: Add return types to `SetupController` methods**

Open `app/Http/Controllers/SetupController.php`. For every public controller action, add an explicit return type. Common return types are `Illuminate\Http\RedirectResponse`, `Illuminate\View\View`, or `Illuminate\Http\Response`.

Example transformation:

```php
// Before
public function quickSetup(Request $request)
{
    // ...
}

// After
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

public function quickSetup(Request $request): RedirectResponse
{
    // ...
}
```

Repeat for every public method in `SetupController` (`step1CompanyInfo`, `step2AdminUser`, etc.) using the return type that matches the existing `return redirect(...)` or `return view(...)` statement.

- [ ] **Step 2: Add return types to `StockTransferController`, `StockCashController`, `Auth/LoginController`, `Report/*Controller`**

Apply the same pattern as Step 1. Use `View` for methods that `return view(...)`, `RedirectResponse` for methods that `return redirect(...)`, `JsonResponse` for methods that `return response()->json(...)`, and `Response` for raw responses.

- [ ] **Step 3: Type-hint untyped parameters**

Open each file listed in the audit and add type hints to parameters that are currently bare variables.

Examples:

```php
// app/Http/Controllers/Report/AnalyticsController.php:74
// Before
private function calculateTrends($data)

// After
private function calculateTrends(array $data): array
```

```php
// app/Http/Controllers/TransactionWizardController.php:252
// Before
private function processDocuments($request)

// After
use Illuminate\Http\Request;

private function processDocuments(Request $request): void
```

```php
// app/Http/Controllers/MfaController.php:254
// Before
public function removeDevice($deviceId)

// After
public function removeDevice(int $deviceId): RedirectResponse
```

```php
// app/Http/Controllers/Api/V1/TransactionCancellationController.php:175
// Before
private function canRequestCancellation($user)

// After
use App\Models\User;

private function canRequestCancellation(User $user): bool
```

```php
// app/Http/Controllers/RateController.php:153
// Before
private function resolveBranchId($user)

// After
use App\Models\User;

private function resolveBranchId(User $user): int
```

- [ ] **Step 4: Run tests and Pint**

```bash
APP_ENV=testing php artisan test --compact
vendor/bin/pint --dirty --format agent
```

Expected: all tests pass, Pint passes.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers
git commit -m "style: add explicit return types and parameter hints to controllers"
```

---

## Phase 2: Inline validation → Form Request classes

### Task 2.1: Extract validation from `TransactionController`

**Files:**
- Create: `app/Http/Requests/StoreTransactionRequest.php`
- Modify: `app/Http/Controllers/TransactionController.php:47`
- Modify: `app/Http/Controllers/TransactionController.php:107`

- [ ] **Step 1: Create `StoreTransactionRequest`**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:buy,sell'],
            'currency_code' => ['required', 'string', 'exists:currencies,code'],
            'amount_foreign' => ['required', 'numeric', 'min:0.01'],
            'rate' => ['required', 'numeric', 'min:0'],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'purpose' => ['required', 'string', 'max:255'],
            'source_of_funds' => ['required', 'string', 'max:255'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'counter_id' => ['required', 'integer', 'exists:counters,id'],
            'idempotency_key' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount_foreign.min' => 'The transaction amount must be greater than zero.',
        ];
    }
}
```

- [ ] **Step 2: Replace inline validation in `TransactionController::store`**

```php
use App\Http\Requests\StoreTransactionRequest;

public function store(StoreTransactionRequest $request): RedirectResponse
{
    $validated = $request->validated();
    // ... existing logic using $validated instead of $request->validate(...)
}
```

- [ ] **Step 3: Create request for the second validation block and update the controller**

Create a second request class (e.g. `UpdateTransactionRequest` or `CancelTransactionRequest`) matching the rules at `TransactionController.php:107`. Type-hint the controller method with that request and use `$request->validated()`.

### Task 2.2: Extract validation from `AccountingController`

**Files:**
- Create: `app/Http/Requests/Accounting/StoreJournalEntryRequest.php`
- Create: `app/Http/Requests/Accounting/ReverseJournalEntryRequest.php`
- Create: `app/Http/Requests/Accounting/ClosePeriodRequest.php`
- Create: `app/Http/Requests/Accounting/StoreBudgetRequest.php`
- Create: `app/Http/Requests/Accounting/ImportBankStatementRequest.php`
- Create: `app/Http/Requests/Accounting/MarkReconciliationExceptionRequest.php`
- Modify: `app/Http/Controllers/AccountingController.php`

- [ ] **Step 1: Create request classes**

For each inline `$request->validate([...])` block in `AccountingController`, create a matching Form Request. Copy the rules array verbatim into the `rules()` method and add appropriate `authorize()` logic (e.g. `return auth()->check();` or a gate check).

Example `StoreJournalEntryRequest`:

```php
<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class StoreJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            // paste exact rules from AccountingController::store here
        ];
    }
}
```

- [ ] **Step 2: Update `AccountingController` method signatures**

Replace every inline validation call with the new request class:

```php
use App\Http\Requests\Accounting\StoreJournalEntryRequest;

public function store(StoreJournalEntryRequest $request): RedirectResponse
{
    $validated = $request->validated();
    // ...
}
```

Repeat for `reverse`, `closePeriod`, `storeBudget`, `importBankStatement`, and `markAsException`.

### Task 2.3: Extract validation from `MfaController`

**Files:**
- Create: `app/Http/Requests/Mfa/SetupMfaRequest.php`
- Create: `app/Http/Requests/Mfa/VerifyMfaRequest.php`
- Create: `app/Http/Requests/Mfa/DisableMfaRequest.php`
- Create: `app/Http/Requests/Mfa/VerifyRecoveryCodeRequest.php`
- Modify: `app/Http/Controllers/MfaController.php`

- [ ] **Step 1: Create request classes and update controller**

Follow the same pattern as Task 2.1/2.2. Replace inline validation in `setupStore`, `verifyStore`, `disable`, and `recoveryVerify` with the corresponding request classes.

### Task 2.4: Extract validation from API controllers

**Files:**
- Create: `app/Http/Requests/Api/V1/Counter/CloseCounterRequest.php`
- Create: `app/Http/Requests/Api/V1/Rate/CopyPreviousRateRequest.php`
- Create: `app/Http/Requests/Api/V1/Rate/CheckRateSetRequest.php`
- Create: `app/Http/Requests/Api/V1/Rate/ValidateRateRequest.php`
- Modify: `app/Http/Controllers/Api/V1/CounterApiController.php`
- Modify: `app/Http/Controllers/Api/V1/RateController.php`

- [x] **Step 1: Replace `Validator::make` in `CounterApiController`**

Create `CloseCounterRequest` with the rules from `CounterApiController.php:21`. Type-hint the controller method and use `$request->validated()`.

- [x] **Step 2: Replace inline validation in `RateController`**

Create the three Rate request classes. Replace the validation blocks at lines 143, 199, and 218 with the corresponding request classes.

- [x] **Step 3: Run tests and Pint**

```bash
APP_ENV=testing php artisan test --compact
vendor/bin/pint --dirty --format agent
```

Expected: all tests pass, Pint passes.

- [x] **Step 4: Commit**

```bash
git add app/Http/Requests app/Http/Controllers
git commit -m "refactor: move inline validation into Form Request classes"
```

---

## Phase 3: API Resources for v1 API responses

### Task 3.1: Create resource classes

**Files:**
- Create: `app/Http/Resources/Api/V1/UserResource.php`
- Create: `app/Http/Resources/Api/V1/TransactionResource.php`
- Create: `app/Http/Resources/Api/V1/TransactionCollection.php`
- Create: `app/Http/Resources/Api/V1/CustomerResource.php`
- Create: `app/Http/Resources/Api/V1/CustomerCollection.php`
- Create: `app/Http/Resources/Api/V1/Compliance/CaseResource.php`
- Create: `app/Http/Resources/Api/V1/Compliance/CaseCollection.php`

- [x] **Step 1: Create `UserResource`**

```php
<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
```

- [x] **Step 2: Create `TransactionResource`**

```php
<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'currency_code' => $this->currency_code,
            'amount_foreign' => $this->amount_foreign,
            'amount_local' => $this->amount_local,
            'rate' => $this->rate,
            'status' => $this->status,
            'customer_id' => $this->customer_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
```

- [x] **Step 3: Create `TransactionCollection`**

```php
<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class TransactionCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => TransactionResource::collection($this->collection),
        ];
    }
}
```

- [x] **Step 4: Create `CustomerResource`, `CustomerCollection`, `Compliance/CaseResource`, `Compliance/CaseCollection`**

Follow the same pattern. Include only the fields currently returned by the corresponding controllers.

### Task 3.2: Wire resources into API controllers

**Files:**
- Modify: `routes/api_v1.php:44`
- Modify: `app/Http/Controllers/Api/V1/TransactionController.php`
- Modify: `app/Http/Controllers/Api/V1/CustomerController.php`
- Modify: `app/Http/Controllers/Api/V1/Compliance/CaseController.php`

- [x] **Step 1: Replace closure `/user` route**

Create `app/Http/Controllers/Api/V1/CurrentUserController.php`:

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\Request;

class CurrentUserController extends Controller
{
    public function __invoke(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
```

Update `routes/api_v1.php`:

```php
use App\Http\Controllers\Api\V1\CurrentUserController;

Route::middleware('auth:sanctum')->get('/user', CurrentUserController::class)->name('api.v1.user');
```

- [x] **Step 2: Update `Api/V1/TransactionController`**

Replace raw model returns with resources:

```php
use App\Http\Resources\Api\V1\TransactionResource;
use App\Http\Resources\Api\V1\TransactionCollection;

public function index(Request $request): TransactionCollection
{
    $transactions = Transaction::query()->paginate();

    return new TransactionCollection($transactions);
}

public function show(Transaction $transaction): TransactionResource
{
    return new TransactionResource($transaction);
}
```

- [x] **Step 3: Update `Api/V1/CustomerController`**

```php
use App\Http\Resources\Api\V1\CustomerResource;
use App\Http\Resources\Api\V1\CustomerCollection;

public function index(Request $request): CustomerCollection
{
    $customers = Customer::query()->paginate();

    return new CustomerCollection($customers);
}

public function show(Customer $customer): CustomerResource
{
    return new CustomerResource($customer->load(['documents', 'transactions']));
}
```

- [x] **Step 4: Update `Api/V1/Compliance/CaseController`**

```php
use App\Http\Resources\Api\V1\Compliance\CaseResource;
use App\Http\Resources\Api\V1\Compliance\CaseCollection;

public function index(Request $request): CaseCollection
{
    $cases = ComplianceCase::query()->paginate();

    return new CaseCollection($cases);
}
```

- [x] **Step 5: Run API feature tests and Pint**

```bash
APP_ENV=testing php artisan test --compact tests/Feature/Api
vendor/bin/pint --dirty --format agent
```

Expected: API tests pass, Pint passes.

- [x] **Step 6: Commit**

```bash
git add app/Http/Resources app/Http/Controllers/Api routes/api_v1.php
git commit -m "refactor: introduce API Resources for v1 API responses"
```

---

## Phase 4: Replace `decimal:*` money casts with `MoneyCast`

### Task 4.1: Apply `MoneyCast` to monetary models

**Files:**
- Modify: `app/Models/Transaction.php:103-107`
- Modify: `app/Models/TellerAllocation.php`
- Modify: `app/Models/CurrencyPosition.php`
- Modify: `app/Models/TillBalance.php`
- Modify: `app/Models/Customer.php`

- [ ] **Step 1: Update `Transaction` casts**

```php
use App\Casts\MoneyCast;

protected $casts = [
    'amount_local' => MoneyCast::class,
    'amount_foreign' => MoneyCast::class,
    'rate' => MoneyCast::class,        // default scale 4; rates need 6 decimals
    'base_rate' => MoneyCast::class,
    // ... keep other casts
];
```

If rates need 6 decimals, extend `MoneyCast` to accept a scale argument or create a `RateCast` (see Step 3).

- [ ] **Step 2: Update the other monetary models**

For each model, replace `'field' => 'decimal:N'` with `'field' => MoneyCast::class`.

- [ ] **Step 3: (Optional) Add configurable scale to `MoneyCast`**

If `MoneyCast` does not already accept a scale argument, modify `app/Casts/MoneyCast.php`:

```php
public function __construct(protected int $scale = 4) {}
```

Then use `MoneyCast::class.':6'` syntax in casts if Laravel supports cast arguments (it does in Laravel 11):

```php
'rate' => MoneyCast::class.':6',
'base_rate' => MoneyCast::class.':6',
```

- [ ] **Step 4: Run model tests and full suite**

```bash
APP_ENV=testing php artisan test --compact
vendor/bin/pint --dirty --format agent
```

Expected: all tests pass.

- [ ] **Step 5: Commit**

```bash
git add app/Models app/Casts
if [ -d app/Casts ]; then git add app/Casts; fi
git commit -m "refactor: use MoneyCast for monetary fields"
```

---

## Phase 5: Raw `DB::table()` → Eloquent

### Task 5.1: Convert simple reads/writes

**Files:**
- Modify: `app/Http/Controllers/Api/V1/CustomerController.php:105-108`
- Modify: `app/Http/Controllers/Api/V1/Compliance/RiskController.php:140-152`
- Modify: `app/Services/Compliance/RiskScoringEngine.php:204`
- Modify: `app/Services/ComplianceService.php:201`
- Modify: `app/Services/AlertTriageService.php:243`
- Modify: `app/Http/Controllers/SetupController.php:443`

- [ ] **Step 1: Replace customer stats query with Eloquent**

If the query is `DB::table('transactions')->selectRaw(...)->where('customer_id', ...)->get()`, replace it with:

```php
use App\Models\Transaction;

$stats = Transaction::query()
    ->selectRaw('SUM(amount_local) as total_local, COUNT(*) as count')
    ->where('customer_id', $customerId)
    ->first();
```

- [ ] **Step 2: Replace risk tier aggregation with Eloquent**

```php
use App\Models\CustomerRiskProfile;

$distribution = CustomerRiskProfile::query()
    ->selectRaw('risk_tier, COUNT(*) as count')
    ->groupBy('risk_tier')
    ->pluck('count', 'risk_tier')
    ->toArray();
```

- [ ] **Step 3: Replace simple existence/insert queries**

For each remaining `DB::table(...)->where(...)->exists()`:

```php
// Before
DB::table('high_risk_countries')->where('code', $code)->exists()

// After
HighRiskCountry::query()->where('code', $code)->exists()
```

For each `DB::table(...)->insert([...])`:

```php
// Before
DB::table('branch_pools')->insert([...]);

// After
BranchPool::create([...]);
```

- [ ] **Step 4: Run tests and Pint**

```bash
APP_ENV=testing php artisan test --compact
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers app/Services
if [ -d app/Services ]; then git add app/Services; fi
git commit -m "refactor: replace simple DB::table calls with Eloquent"
```

---

## Phase 6: Eager loading / N+1 fixes

### Task 6.1: Add eager loading where iterations re-query relations

**Files:**
- Modify: `app/Services/BranchClosingService.php:77-84`
- Modify: `app/Services/CounterService.php:498-505`
- Modify: `app/Services/TransactionRecoveryService.php:113-119`
- Modify: `app/Http/Controllers/CustomerController.php:268-279`

- [ ] **Step 1: Eager-load relations before iteration**

In `BranchClosingService`:

```php
$allocations = TellerAllocation::query()
    ->with(['counter', 'user']) // add relations used inside returnToPool
    ->where('branch_id', $branchId)
    ->where('status', TellerAllocationStatus::ACTIVE)
    ->get();

foreach ($allocations as $allocation) {
    $this->returnToPool($allocation);
}
```

In `CounterService`:

```php
$allocations = TellerAllocation::query()
    ->with(['counter', 'user', 'branch'])
    ->where(...)
    ->get();
```

In `TransactionRecoveryService`:

```php
$transactions = Transaction::query()
    ->with('transactionErrors')
    ->whereHas('transactionErrors', function ($query) {
        // ...
    })
    ->get();
```

- [ ] **Step 2: Consolidate repeated aggregates in `CustomerController::show`**

Before:

```php
$transactionCount = $customer->transactions()->count();
$transactionTotal = $customer->transactions()->sum('amount_local');
$transactionAvg = $customer->transactions()->avg('amount_local');
$documentCount = $customer->documents()->count();
```

After:

```php
$customer->loadCount(['transactions', 'documents']);
$customer->loadSum('transactions', 'amount_local');
$customer->loadAvg('transactions', 'amount_local');

$transactionCount = $customer->transactions_count;
$transactionTotal = $customer->transactions_sum_amount_local;
$transactionAvg = $customer->transactions_avg_amount_local;
$documentCount = $customer->documents_count;
```

- [ ] **Step 3: Run tests and Pint**

```bash
APP_ENV=testing php artisan test --compact
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 4: Commit**

```bash
git add app/Services app/Http/Controllers
if [ -d app/Services ]; then git add app/Services; fi
git commit -m "perf: add eager loading and consolidate aggregate queries"
```

---

## Phase 7: Routes — closures, names, and Horizon errors

### Task 7.1: Move closures to controllers

**Files:**
- Create: `app/Http/Controllers/HomeController.php`
- Create: `app/Http/Controllers/Api/V1/CurrentUserController.php` (already created in Phase 3)
- Modify: `routes/web.php:39`
- Modify: `routes/api_v1.php:44`

- [ ] **Step 1: Create `HomeController`**

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('home');
    }
}
```

- [ ] **Step 2: Replace web home closure**

```php
use App\Http\Controllers\HomeController;

Route::get('/', HomeController::class)->name('home');
```

- [ ] **Step 3: Replace `/user` closure (if not done in Phase 3)**

Use `CurrentUserController` and `UserResource` from Phase 3.

### Task 7.2: Name unnamed API routes

**Files:**
- Modify: `routes/api_v1.php`

- [ ] **Step 1: Add `->name(...)` to rate routes**

Example:

```php
Route::get('/rates/dates', [RateController::class, 'dates'])
    ->name('api.v1.rates.dates');

Route::get('/rates/history/{currencyCode}', [RateController::class, 'history'])
    ->name('api.v1.rates.history');
```

Repeat for all 100 unnamed routes, using the convention `api.v1.{resource}.{action}`.

### Task 7.3: Horizon errors

**Files:**
- None (vendor/package issue)

- [ ] **Step 1: Verify Horizon assets are published**

```bash
php artisan horizon:publish
```

If the view error persists after publish, the error is cosmetic in `routes:validate` and can be documented. Do not modify vendor files.

- [ ] **Step 2: Run route validation**

```bash
php artisan routes:validate
```

Expected: closure warnings gone; unnamed route warnings reduced; Horizon errors remain if vendor view is missing, otherwise resolved.

- [ ] **Step 3: Run tests and Pint**

```bash
APP_ENV=testing php artisan test --compact
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers routes
if [ -d app/Http/Controllers ]; then git add app/Http/Controllers; fi
git commit -m "refactor: move route closures to controllers and name API routes"
```

---

## Phase 8: Tailwind v4 deprecated utilities

### Task 8.1: Replace `flex-shrink-0` with `shrink-0`

**Files:**
- Modify: `resources/views/users/show.blade.php`
- Modify: `resources/views/components/alert.blade.php`
- Modify: `resources/views/components/navigation.blade.php`
- Modify: `resources/views/components/card-section.blade.php`

- [ ] **Step 1: Replace class strings**

```bash
sed -i 's/flex-shrink-0/shrink-0/g' resources/views/users/show.blade.php
sed -i 's/flex-shrink-0/shrink-0/g' resources/views/components/alert.blade.php
sed -i 's/flex-shrink-0/shrink-0/g' resources/views/components/navigation.blade.php
sed -i 's/flex-shrink-0/shrink-0/g' resources/views/components/card-section.blade.php
```

- [ ] **Step 2: Build assets**

```bash
npm run build
```

Expected: build succeeds with no Tailwind warnings.

- [ ] **Step 3: Commit**

```bash
git add resources/views
if [ -d public/build ]; then git add public/build; fi
git commit -m "style: replace deprecated flex-shrink-0 with shrink-0"
```

---

## Phase 9: Inline comments → PHPDoc / extracted methods

### Task 9.1: Refactor comment-heavy controllers

**Files:**
- Modify: `app/Http/Controllers/Transaction/TransactionApprovalController.php`
- Modify: `app/Http/Controllers/TransactionController.php`
- Modify: `app/Http/Controllers/MfaController.php`
- Modify: `app/Http/Controllers/DashboardController.php`
- Modify: `app/Http/Controllers/Api/V1/TransactionCancellationController.php`

- [ ] **Step 1: Convert business-rule comments to PHPDoc summaries**

Before:

```php
public function approve(Request $request, Transaction $transaction)
{
    // Only managers can approve transactions for their own branch
    // ...
}
```

After:

```php
/**
 * Approve a transaction for the teller's branch.
 *
 * Only managers may approve transactions, and only within their own branch.
 */
public function approve(Request $request, Transaction $transaction): RedirectResponse
{
    // ...
}
```

- [ ] **Step 2: Extract complex guard clauses into private methods**

Before:

```php
// Ensure the transaction is in a pending approval state and the user is a manager
if ($transaction->status !== TransactionStatus::PendingApproval || $request->user()->role !== UserRole::Manager) {
    abort(403);
}
```

After:

```php
private function ensureCanApprove(Transaction $transaction, User $user): void
{
    if ($transaction->status !== TransactionStatus::PendingApproval) {
        abort(403, 'Transaction is not pending approval.');
    }

    if ($user->role !== UserRole::Manager) {
        abort(403, 'Only managers can approve transactions.');
    }
}
```

Call ` $this->ensureCanApprove($transaction, $request->user());` at the top of the action.

- [ ] **Step 3: Run tests and Pint**

```bash
APP_ENV=testing php artisan test --compact
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers
git commit -m "docs: replace inline comments with PHPDoc and extracted guards"
```

---

## Phase 10: Remove empty constructors

### Task 10.1: Clean up empty constructors

**Files:**
- Modify: `app/Jobs/Sanctions/BaseSanctionsDownloadJob.php:40`
- Modify: `app/Exceptions/Domain/SelfApprovalException.php:9`
- Modify: `app/Services/BranchPoolService.php:15`
- Modify: `app/Services/ExportService.php:13`
- Modify: `app/Services/EncryptionService.php:9`

- [ ] **Step 1: Remove empty constructors**

For each file, delete the empty `public function __construct() {}` block entirely.

Example:

```php
// Before
class BranchPoolService
{
    public function __construct()
    {
    }

    // ...
}

// After
class BranchPoolService
{
    // ...
}
```

If a constructor is intentionally preventing direct instantiation, make it `private` instead of removing it.

- [ ] **Step 2: Run tests and Pint**

```bash
APP_ENV=testing php artisan test --compact
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 3: Commit**

```bash
git add app/Jobs app/Exceptions app/Services
git commit -m "style: remove empty constructors"
```

---

## Final verification

- [ ] **Step 1: Run full test suite**

```bash
APP_ENV=testing php artisan test --compact
```

Expected: `Tests: 893 passed` (or current count, no failures).

- [ ] **Step 2: Run style checker**

```bash
vendor/bin/pint --test --format agent
```

Expected: `{"tool":"pint","result":"passed"}`

- [ ] **Step 3: Validate routes**

```bash
php artisan routes:validate
```

Expected: no closure routes, significantly fewer unnamed routes, and the Horizon vendor errors resolved if `horizon:publish` fixed them.

- [ ] **Step 4: Refresh GitNexus index**

```bash
npx gitnexus analyze /www/wwwroot/local.host
```

- [ ] **Step 5: Push all commits**

```bash
git push origin main
```

---

## Execution handoff

Plan saved to `docs/superpowers/plans/2026-06-15-laravel-convention-remediation.md`.

Two execution options:

1. **Subagent-Driven (recommended)** — I dispatch a fresh subagent per phase, review between phases, fast iteration.
2. **Inline Execution** — Execute tasks in this session with checkpoints for review.

Which approach do you want?