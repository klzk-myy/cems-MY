# Controller Audit Fixes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix type safety bugs, dead code, N+1 queries, missing Form Requests, and long methods across all controllers.

**Architecture:** Four phases by risk tier: (1) critical/high bug fixes, (2) N+1 and performance fixes, (3) missing Form Request extraction, (4) long method refactoring. Each phase produces working, testable code.

**Tech Stack:** Laravel 10, PHP 8.3, PHPUnit 10, MySQL 8.0

## Global Constraints

- PHP 8.3.30, Laravel 10, PHPUnit 10
- Run `vendor/bin/pint --dirty --format agent` before every commit
- Run affected tests after each task: `php artisan test --compact --filter=<TestName>`
- Preserve exact API response shapes — no breaking changes
- Follow existing code conventions — check sibling files for patterns
- Use `php artisan make:test --phpunit` for new tests

---

## Phase 1: Critical & High-Priority Fixes

### Task 1: Fix RiskController type safety bug

**Files:**
- Modify: `app/Http/Controllers/Api/V1/Compliance/RiskController.php:130-139`

**Context:** `findProfileOrFail()` declares return type `CustomerRiskProfile` but returns `response()->json(...)` on failure. This violates PHP's type system — callers expect `CustomerRiskProfile` but receive `JsonResponse`.

- [ ] **Step 1: Fix the method to throw an exception instead**

Replace the `findProfileOrFail()` method:

```php
private function findProfileOrFail(string $customerId): CustomerRiskProfile
{
    $profile = CustomerRiskProfile::where('customer_id', (int) $customerId)->first();

    if (! $profile) {
        abort(404, 'Risk profile not found.');
    }

    return $profile;
}
```

- [ ] **Step 2: Run tests**

```bash
php artisan test --compact --filter=RiskController
```

Expected: PASS (or no tests exist).

- [ ] **Step 3: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/V1/Compliance/RiskController.php
git commit -m "fix: RiskController findProfileOrFail type safety — throw abort instead of returning JsonResponse"
```

---

### Task 2: Remove unused constructor injections from TransactionApprovalController

**Files:**
- Modify: `app/Http/Controllers/Api/V1/TransactionApprovalController.php:1-29`

**Context:** 6 of 7 injected services are never used. Only `TransactionApprovalService` is called.

- [ ] **Step 1: Remove unused imports and constructor parameters**

Edit `app/Http/Controllers/Api/V1/TransactionApprovalController.php`:

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Domain\SelfApprovalException;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\Transaction\TransactionApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TransactionApprovalController extends Controller
{
    public function __construct(
        protected TransactionApprovalService $approvalService
    ) {}
```

- [ ] **Step 2: Run tests**

```bash
php artisan test --compact --filter=TransactionApproval
```

Expected: PASS.

- [ ] **Step 3: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/V1/TransactionApprovalController.php
git commit -m "fix: remove 6 unused constructor injections from API TransactionApprovalController"
```

---

### Task 3: Move RateController::apiOverride DB write to service

**Files:**
- Modify: `app/Http/Controllers/Api/V1/RateController.php:111-126`

**Context:** `apiOverride()` directly calls `ExchangeRate::updateOrCreate()` instead of using the injected `RateManagementService`. The controller should delegate to the service.

- [ ] **Step 1: Check if RateManagementService has an override method**

```bash
grep -n "override\|updateOrCreate\|setRate" app/Services/RateManagementService.php | head -10
```

If a method exists, use it. If not, add one.

- [ ] **Step 2: Add override method to RateManagementService (if needed)**

If no existing method, add to `app/Services/RateManagementService.php`:

```php
public function overrideRate(string $currencyCode, float $buyRate, float $sellRate): \App\Models\ExchangeRate
{
    return ExchangeRate::updateOrCreate(
        ['currency_code' => $currencyCode],
        [
            'rate_buy' => $buyRate,
            'rate_sell' => $sellRate,
            'source' => 'manual',
            'fetched_at' => now(),
        ]
    );
}
```

- [ ] **Step 3: Update the controller to use the service**

```php
public function apiOverride(OverrideRateRequest $request, string $currencyCode): JsonResponse
{
    $validated = $request->validated();

    $rate = $this->rateService->overrideRate(
        $currencyCode,
        $validated['rate_buy'],
        $validated['rate_sell']
    );

    return response()->json(['success' => true, 'message' => 'Rate override saved.', 'data' => $rate]);
}
```

- [ ] **Step 4: Run tests**

```bash
php artisan test --compact --filter=RateController
```

- [ ] **Step 5: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/V1/RateController.php app/Services/RateManagementService.php
git commit -m "fix: delegate RateController::apiOverride DB write to RateManagementService"
```

---

### Task 4: Fix AnalyticsController N+1 in profitability loop

**Files:**
- Modify: `app/Http/Controllers/Report/AnalyticsController.php:100-196`

**Context:** `calculateCurrencyProfitability()` runs 2 queries per currency (sells + buys) inside a map loop. For N currencies = 2*N queries. Should batch into single queries.

- [ ] **Step 1: Batch the transaction queries**

Replace the profitability method to batch queries:

```php
public function profitability(Request $request): View
{
    $this->requireManagerOrAdmin();

    $startDate = $request->input('start_date', now()->subMonth()->startOfMonth()->toDateString());
    $endDate = $request->input('end_date', now()->subMonth()->endOfMonth()->toDateString());

    $positionModels = CurrencyPosition::with('currency')->get();
    $currencyCodes = $positionModels->pluck('currency_code')->unique();
    $rates = $this->getCurrentRates($currencyCodes);

    // Batch: fetch all sell transactions for all currencies in one query
    $allSells = Transaction::whereIn('currency_code', $currencyCodes)
        ->where('type', TransactionType::Sell)
        ->where('status', TransactionStatus::Completed)
        ->whereBetween('created_at', [$startDate, $endDate])
        ->select('currency_code', 'rate', 'amount_foreign', 'amount_local')
        ->get()
        ->groupBy('currency_code');

    // Batch: fetch all buy volumes for all currencies in one query
    $buyVolumes = Transaction::whereIn('currency_code', $currencyCodes)
        ->where('type', TransactionType::Buy)
        ->where('status', TransactionStatus::Completed)
        ->whereBetween('created_at', [$startDate, $endDate])
        ->select('currency_code', DB::raw('SUM(amount_local) as buy_volume'))
        ->groupBy('currency_code')
        ->pluck('buy_volume', 'currency_code');

    $positions = $positionModels->map(function ($position) use ($rates, $allSells, $buyVolumes, $startDate, $endDate) {
        $currentRate = $rates[$position->currency_code] ?? 0;
        $avgCost = (string) $position->average_cost;
        $balance = (string) $position->quantity;

        $unrealizedPnl = $this->mathService->multiply(
            $this->mathService->subtract((string) $currentRate, $avgCost),
            $balance
        );

        $sells = $allSells->get($position->currency_code, collect());
        $realizedPnl = '0';
        $sellVolume = '0';
        foreach ($sells as $sell) {
            $gain = $this->mathService->multiply(
                $this->mathService->subtract((string) $sell->rate, $avgCost),
                (string) $sell->amount_foreign
            );
            $realizedPnl = $this->mathService->add($realizedPnl, $gain);
            $sellVolume = $this->mathService->add($sellVolume, (string) $sell->amount_local);
        }

        $buyVolume = $buyVolumes->get($position->currency_code, '0');

        return [
            'currency' => $position->currency,
            'quantity' => $position->quantity,
            'average_cost' => $position->average_cost,
            'current_rate' => $currentRate,
            'unrealized_gain_loss' => $unrealizedPnl,
            'realized_pnl' => $realizedPnl,
            'total_pnl' => $this->mathService->add($unrealizedPnl, $realizedPnl),
            'buy_volume' => $buyVolume,
            'sell_volume' => $sellVolume,
        ];
    });

    $totals = [
        'total_unrealized' => $positions->sum('unrealized_pnl'),
        'total_realized' => $positions->sum('realized_pnl'),
        'total_pnl' => $positions->sum('total_pnl'),
    ];

    return view('reports.profitability', compact('positions', 'totals', 'startDate', 'endDate'));
}
```

- [ ] **Step 2: Remove the now-unused `calculateCurrencyProfitability()` method**

- [ ] **Step 3: Run tests**

```bash
php artisan test --compact --filter=AnalyticsController
```

- [ ] **Step 4: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Report/AnalyticsController.php
git commit -m "fix: batch N+1 queries in AnalyticsController profitability to 2 queries total"
```

---

### Task 5: Fix UnifiedAlertController memory-loading anti-pattern

**Files:**
- Modify: `app/Http/Controllers/Compliance/UnifiedAlertController.php:121-149`

**Context:** `fetchFindings()` loads ALL findings into memory, then filters by customer in PHP (line 146-148). Should push the customer filter into SQL.

- [ ] **Step 1: Move customer search filter into the query**

Replace the customer search section in `fetchFindings()`:

```php
// Before (loads all, filters in PHP):
$findings = $query->orderBy('generated_at', 'desc')->get();

if ($customerSearch) {
    $customerIds = Customer::where('full_name', 'like', "%{$customerSearch}%")->pluck('id');
    $findings = $findings->filter(fn ($f) => $f->subject_type === 'Customer' && in_array($f->subject_id, $customerIds->toArray()));
}

// After (filter in SQL):
if ($customerSearch) {
    $customerIds = Customer::where('full_name', 'like', "%{$customerSearch}%")->pluck('id');
    $query->where(function ($q) use ($customerIds) {
        $q->where('subject_type', 'Customer')
          ->whereIn('subject_id', $customerIds);
    });
}

$findings = $query->orderBy('generated_at', 'desc')->get();
```

- [ ] **Step 2: Run tests**

```bash
php artisan test --compact --filter=UnifiedAlertController
```

- [ ] **Step 3: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Compliance/UnifiedAlertController.php
git commit -m "fix: push customer search filter into SQL query in UnifiedAlertController"
```

---

## Phase 2: Missing Form Request Extraction

### Task 6: Extract inline validation from API controllers (batch 1)

**Files:**
- Create: `app/Http/Requests/Api/V1/BranchClosingRequest.php`
- Create: `app/Http/Requests/Api/V1/ScreeningRequest.php`
- Create: `app/Http/Requests/Api/V1/MonthEndCloseRequest.php`
- Create: `app/Http/Requests/Api/V1/RateHistoryRequest.php`
- Modify: `app/Http/Controllers/Api/V1/BranchClosingController.php`
- Modify: `app/Http/Controllers/Api/V1/ScreeningController.php`
- Modify: `app/Http/Controllers/Api/V1/MonthEndCloseController.php`
- Modify: `app/Http/Controllers/Api/V1/RateController.php`

**Context:** 4 API controllers accept `Request` without validation. Extract into Form Requests extending `ApiFormRequest`.

- [ ] **Step 1: Create BranchClosingRequest**

```bash
php artisan make:request Api/V1/BranchClosingRequest --no-interaction
```

Edit `app/Http/Requests/Api/V1/BranchClosingRequest.php`:

```php
<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\ApiFormRequest;

class BranchClosingRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {
            'initiate' => [],
            'checklist' => [],
            'finalize' => [
                'notes' => 'nullable|string',
            ],
            default => [],
        };
    }
}
```

- [ ] **Step 2: Update BranchClosingController to use BranchClosingRequest**

Replace `Request` type hints with `BranchClosingRequest` in `initiate()`, `checklist()`, `finalize()`.

- [ ] **Step 3: Create ScreeningRequest, MonthEndCloseRequest, RateHistoryRequest**

Follow the same pattern — create Form Requests with appropriate validation rules.

- [ ] **Step 4: Update controllers to use new Form Requests**

- [ ] **Step 5: Run tests**

```bash
php artisan test --compact --filter="BranchClosing|Screening|MonthEndClose|RateController"
```

- [ ] **Step 6: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Api/V1/BranchClosingRequest.php app/Http/Requests/Api/V1/ScreeningRequest.php app/Http/Requests/Api/V1/MonthEndCloseRequest.php app/Http/Requests/Api/V1/RateHistoryRequest.php app/Http/Controllers/Api/V1/BranchClosingController.php app/Http/Controllers/Api/V1/ScreeningController.php app/Http/Controllers/Api/V1/MonthEndCloseController.php app/Http/Controllers/Api/V1/RateController.php
git commit -m "feat: extract inline validation from 4 API controllers into Form Requests"
```

---

### Task 7: Extract inline validation from API compliance controllers

**Files:**
- Create: `app/Http/Requests/Api/V1/Compliance/FindingIndexRequest.php`
- Create: `app/Http/Requests/Api/V1/Compliance/EddIndexRequest.php`
- Create: `app/Http/Requests/Api/V1/Compliance/CaseIndexRequest.php`
- Create: `app/Http/Requests/Api/V1/Compliance/AlertIndexRequest.php`
- Create: `app/Http/Requests/Api/V1/TransactionIndexRequest.php`
- Create: `app/Http/Requests/Api/V1/CustomerIndexRequest.php`
- Modify: 6 compliance/transaction/customer API controllers

**Context:** 6 API controllers accept `Request` for index methods without validation.

- [ ] **Step 1: Create Form Requests with filter validation**

Each request validates query parameters: `per_page` (integer, max 100), filter fields (nullable strings).

- [ ] **Step 2: Update controllers to use new Form Requests**

- [ ] **Step 3: Run tests**

```bash
php artisan test --compact --filter="Finding|Edd|Case|Alert|Transaction|Customer"
```

- [ ] **Step 4: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/Api/V1/Compliance/ app/Http/Requests/Api/V1/TransactionIndexRequest.php app/Http/Requests/Api/V1/CustomerIndexRequest.php
git commit -m "feat: extract inline validation from 6 API compliance/transaction/customer controllers"
```

---

### Task 8: Extract inline validation from web controllers

**Files:**
- Create: `app/Http/Requests/LoginRequest.php`
- Create: `app/Http/Requests/CopyPreviousRateRequest.php`
- Create: `app/Http/Requests/FiscalYearCloseRequest.php`
- Modify: `app/Http/Controllers/Auth/LoginController.php`
- Modify: `app/Http/Controllers/RateController.php`
- Modify: `app/Http/Controllers/FiscalYearController.php`

**Context:** 3 web controllers use inline validation.

- [ ] **Step 1: Create LoginRequest**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => 'required|string',
            'password' => 'required',
        ];
    }
}
```

- [ ] **Step 2: Create CopyPreviousRateRequest and FiscalYearCloseRequest**

- [ ] **Step 3: Update controllers**

- [ ] **Step 4: Run tests**

```bash
php artisan test --compact --filter="Login|RateController|FiscalYear"
```

- [ ] **Step 5: Run Pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Requests/LoginRequest.php app/Http/Requests/CopyPreviousRateRequest.php app/Http/Requests/FiscalYearCloseRequest.php app/Http/Controllers/Auth/LoginController.php app/Http/Controllers/RateController.php app/Http/Controllers/FiscalYearController.php
git commit -m "feat: extract inline validation from 3 web controllers into Form Requests"
```

---

## Phase 3: Long Method Refactoring

### Task 9: Extract TransactionApprovalController::approve() exception handling

**Files:**
- Modify: `app/Http/Controllers/Api/V1/TransactionApprovalController.php:41-107`

**Context:** 67-line method with 5 catch blocks. Extract exception handling.

- [ ] **Step 1: Simplify the approve method**

```php
public function approve(Request $request, int $transactionId): JsonResponse
{
    $this->requireManagerOrAdmin();

    $transaction = Transaction::findOrFail($transactionId);

    $user = auth()->user();
    if (! $user->isAdmin() && $transaction->branch_id !== $user->branch_id) {
        return response()->json([
            'success' => false,
            'message' => 'You can only approve transactions for your own branch.',
        ], 403);
    }

    try {
        $this->approvalService->validateApprovalEligibility($transaction, auth()->id());

        $result = $this->approvalService->approve($transaction, auth()->id(), $request->ip());

        if (! $result['success']) {
            return response()->json(['success' => false, 'message' => $result['message']], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => $result['transaction'],
        ]);
    } catch (SelfApprovalException $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
    } catch (\InvalidArgumentException $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
    } catch (\RuntimeException $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()], 409);
    } catch (\Exception $e) {
        Log::error('Transaction approval failed (API)', [
            'transaction_id' => $transaction->id,
            'user_id' => auth()->id(),
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Approval failed due to a system error. Please contact support.',
            'code' => 'INTERNAL_ERROR',
        ], 500);
    }
}
```

- [ ] **Step 2: Run tests and commit**

```bash
php artisan test --compact --filter=TransactionApproval
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/V1/TransactionApprovalController.php
git commit -m "refactor: simplify TransactionApprovalController::approve exception handling"
```

---

### Task 10: Run full test suite and final Pint check

- [ ] **Step 1: Run full test suite**

```bash
php artisan test --compact
```

Expected: All tests pass.

- [ ] **Step 2: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 3: Commit if Pint made changes**

```bash
git add -A && git commit -m "style: apply Pint formatting after controller audit fixes"
```
