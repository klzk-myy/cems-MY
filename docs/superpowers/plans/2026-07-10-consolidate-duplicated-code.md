# Consolidate Duplicated Code Blocks Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eliminate repeated code blocks for API responses, reporting queries, transaction service operations, and validation/authorization by introducing shared helpers, traits, services, query scopes, and validation rules.

**Architecture:** Introduce a thin controller `ApiResponse` trait and `ApiFormRequest` base class for uniform API envelopes; add domain helpers (`TillBalanceManager`, `CurrencyPositionLockService`, `AuditTrailHelper`) to centralize stateful operations; add Eloquent query scopes and a `TransactionReportQuery` builder for reporting; and replace duplicated Form Request classes and inline validation rules with shared rules and traits.

**Tech Stack:** PHP 8.3, Laravel 11, PHPUnit 10, Laravel Pint.

---

## File Structure

### New files

- `app/Http/Controllers/Api/V1/Traits/ApiResponse.php` — standardized JSON response helpers.
- `app/Services/Branch/TillBalanceManager.php` — central till balance lookup and update operations.
- `app/Services/Accounting/CurrencyPositionLockService.php` — pessimistic locking + position mutation.
- `app/Services/Audit/AuditTrailHelper.php` — standardized audit array builder and persistence.
- `app/Services/Reporting/TransactionReportQuery.php` — reusable reporting query builder.
- `app/Rules/ValidCurrencyCode.php` — reusable active-currency code rule.
- `app/Rules/ValidTill.php` — reusable till existence/open rule.
- `app/Rules/ValidAmountForeign.php` — reusable amount rule.
- `app/Rules/ValidRate.php` — reusable rate rule.

### Modified files

- `app/Http/Controllers/Controller.php` — keep existing helpers; add JSON-specific helper trait usage notes.
- `app/Http/Controllers/Api/V1/*` (24 files) — replace inline response envelopes with `ApiResponse` trait.
- `app/Http/Requests/Api/V1/*Request.php` (flat legacy versions) — delete after route migration.
- `app/Http/Requests/Api/V1/Transaction/StoreTransactionRequest.php` — adopt shared rules.
- `app/Services/Transaction/TransactionCreationService.php` — use `TillBalanceManager` and `CurrencyPositionLockService`.
- `app/Services/Transaction/TransactionApprovalService.php` — same.
- `app/Services/Transaction/TransactionReversalService.php` — same.
- `app/Services/Transaction/TransactionImportService.php` — same.
- `app/Services/Accounting/CurrencyPositionService.php` — delegate locking to `CurrencyPositionLockService`.
- `app/Services/Reporting/ReportingService.php` — use `TransactionReportQuery`.
- `app/Services/Reporting/CustomerReportService.php` — use `TransactionReportQuery`.
- `app/Services/Eod/EodReconciliationService.php` — use `TransactionReportQuery`.
- `app/Services/Compliance/PatternRiskService.php` — use `TransactionReportQuery`.
- `app/Services/Monitoring/CurrencyFlowMonitor.php` — use `TransactionReportQuery`.
- `app/Services/Branch/CounterService.php` — use `TillBalanceManager` for variance math.
- `app/Models/Transaction.php` — add query scopes for status, date range, branch, and type sums.

### Test files

- `tests/Unit/Http/Controllers/Api/V1/Traits/ApiResponseTest.php`
- `tests/Unit/Services/Branch/TillBalanceManagerTest.php`
- `tests/Unit/Services/Accounting/CurrencyPositionLockServiceTest.php`
- `tests/Unit/Services/Audit/AuditTrailHelperTest.php`
- `tests/Unit/Services/Reporting/TransactionReportQueryTest.php`
- `tests/Unit/Rules/ValidCurrencyCodeTest.php`
- `tests/Unit/Rules/ValidTillTest.php`
- `tests/Feature/Api/V1/TransactionStoreResponseShapeTest.php`

---

## Task 1: Create `ApiResponse` Trait

**Files:**
- Create: `app/Http/Controllers/Api/V1/Traits/ApiResponse.php`
- Test: `tests/Unit/Http/Controllers/Api/V1/Traits/ApiResponseTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Http\Controllers\Api\V1\Traits;

use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use PHPUnit\Framework\TestCase;

class ApiResponseTest extends TestCase
{
    use ApiResponse;

    public function test_success_response_returns_expected_shape(): void
    {
        $response = $this->successResponse(['id' => 1], 'Created');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals([
            'success' => true,
            'message' => 'Created',
            'data' => ['id' => 1],
        ], $response->getData(true));
    }

    public function test_error_response_returns_expected_shape(): void
    {
        $response = $this->errorResponse('Validation failed', ['field' => ['required']], 422);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => ['field' => ['required']],
        ], $response->getData(true));
    }

    public function test_resource_response_wraps_with_success(): void
    {
        $resource = new JsonResource(['id' => 1]);
        $response = $this->resourceResponse($resource, 'OK');

        $this->assertEquals([
            'success' => true,
            'message' => 'OK',
            'data' => ['id' => 1],
        ], $response->response()->getData(true));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Unit/Http/Controllers/Api/V1/Traits/ApiResponseTest.php`

Expected: FAIL with trait/file not found.

- [ ] **Step 3: Implement the trait**

```php
<?php

namespace App\Http\Controllers\Api\V1\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

trait ApiResponse
{
    protected function successResponse(mixed $data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    protected function errorResponse(string $message, array $errors = [], int $code = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }

    protected function resourceResponse(JsonResource $resource, string $message = 'Success'): JsonResponse
    {
        return $resource->additional([
            'success' => true,
            'message' => $message,
        ])->response();
    }

    protected function notFoundResponse(string $message = 'Resource not found.'): JsonResponse
    {
        return $this->errorResponse($message, [], 404);
    }

    protected function serverErrorResponse(string $message = 'An error occurred.', \Throwable $e = null): JsonResponse
    {
        if ($e !== null) {
            \Log::error($message, ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        }

        return $this->errorResponse($message, [], 500);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Unit/Http/Controllers/Api/V1/Traits/ApiResponseTest.php`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/V1/Traits/ApiResponse.php tests/Unit/Http/Controllers/Api/V1/Traits/ApiResponseTest.php
git commit -m "feat: add ApiResponse trait for standardized API envelopes"
```

---

## Task 2: Migrate One Controller to `ApiResponse`

**Files:**
- Modify: `app/Http/Controllers/Api/V1/TransactionController.php`
- Test: `tests/Feature/Api/V1/TransactionStoreResponseShapeTest.php`

- [ ] **Step 1: Write the failing feature test**

```php
<?php

namespace Tests\Feature\Api\V1;

use App\Models\Currency;
use App\Models\Customer;
use App\Models\Till;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionStoreResponseShapeTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_transaction_returns_standardized_success_envelope(): void
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();
        $currency = Currency::factory()->create(['code' => 'USD']);
        $till = Till::factory()->create(['status' => 'open']);

        $payload = [
            'customer_id' => $customer->id,
            'type' => 'Buy',
            'currency_code' => $currency->code,
            'amount_foreign' => 100,
            'rate' => 1.5,
            'purpose' => 'Travel',
            'source_of_funds' => 'Savings',
            'till_id' => (string) $till->id,
        ];

        $response = $this->actingAs($user)->postJson('/api/v1/transactions', $payload);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'message' => 'Transaction created successfully.',
        ]);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => ['id', 'customer_id', 'type', 'currency_code', 'amount_foreign'],
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Api/V1/TransactionStoreResponseShapeTest.php`

Expected: FAIL — test file or route may not exist; adjust route path if needed.

- [ ] **Step 3: Refactor `TransactionController` to use `ApiResponse`**

Open `app/Http/Controllers/Api/V1/TransactionController.php`.

Add the import:

```php
use App\Http\Controllers\Api\V1\Traits\ApiResponse;
```

Add the trait inside the class:

```php
class TransactionController extends Controller
{
    use ApiResponse;
```

Replace a representative inline response block. Example before:

```php
return response()->json([
    'success' => true,
    'message' => 'Transaction created successfully.',
    'data' => $transaction,
], 201);
```

Example after:

```php
return $this->successResponse($transaction, 'Transaction created successfully.', 201);
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Api/V1/TransactionStoreResponseShapeTest.php`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/V1/TransactionController.php tests/Feature/Api/V1/TransactionStoreResponseShapeTest.php
git commit -m "refactor(api): use ApiResponse trait in TransactionController"
```

---

## Task 3: Roll Out `ApiResponse` to Remaining API Controllers

**Files:**
- Modify: all `app/Http/Controllers/Api/V1/*.php` except `TransactionController.php`

- [ ] **Step 1: Add trait and import to each controller**

For each controller under `app/Http/Controllers/Api/V1/`:

```php
use App\Http\Controllers\Api\V1\Traits\ApiResponse;

class XxxController extends Controller
{
    use ApiResponse;
```

- [ ] **Step 2: Replace inline success/error envelopes**

Replace patterns of the form:

```php
return response()->json([
    'success' => true,
    'message' => '...',
    'data' => $data,
], $code);
```

with:

```php
return $this->successResponse($data, '...', $code);
```

Replace error patterns:

```php
return response()->json([
    'success' => false,
    'message' => '...',
    'errors' => $errors,
], $code);
```

with:

```php
return $this->errorResponse('...', $errors, $code);
```

Replace resource wrapping:

```php
return (new XxxResource($model))->additional(['success' => true, 'message' => '...']);
```

with:

```php
return $this->resourceResponse(new XxxResource($model), '...');
```

- [ ] **Step 3: Replace catch-block boilerplate**

Before:

```php
catch (\Exception $e) {
    Log::error('...', ['error' => $e->getMessage()]);
    return response()->json([
        'success' => false,
        'message' => '...',
    ], 500);
}
```

After:

```php
catch (\Exception $e) {
    return $this->serverErrorResponse('...', $e);
}
```

- [ ] **Step 4: Run API feature tests**

Run: `php artisan test --compact tests/Feature/Api/V1`

Expected: PASS (or pre-existing failures documented).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/V1
git commit -m "refactor(api): use ApiResponse trait across all V1 controllers"
```

---

## Task 4: Centralize Till Balance Operations in `TillBalanceManager`

**Files:**
- Create: `app/Services/Branch/TillBalanceManager.php`
- Modify: `app/Services/Transaction/TransactionCreationService.php`
- Modify: `app/Services/Transaction/TransactionApprovalService.php`
- Modify: `app/Services/Transaction/TransactionReversalService.php`
- Modify: `app/Services/Transaction/TransactionImportService.php`
- Test: `tests/Unit/Services/Branch/TillBalanceManagerTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Branch;

use App\Models\Currency;
use App\Models\Till;
use App\Services\Branch\TillBalanceManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TillBalanceManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_opens_till_balance_for_currency(): void
    {
        $till = Till::factory()->create(['status' => 'open']);
        $currency = Currency::factory()->create();
        $manager = new TillBalanceManager();

        $balance = $manager->openBalance($till, $currency->code);

        $this->assertNotNull($balance);
        $this->assertEquals($currency->id, $balance->currency_id);
    }

    public function test_throws_when_currency_not_found(): void
    {
        $till = Till::factory()->create();
        $manager = new TillBalanceManager();

        $this->expectException(ModelNotFoundException::class);
        $manager->openBalance($till, 'XYZ');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Unit/Services/Branch/TillBalanceManagerTest.php`

Expected: FAIL — class not found.

- [ ] **Step 3: Implement `TillBalanceManager`**

```php
<?php

namespace App\Services\Branch;

use App\Models\Currency;
use App\Models\Till;
use App\Models\TillBalance;

class TillBalanceManager
{
    public function openBalance(Till $till, string $currencyCode): TillBalance
    {
        $currency = Currency::where('code', $currencyCode)->firstOrFail();

        return $till->balances()->firstOrCreate(
            ['currency_id' => $currency->id],
            ['opening_balance' => 0, 'closing_balance' => 0, 'current_balance' => 0]
        );
    }

    public function adjustBalance(TillBalance $balance, float $amount, string $operation = 'add'): TillBalance
    {
        $newBalance = match ($operation) {
            'add' => $balance->current_balance + $amount,
            'subtract' => $balance->current_balance - $amount,
            default => throw new \InvalidArgumentException("Unknown till balance operation: {$operation}"),
        };

        $balance->update(['current_balance' => $newBalance]);

        return $balance->refresh();
    }

    public function currentBalance(Till $till, string $currencyCode): ?TillBalance
    {
        $currency = Currency::where('code', $currencyCode)->first();

        if ($currency === null) {
            return null;
        }

        return $till->balances()->where('currency_id', $currency->id)->first();
    }

    public function variance(TillBalance $balance): float
    {
        return $balance->current_balance - $balance->closing_balance;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Unit/Services/Branch/TillBalanceManagerTest.php`

Expected: PASS.

- [ ] **Step 5: Refactor services to use `TillBalanceManager`**

In each transaction service, replace inline till balance lookup:

Before:

```php
$balance = $till->balances()->where('currency_id', $currency->id)->first();
if (! $balance) {
    $balance = $till->balances()->create([...]);
}
```

After:

```php
$balance = app(TillBalanceManager::class)->openBalance($till, $currencyCode);
```

Replace inline balance updates with:

```php
app(TillBalanceManager::class)->adjustBalance($balance, $amount, 'subtract');
```

- [ ] **Step 6: Run transaction feature tests**

Run: `php artisan test --compact tests/Feature/Transaction`

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Services/Branch/TillBalanceManager.php tests/Unit/Services/Branch/TillBalanceManagerTest.php app/Services/Transaction
git commit -m "refactor(services): centralize till balance operations in TillBalanceManager"
```

---

## Task 5: Centralize Currency Position Locking in `CurrencyPositionLockService`

**Files:**
- Create: `app/Services/Accounting/CurrencyPositionLockService.php`
- Modify: `app/Services/Accounting/CurrencyPositionService.php`
- Modify: `app/Services/Transaction/TransactionCreationService.php`
- Modify: `app/Services/Transaction/TransactionApprovalService.php`
- Modify: `app/Services/Transaction/TransactionReversalService.php`
- Modify: `app/Services/Transaction/TransactionImportService.php`
- Test: `tests/Unit/Services/Accounting/CurrencyPositionLockServiceTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Accounting;

use App\Models\CurrencyPosition;
use App\Services\Accounting\CurrencyPositionLockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyPositionLockServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_locks_existing_position(): void
    {
        $position = CurrencyPosition::factory()->create();
        $service = new CurrencyPositionLockService();

        $locked = $service->lock($position->branch_id, $position->currency_id);

        $this->assertEquals($position->id, $locked->id);
    }

    public function test_adjusts_position_balance(): void
    {
        $position = CurrencyPosition::factory()->create(['balance' => 1000]);
        $service = new CurrencyPositionLockService();

        $service->adjust($position, 100, 'add');

        $this->assertEquals(1100, $position->fresh()->balance);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Unit/Services/Accounting/CurrencyPositionLockServiceTest.php`

Expected: FAIL — class not found.

- [ ] **Step 3: Implement `CurrencyPositionLockService`**

```php
<?php

namespace App\Services\Accounting;

use App\Models\CurrencyPosition;

class CurrencyPositionLockService
{
    public function lock(int $branchId, int $currencyId): CurrencyPosition
    {
        return CurrencyPosition::where('branch_id', $branchId)
            ->where('currency_id', $currencyId)
            ->lockForUpdate()
            ->firstOrCreate([
                'branch_id' => $branchId,
                'currency_id' => $currencyId,
            ], [
                'balance' => 0,
                'reserved' => 0,
            ]);
    }

    public function adjust(CurrencyPosition $position, float $amount, string $operation): CurrencyPosition
    {
        $newBalance = match ($operation) {
            'add' => $position->balance + $amount,
            'subtract' => $position->balance - $amount,
            default => throw new \InvalidArgumentException("Unknown position operation: {$operation}"),
        };

        $position->update(['balance' => $newBalance]);

        return $position->refresh();
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Unit/Services/Accounting/CurrencyPositionLockServiceTest.php`

Expected: PASS.

- [ ] **Step 5: Refactor services to use the lock service**

Replace inline `CurrencyPosition::...->lockForUpdate()` blocks in the five transaction services with calls to `CurrencyPositionLockService`.

Before:

```php
$position = CurrencyPosition::where('branch_id', $branchId)
    ->where('currency_id', $currencyId)
    ->lockForUpdate()
    ->firstOrFail();
```

After:

```php
$position = app(CurrencyPositionLockService::class)->lock($branchId, $currencyId);
```

- [ ] **Step 6: Run transaction and accounting tests**

Run: `php artisan test --compact tests/Feature/Transaction tests/Feature/Accounting`

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Services/Accounting/CurrencyPositionLockService.php tests/Unit/Services/Accounting/CurrencyPositionLockServiceTest.php app/Services/Transaction app/Services/Accounting/CurrencyPositionService.php
git commit -m "refactor(services): centralize currency position locking"
```

---

## Task 6: Create `AuditTrailHelper`

**Files:**
- Create: `app/Services/Audit/AuditTrailHelper.php`
- Modify: transaction services and `CustomerService`
- Test: `tests/Unit/Services/Audit/AuditTrailHelperTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Audit;

use App\Models\AuditTrail;
use App\Models\User;
use App\Services\Audit\AuditTrailHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditTrailHelperTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_transaction_action(): void
    {
        $user = User::factory()->create();
        $helper = new AuditTrailHelper();

        $helper->record('transaction', 1, 'created', ['amount' => 100], $user);

        $this->assertDatabaseHas('audit_trails', [
            'auditable_type' => 'transaction',
            'auditable_id' => 1,
            'action' => 'created',
            'user_id' => $user->id,
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Unit/Services/Audit/AuditTrailHelperTest.php`

Expected: FAIL — class not found.

- [ ] **Step 3: Implement `AuditTrailHelper`**

```php
<?php

namespace App\Services\Audit;

use App\Models\AuditTrail;
use App\Models\User;
use App\Services\AuditService;

/**
 * Helper that records auditable events to both the application audit_trails
 * table and the tamper-evident system_logs stream via AuditService.
 *
 * The dual-write design preserves the existing system_logs chain (hashed,
 * sequential, tamper-evident) while also populating the richer audit_trails
 * table used for business-level querying and reporting. The secondary
 * system_logs write is isolated: if AuditService throws, the exception is
 * logged but not re-thrown, so the primary audit_trails write and the
 * business transaction are not aborted by a system_logs failure.
 */
class AuditTrailHelper
{
    public function __construct(protected AuditService $auditService) {}

    public function record(
        string $auditableType,
        int $auditableId,
        string $action,
        array $metadata = [],
        ?User $user = null,
        ?string $ipAddress = null
    ): AuditTrail {
        return AuditTrail::create([
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'action' => $action,
            'user_id' => $user?->id,
            'metadata' => $metadata,
            'ip_address' => $ipAddress ?? request()?->ip(),
        ]);
    }

    public function recordTransaction(
        int $transactionId,
        string $action,
        array $metadata = [],
        ?User $user = null,
        string $severity = 'INFO',
        ?string $ipAddress = null
    ): AuditTrail {
        $auditTrail = $this->record('Transaction', $transactionId, $action, $metadata, $user, $ipAddress);

        try {
            $this->auditService->logTransaction($action, $transactionId, [
                'old' => $metadata['old'] ?? [],
                'new' => $metadata['new'] ?? [],
                'severity' => $severity,
                'user_id' => $user?->id,
                'ip_address' => $ipAddress,
            ]);
        } catch (\Exception $e) {
            \Log::error('AuditService transaction write failed', [
                'action' => $action,
                'transaction_id' => $transactionId,
                'exception' => $e->getMessage(),
            ]);
        }

        return $auditTrail;
    }

    public function recordCustomer(
        int $customerId,
        string $action,
        array $metadata = [],
        ?User $user = null,
        string $severity = 'INFO',
        ?string $ipAddress = null
    ): AuditTrail {
        $auditTrail = $this->record('Customer', $customerId, $action, $metadata, $user, $ipAddress);

        try {
            $this->auditService->logCustomer($action, $customerId, [
                'old' => $metadata['old'] ?? [],
                'new' => $metadata['new'] ?? [],
                'severity' => $severity,
                'user_id' => $user?->id,
                'ip_address' => $ipAddress,
            ]);
        } catch (\Exception $e) {
            \Log::error('AuditService customer write failed', [
                'action' => $action,
                'customer_id' => $customerId,
                'exception' => $e->getMessage(),
            ]);
        }

        return $auditTrail;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Unit/Services/Audit/AuditTrailHelperTest.php`

Expected: PASS.

- [ ] **Step 5: Replace manual audit arrays and direct AuditService calls**

In transaction services and `CustomerService`, replace direct `AuditTrail::create(...)` blocks and direct `$this->auditService->logWithSeverity(...)` calls with the wrapper methods.

For transaction events:

```php
app(AuditTrailHelper::class)->recordTransaction(
    $transaction->id,
    'transaction_created',
    [
        'new' => [
            'type' => $transaction->type,
            'amount_local' => $transaction->amount_local,
            'amount_foreign' => $transaction->amount_foreign,
            'currency' => $transaction->currency_code,
            'status' => $transaction->status->value,
        ],
    ],
    $user,
    'INFO',
    $ipAddress
);
```

For customer events:

```php
app(AuditTrailHelper::class)->recordCustomer(
    $customer->id,
    'customer_updated',
    [
        'old' => ['risk_rating' => 'Low'],
        'new' => ['risk_rating' => 'High'],
    ],
    $user,
    'INFO',
    $ipAddress
);
```

- [ ] **Step 6: Run audit-related tests**

Run: `php artisan test --compact --filter=Audit`

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Services/Audit/AuditTrailHelper.php tests/Unit/Services/Audit/AuditTrailHelperTest.php app/Services/Transaction app/Services/CustomerService.php
git commit -m "refactor(services): centralize audit trail creation via AuditTrailHelper"
```

---

## Task 7: Add Reporting Query Scopes and `TransactionReportQuery`

**Files:**
- Modify: `app/Models/Transaction.php`
- Create: `app/Services/Reporting/TransactionReportQuery.php`
- Modify: reporting services and controllers
- Test: `tests/Unit/Services/Reporting/TransactionReportQueryTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services\Reporting;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Services\Reporting\TransactionReportQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionReportQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_sum_by_type(): void
    {
        Transaction::factory()->create(['type' => 'Buy', 'status' => TransactionStatus::Completed->value, 'amount_foreign' => 100]);
        Transaction::factory()->create(['type' => 'Sell', 'status' => TransactionStatus::Completed->value, 'amount_foreign' => 50]);
        Transaction::factory()->create(['type' => 'Buy', 'status' => TransactionStatus::Cancelled->value, 'amount_foreign' => 200]);

        $query = new TransactionReportQuery();
        $result = $query->completed()->sumByType();

        $this->assertEquals(100, $result['buy']);
        $this->assertEquals(50, $result['sell']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Unit/Services/Reporting/TransactionReportQueryTest.php`

Expected: FAIL — class not found.

- [ ] **Step 3: Add scopes to `Transaction`**

Open `app/Models/Transaction.php` and add:

```php
public function scopeCompleted($query)
{
    return $query->where('status', TransactionStatus::Completed->value);
}

public function scopeNotCancelled($query)
{
    return $query->where('status', '!=', TransactionStatus::Cancelled->value);
}

public function scopeForDateRange($query, string $from, string $to)
{
    return $query->whereDate('created_at', '>=', $from)
        ->whereDate('created_at', '<=', $to);
}

public function scopeForBranch($query, ?int $branchId)
{
    return $query->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
}

public function scopeBuy($query)
{
    return $query->where('type', TransactionType::Buy->value);
}

public function scopeSell($query)
{
    return $query->where('type', TransactionType::Sell->value);
}
```

- [ ] **Step 4: Implement `TransactionReportQuery`**

```php
<?php

namespace App\Services\Reporting;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;

class TransactionReportQuery
{
    public function baseQuery(?int $branchId = null): Builder
    {
        return Transaction::query()
            ->notCancelled()
            ->forBranch($branchId);
    }

    public function completed(?int $branchId = null): Builder
    {
        return $this->baseQuery($branchId)->completed();
    }

    public function forDateRange(string $from, string $to, ?int $branchId = null): Builder
    {
        return $this->baseQuery($branchId)->forDateRange($from, $to);
    }

    public function sumByType(?int $branchId = null): array
    {
        $query = $this->completed($branchId);

        return [
            'buy' => (float) (clone $query)->buy()->sum('amount_foreign'),
            'sell' => (float) (clone $query)->sell()->sum('amount_foreign'),
        ];
    }

    public function countByStatus(?int $branchId = null): array
    {
        return $this->baseQuery($branchId)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact tests/Unit/Services/Reporting/TransactionReportQueryTest.php`

Expected: PASS.

- [ ] **Step 6: Refactor reporting code**

Replace manual buy/sell sum loops and status filters in:

- `app/Services/Reporting/ReportingService.php`
- `app/Services/Reporting/CustomerReportService.php`
- `app/Services/Eod/EodReconciliationService.php`
- `app/Services/Compliance/PatternRiskService.php`
- `app/Services/Monitoring/CurrencyFlowMonitor.php`
- `app/Http/Controllers/Report/DashboardController.php`
- `app/Services/Compliance/ComplianceReportingService.php`

Example before:

```php
$buyTotal = Transaction::where('status', 'Completed')
    ->where('type', 'Buy')
    ->where('branch_id', $branchId)
    ->sum('amount_foreign');
```

Example after:

```php
$sums = app(TransactionReportQuery::class)->sumByType($branchId);
$buyTotal = $sums['buy'];
```

- [ ] **Step 7: Run reporting tests**

Run: `php artisan test --compact tests/Feature/Report tests/Unit/Services/Reporting`

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Models/Transaction.php app/Services/Reporting/TransactionReportQuery.php tests/Unit/Services/Reporting/TransactionReportQueryTest.php app/Services/Reporting app/Services/Eod app/Services/Compliance app/Services/Monitoring app/Http/Controllers/Report
git commit -m "refactor(reporting): centralize transaction reporting queries"
```

---

## Task 8: Create Shared Validation Rules

**Files:**
- Create: `app/Rules/ValidCurrencyCode.php`
- Create: `app/Rules/ValidTill.php`
- Create: `app/Rules/ValidAmountForeign.php`
- Create: `app/Rules/ValidRate.php`
- Modify: `app/Http/Requests/Api/V1/Transaction/StoreTransactionRequest.php`
- Test: `tests/Unit/Rules/ValidCurrencyCodeTest.php`, `tests/Unit/Rules/ValidTillTest.php`

- [ ] **Step 1: Write failing tests for rules**

`tests/Unit/Rules/ValidCurrencyCodeTest.php`:

```php
<?php

namespace Tests\Unit\Rules;

use App\Models\Currency;
use App\Rules\ValidCurrencyCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidCurrencyCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_for_active_currency(): void
    {
        Currency::factory()->create(['code' => 'USD', 'is_active' => true]);
        $rule = new ValidCurrencyCode();

        $rule->validate('currency_code', 'USD', fn ($message) => $this->fail($message));
    }

    public function test_fails_for_inactive_currency(): void
    {
        Currency::factory()->create(['code' => 'USD', 'is_active' => false]);
        $rule = new ValidCurrencyCode();

        $passed = true;
        $rule->validate('currency_code', 'USD', function ($message) use (&$passed) {
            $passed = false;
        });

        $this->assertFalse($passed);
    }
}
```

`tests/Unit/Rules/ValidTillTest.php`:

```php
<?php

namespace Tests\Unit\Rules;

use App\Models\Till;
use App\Rules\ValidTill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidTillTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_for_open_till(): void
    {
        $till = Till::factory()->create(['status' => 'open']);
        $rule = new ValidTill();

        $rule->validate('till_id', (string) $till->id, fn ($message) => $this->fail($message));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Unit/Rules/ValidCurrencyCodeTest.php tests/Unit/Rules/ValidTillTest.php`

Expected: FAIL — classes not found.

- [ ] **Step 3: Implement the rules**

`app/Rules/ValidCurrencyCode.php`:

```php
<?php

namespace App\Rules;

use App\Models\Currency;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidCurrencyCode implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail("The {$attribute} must be a string.");
            return;
        }

        $currency = Currency::where('code', $value)->first();

        if ($currency === null) {
            $fail("The selected {$attribute} is invalid.");
            return;
        }

        if (! $currency->is_active) {
            $fail("The selected {$attribute} is inactive.");
        }
    }
}
```

`app/Rules/ValidTill.php`:

```php
<?php

namespace App\Rules;

use App\Models\Till;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidTill implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $till = Till::find($value);

        if ($till === null) {
            $fail("The selected {$attribute} is invalid.");
            return;
        }

        if ($till->status !== 'open') {
            $fail("The selected {$attribute} is not open.");
        }
    }
}
```

`app/Rules/ValidAmountForeign.php`:

```php
<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidAmountForeign implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value)) {
            $fail("The {$attribute} must be a number.");
            return;
        }

        if ($value < 0.01) {
            $fail("The {$attribute} must be at least 0.01.");
            return;
        }

        if ($value > 9999999999.9999) {
            $fail("The {$attribute} may not be greater than 9999999999.9999.");
        }
    }
}
```

`app/Rules/ValidRate.php`:

```php
<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidRate implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value)) {
            $fail("The {$attribute} must be a number.");
            return;
        }

        if ($value < 0.0001) {
            $fail("The {$attribute} must be at least 0.0001.");
            return;
        }

        if ($value > 999999) {
            $fail("The {$attribute} may not be greater than 999999.");
        }
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact tests/Unit/Rules/ValidCurrencyCodeTest.php tests/Unit/Rules/ValidTillTest.php`

Expected: PASS.

- [ ] **Step 5: Apply shared rules to `StoreTransactionRequest`**

Open `app/Http/Requests/Api/V1/Transaction/StoreTransactionRequest.php` and replace:

```php
'currency_code' => 'required|exists:currencies,code',
'amount_foreign' => 'required|numeric|min:0.01|max:9999999999.9999',
'rate' => 'required|numeric|min:0.0001|max:999999',
'till_id' => 'required|string',
```

with:

```php
'currency_code' => ['required', 'string', new ValidCurrencyCode()],
'amount_foreign' => ['required', new ValidAmountForeign()],
'rate' => ['required', new ValidRate()],
'till_id' => ['required', 'string', new ValidTill()],
```

- [ ] **Step 6: Run transaction request tests**

Run: `php artisan test --compact tests/Feature/Api/V1/TransactionStoreResponseShapeTest.php`

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Rules tests/Unit/Rules app/Http/Requests/Api/V1/Transaction/StoreTransactionRequest.php
git commit -m "feat(validation): add shared currency, till, amount, and rate rules"
```

---

## Task 9: Remove Duplicate Flat Form Request Classes

**Files:**
- Delete: `app/Http/Requests/Api/V1/StoreTransactionRequest.php`
- Delete: `app/Http/Requests/Api/V1/ApproveAllocationRequest.php`
- Delete: `app/Http/Requests/Api/V1/RejectAllocationRequest.php`
- Delete: `app/Http/Requests/Api/V1/ModifyAllocationRequest.php`
- Delete: `app/Http/Requests/Api/V1/MyActiveAllocationRequest.php`

- [ ] **Step 1: Identify route bindings**

Run: `grep -R "StoreTransactionRequest\|ApproveAllocationRequest\|RejectAllocationRequest\|ModifyAllocationRequest\|MyActiveAllocationRequest" routes/ app/Http/Controllers`

Confirm every usage imports the namespaced version (`App\Http\Requests\Api\V1\Transaction\...` or `App\Http\Requests\Api\V1\TellerAllocation\...`).

- [ ] **Step 2: Delete the flat legacy classes**

```bash
rm app/Http/Requests/Api/V1/StoreTransactionRequest.php
rm app/Http/Requests/Api/V1/ApproveAllocationRequest.php
rm app/Http/Requests/Api/V1/RejectAllocationRequest.php
rm app/Http/Requests/Api/V1/ModifyAllocationRequest.php
rm app/Http/Requests/Api/V1/MyActiveAllocationRequest.php
```

- [ ] **Step 3: Run all request/controller tests**

Run: `php artisan test --compact tests/Feature/Api/V1`

Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "chore(requests): remove duplicate flat Form Request classes"
```

---

## Task 10: Centralize IP Validation

**Files:**
- Create or modify: `app/Services/Security/IpValidationService.php`
- Modify: `App\Services\Validation\ValidatorMethods` trait
- Modify: `app/Services/Transaction/TransactionValidationService.php`
- Modify: `app/Services/Transaction/TransactionApprovalService.php`
- Modify: `app/Services/Security/RateLimitService.php`
- Modify: `app/Console/Commands/IpBlockerCommand.php`

- [ ] **Step 1: Extract IP validation logic**

Create `app/Services/Security/IpValidationService.php`:

```php
<?php

namespace App\Services\Security;

class IpValidationService
{
    public function isAllowed(string $ip, array $allowlist = [], array $blocklist = []): bool
    {
        if (in_array($ip, $blocklist, true)) {
            return false;
        }

        if ($allowlist === []) {
            return true;
        }

        return in_array($ip, $allowlist, true);
    }
}
```

- [ ] **Step 2: Refactor consumers**

Replace inline IP checks in the listed files with:

```php
app(IpValidationService::class)->isAllowed($ip, $allowlist, $blocklist);
```

- [ ] **Step 3: Run security tests**

Run: `php artisan test --compact --filter=Ip`

Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add app/Services/Security/IpValidationService.php app/Services/Validation app/Services/Transaction app/Services/Security/RateLimitService.php app/Console/Commands/IpBlockerCommand.php
git commit -m "refactor(security): centralize IP allowlist/blocklist validation"
```

---

## Task 11: Final Verification and Formatting

**Files:**
- All modified files

- [ ] **Step 1: Run full test suite**

Run: `php artisan test --compact`

Expected: PASS. Document any pre-existing failures.

- [ ] **Step 2: Run Pint on changed files**

Run: `vendor/bin/pint --dirty --format agent`

Expected: no remaining style issues.

- [ ] **Step 3: Run GitNexus change detection**

Run: `npx gitnexus detect_changes`

Review affected processes and confirm they match the consolidation scope.

- [ ] **Step 4: Commit formatting fixes**

```bash
git add -A
git commit -m "style: apply pint formatting to consolidation changes"
```

---

## Self-Review

1. **Spec coverage:** Each major duplication area from the assessment report maps to a task: API responses (Tasks 1–3), till/currency service operations (Tasks 4–5), audit (Task 6), reporting queries (Task 7), validation rules (Task 8), Form Request deduplication (Task 9), IP validation (Task 10).
2. **Placeholder scan:** No TBD/TODO placeholders; each step includes concrete code, file paths, and commands.
3. **Type consistency:** All helper classes use explicit return types and match Laravel 11 conventions.
4. **Risk notes:** Task 9 (deleting Form Requests) requires confirming route bindings first. Task 5 (currency locking) touches concurrency-sensitive code and must be tested under load if possible.
