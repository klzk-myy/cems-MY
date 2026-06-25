# Controller Cleanup — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Address all outstanding issues found during comprehensive controller analysis — extract shared authorization logic, fix auth bugs, convert old-style constructors, standardize API responses, extract inline validations, and remove dead code.

**Architecture:** Create two traits (`BranchScoped`, `ApiResponse`) to DRY up repeated authorization and response patterns. Fix the auth logic bug in DashboardController. Convert 9 old-style constructors to PHP 8 property promotion. Standardize API response format across 5 controllers. Extract inline validations from 23 web controller methods into Form Request classes.

**Tech Stack:** PHP 8.3, Laravel 10, PHPUnit 11

## Global Constraints

- PHP 8.3.30, Laravel 10, PHPUnit 11
- Run `vendor/bin/pint --dirty --format agent` before every commit
- Run affected tests after each task to verify no regressions
- Use `php artisan make:request` to scaffold new Form Request classes
- Do NOT add comments to code
- Preserve exact validation rules and behavior — only change how code is organized
- Follow existing code conventions — check sibling files for patterns

---

## File Structure

### New Files (25 total)

| File | Purpose |
|------|---------|
| `app/Http/Controllers/Concerns/BranchScoped.php` | Trait for branch authorization checks |
| `app/Http/Controllers/Concerns/ApiResponse.php` | Trait for consistent JSON responses |
| `app/Http/Requests/CustomerSearchRequest` | CustomerController::search |
| `app/Http/Requests/QuickCreateCustomerRequest` | CustomerController::quickCreate |
| `app/Http/Requests/ResetPasswordRequest` | UserController::resetPassword |
| `app/Http/Requests/TillReportRequest` | StockCashController::tillReport |
| `app/Http/Requests/TillReconciliationRequest` | StockCashController::reconciliationReport |
| `app/Http/Requests/EmergencyCloseRequest` | CounterController::emergency |
| `app/Http/Requests/AcknowledgeHandoverWebRequest` | CounterController::acknowledgeHandover |
| `app/Http/Requests/BatchUploadRequest` | TransactionBatchController::processBatchUpload |
| `app/Http/Requests/ReceiveStockTransferRequest` | StockTransferController::receive |
| `app/Http/Requests/CancelStockTransferRequest` | StockTransferController::cancel |
| `app/Http/Requests/CreateCaseFromAlertsRequest` | CaseManagementController::store |
| `app/Http/Requests/UpdateCaseStatusRequest` | CaseManagementController::update |
| `app/Http/Requests/MergeCasesRequest` | CaseManagementController::merge |
| `app/Http/Requests/LinkAlertToCaseRequest` | CaseManagementController::linkAlert |
| `app/Http/Requests/UploadCaseDocumentRequest` | CaseManagementController::uploadDocument |
| `app/Http/Requests/AddCaseLinkRequest` | CaseManagementController::addLink |
| `app/Http/Requests/RescreenCustomerRequest` | RiskDashboardController::rescreen |
| `app/Http/Requests/Msb2ReportRequest` | RegulatoryReportController::msb2 |
| `app/Http/Requests/LmcaReportRequest` | RegulatoryReportController::lmca |
| `app/Http/Requests/LmcaGenerateRequest` | RegulatoryReportController::lmcaGenerate |
| `app/Http/Requests/UpdateReportStatusRequest` | RegulatoryReportController::updateReportStatus |
| `app/Http/Requests/QuarterlyLvrRequest` | RegulatoryReportController::quarterlyLvr |
| `app/Http/Requests/QuarterlyLvrGenerateRequest` | RegulatoryReportController::quarterlyLvrGenerate |

### Modified Files (18 total)

| File | Changes |
|------|---------|
| `app/Http/Controllers/DashboardController.php` | Fix auth bug in ensureComplianceOfficerAccess |
| `app/Http/Controllers/AccountingController.php` | Property promotion |
| `app/Http/Controllers/StockCashController.php` | Property promotion |
| `app/Http/Controllers/TestResultsController.php` | Property promotion |
| `app/Http/Controllers/RateController.php` | Property promotion |
| `app/Http/Controllers/FiscalYearController.php` | Property promotion |
| `app/Http/Controllers/RevaluationController.php` | Property promotion |
| `app/Http/Controllers/Report/RegulatoryReportController.php` | Property promotion |
| `app/Http/Controllers/Report/AnalyticsController.php` | Property promotion |
| `app/Http/Controllers/Api/V1/RateController.php` | Property promotion |
| `app/Http/Controllers/Api/V1/SanctionListController.php` | Add success key to responses |
| `app/Http/Controllers/Api/V1/ScreeningController.php` | Add success key to responses |
| `app/Http/Controllers/Api/V1/MonthEndCloseController.php` | Use message instead of error key |
| `app/Http/Controllers/Api/V1/EmergencyCounterController.php` | Replace abort with JSON |
| `app/Http/Controllers/Api/V1/CounterHandoverController.php` | Replace abort with JSON |
| `app/Http/Controllers/Api/V1/CounterOpeningController.php` | Replace abort with JSON |
| `app/Http/Controllers/Transaction/TransactionApprovalController.php` | Fix no-op update |
| `app/Http/Controllers/TransactionBatchController.php` | Remove unused import |

---

## Task 1: Fix Auth Logic Bug in DashboardController

**Priority:** High — Security bug

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php:265-269`

**Context:** `ensureComplianceOfficerAccess()` only checks `isComplianceOfficer()` but not `isAdmin()`. Admin users cannot access the compliance dashboard, which is incorrect — admins should have access to everything.

- [ ] **Step 1: Write a failing test**

```php
// tests/Feature/ComplianceDashboardAccessTest.php
<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Tests\TestCase;

class ComplianceDashboardAccessTest extends TestCase
{
    public function test_admin_can_access_compliance_dashboard(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $response = $this->actingAs($admin)->get('/compliance');

        $response->assertOk();
    }

    public function test_compliance_officer_can_access_compliance_dashboard(): void
    {
        $officer = User::factory()->create(['role' => UserRole::ComplianceOfficer, 'is_active' => true]);

        $response = $this->actingAs($officer)->get('/compliance');

        $response->assertOk();
    }

    public function test_teller_cannot_access_compliance_dashboard(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller, 'is_active' => true]);

        $response = $this->actingAs($teller)->get('/compliance');

        $response->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/ComplianceDashboardAccessTest.php`
Expected: FAIL — admin test fails because `ensureComplianceOfficerAccess` doesn't check `isAdmin()`

- [ ] **Step 3: Fix the auth check**

In `app/Http/Controllers/DashboardController.php`, change line 267:

```php
private function ensureComplianceOfficerAccess(User $user, string $message = ''): void
{
    if (! $user->isComplianceOfficer() && ! $user->isAdmin()) {
        abort(403, $message);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/ComplianceDashboardAccessTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/DashboardController.php tests/Feature/ComplianceDashboardAccessTest.php
git commit -m "fix: allow admin users to access compliance dashboard"
```

---

## Task 2: Fix TransactionApprovalController No-Op Update

**Priority:** Medium — Dead code

**Files:**
- Modify: `app/Http/Controllers/Transaction/TransactionApprovalController.php:213-217`

**Context:** In `confirm()`, the code updates `status` to `TransactionStatus::PendingApproval` but the transaction is already in that status (that's why we're confirming it). This is a no-op DB update.

- [ ] **Step 1: Review the confirm method**

Read `app/Http/Controllers/Transaction/TransactionApprovalController.php:195-240`

- [ ] **Step 2: Remove the no-op update**

Remove lines 213-217 (the `Transaction::where(...)->update(...)` block) and replace with just `$transaction->refresh()`:

```php
$confirmation->markConfirmed(auth()->id(), $validated['notes'] ?? null);

$transaction->refresh();

$this->auditService->logWithSeverity('transaction_confirmed', [
    'user_id' => auth()->id(),
    'entity_type' => 'Transaction',
    'entity_id' => $transaction->id,
    'new_values' => [
        'confirmation_id' => $confirmation->id,
        'confirmed_by' => auth()->id(),
    ],
], 'INFO');
```

- [ ] **Step 3: Run affected tests**

Run: `php artisan test --compact --filter=confirmation`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Transaction/TransactionApprovalController.php
git commit -m "fix: remove no-op status update in transaction confirmation"
```

---

## Task 3: Convert Old-Style Constructors to Property Promotion

**Priority:** Low — Code style consistency

**Files:**
- Modify: `app/Http/Controllers/AccountingController.php:37-63`
- Modify: `app/Http/Controllers/StockCashController.php:21-32`
- Modify: `app/Http/Controllers/TestResultsController.php:16-21`
- Modify: `app/Http/Controllers/RateController.php:18-23`
- Modify: `app/Http/Controllers/FiscalYearController.php:15-20`
- Modify: `app/Http/Controllers/RevaluationController.php:14-19`
- Modify: `app/Http/Controllers/Report/RegulatoryReportController.php:21-33`
- Modify: `app/Http/Controllers/Report/AnalyticsController.php:24-32`
- Modify: `app/Http/Controllers/Api/V1/RateController.php:26-31`

- [ ] **Step 1: Convert AccountingController**

```php
public function __construct(
    protected AccountingService $accountingService,
    protected BudgetService $budgetService,
    protected MathService $mathService,
    protected PeriodCloseService $periodCloseService,
    protected BankReconciliationService $bankReconciliationService,
    protected LedgerService $ledgerService
) {}
```

- [ ] **Step 2: Convert StockCashController**

```php
public function __construct(
    protected MathService $mathService,
    protected CurrencyPositionService $currencyPositionService,
    protected TillService $tillService
) {}
```

- [ ] **Step 3: Convert TestResultsController**

```php
public function __construct(
    protected TestRunnerService $testRunner
) {}
```

- [ ] **Step 4: Convert web RateController**

```php
public function __construct(
    protected RateManagementService $rateService
) {}
```

- [ ] **Step 5: Convert FiscalYearController**

```php
public function __construct(
    protected FiscalYearService $fiscalYearService
) {}
```

- [ ] **Step 6: Convert RevaluationController**

```php
public function __construct(
    protected RevaluationService $revaluationService
) {}
```

- [ ] **Step 7: Convert RegulatoryReportController**

```php
public function __construct(
    protected ReportingService $reportingService,
    protected MathService $mathService,
) {}
```

- [ ] **Step 8: Convert AnalyticsController**

```php
public function __construct(
    protected MathService $mathService,
    protected ThresholdService $thresholdService
) {}
```

- [ ] **Step 9: Convert API RateController**

```php
public function __construct(
    protected RateManagementService $rateService
) {}
```

- [ ] **Step 10: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: PASS

- [ ] **Step 11: Run full test suite**

Run: `php artisan test --compact`
Expected: PASS

- [ ] **Step 12: Commit**

```bash
git add app/Http/Controllers/AccountingController.php \
  app/Http/Controllers/StockCashController.php \
  app/Http/Controllers/TestResultsController.php \
  app/Http/Controllers/RateController.php \
  app/Http/Controllers/FiscalYearController.php \
  app/Http/Controllers/RevaluationController.php \
  app/Http/Controllers/Report/RegulatoryReportController.php \
  app/Http/Controllers/Report/AnalyticsController.php \
  app/Http/Controllers/Api/V1/RateController.php
git commit -m "refactor: convert old-style constructors to PHP 8 property promotion"
```

---

## Task 4: Fix API Response Consistency — Add Missing `success` Key

**Priority:** High — API consistency

**Files:**
- Modify: `app/Http/Controllers/Api/V1/SanctionListController.php`
- Modify: `app/Http/Controllers/Api/V1/ScreeningController.php`
- Modify: `app/Http/Controllers/Api/V1/MonthEndCloseController.php`
- Modify: `app/Http/Controllers/Api/V1/RateController.php`

- [ ] **Step 1: Fix SanctionListController responses**

Add `'success' => true` to all responses in `lists()`, `importLogs()`, `storeEntry()`, `updateEntry()`, `deleteEntry()` methods. For `triggerImport()`, wrap the response in a consistent format.

Example for `lists()`:
```php
return response()->json([
    'success' => true,
    'data' => $lists->map(fn ($list) => [
        // ... existing mapping
    ])->toArray(),
]);
```

Example for `deleteEntry()`:
```php
return response()->json([
    'success' => true,
    'message' => 'Entry deactivated',
]);
```

Example for `triggerImport()` success:
```php
return response()->json([
    'success' => true,
    'data' => [
        'status' => 'success',
        'records_added' => $result['added'],
        'records_updated' => $result['updated'],
        'records_deactivated' => $result['deactivated'],
    ],
]);
```

Example for `triggerImport()` error:
```php
return response()->json([
    'success' => false,
    'message' => $e->getMessage(),
], 500);
```

- [ ] **Step 2: Fix ScreeningController responses**

Add `'success' => true` to all responses in `screen()`, `history()`, `status()`, `batchScreen()`:

```php
return response()->json([
    'success' => true,
    'data' => $response->toArray(),
]);
```

- [ ] **Step 3: Fix MonthEndCloseController error responses**

Change `'error'` key to `'message'` in error responses:

```php
// In close() catch blocks:
return response()->json([
    'success' => false,
    'message' => $e->getMessage(),
], 422);

// In status() catch block:
return response()->json([
    'success' => false,
    'message' => $e->getMessage(),
], 500);
```

- [ ] **Step 4: Fix RateController::apiOverride() response**

```php
return response()->json([
    'success' => true,
    'message' => 'Rate override saved.',
    'data' => $rate,
]);
```

- [ ] **Step 5: Run affected tests**

Run: `php artisan test --compact --filter=SanctionList|Screening|MonthEnd|Rate`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/V1/SanctionListController.php \
  app/Http/Controllers/Api/V1/ScreeningController.php \
  app/Http/Controllers/Api/V1/MonthEndCloseController.php \
  app/Http/Controllers/Api/V1/RateController.php
git commit -m "fix(api): add missing success key to API responses for consistency"
```

---

## Task 5: Replace `abort()` with JSON Responses in API Controllers

**Priority:** High — API consistency

**Files:**
- Modify: `app/Http/Controllers/Api/V1/EmergencyCounterController.php`
- Modify: `app/Http/Controllers/Api/V1/CounterHandoverController.php`
- Modify: `app/Http/Controllers/Api/V1/CounterOpeningController.php`
- Modify: `app/Http/Controllers/Api/V1/TransactionApprovalController.php`

**Context:** API controllers should return JSON error responses, not HTML abort pages. The `abort(403)` calls return HTML which breaks API clients.

- [ ] **Step 1: Fix EmergencyCounterController**

Replace all `abort(403, ...)` calls with JSON responses:

```php
// In initiateClose(), getVariance(), acknowledge():
if ($user->role !== UserRole::Admin && $counter->branch_id !== $user->branch_id) {
    return response()->json([
        'success' => false,
        'message' => 'You do not have permission to access this resource.',
    ], 403);
}
```

- [ ] **Step 2: Fix CounterHandoverController**

Replace `abort(403, ...)` on line 43:

```php
if ($user->role !== UserRole::Admin && $counter->branch_id !== $user->branch_id) {
    return response()->json([
        'success' => false,
        'message' => 'You do not have permission to access this resource.',
    ], 403);
}
```

- [ ] **Step 3: Fix CounterOpeningController**

Replace `abort(403, ...)` on line 75:

```php
if ($user->branch_id !== $counter->branch_id) {
    return response()->json([
        'success' => false,
        'message' => 'Counter does not belong to your branch',
    ], 403);
}
```

- [ ] **Step 4: Fix TransactionApprovalController**

Replace `throw new AccessDeniedHttpException(...)` on line 53:

```php
if (! $user->isAdmin() && $transaction->branch_id !== $user->branch_id) {
    return response()->json([
        'success' => false,
        'message' => 'You can only approve transactions for your own branch.',
    ], 403);
}
```

Remove unused import:
```php
// Remove: use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
```

- [ ] **Step 5: Run affected tests**

Run: `php artisan test --compact --filter=EmergencyCounter|CounterHandover|CounterOpening|TransactionApproval`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/V1/EmergencyCounterController.php \
  app/Http/Controllers/Api/V1/CounterHandoverController.php \
  app/Http/Controllers/Api/V1/CounterOpeningController.php \
  app/Http/Controllers/Api/V1/TransactionApprovalController.php
git commit -m "fix(api): replace abort() with JSON responses in API controllers"
```

---

## Task 6: Extract Inline Validations — Customer & User Controllers

**Priority:** Medium — Validation consistency

**Files:**
- Create: `app/Http/Requests/CustomerSearchRequest.php`
- Create: `app/Http/Requests/QuickCreateCustomerRequest.php`
- Create: `app/Http/Requests/ResetPasswordRequest.php`
- Modify: `app/Http/Controllers/CustomerController.php`
- Modify: `app/Http/Controllers/UserController.php`

- [ ] **Step 1: Create CustomerSearchRequest**

```php
php artisan make:request CustomerSearchRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'query' => 'required|string|min:2',
        ];
    }
}
```

- [ ] **Step 2: Update CustomerController::search()**

```php
public function search(CustomerSearchRequest $request): JsonResponse
{
    $validated = $request->validated();

    $results = $this->customerService->searchCustomers($validated['query']);

    return response()->json([
        'success' => true,
        'query' => $validated['query'],
        'results' => $results,
        'count' => count($results),
    ]);
}
```

- [ ] **Step 3: Create QuickCreateCustomerRequest**

```php
php artisan make:request QuickCreateCustomerRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuickCreateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => 'required|string|max:255',
            'id_type' => 'required|in:MyKad,Passport,Others',
            'id_number' => 'required|string|max:50',
            'date_of_birth' => 'required|date|before:today',
            'nationality' => 'required|string|max:100',
            'phone' => 'nullable|string|max:20',
        ];
    }
}
```

- [ ] **Step 4: Update CustomerController::quickCreate()**

```php
public function quickCreate(QuickCreateCustomerRequest $request): JsonResponse
{
    $validated = $request->validated();

    try {
        $customer = $this->customerService->createCustomer($validated, auth()->id());

        $exchangeRates = Cache::remember('exchange_rates_for_transactions', 300, fn () => ExchangeRate::all()
            ->mapWithKeys(fn ($r) => [$r->currency_code => [
                'buy' => $r->rate_buy,
                'sell' => $r->rate_sell,
            ]])
            ->toArray()
        );

        return response()->json([
            'success' => true,
            'message' => 'Customer created successfully',
            'customer' => [
                'id' => $customer->id,
                'full_name' => $customer->full_name,
                'ic_number_masked' => $customer->ic_number,
                'nationality' => $customer->nationality,
                'risk_rating' => $customer->risk_rating,
                'cdd_level' => $customer->cdd_level instanceof CddLevel ? $customer->cdd_level->value : $customer->cdd_level,
                'is_pep' => $customer->pep_status,
                'is_sanctioned' => $customer->sanction_hit,
            ],
            'exchange_rates' => $exchangeRates,
        ]);
    } catch (\Exception $e) {
        Log::error('Customer quick create failed', [
            'error' => $e->getMessage(),
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Failed to create customer. Please contact support.',
        ], 500);
    }
}
```

- [ ] **Step 5: Create ResetPasswordRequest**

```php
php artisan make:request ResetPasswordRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => [
                'required',
                'string',
                'min:12',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/',
            ],
        ];
    }
}
```

- [ ] **Step 6: Update UserController::resetPassword()**

```php
public function resetPassword(ResetPasswordRequest $request, User $user): RedirectResponse
{
    $this->requireAdmin();

    $validated = $request->validated();

    $this->userService->resetPassword($user, $validated['password'], auth()->id());

    return redirect()->route('users.index')
        ->with('success', "Password for {$user->username} has been reset!");
}
```

- [ ] **Step 7: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: PASS

- [ ] **Step 8: Run affected tests**

Run: `php artisan test --compact --filter=Customer|User`
Expected: PASS

- [ ] **Step 9: Commit**

```bash
git add app/Http/Requests/CustomerSearchRequest.php \
  app/Http/Requests/QuickCreateCustomerRequest.php \
  app/Http/Requests/ResetPasswordRequest.php \
  app/Http/Controllers/CustomerController.php \
  app/Http/Controllers/UserController.php
git commit -m "refactor: extract inline validations from Customer and User controllers"
```

---

## Task 7: Extract Inline Validations — Counter & Stock Controllers

**Priority:** Medium — Validation consistency

**Files:**
- Create: `app/Http/Requests/TillReportRequest.php`
- Create: `app/Http/Requests/TillReconciliationRequest.php`
- Create: `app/Http/Requests/EmergencyCloseRequest.php`
- Create: `app/Http/Requests/AcknowledgeHandoverWebRequest.php`
- Create: `app/Http/Requests/BatchUploadRequest.php`
- Create: `app/Http/Requests/ReceiveStockTransferRequest.php`
- Create: `app/Http/Requests/CancelStockTransferRequest.php`
- Modify: `app/Http/Controllers/StockCashController.php`
- Modify: `app/Http/Controllers/CounterController.php`
- Modify: `app/Http/Controllers/TransactionBatchController.php`
- Modify: `app/Http/Controllers/StockTransferController.php`

- [ ] **Step 1: Create TillReportRequest**

```php
php artisan make:request TillReportRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TillReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'till_id' => 'required|string',
            'date' => 'nullable|date',
        ];
    }
}
```

- [ ] **Step 2: Create TillReconciliationRequest**

```php
php artisan make:request TillReconciliationRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TillReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => 'nullable|date',
            'till_id' => 'required|string',
        ];
    }
}
```

- [ ] **Step 3: Update StockCashController methods**

Replace inline validation in `tillReport()` and `reconciliationReport()` with the new Form Requests.

- [ ] **Step 4: Create EmergencyCloseRequest**

```php
php artisan make:request EmergencyCloseRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmergencyCloseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|max:500',
        ];
    }
}
```

- [ ] **Step 5: Create AcknowledgeHandoverWebRequest**

```php
php artisan make:request AcknowledgeHandoverWebRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AcknowledgeHandoverWebRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'verified' => 'required|boolean',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
```

- [ ] **Step 6: Update CounterController methods**

Replace inline validation in `emergency()` and `acknowledgeHandover()` with the new Form Requests.

- [ ] **Step 7: Create BatchUploadRequest**

```php
php artisan make:request BatchUploadRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BatchUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'csv_file' => 'required|file|mimes:csv,txt|max:2048',
        ];
    }
}
```

- [ ] **Step 8: Update TransactionBatchController::processBatchUpload()**

Replace inline validation with `BatchUploadRequest`.

- [ ] **Step 9: Create ReceiveStockTransferRequest**

```php
php artisan make:request ReceiveStockTransferRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => 'required|array',
            'items.*.id' => 'required|exists:stock_transfer_items,id',
            'items.*.quantity_received' => 'required|numeric|min:0',
        ];
    }
}
```

- [ ] **Step 10: Create CancelStockTransferRequest**

```php
php artisan make:request CancelStockTransferRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|max:500',
        ];
    }
}
```

- [ ] **Step 11: Update StockTransferController methods**

Replace inline validation in `receive()` and `cancel()` with the new Form Requests.

- [ ] **Step 12: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: PASS

- [ ] **Step 13: Run affected tests**

Run: `php artisan test --compact --filter=StockCash|Counter|Batch|StockTransfer`
Expected: PASS

- [ ] **Step 14: Commit**

```bash
git add app/Http/Requests/TillReportRequest.php \
  app/Http/Requests/TillReconciliationRequest.php \
  app/Http/Requests/EmergencyCloseRequest.php \
  app/Http/Requests/AcknowledgeHandoverWebRequest.php \
  app/Http/Requests/BatchUploadRequest.php \
  app/Http/Requests/ReceiveStockTransferRequest.php \
  app/Http/Requests/CancelStockTransferRequest.php \
  app/Http/Controllers/StockCashController.php \
  app/Http/Controllers/CounterController.php \
  app/Http/Controllers/TransactionBatchController.php \
  app/Http/Controllers/StockTransferController.php
git commit -m "refactor: extract inline validations from Counter and Stock controllers"
```

---

## Task 8: Extract Inline Validations — Compliance Controllers

**Priority:** Medium — Validation consistency

**Files:**
- Create: `app/Http/Requests/CreateCaseFromAlertsRequest.php`
- Create: `app/Http/Requests/UpdateCaseStatusRequest.php`
- Create: `app/Http/Requests/MergeCasesRequest.php`
- Create: `app/Http/Requests/LinkAlertToCaseRequest.php`
- Create: `app/Http/Requests/UploadCaseDocumentRequest.php`
- Create: `app/Http/Requests/AddCaseLinkRequest.php`
- Create: `app/Http/Requests/RescreenCustomerRequest.php`
- Modify: `app/Http/Controllers/Compliance/CaseManagementController.php`
- Modify: `app/Http/Controllers/Compliance/RiskDashboardController.php`

- [ ] **Step 1: Create CreateCaseFromAlertsRequest**

```php
php artisan make:request CreateCaseFromAlertsRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCaseFromAlertsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'alert_ids' => 'required|array|min:1',
            'alert_ids.*' => 'exists:alerts,id',
        ];
    }
}
```

- [ ] **Step 2: Create UpdateCaseStatusRequest**

```php
php artisan make:request UpdateCaseStatusRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCaseStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'nullable|in:open,in_progress,pending_review,resolved,closed',
            'notes' => 'nullable|string',
        ];
    }
}
```

- [ ] **Step 3: Create MergeCasesRequest**

```php
php artisan make:request MergeCasesRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MergeCasesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_case_id' => 'required|exists:compliance_cases,id',
        ];
    }
}
```

- [ ] **Step 4: Create LinkAlertToCaseRequest**

```php
php artisan make:request LinkAlertToCaseRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LinkAlertToCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'alert_id' => 'required|exists:alerts,id',
        ];
    }
}
```

- [ ] **Step 5: Create UploadCaseDocumentRequest**

```php
php artisan make:request UploadCaseDocumentRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadCaseDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
        ];
    }
}
```

- [ ] **Step 6: Create AddCaseLinkRequest**

```php
php artisan make:request AddCaseLinkRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddCaseLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'linked_type' => 'required|string',
            'linked_id' => 'required|integer',
        ];
    }
}
```

- [ ] **Step 7: Create RescreenCustomerRequest**

```php
php artisan make:request RescreenCustomerRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RescreenCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'required|exists:customers,id',
        ];
    }
}
```

- [ ] **Step 8: Update CaseManagementController methods**

Replace inline validation in `store()`, `update()`, `merge()`, `linkAlert()`, `uploadDocument()`, `addLink()` with the new Form Requests.

- [ ] **Step 9: Update RiskDashboardController::rescreen()**

Replace inline validation with `RescreenCustomerRequest`.

- [ ] **Step 10: Remove unused Request parameter from escalate()**

```php
// Change:
public function escalate(Request $request, ComplianceCase $case): RedirectResponse
// To:
public function escalate(ComplianceCase $case): RedirectResponse
```

- [ ] **Step 11: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: PASS

- [ ] **Step 12: Run affected tests**

Run: `php artisan test --compact --filter=CaseManagement|RiskDashboard`
Expected: PASS

- [ ] **Step 13: Commit**

```bash
git add app/Http/Requests/CreateCaseFromAlertsRequest.php \
  app/Http/Requests/UpdateCaseStatusRequest.php \
  app/Http/Requests/MergeCasesRequest.php \
  app/Http/Requests/LinkAlertToCaseRequest.php \
  app/Http/Requests/UploadCaseDocumentRequest.php \
  app/Http/Requests/AddCaseLinkRequest.php \
  app/Http/Requests/RescreenCustomerRequest.php \
  app/Http/Controllers/Compliance/CaseManagementController.php \
  app/Http/Controllers/Compliance/RiskDashboardController.php
git commit -m "refactor: extract inline validations from Compliance controllers"
```

---

## Task 9: Extract Inline Validations — Regulatory Report Controllers

**Priority:** Medium — Validation consistency

**Files:**
- Create: `app/Http/Requests/Msb2ReportRequest.php`
- Create: `app/Http/Requests/LmcaReportRequest.php`
- Create: `app/Http/Requests/LmcaGenerateRequest.php`
- Create: `app/Http/Requests/UpdateReportStatusRequest.php`
- Create: `app/Http/Requests/QuarterlyLvrRequest.php`
- Create: `app/Http/Requests/QuarterlyLvrGenerateRequest.php`
- Modify: `app/Http/Controllers/Report/RegulatoryReportController.php`

- [ ] **Step 1: Create Msb2ReportRequest**

```php
php artisan make:request Msb2ReportRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class Msb2ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => 'nullable|date_format:Y-m-d',
        ];
    }
}
```

- [ ] **Step 2: Create LmcaReportRequest**

```php
php artisan make:request LmcaReportRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LmcaReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'month' => 'nullable|date_format:Y-m',
        ];
    }
}
```

- [ ] **Step 3: Create LmcaGenerateRequest**

```php
php artisan make:request LmcaGenerateRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LmcaGenerateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'month' => 'required|date_format:Y-m',
        ];
    }
}
```

- [ ] **Step 4: Create UpdateReportStatusRequest**

```php
php artisan make:request UpdateReportStatusRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReportStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => 'required_with:date|date_format:Y-m-d',
            'month' => 'required_with:month|date_format:Y-m',
            'status' => 'required|in:Submitted',
        ];
    }
}
```

- [ ] **Step 5: Create QuarterlyLvrRequest**

```php
php artisan make:request QuarterlyLvrRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuarterlyLvrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quarter' => 'nullable|date_format:Y-q',
        ];
    }
}
```

- [ ] **Step 6: Create QuarterlyLvrGenerateRequest**

```php
php artisan make:request QuarterlyLvrGenerateRequest --no-interaction
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuarterlyLvrGenerateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quarter' => 'required|date_format:Y-q',
        ];
    }
}
```

- [ ] **Step 7: Update RegulatoryReportController methods**

Replace inline validation in `msb2()`, `lmca()`, `lmcaGenerate()`, `updateReportStatus()`, `quarterlyLvr()`, `quarterlyLvrGenerate()` with the new Form Requests.

Note: `updateReportStatus()` uses conditional validation based on `$reportType`. The Form Request should accept both `date` and `month` fields, and the controller logic should determine which is required based on context.

- [ ] **Step 8: Remove unused ThresholdService import**

```php
// Remove: use App\Services\ThresholdService;
```

- [ ] **Step 9: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: PASS

- [ ] **Step 10: Run affected tests**

Run: `php artisan test --compact --filter=RegulatoryReport`
Expected: PASS

- [ ] **Step 11: Commit**

```bash
git add app/Http/Requests/Msb2ReportRequest.php \
  app/Http/Requests/LmcaReportRequest.php \
  app/Http/Requests/LmcaGenerateRequest.php \
  app/Http/Requests/UpdateReportStatusRequest.php \
  app/Http/Requests/QuarterlyLvrRequest.php \
  app/Http/Requests/QuarterlyLvrGenerateRequest.php \
  app/Http/Controllers/Report/RegulatoryReportController.php
git commit -m "refactor: extract inline validations from RegulatoryReport controller"
```

---

## Task 10: Remove Unused Imports and Final Cleanup

**Priority:** Low — Code hygiene

**Files:**
- Modify: `app/Http/Controllers/TransactionBatchController.php:18`
- Modify: `app/Http/Controllers/Api/V1/TransactionApprovalController.php:18`

- [ ] **Step 1: Remove unused import from TransactionBatchController**

```php
// Remove: use Illuminate\Http\Response;
```

- [ ] **Step 2: Remove unused import from TransactionApprovalController**

```php
// Remove: use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
```

- [ ] **Step 3: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: PASS

- [ ] **Step 4: Run full test suite**

Run: `php artisan test --compact`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/TransactionBatchController.php \
  app/Http/Controllers/Api/V1/TransactionApprovalController.php
git commit -m "chore: remove unused imports from controllers"
```

---

## Final Verification

- [ ] **Step 1: Run full test suite**

Run: `php artisan test --compact`
Expected: All tests pass

- [ ] **Step 2: Run Pint on all files**

Run: `vendor/bin/pint --format agent`
Expected: All files pass

- [ ] **Step 3: Verify no regressions**

Run: `php artisan test --compact --group=slow`
Expected: All tests pass (if slow tests are enabled)
