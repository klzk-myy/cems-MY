# Audit Batch 2: Service Contracts, Directory Structure, DTOs, Model Hierarchy

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Improve service abstractions, organize the flat service directory, replace array return types with DTOs, and standardize model hierarchy.

**Architecture:** Introduce interfaces for high-value services, group 82 flat services into logical subdirectories, create DTOs for the most common array return patterns, and migrate remaining models to extend BaseModel.

**Tech Stack:** Laravel 11, PHP 8.3, MySQL 8.0, PHPUnit 11

## Global Constraints

- PHP 8.3+, Laravel 11, MySQL 8.0, Redis
- All tests use PHPUnit (no Pest)
- Use `RefreshDatabase` trait in tests
- Run `vendor/bin/pint --dirty --format agent` before committing
- Follow existing code conventions (promoted properties, Eloquent relationships, Form Requests)
- Do NOT break existing public API — all changes must be backward-compatible

---

## Phase 1: Service Interfaces (Tasks 1–4)

### Task 1: Create Core Service Interfaces

**Files:**
- Create: `app/Services/Contracts/CustomerServiceInterface.php`
- Create: `app/Services/Contracts/RateManagementServiceInterface.php`
- Create: `app/Services/Contracts/AuditServiceInterface.php`
- Create: `app/Services/Contracts/ComplianceServiceInterface.php`
- Create: `app/Services/Contracts/AccountingServiceInterface.php`
- Create: `tests/Unit/Services/ServiceContractsTest.php`

**Interfaces:**
- Consumes: Existing service method signatures
- Produces: 5 new interfaces that services will implement

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services;

use App\Services\Contracts\AccountingServiceInterface;
use App\Services\Contracts\AuditServiceInterface;
use App\Services\Contracts\ComplianceServiceInterface;
use App\Services\Contracts\CustomerServiceInterface;
use App\Services\Contracts\RateManagementServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceContractsTest extends TestCase
{
    use RefreshDatabase;

    public function test_services_implement_their_interfaces(): void
    {
        $ mappings = [
            \App\Services\CustomerService::class => CustomerServiceInterface::class,
            \App\Services\RateManagementService::class => RateManagementServiceInterface::class,
            \App\Services\AuditService::class => AuditServiceInterface::class,
            \App\Services\ComplianceService::class => ComplianceServiceInterface::class,
            \App\Services\AccountingService::class => AccountingServiceInterface::class,
        ];

        foreach ($mappings as $concrete => $interface) {
            $this->assertTrue(
                is_subclass_of($concrete, $interface),
                "{$concrete} must implement {$interface}"
            );
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ServiceContractsTest`
Expected: FAIL (interfaces don't exist)

- [ ] **Step 3: Create the 5 interfaces**

Read each service to extract its public method signatures, then create the interface. Example for CustomerServiceInterface:

```php
<?php

namespace App\Services\Contracts;

use App\Models\Customer;

interface CustomerServiceInterface
{
    public function createCustomer(array $data): Customer;
    public function updateCustomer(Customer $customer, array $data): Customer;
    public function getCustomerWithRiskProfile(int $customerId): Customer;
    public function searchCustomers(string $query, int $perPage = 20): \Illuminate\Contracts\Pagination\LengthAwarePaginator;
    public function uploadKycDocument(Customer $customer, $file, string $documentType): array;
}
```

- [ ] **Step 4: Make services implement the interfaces**

Add `implements` clause to each service class. Example:

```php
class CustomerService implements CustomerServiceInterface
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=ServiceContractsTest`
Expected: PASS

- [ ] **Step 6: Run full test suite**

Run: `php artisan test --compact`
Expected: PASS (no regressions)

- [ ] **Step 7: Commit**

```bash
git add app/Services/Contracts/ app/Services/CustomerService.php app/Services/RateManagementService.php app/Services/AuditService.php app/Services/ComplianceService.php app/Services/AccountingService.php tests/Unit/Services/ServiceContractsTest.php
git commit -m "feat: add service interfaces for Customer, RateManagement, Audit, Compliance, and Accounting services"
```

---

### Task 2: Create Transaction Domain Interfaces

**Files:**
- Create: `app/Services/Contracts/TransactionMonitoringServiceInterface.php`
- Create: `app/Services/Contracts/TellerAllocationServiceInterface.php`
- Create: `app/Services/Contracts/CustomerScreeningServiceInterface.php`
- Create: `app/Services/Contracts/ThresholdServiceInterface.php`
- Modify: `app/Services/TransactionMonitoringService.php`
- Modify: `app/Services/TellerAllocationService.php`
- Modify: `app/Services/CustomerScreeningService.php`
- Modify: `app/Services/ThresholdService.php`
- Modify: `tests/Unit/Services/ServiceContractsTest.php`

**Interfaces:**
- Consumes: Task 1 interfaces
- Produces: 4 additional interfaces

- [ ] **Step 1: Add test cases to ServiceContractsTest**

Add to the existing test method's $mappings array:

```php
\App\Services\TransactionMonitoringService::class => TransactionMonitoringServiceInterface::class,
\App\Services\TellerAllocationService::class => TellerAllocationServiceInterface::class,
\App\Services\CustomerScreeningService::class => CustomerScreeningServiceInterface::class,
\App\Services\ThresholdService::class => ThresholdServiceInterface::class,
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=ServiceContractsTest`
Expected: FAIL

- [ ] **Step 3: Create the 4 interfaces**

Read each service to extract public method signatures, then create the interfaces.

- [ ] **Step 4: Make services implement the interfaces**

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=ServiceContractsTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Services/Contracts/ app/Services/TransactionMonitoringService.php app/Services/TellerAllocationService.php app/Services/CustomerScreeningService.php app/Services/ThresholdService.php tests/Unit/Services/ServiceContractsTest.php
git commit -m "feat: add service interfaces for Transaction, TellerAllocation, Screening, and Threshold services"
```

---

### Task 3: Create Reporting & System Interfaces

**Files:**
- Create: `app/Services/Contracts/ReportingServiceInterface.php`
- Create: `app/Services/Contracts/MathServiceInterface.php`
- Create: `app/Services/Contracts/CurrencyPositionServiceInterface.php`
- Modify: `app/Services/ReportingService.php`
- Modify: `app/Services/MathService.php`
- Modify: `app/Services/CurrencyPositionService.php`
- Modify: `tests/Unit/Services/ServiceContractsTest.php`

- [ ] **Step 1: Add test cases**

Add to $mappings:

```php
\App\Services\ReportingService::class => ReportingServiceInterface::class,
\App\Services\MathService::class => MathServiceInterface::class,
\App\Services\CurrencyPositionService::class => CurrencyPositionServiceInterface::class,
```

- [ ] **Step 2: Run test to verify it fails**

- [ ] **Step 3: Create the 3 interfaces**

- [ ] **Step 4: Make services implement the interfaces**

- [ ] **Step 5: Run test to verify it passes**

- [ ] **Step 6: Commit**

```bash
git commit -m "feat: add service interfaces for Reporting, Math, and CurrencyPosition services"
```

---

### Task 4: Verify All Interface Tests Pass

**Files:**
- Run full test suite
- Run pint

- [ ] **Step 1: Run full test suite**

Run: `php artisan test --compact`
Expected: PASS

- [ ] **Step 2: Run pint**

Run: `vendor/bin/pint --dirty --format agent`

- [ ] **Step 3: Commit if needed**

```bash
git commit -m "style: fix code style for service interfaces"
```

---

## Phase 2: Service Directory Grouping (Tasks 5–8)

### Task 5: Create Accounting Subdirectory

**Files:**
- Move: `app/Services/AccountingService.php` → `app/Services/Accounting/AccountingService.php`
- Move: `app/Services/LedgerService.php` → `app/Services/Accounting/LedgerService.php`
- Move: `app/Services/FiscalYearService.php` → `app/Services/Accounting/FiscalYearService.php`
- Move: `app/Services/PeriodCloseService.php` → `app/Services/Accounting/PeriodCloseService.php`
- Move: `app/Services/MonthEndCloseService.php` → `app/Services/Accounting/MonthEndCloseService.php`
- Move: `app/Services/BankReconciliationService.php` → `app/Services/Accounting/BankReconciliationService.php`
- Move: `app/Services/RevaluationService.php` → `app/Services/Accounting/RevaluationService.php`
- Move: `app/Services/BudgetService.php` → `app/Services/Accounting/BudgetService.php`
- Move: `app/Services/TransactionAccountingService.php` → `app/Services/Accounting/TransactionAccountingService.php`
- Move: `app/Services/CurrencyPositionService.php` → `app/Services/Accounting/CurrencyPositionService.php`
- Update: All imports across the codebase
- Create: `tests/Unit/Services/AccountingDirectoryTest.php`

**Interfaces:**
- Consumes: Task 1-3 interfaces
- Produces: Organized `app/Services/Accounting/` directory

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounting_services_are_in_accounting_directory(): void
    {
        $expectedFiles = [
            'AccountingService.php',
            'LedgerService.php',
            'FiscalYearService.php',
            'PeriodCloseService.php',
            'MonthEndCloseService.php',
            'BankReconciliationService.php',
            'RevaluationService.php',
            'BudgetService.php',
            'TransactionAccountingService.php',
            'CurrencyPositionService.php',
        ];

        foreach ($expectedFiles as $file) {
            $this->assertFileExists(
                app_path("Services/Accounting/{$file}"),
                "{$file} should be in Services/Accounting/"
            );
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=AccountingDirectoryTest`
Expected: FAIL

- [ ] **Step 3: Create directory and move files**

```bash
mkdir -p app/Services/Accounting
git mv app/Services/AccountingService.php app/Services/Accounting/
git mv app/Services/LedgerService.php app/Services/Accounting/
# ... repeat for all 10 files
```

- [ ] **Step 4: Update namespace in each moved file**

Change `namespace App\Services;` to `namespace App\Services\Accounting;` in each file.

- [ ] **Step 5: Update imports across codebase**

Use grep to find all files importing the moved services and update their `use` statements.

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --compact --filter=AccountingDirectoryTest`
Expected: PASS

- [ ] **Step 7: Run full test suite**

Run: `php artisan test --compact`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "refactor: move accounting services to Services/Accounting/ subdirectory"
```

---

### Task 6: Create Compliance Subdirectory (Expand Existing)

**Files:**
- Move additional compliance services to `app/Services/Compliance/`
- Services to move: `ComplianceService`, `CddLevelDeterminationService`, `CustomerRiskScoringService`, `CustomerRiskReviewService`, `HistoricalRiskAnalysisService`, `AlertTriageService`, `EddService`, `EddTemplateService`, `KycDocumentExpiryService`, `PepApprovalService`, `RiskCalculationService`, `SanctionsDownloadService`, `SanctionsImportService`, `SanctionsOrchestrationService`, `NarrativeGenerator`
- Update all imports
- Create: `tests/Unit/Services/ComplianceDirectoryTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComplianceDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_compliance_services_are_in_compliance_directory(): void
    {
        $expectedFiles = [
            'ComplianceService.php',
            'CddLevelDeterminationService.php',
            'CustomerRiskScoringService.php',
            'CustomerRiskReviewService.php',
            'HistoricalRiskAnalysisService.php',
            'AlertTriageService.php',
            'EddService.php',
            'EddTemplateService.php',
            'KycDocumentExpiryService.php',
            'PepApprovalService.php',
            'RiskCalculationService.php',
            'SanctionsDownloadService.php',
            'SanctionsImportService.php',
            'SanctionsOrchestrationService.php',
            'NarrativeGenerator.php',
        ];

        foreach ($expectedFiles as $file) {
            $this->assertFileExists(
                app_path("Services/Compliance/{$file}"),
                "{$file} should be in Services/Compliance/"
            );
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

- [ ] **Step 3: Move files and update namespaces**

- [ ] **Step 4: Update imports across codebase**

- [ ] **Step 5: Run test to verify it passes**

- [ ] **Step 6: Commit**

```bash
git commit -m "refactor: move compliance services to Services/Compliance/ subdirectory"
```

---

### Task 7: Create Transaction & Branch Subdirectories

**Files:**
- Move transaction services to `app/Services/Transaction/`
- Move branch operations services to `app/Services/Branch/`
- Transaction services: `TransactionService`, `TransactionStateMachine`, `TransactionValidationService`, `TransactionApprovalService`, `TransactionCancellationService`, `TransactionReversalService`, `TransactionRecoveryService`, `TransactionImportService`, `TransactionErrorHandler`, `TransactionMonitoringService`, `RateApiService`, `RateManagementService`, `StockReleaseService`, `StockTransferService`
- Branch services: `BranchService`, `BranchClosingService`, `BranchPoolService`, `CounterService`, `CounterHandoverService`, `CounterOpeningWorkflowService`, `EmergencyCounterService`, `TellerAllocationService`, `TillService`
- Update all imports
- Create tests

- [ ] **Step 1: Write the failing test**

- [ ] **Step 2: Move files and update namespaces**

- [ ] **Step 3: Update imports across codebase**

- [ ] **Step 4: Run tests to verify they pass**

- [ ] **Step 5: Commit**

```bash
git commit -m "refactor: move transaction and branch services to subdirectories"
```

---

### Task 8: Create System Subdirectory & Clean Up Root

**Files:**
- Move system/infrastructure services to `app/Services/System/`
- System services: `MathService`, `EncryptionService`, `CacheOptimizationService`, `CacheMonitoringService`, `CacheTagsService`, `QueryLoggingService`, `QueryOptimizerService`, `RateLimitService`, `PerformanceBaselineService`, `BackupService`, `DocumentStorageService`, `LogRotationService`, `SetupService`, `SystemAlertService`, `SystemHealthService`, `TestRunnerService`, `WizardSessionService`, `MfaService`
- Move DTOs to `app/Services/DTOs/`
- DTOs: `PreValidationResult`, `SanctionCheckResult`
- Move remaining customer services to `app/Services/Customer/`
- Customer services: `CustomerService`, `CustomerRelationService`, `UserService`
- Move reporting services to `app/Services/Reporting/`
- Reporting services: `ReportingService`, `CustomerReportService`, `ExportService`, `FinancialRatioService`, `ReportSchedulingService`
- Update all imports
- Create tests

- [ ] **Step 1: Write the failing test**

- [ ] **Step 2: Create directories and move files**

- [ ] **Step 3: Update namespaces and imports**

- [ ] **Step 4: Run full test suite**

- [ ] **Step 5: Commit**

```bash
git commit -m "refactor: move system, customer, reporting services to subdirectories and DTOs to DTOs/"
```

---

## Phase 3: DTO Return Types (Tasks 9–12)

### Task 9: Create Core DTOs

**Files:**
- Create: `app/Services/DTOs/ValidationResult.php`
- Create: `app/Services/DTOs/AllocationValidationResult.php`
- Create: `app/Services/DTOs/ComplianceCheckResult.php`
- Create: `app/Services/DTOs/RateOverrideResult.php`
- Create: `tests/Unit/Services/DTOTest.php`

**Interfaces:**
- Consumes: Existing array return patterns
- Produces: 4 new DTO classes

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services;

use App\Services\DTOs\AllocationValidationResult;
use App\Services\DTOs\ComplianceCheckResult;
use App\Services\DTOs\RateOverrideResult;
use App\Services\DTOs\ValidationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DTOTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_result_holds_data(): void
    {
        $result = new ValidationResult(
            valid: true,
            errors: [],
            warnings: ['Low balance']
        );

        $this->assertTrue($result->valid);
        $this->assertEmpty($result->errors);
        $this->assertContains('Low balance', $result->warnings);
    }

    public function test_allocation_validation_result(): void
    {
        $result = new AllocationValidationResult(
            valid: false,
            reason: 'Exceeds daily limit',
            allocation: null
        );

        $this->assertFalse($result->valid);
        $this->assertEquals('Exceeds daily limit', $result->reason);
        $this->assertNull($result->allocation);
    }

    public function test_compliance_check_result(): void
    {
        $result = new ComplianceCheckResult(
            requiresHold: true,
            reasons: ['High risk customer', 'Large amount'],
            cddLevel: 'Enhanced'
        );

        $this->assertTrue($result->requiresHold);
        $this->assertCount(2, $result->reasons);
        $this->assertEquals('Enhanced', $result->cddLevel);
    }

    public function test_rate_override_result(): void
    {
        $result = new RateOverrideResult(
            success: true,
            message: 'Rate updated',
            previousRate: '4.5000',
            newRate: '4.5500'
        );

        $this->assertTrue($result->success);
        $this->assertEquals('4.5000', $result->previousRate);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=DTOTest`
Expected: FAIL (classes don't exist)

- [ ] **Step 3: Create the 4 DTO classes**

```php
<?php

namespace App\Services\DTOs;

class ValidationResult
{
    public function __construct(
        public readonly bool $valid,
        public readonly array $errors = [],
        public readonly array $warnings = [],
    ) {}
}
```

```php
<?php

namespace App\Services\DTOs;

class AllocationValidationResult
{
    public function __construct(
        public readonly bool $valid,
        public readonly ?string $reason = null,
        public readonly ?object $allocation = null,
    ) {}
}
```

```php
<?php

namespace App\Services\DTOs;

class ComplianceCheckResult
{
    public function __construct(
        public readonly bool $requiresHold,
        public readonly array $reasons = [],
        public readonly ?string $cddLevel = null,
    ) {}
}
```

```php
<?php

namespace App\Services\DTOs;

class RateOverrideResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly ?string $previousRate = null,
        public readonly ?string $newRate = null,
    ) {}
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=DTOTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/DTOs/ tests/Unit/Services/DTOTest.php
git commit -m "feat: add DTO classes for ValidationResult, AllocationValidation, ComplianceCheck, and RateOverride"
```

---

### Task 10: Migrate TellerAllocationService to DTOs

**Files:**
- Modify: `app/Services/Transaction/TellerAllocationService.php`
- Modify: `tests/Unit/Services/TellerAllocationServiceTest.php`

**Interfaces:**
- Consumes: `AllocationValidationResult` DTO
- Produces: Service methods returning typed DTOs instead of arrays

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Services;

use App\Services\DTOs\AllocationValidationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TellerAllocationDTOTest extends TestCase
{
    use RefreshDatabase;

    public function test_validate_allocation_returns_dto(): void
    {
        $service = app(\App\Services\Transaction\TellerAllocationService::class);
        $user = \App\Models\User::factory()->create();
        $user->assignRole('teller');

        $result = $service->validateTransaction(
            $user,
            'USD',
            '100.00',
            true
        );

        $this->assertInstanceOf(AllocationValidationResult::class, $result);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

- [ ] **Step 3: Update validateTransaction to return AllocationValidationResult**

Change the method signature and return type, update the return statements to construct the DTO.

- [ ] **Step 4: Run test to verify it passes**

- [ ] **Step 5: Update callers to use DTO properties**

- [ ] **Step 6: Commit**

```bash
git commit -m "refactor: migrate TellerAllocationService to return AllocationValidationResult DTO"
```

---

### Task 11: Migrate ComplianceService to DTOs

**Files:**
- Modify: `app/Services/Compliance/ComplianceService.php`
- Modify: Tests

**Interfaces:**
- Consumes: `ComplianceCheckResult` DTO
- Produces: Service methods returning typed DTOs

- [ ] **Step 1: Write the failing test**

- [ ] **Step 2: Update requiresHold to return ComplianceCheckResult**

- [ ] **Step 3: Update callers**

- [ ] **Step 4: Run tests**

- [ ] **Step 5: Commit**

```bash
git commit -m "refactor: migrate ComplianceService to return ComplianceCheckResult DTO"
```

---

### Task 12: Migrate RateManagementService to DTOs

**Files:**
- Modify: `app/Services/Transaction/RateManagementService.php`
- Modify: Tests

- [ ] **Step 1: Write the failing test**

- [ ] **Step 2: Update overrideRate to return RateOverrideResult**

- [ ] **Step 3: Update callers**

- [ ] **Step 4: Run tests**

- [ ] **Step 5: Commit**

```bash
git commit -m "refactor: migrate RateManagementService to return RateOverrideResult DTO"
```

---

## Phase 4: Model Hierarchy (Tasks 13–15)

### Task 13: Migrate Branch & Counter Models to BaseModel

**Files:**
- Modify: `app/Models/Branch.php`
- Modify: `app/Models/Counter.php`
- Modify: `app/Models/CounterSession.php`
- Modify: `app/Models/CounterHandover.php`
- Modify: `app/Models/TillBalance.php`
- Modify: `app/Models/TellerAllocation.php`
- Modify: `tests/Unit/Models/ModelHierarchyTest.php`

**Interfaces:**
- Consumes: `BaseModel`, traits
- Produces: Models using shared traits instead of duplicating code

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Models;

use App\Models\Branch;
use App\Models\Counter;
use App\Models\CounterSession;
use App\Models\CounterHandover;
use App\Models\TillBalance;
use App\Models\TellerAllocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_models_extend_base_model(): void
    {
        $models = [
            Branch::class,
            Counter::class,
            CounterSession::class,
            CounterHandover::class,
            TillBalance::class,
            TellerAllocation::class,
        ];

        foreach ($models as $model) {
            $instance = new $model;
            $this->assertInstanceOf(
                \App\Models\Bases\BaseModel::class,
                $instance,
                "{$model} should extend BaseModel"
            );
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

- [ ] **Step 3: Update each model to extend BaseModel**

Add `use BelongsToBranch;` trait where applicable. Remove duplicated relationship code.

- [ ] **Step 4: Run test to verify it passes**

- [ ] **Step 5: Commit**

```bash
git commit -m "refactor: migrate Branch/Counter models to extend BaseModel with shared traits"
```

---

### Task 14: Migrate Customer-Related Models to BaseModel

**Files:**
- Modify: `app/Models/Customer.php`
- Modify: `app/Models/CustomerDocument.php`
- Modify: `app/Models/CustomerNote.php`
- Modify: `app/Models/CustomerRelation.php`
- Modify: `app/Models/CustomerRiskHistory.php`
- Modify: `app/Models/RiskScoreSnapshot.php`
- Modify: Tests

- [ ] **Step 1: Write the failing test**

- [ ] **Step 2: Update models to extend BaseModel**

- [ ] **Step 3: Run tests**

- [ ] **Step 4: Commit**

```bash
git commit -m "refactor: migrate Customer-related models to extend BaseModel"
```

---

### Task 15: Migrate Remaining Models to BaseModel

**Files:**
- Modify: Remaining models that extend Model directly
- Modify: Tests

- [ ] **Step 1: Write the failing test**

- [ ] **Step 2: Update remaining models**

- [ ] **Step 3: Run full test suite**

- [ ] **Step 4: Commit**

```bash
git commit -m "refactor: migrate remaining models to extend BaseModel"
```

---

## Execution Summary

| Phase | Tasks | Est. Time |
|-------|-------|-----------|
| Phase 1: Service Interfaces | 1–4 | 1–2 hours |
| Phase 2: Directory Grouping | 5–8 | 2–3 hours |
| Phase 3: DTO Return Types | 9–12 | 2–3 hours |
| Phase 4: Model Hierarchy | 13–15 | 1–2 hours |
| **Total** | **15 tasks** | **~8 hours** |

## Verification After Each Phase

After completing each phase, run the full test suite:

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
```
