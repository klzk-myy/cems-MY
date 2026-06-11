# Code Consolidation & Refactoring Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Consolidate overlapping logging infrastructure, unify customer retrieval patterns, reduce Form Request boilerplate, and extract oversized services.

**Architecture:** Merge duplicate logging services into a unified `AuditService`, introduce a `CustomerRepository` for consolidated retrieval, create a base `AuthorizedFormRequest` to eliminate 40+ identical `authorize()` methods, and extract responsibilities from oversized services.

**Tech Stack:** PHP 8.3, Laravel 10, Eloquent ORM

---

## Phase 1: Logging Consolidation

### Task 1: Analyze AuditService and ComprehensiveLogService overlap

**Files:**
- Modify: `app/Services/AuditService.php`
- Modify: `app/Services/ComprehensiveLogService.php`
- Test: `tests/Unit/Services/AuditServiceTest.php` (create if not exists)

- [ ] **Step 1: Inventory all methods in ComprehensiveLogService**

Run: `grep -n "public function" app/Services/ComprehensiveLogService.php`
List each method and its purpose.

- [ ] **Step 2: Inventory all 30+ log* methods in AuditService**

Run: `grep -n "public function log" app/Services/AuditService.php`
Categorize into: transaction, customer, compliance, MFA, session, permission, other.

- [ ] **Step 3: Identify exact overlap — methods logging to SystemLog**

Run: `grep -n "SystemLog::" app/Services/ComprehensiveLogService.php`
Compare with AuditService's SystemLog usage.

- [ ] **Step 4: Write test to verify both services produce same SystemLog entries**

```php
// tests/Unit/Services/LoggingConsolidationTest.php
public function test_audit_service_logs_transaction_action(): void
{
    $auditService = app(AuditService::class);
    $log = $auditService->logTransaction('test_action', 1, ['old' => ['a' => 1], 'new' => ['a' => 2]]);

    $this->assertInstanceOf(SystemLog::class, $log);
    $this->assertEquals('Transaction', $log->entity_type);
    $this->assertEquals(1, $log->entity_id);
}
```

- [ ] **Step 5: Commit**

```bash
git add app/Services/AuditService.php app/Services/ComprehensiveLogService.php
git commit -m "refactor: inventory logging services for consolidation"
```

---

### Task 2: Deprecate ComprehensiveLogService in favor of AuditService

**Files:**
- Modify: `app/Services/ComprehensiveLogService.php` (add deprecation notices)
- Modify: `app/Services/AuditService.php` (add missing methods if any)
- Modify: `app/Providers/AppServiceProvider.php` (register deprecated alias)
- Test: `tests/Unit/Services/AuditServiceTest.php`

- [ ] **Step 1: Identify any ComprehensiveLogService methods not in AuditService**

Compare method lists from Task 1. List unique methods.

- [ ] **Step 2: Add unique methods to AuditService**

If `logProcedureTrigger`, `logControllerAction`, `logModelEvent`, or `logTransactionWorkflow` exist only in ComprehensiveLogService, add them to AuditService.

```php
// Add to AuditService.php
public function logProcedureTrigger(string $procedureName, array $parameters = []): SystemLog
{
    return $this->logWithSeverity('procedure_trigger', [
        'entity_type' => 'Procedure',
        'new_values' => [
            'procedure_name' => $procedureName,
            'parameters' => $parameters,
        ],
    ]);
}
```

- [ ] **Step 3: Add deprecation attribute to ComprehensiveLogService**

```php
#[Deprecated(since: '2.0', message: 'Use AuditService instead.')]
class ComprehensiveLogService
{
    // Keep existing methods but mark as deprecated
}
```

- [ ] **Step 4: Wire up alias in AppServiceProvider**

```php
// app/Providers/AppServiceProvider.php boot()
$this->app->alias(AuditService::class, ComprehensiveLogService::class);
```

- [ ] **Step 5: Update ComprehensiveLogService consumers**

Find all consumers: `grep -rn "ComprehensiveLogService" app/ --include="*.php" | grep -v "ComprehensiveLogService.php"`
Replace with `AuditService`.

- [ ] **Step 6: Run tests**

Run: `php artisan test --filter=AuditServiceTest`
Expected: All pass.

- [ ] **Step 7: Commit**

```bash
git add app/Services/AuditService.php app/Services/ComprehensiveLogService.php app/Providers/AppServiceProvider.php
git commit -m "refactor!: deprecate ComprehensiveLogService, unify on AuditService"
```

---

### Task 3: Extract LoggerInjectable trait usage analysis

**Files:**
- Modify: `app/Http/Traits/LoggerInjectable.php`
- Test: `grep -rn "use.*LoggerInjectable" app/ --include="*.php"`

- [ ] **Step 1: Find all classes using LoggerInjectable**

Run: `grep -rn "use.*LoggerInjectable" app/ --include="*.php"`
List them.

- [ ] **Step 2: Determine if LoggerInjectable is redundant given PSR-3 is auto-injected**

Most Laravel services can simply `use Log;` or type-hint `LoggerInterface`. Determine if this trait adds value.

- [ ] **Step 3: Document findings and decide to keep or remove trait**

If trait is used by 3+ classes meaningfully, keep it. Otherwise, inline its usage.

- [ ] **Step 4: Commit**

---

## Phase 2: Customer Retrieval Unification

### Task 4: Create CustomerRepository

**Files:**
- Create: `app/Repositories/CustomerRepository.php`
- Modify: `app/Models/Customer.php` (add repository property)
- Test: `tests/Unit/Repositories/CustomerRepositoryTest.php`

- [ ] **Step 1: Create CustomerRepository with all retrieval methods**

```php
// app/Repositories/CustomerRepository.php
namespace App\Repositories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Collection;

class CustomerRepository
{
    public function findById(int $customerId): ?Customer
    {
        return Customer::find($customerId);
    }

    public function findByIdNumber(string $idNumber): ?Customer
    {
        return Customer::where('id_number_hash', hash('sha256', $idNumber))->first();
    }

    public function search(string $query): Collection
    {
        return Customer::where('name', 'like', "%{$query}%")
            ->orWhere('id_number_hash', 'like', "%{$query}%")
            ->get();
    }

    public function getByIds(array $customerIds): Collection
    {
        return Customer::whereIn('id', $customerIds)->get();
    }

    public function getCustomersNeedingRescreening(): Collection
    {
        return Customer::where('risk_score', '>=', 60)
            ->orWhere('last_screened_at', '<', now()->subDays(30))
            ->get();
    }
}
```

- [ ] **Step 2: Update CustomerService to delegate to repository**

```php
// app/Services/CustomerService.php
public function __construct(
    public CustomerRepository $customerRepository,
    // ... other deps
) {}

public function getCustomer(int $customerId): ?Customer
{
    return $this->customerRepository->findById($customerId);
}

public function findByIdNumber(string $idNumber): ?Customer
{
    return $this->customerRepository->findByIdNumber($idNumber);
}
```

- [ ] **Step 3: Update all other services using customer retrieval**

Find: `grep -rn "Customer::" app/Services/ --include="*.php" | head -30`
Replace scattered `Customer::find()` calls with repository.

- [ ] **Step 4: Write repository tests**

```php
// tests/Unit/Repositories/CustomerRepositoryTest.php
public function test_find_by_id_returns_customer(): void
{
    $customer = Customer::factory()->create();
    $repo = new CustomerRepository();

    $result = $repo->findById($customer->id);

    $this->assertEquals($customer->id, $result->id);
}
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter=CustomerRepositoryTest`
Expected: All pass.

- [ ] **Step 6: Commit**

```bash
git add app/Repositories/CustomerRepository.php app/Services/CustomerService.php
git commit -m "feat: add CustomerRepository for unified customer retrieval"
```

---

## Phase 3: Form Request Boilerplate Reduction

### Task 5: Create AuthorizedFormRequest base class

**Files:**
- Create: `app/Http/Requests/AuthorizedFormRequest.php`
- Modify: All 40+ form requests that have `authorize(): bool { return true; }`
- Test: `tests/Unit/Http/Requests/AuthorizedFormRequestTest.php`

- [ ] **Step 1: List all Form Request classes**

Run: `grep -rn "class.*extends.*FormRequest" app/Http/Requests/*.php | grep -v AuthorizedFormRequest`
Count them.

- [ ] **Step 2: Create base class**

```php
// app/Http/Requests/AuthorizedFormRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class AuthorizedFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
```

- [ ] **Step 3: Update first 5 form requests to extend base class**

Example:
```php
// Before
class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool { return true; }
}

// After
class StoreCustomerRequest extends AuthorizedFormRequest {}
```

Use `replaceAll` in edit tool for `extends FormRequest` where `authorize(): bool { return true; }` follows.

- [ ] **Step 4: Run tests**

Run: `php artisan test --filter=StoreCustomerRequest`
Expected: All pass.

- [ ] **Step 5: Update remaining form requests in batches**

Repeat batch updates until all 40+ are migrated.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Requests/AuthorizedFormRequest.php
git add app/Http/Requests/*.php
git commit -m "refactor!: add AuthorizedFormRequest base class, eliminate 40+ duplicate authorize() methods"
```

---

## Phase 4: Large Service Extraction

### Task 6: Analyze and split TransactionCancellationService (1,012 lines)

**Files:**
- Modify: `app/Services/TransactionCancellationService.php`
- Create: `app/Services/TransactionReversalService.php` (extracted)
- Create: `app/Services/StockReleaseService.php` (extracted)
- Test: `tests/Unit/Services/TransactionCancellationServiceTest.php`

- [ ] **Step 1: Identify natural split points**

Run: `grep -n "private function\|protected function" app/Services/TransactionCancellationService.php`
Categorize methods by responsibility.

Potential splits:
- `handleReversal()` + reversal-specific helpers → `TransactionReversalService`
- `releaseStock()`, `restorePositions()` → `StockReleaseService`

- [ ] **Step 2: Create StockReleaseService skeleton**

```php
// app/Services/StockReleaseService.php
namespace App\Services;

class StockReleaseService
{
    public function releaseReservation(Transaction $transaction): void { /* ... */ }
    public function restorePositions(Transaction $transaction): void { /* ... */ }
    public function releaseTillBalance(Transaction $transaction): void { /* ... */ }
}
```

- [ ] **Step 3: Extract reversal logic to TransactionReversalService**

```php
// app/Services/TransactionReversalService.php
namespace App\Services;

class TransactionReversalService
{
    public function reverse(Transaction $transaction, int $userId): void { /* ... */ }
    protected function validateReversalEligibility(Transaction $transaction): void { /* ... */ }
    protected function createReversalJournal(Transaction $transaction): void { /* ... */ }
}
```

- [ ] **Step 4: Update TransactionCancellationService to compose new services**

```php
// app/Services/TransactionCancellationService.php
public function __construct(
    public TransactionReversalService $reversalService,
    public StockReleaseService $stockReleaseService,
    // ... existing deps
) {}
```

- [ ] **Step 5: Write tests for new services**

```php
// tests/Unit/Services/TransactionReversalServiceTest.php
public function test_reverse_creates_reversal_journal(): void
{
    // Arrange
    $transaction = Transaction::factory()->create(['status' => 'completed']);
    $service = new TransactionReversalService();

    // Act
    $service->reverse($transaction, user()->id);

    // Assert
    $this->assertEquals('reversed', $transaction->fresh()->status);
}
```

- [ ] **Step 6: Run full transaction cancellation tests**

Run: `php artisan test --filter=TransactionCancellation`
Expected: All pass.

- [ ] **Step 7: Commit**

```bash
git add app/Services/TransactionReversalService.php app/Services/StockReleaseService.php
git add app/Services/TransactionCancellationService.php
git commit -m "refactor: extract reversal and stock release from TransactionCancellationService"
```

---

## Phase 5: Validation Centralization

### Task 7: Create Validators trait for scattered validation methods

**Files:**
- Create: `app/Http/Traits/ValidatorMethods.php`
- Modify: `app/Services/SanctionsDownloadService.php`
- Modify: `app/Services/TransactionService.php`
- Test: `tests/Unit/Http/Traits/ValidatorMethodsTest.php`

- [ ] **Step 1: Inventory validation methods across services**

Run: `grep -n "protected function validate\|private function validate" app/Services/*.php`
List each with its file and method signature.

- [ ] **Step 2: Create Validators trait with reusable methods**

```php
// app/Http/Traits/ValidatorMethods.php
namespace App\Http\Traits;

trait ValidatorMethods
{
    protected function validateCurrencyCode(string $currencyCode): void
    {
        if (! preg_match('/^[A-Z]{3}$/', $currencyCode)) {
            throw new \InvalidArgumentException("Invalid currency code: {$currencyCode}");
        }
    }

    protected function validateIpAddress(?string $ipAddress): void
    {
        if ($ipAddress && ! filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            throw new \InvalidArgumentException("Invalid IP address: {$ipAddress}");
        }
    }

    protected function validateXml(string $content): bool
    {
        libxml_use_internal_errors(true);
        $result = simplexml_load_string($content);
        return $result !== false;
    }

    protected function validateJson(string $content): bool
    {
        json_decode($content);
        return json_last_error() === JSON_ERROR_NONE;
    }
}
```

- [ ] **Step 3: Apply trait to services that have matching methods**

Add `use ValidatorMethods;` to SanctionsDownloadService, TransactionService, etc.

- [ ] **Step 4: Run tests**

Run: `php artisan test --filter=ValidatorMethodsTest`
Expected: All pass.

- [ ] **Step 5: Commit**

---

## Phase 6: Sanctions Workflow Consolidation

### Task 8: Create SanctionsOrchestrationService

**Files:**
- Create: `app/Services/SanctionsOrchestrationService.php`
- Modify: `app/Services/SanctionsDownloadService.php`
- Modify: `app/Services/SanctionsImportService.php`
- Modify: `app/Http/Controllers/Compliance/SanctionListController.php`
- Test: `tests/Unit/Services/SanctionsOrchestrationServiceTest.php`

- [ ] **Step 1: Map current sanctions workflow**

Read SanctionListController to understand download → import → store flow.

- [ ] **Step 2: Create orchestration service**

```php
// app/Services/SanctionsOrchestrationService.php
namespace App\Services;

class SanctionsOrchestrationService
{
    public function __construct(
        public SanctionsDownloadService $downloadService,
        public SanctionsImportService $importService,
    ) {}

    public function syncSanctionsList(SanctionList $list): array
    {
        // Orchestrate download + import in proper order
        $content = $this->downloadService->download($list);
        $result = $this->importService->import($list, $content);
        return $result;
    }
}
```

- [ ] **Step 3: Update SanctionListController to use orchestration service**

Replace inline orchestration with single service call.

- [ ] **Step 4: Write tests**

- [ ] **Step 5: Commit**

---

## Execution Summary

| Phase | Task | Files Affected | Estimated Risk |
|-------|------|---------------|----------------|
| 1 | Logging Consolidation | AuditService, ComprehensiveLogService | Medium |
| 2 | CustomerRepository | app/Repositories/, CustomerService | Low |
| 3 | AuthorizedFormRequest | 40+ Request classes | Low |
| 4 | TransactionCancellationService Split | 3 service files | High |
| 5 | ValidatorMethods | 4 service files | Low |
| 6 | SanctionsOrchestrationService | 3 services, 1 controller | Medium |

**Start with Phase 2 (lowest risk, establishes pattern) before Phase 4 (highest risk refactoring).**
