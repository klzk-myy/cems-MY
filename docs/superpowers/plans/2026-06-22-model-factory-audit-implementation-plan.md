# Model & Factory Audit Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Backfill missing factories, fix factory enum usage, add high-value inverse relationships, and align verified schema mismatches so the model layer is complete and testable.

**Architecture:** Keep the existing `BaseModel` / `AccountingModel` / `ComplianceModel` / `TransactionModel` / `SystemModel` hierarchy and shared traits. Changes are additive and corrective. Each change is verified with a targeted PHPUnit test and Laravel Pint.

**Tech Stack:** Laravel 11.51.0, PHP 8.3, Eloquent, PHPUnit 11, SQLite/MySQL, Laravel Pint.

---

## File Structure

| File | Responsibility |
|------|----------------|
| `database/factories/*` | Backfill missing factories and fix enum usage. |
| `app/Models/*` | Add inverse relationships and align `$fillable`/`$casts` with actual DB schema. |
| `tests/Unit/Models/*` | New tests for factories and relationships. |
| `scripts/verify-model-schema.php` | Reusable script that compares model `$fillable`/`$casts` against the live DB schema. |

---

## Phase 1: Backfill Missing Factories

### Task 1.1: Generate and fill missing factory stubs

**Files:**
- Create: `database/factories/*Factory.php` (26 stubs)
- Modify: `database/factories/*Factory.php` (fill definitions)
- Test: `tests/Unit/Models/FactorySmokeTest.php` (create)

- [ ] **Step 1: Generate factory stubs (skip if stubs already exist)**

Run the following commands to create stubs for every model that currently lacks a factory. If stubs already exist, proceed to Step 2.

```bash
php artisan make:factory AccountLedgerFactory --model=AccountLedger --no-interaction
php artisan make:factory AmlRuleFactory --model=AmlRule --no-interaction
php artisan make:factory BackupLogFactory --model=BackupLog --no-interaction
php artisan make:factory BankReconciliationFactory --model=BankReconciliation --no-interaction
php artisan make:factory BranchClosureWorkflowFactory --model=BranchClosureWorkflow --no-interaction
php artisan make:factory ComplianceCaseDocumentFactory --model='App\\Models\\Compliance\\ComplianceCaseDocument' --no-interaction
php artisan make:factory ComplianceCaseLinkFactory --model='App\\Models\\Compliance\\ComplianceCaseLink' --no-interaction
php artisan make:factory CostCenterFactory --model=CostCenter --no-interaction
php artisan make:factory CustomerBehavioralBaselineFactory --model='App\\Models\\Compliance\\CustomerBehavioralBaseline' --no-interaction
php artisan make:factory CustomerRiskProfileFactory --model='App\\Models\\Compliance\\CustomerRiskProfile' --no-interaction
php artisan make:factory DepartmentFactory --model=Department --no-interaction
php artisan make:factory DeviceComputationsFactory --model=DeviceComputations --no-interaction
php artisan make:factory EddDocumentRequestFactory --model='App\\Models\\Compliance\\EddDocumentRequest' --no-interaction
php artisan make:factory EddTemplateFactory --model=EddTemplate --no-interaction
php artisan make:factory ExchangeRateHistoryFactory --model=ExchangeRateHistory --no-interaction
php artisan make:factory HighRiskCountryFactory --model=HighRiskCountry --no-interaction
php artisan make:factory MfaRecoveryCodeFactory --model=MfaRecoveryCode --no-interaction
php artisan make:factory PepApprovalRequestFactory --model=PepApprovalRequest --no-interaction
php artisan make:factory RevaluationEntryFactory --model=RevaluationEntry --no-interaction
php artisan make:factory SanctionImportLogFactory --model=SanctionImportLog --no-interaction
php artisan make:factory SanctionsAnalysisFactory --model=SanctionsAnalysis --no-interaction
php artisan make:factory StockTransferFactory --model=StockTransfer --no-interaction
php artisan make:factory StockTransferItemFactory --model=StockTransferItem --no-interaction
php artisan make:factory SystemLogFactory --model=SystemLog --no-interaction
php artisan make:factory ThresholdAuditFactory --model=ThresholdAudit --no-interaction
php artisan make:factory TransactionImportFactory --model=TransactionImport --no-interaction
```

- [ ] **Step 2: Fill each factory definition**

For each generated stub, replace the empty `definition()` array with values that match the model's `$fillable` and the DB column types. Use the model's existing relationships and enum classes. Example pattern for `AccountLedgerFactory`:

```php
<?php

namespace Database\Factories;

use App\Models\AccountLedger;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountLedgerFactory extends Factory
{
    protected $model = AccountLedger::class;

    public function definition(): array
    {
        return [
            'account_code' => ChartOfAccount::factory(),
            'journal_entry_id' => JournalEntry::factory(),
            'entry_date' => $this->faker->date(),
            'transaction_date' => $this->faker->date(),
            'entry_type' => $this->faker->randomElement(['debit', 'credit']),
            'entry_id' => $this->faker->randomNumber(),
            'debit' => $this->faker->randomFloat(2, 0, 1000),
            'credit' => $this->faker->randomFloat(2, 0, 1000),
            'debit_amount' => $this->faker->randomFloat(2, 0, 1000),
            'credit_amount' => $this->faker->randomFloat(2, 0, 1000),
            'running_balance' => $this->faker->randomFloat(2, 0, 10000),
            'reference_type' => $this->faker->word(),
            'reference_id' => $this->faker->randomNumber(),
            'description' => $this->faker->sentence(),
            'cost_center_id' => null,
            'department_id' => null,
            'branch_id' => null,
        ];
    }
}
```

Repeat this pattern for all 26 factories, using the model's `$fillable` array and DB column types as the source of truth. Add convenience `state()` methods where useful (e.g., `PepApprovalRequestFactory::approved()`, `MfaRecoveryCodeFactory::used()`).

- [ ] **Step 3: Create factory smoke test**

Create `tests/Unit/Models/FactorySmokeTest.php`:

```php
<?php

namespace Tests\Unit\Models;

use App\Models\AccountLedger;
use App\Models\AmlRule;
use App\Models\BackupLog;
use App\Models\BankReconciliation;
use App\Models\BranchClosureWorkflow;
use App\Models\BranchPool;
use App\Models\Compliance\ComplianceCaseDocument;
use App\Models\Compliance\ComplianceCaseLink;
use App\Models\Compliance\CustomerBehavioralBaseline;
use App\Models\Compliance\CustomerRiskProfile;
use App\Models\Compliance\EddDocumentRequest;
use App\Models\CostCenter;
use App\Models\Department;
use App\Models\DeviceComputations;
use App\Models\EddTemplate;
use App\Models\ExchangeRateHistory;
use App\Models\HighRiskCountry;
use App\Models\MfaRecoveryCode;
use App\Models\PepApprovalRequest;
use App\Models\RevaluationEntry;
use App\Models\SanctionImportLog;
use App\Models\SanctionsAnalysis;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\SystemLog;
use App\Models\ThresholdAudit;
use App\Models\TransactionImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FactorySmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_factories_can_create_records(): void
    {
        $this->assertInstanceOf(AccountLedger::class, AccountLedger::factory()->create());
        $this->assertInstanceOf(AmlRule::class, AmlRule::factory()->create());
        $this->assertInstanceOf(BackupLog::class, BackupLog::factory()->create());
        $this->assertInstanceOf(BankReconciliation::class, BankReconciliation::factory()->create());
        $this->assertInstanceOf(BranchClosureWorkflow::class, BranchClosureWorkflow::factory()->create());
        $this->assertInstanceOf(ComplianceCaseDocument::class, ComplianceCaseDocument::factory()->create());
        $this->assertInstanceOf(ComplianceCaseLink::class, ComplianceCaseLink::factory()->create());
        $this->assertInstanceOf(CostCenter::class, CostCenter::factory()->create());
        $this->assertInstanceOf(CustomerBehavioralBaseline::class, CustomerBehavioralBaseline::factory()->create());
        $this->assertInstanceOf(CustomerRiskProfile::class, CustomerRiskProfile::factory()->create());
        $this->assertInstanceOf(Department::class, Department::factory()->create());
        $this->assertInstanceOf(DeviceComputations::class, DeviceComputations::factory()->create());
        $this->assertInstanceOf(EddDocumentRequest::class, EddDocumentRequest::factory()->create());
        $this->assertInstanceOf(EddTemplate::class, EddTemplate::factory()->create());
        $this->assertInstanceOf(ExchangeRateHistory::class, ExchangeRateHistory::factory()->create());
        $this->assertInstanceOf(HighRiskCountry::class, HighRiskCountry::factory()->create());
        $this->assertInstanceOf(MfaRecoveryCode::class, MfaRecoveryCode::factory()->create());
        $this->assertInstanceOf(PepApprovalRequest::class, PepApprovalRequest::factory()->create());
        $this->assertInstanceOf(RevaluationEntry::class, RevaluationEntry::factory()->create());
        $this->assertInstanceOf(SanctionImportLog::class, SanctionImportLog::factory()->create());
        $this->assertInstanceOf(SanctionsAnalysis::class, SanctionsAnalysis::factory()->create());
        $this->assertInstanceOf(StockTransfer::class, StockTransfer::factory()->create());
        $this->assertInstanceOf(StockTransferItem::class, StockTransferItem::factory()->create());
        $this->assertInstanceOf(SystemLog::class, SystemLog::factory()->create());
        $this->assertInstanceOf(ThresholdAudit::class, ThresholdAudit::factory()->create());
        $this->assertInstanceOf(TransactionImport::class, TransactionImport::factory()->create());
    }
}
```

- [ ] **Step 4: Run smoke test**

Run: `php artisan test --compact tests/Unit/Models/FactorySmokeTest.php`
Expected: `Tests: 1 passed (26 assertions)`

- [ ] **Step 5: Commit**

```bash
git add database/factories/ tests/Unit/Models/FactorySmokeTest.php
git commit -m "feat: backfill missing model factories"
```

---

## Phase 2: Fix Factory Enum Consistency

### Task 2.1: Replace raw enum strings with enum values

**Files:**
- Modify: `database/factories/AccountingPeriodFactory.php`
- Modify: `database/factories/CounterSessionFactory.php`
- Modify: `database/factories/CustomerDocumentFactory.php`
- Modify: `database/factories/FiscalYearFactory.php`
- Modify: `database/factories/JournalEntryFactory.php`
- Modify: `database/factories/ReportGeneratedFactory.php`
- Modify: `database/factories/RiskScoreSnapshotFactory.php`
- Modify: `database/factories/SanctionEntryFactory.php`
- Modify: `database/factories/TransactionFactory.php`

- [ ] **Step 1: Update each factory to use enum values**

Replace raw strings with the matching enum case values:

| Factory | Field | Current | Replace with |
|---------|-------|---------|--------------|
| `AccountingPeriodFactory` | `period_type` | `'month'` | `AccountingPeriodType::Month->value` |
| `AccountingPeriodFactory` | `status` | `'open'` | `AccountingPeriodStatus::Open->value` |
| `CounterSessionFactory` | `status` | `'open'` | `CounterSessionStatus::Open->value` |
| `CustomerDocumentFactory` | `document_type` | `'MyKad'` | `DocumentType::MyKad->value` |
| `FiscalYearFactory` | `status` | `'Open'` | `FiscalYearStatus::Open->value` |
| `JournalEntryFactory` | `status` | `'Draft'` | `JournalEntryStatus::Draft->value` |
| `JournalEntryFactory` | `reference_type` | `'Manual'` | `ReferenceType::Manual->value` |
| `ReportGeneratedFactory` | `status` | `'Generated'` | `ReportGeneratedStatus::Generated->value` |
| `RiskScoreSnapshotFactory` | `trend` | `'stable'` | `RiskTrend::Stable->value` |
| `SanctionEntryFactory` | `status` | `'active'` | `SanctionStatus::Active->value` |
| `TransactionFactory` | `cdd_level` | `'Standard'` | `CddLevel::Standard->value` |

Add the corresponding `use App\Enums\...;` imports.

- [ ] **Step 2: Run smoke test**

Run: `php artisan test --compact tests/Unit/Models/FactorySmokeTest.php`
Expected: All tests pass.

- [ ] **Step 3: Commit**

```bash
git add database/factories/AccountingPeriodFactory.php database/factories/CounterSessionFactory.php database/factories/CustomerDocumentFactory.php database/factories/FiscalYearFactory.php database/factories/JournalEntryFactory.php database/factories/ReportGeneratedFactory.php database/factories/RiskScoreSnapshotFactory.php database/factories/SanctionEntryFactory.php database/factories/TransactionFactory.php
git commit -m "style: use enum values instead of raw strings in factories"
```

---

## Phase 3: Add High-Value Inverse Relationships

### Task 3.1: Add user-centric inverse relationships

**Files:**
- Modify: `app/Models/User.php`
- Test: `tests/Unit/Models/Relationships/UserInverseRelationshipTest.php` (create)

- [ ] **Step 1: Add inverse relationships to `User`**

Edit `app/Models/User.php` and add the imports and methods:

```php
use App\Models\Compliance\ComplianceCase;
use App\Models\Compliance\ComplianceCaseDocument;
use App\Models\Compliance\ComplianceCaseNote;

public function assignedComplianceCases(): HasMany
{
    return $this->hasMany(ComplianceCase::class, 'assignee_id');
}

public function uploadedComplianceDocuments(): HasMany
{
    return $this->hasMany(ComplianceCaseDocument::class, 'uploaded_by');
}

public function complianceCaseNotes(): HasMany
{
    return $this->hasMany(ComplianceCaseNote::class, 'author_id');
}
```

- [ ] **Step 2: Add test**

Create `tests/Unit/Models/Relationships/UserInverseRelationshipTest.php`:

```php
<?php

namespace Tests\Unit\Models\Relationships;

use App\Models\Compliance\ComplianceCase;
use App\Models\Compliance\ComplianceCaseDocument;
use App\Models\Compliance\ComplianceCaseNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserInverseRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_inverse_compliance_relationships(): void
    {
        $user = User::factory()->create();

        ComplianceCase::factory()->create(['assignee_id' => $user->id]);
        ComplianceCaseDocument::factory()->create(['uploaded_by' => $user->id]);
        ComplianceCaseNote::factory()->create(['author_id' => $user->id]);

        $this->assertCount(1, $user->assignedComplianceCases);
        $this->assertCount(1, $user->uploadedComplianceDocuments);
        $this->assertCount(1, $user->complianceCaseNotes);
    }
}
```

Run: `php artisan test --compact tests/Unit/Models/Relationships/UserInverseRelationshipTest.php`
Expected: `Tests: 1 passed (3 assertions)`

- [ ] **Step 3: Commit**

```bash
git add app/Models/User.php tests/Unit/Models/Relationships/UserInverseRelationshipTest.php
git commit -m "feat: add user inverse relationships for compliance entities"
```

---

### Task 3.2: Add customer-centric inverse relationships

**Files:**
- Modify: `app/Models/Customer.php`
- Test: `tests/Unit/Models/Relationships/CustomerInverseRelationshipTest.php` (create)

- [ ] **Step 1: Add inverse relationships to `Customer`**

Edit `app/Models/Customer.php`:

```php
use App\Models\Compliance\CustomerBehavioralBaseline;
use App\Models\Compliance\CustomerRiskProfile;
use App\Models\PepApprovalRequest;
use App\Models\SanctionsAnalysis;

public function behavioralBaselines(): HasMany
{
    return $this->hasMany(CustomerBehavioralBaseline::class);
}

public function riskProfiles(): HasMany
{
    return $this->hasMany(CustomerRiskProfile::class);
}

public function pepApprovalRequests(): HasMany
{
    return $this->hasMany(PepApprovalRequest::class);
}

public function sanctionsAnalyses(): HasMany
{
    return $this->hasMany(SanctionsAnalysis::class);
}
```

- [ ] **Step 2: Add test**

Create `tests/Unit/Models/Relationships/CustomerInverseRelationshipTest.php`:

```php
<?php

namespace Tests\Unit\Models\Relationships;

use App\Models\Compliance\CustomerBehavioralBaseline;
use App\Models\Compliance\CustomerRiskProfile;
use App\Models\Customer;
use App\Models\PepApprovalRequest;
use App\Models\SanctionsAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerInverseRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_has_inverse_relationships(): void
    {
        $customer = Customer::factory()->create();

        CustomerBehavioralBaseline::factory()->create(['customer_id' => $customer->id]);
        CustomerRiskProfile::factory()->create(['customer_id' => $customer->id]);
        PepApprovalRequest::factory()->create(['customer_id' => $customer->id]);
        SanctionsAnalysis::factory()->create(['customer_id' => $customer->id]);

        $this->assertCount(1, $customer->behavioralBaselines);
        $this->assertCount(1, $customer->riskProfiles);
        $this->assertCount(1, $customer->pepApprovalRequests);
        $this->assertCount(1, $customer->sanctionsAnalyses);
    }
}
```

Run: `php artisan test --compact tests/Unit/Models/Relationships/CustomerInverseRelationshipTest.php`
Expected: `Tests: 1 passed (4 assertions)`

- [ ] **Step 3: Commit**

```bash
git add app/Models/Customer.php tests/Unit/Models/Relationships/CustomerInverseRelationshipTest.php
git commit -m "feat: add customer inverse relationships for compliance and PEP entities"
```

---

### Task 3.3: Add branch, currency, and journal inverses

**Files:**
- Modify: `app/Models/Branch.php`
- Modify: `app/Models/Currency.php`
- Modify: `app/Models/JournalEntry.php`
- Test: `tests/Unit/Models/Relationships/BranchCurrencyJournalInverseTest.php` (create)

- [ ] **Step 1: Add inverses to `Branch`, `Currency`, and `JournalEntry`**

Edit `app/Models/Branch.php`:

```php
use App\Models\Compliance\BranchClosureWorkflow;
use App\Models\ExchangeRate;
use App\Models\ExchangeRateHistory;

public function closureWorkflows(): HasMany
{
    return $this->hasMany(BranchClosureWorkflow::class);
}

public function exchangeRates(): HasMany
{
    return $this->hasMany(ExchangeRate::class);
}

public function exchangeRateHistories(): HasMany
{
    return $this->hasMany(ExchangeRateHistory::class);
}
```

Edit `app/Models/Currency.php`:

```php
use App\Models\ExchangeRateHistory;
use App\Models\RevaluationEntry;
use App\Models\StockTransferItem;

public function exchangeRateHistories(): HasMany
{
    return $this->hasMany(ExchangeRateHistory::class, 'currency_code');
}

public function revaluationEntries(): HasMany
{
    return $this->hasMany(RevaluationEntry::class, 'currency_code');
}

public function stockTransferItems(): HasMany
{
    return $this->hasMany(StockTransferItem::class, 'currency_code');
}
```

Edit `app/Models/JournalEntry.php`:

```php
use App\Models\AccountLedger;
use App\Models\BankReconciliation;
use App\Models\Transaction;

public function accountLedgerEntries(): HasMany
{
    return $this->hasMany(AccountLedger::class);
}

public function bankReconciliations(): HasMany
{
    return $this->hasMany(BankReconciliation::class, 'matched_to_journal_entry_id');
}

public function matchedTransactions(): HasMany
{
    return $this->hasMany(Transaction::class, 'journal_entry_id');
}
```

- [ ] **Step 2: Add test**

Create `tests/Unit/Models/Relationships/BranchCurrencyJournalInverseTest.php`:

```php
<?php

namespace Tests\Unit\Models\Relationships;

use App\Models\AccountLedger;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\ExchangeRateHistory;
use App\Models\JournalEntry;
use App\Models\RevaluationEntry;
use App\Models\StockTransferItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchCurrencyJournalInverseTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_currency_and_journal_inverses(): void
    {
        $branch = Branch::factory()->create();
        $currency = Currency::factory()->create();
        $journal = JournalEntry::factory()->create();

        ExchangeRate::factory()->create(['branch_id' => $branch->id, 'currency_code' => $currency->code]);
        ExchangeRateHistory::factory()->create(['branch_id' => $branch->id, 'currency_code' => $currency->code]);
        RevaluationEntry::factory()->create(['currency_code' => $currency->code]);
        StockTransferItem::factory()->create(['currency_code' => $currency->code]);
        AccountLedger::factory()->create(['journal_entry_id' => $journal->id]);

        $this->assertCount(1, $branch->exchangeRates);
        $this->assertCount(1, $branch->exchangeRateHistories);
        $this->assertCount(1, $currency->exchangeRateHistories);
        $this->assertCount(1, $currency->revaluationEntries);
        $this->assertCount(1, $currency->stockTransferItems);
        $this->assertCount(1, $journal->accountLedgerEntries);
    }
}
```

Run: `php artisan test --compact tests/Unit/Models/Relationships/BranchCurrencyJournalInverseTest.php`
Expected: `Tests: 1 passed (6 assertions)`

- [ ] **Step 3: Commit**

```bash
git add app/Models/Branch.php app/Models/Currency.php app/Models/JournalEntry.php tests/Unit/Models/Relationships/BranchCurrencyJournalInverseTest.php
git commit -m "feat: add branch, currency, and journal inverse relationships"
```

---

## Phase 4: Align Verified Schema Mismatches

### Task 4.1: Add missing `$fillable`/`$casts` entries

**Files:**
- Modify: `app/Models/AccountLedger.php`
- Modify: `app/Models/BranchPool.php`
- Modify: `app/Models/Counter.php`
- Modify: `app/Models/CounterSession.php`
- Modify: `app/Models/Customer.php`
- Modify: `app/Models/CustomerDocument.php`
- Modify: `app/Models/ExchangeRate.php`
- Modify: `app/Models/ExchangeRateHistory.php`
- Modify: `app/Models/JournalEntry.php`
- Modify: `app/Models/JournalLine.php`
- Modify: `app/Models/RevaluationEntry.php`
- Modify: `app/Models/TransactionConfirmation.php`

- [ ] **Step 1: Add missing fields to each model**

| Model | Add to `$fillable` | Add to `$casts` |
|-------|--------------------|-----------------|
| `AccountLedger` | `'branch_id'` | |
| `BranchPool` | `'total_balance'` | `'total_balance' => 'decimal:2'` |
| `Counter` | `'assigned_teller_id'` | |
| `CounterSession` | `'requested_amount_myr'`, `'daily_limit_myr'` | `'requested_amount_myr' => 'decimal:2'`, `'daily_limit_myr' => 'decimal:2'` |
| `Customer` | `'customer_type'`, `'pep_type'`, `'sanctions_screened_at'` | `'customer_type' => 'string'`, `'pep_type' => 'string'`, `'sanctions_screened_at' => 'datetime'` |
| `CustomerDocument` | `'status'` | `'status' => 'string'` |
| `ExchangeRate` | `'spread_applied'` | `'spread_applied' => 'decimal:6'` |
| `ExchangeRateHistory` | `'spread_applied'` | `'spread_applied' => 'decimal:6'` |
| `JournalEntry` | `'branch_id'`, `'created_by'` | |
| `JournalLine` | `'branch_id'` | |
| `RevaluationEntry` | `'posted_at'` | `'posted_at' => 'datetime'` |
| `TransactionConfirmation` | `'user_id'`, `'notes'` | |

- [ ] **Step 2: Run schema verification script**

Run: `php scripts/verify-model-schema.php`
Expected: The mismatches above are resolved; remaining output is limited to intentional cases (`CurrencyPosition` legacy aliases, `Transaction` guarded foreign keys, `User::password`).

- [ ] **Step 3: Commit**

```bash
git add app/Models/AccountLedger.php app/Models/BranchPool.php app/Models/Counter.php app/Models/CounterSession.php app/Models/Customer.php app/Models/CustomerDocument.php app/Models/ExchangeRate.php app/Models/ExchangeRateHistory.php app/Models/JournalEntry.php app/Models/JournalLine.php app/Models/RevaluationEntry.php app/Models/TransactionConfirmation.php
git commit -m "fix: add missing fillable/cast entries for verified schema mismatches"
```

---

### Task 4.2: Remove dead casts and stale fillable fields

**Files:**
- Modify: `app/Models/EnhancedDiligenceRecord.php`
- Modify: `app/Models/ReportGenerated.php`
- Test: `tests/Unit/Models/DeadCastCleanupTest.php` (create)

- [ ] **Step 1: Remove dead casts from `EnhancedDiligenceRecord`**

Edit `app/Models/EnhancedDiligenceRecord.php` — remove from `$casts`:

```php
'responses' => 'array',
'documents_received' => 'array',
```

- [ ] **Step 2: Align `ReportGenerated` with schema**

The `reports_generated` table does not have `version` or `notes` columns, but the model references `version` in `isLatestVersion()`. Choose one of the following options:

**Option A (recommended):** Add a migration to add the missing columns.

Create `database/migrations/2026_06_22_000001_add_version_and_notes_to_reports_generated.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports_generated', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->after('status');
            $table->text('notes')->nullable()->after('version');
        });
    }

    public function down(): void
    {
        Schema::table('reports_generated', function (Blueprint $table) {
            $table->dropColumn(['version', 'notes']);
        });
    }
};
```

Run: `php artisan migrate --no-interaction`

**Option B:** Remove `version` and `notes` from the model and delete `isLatestVersion()` / `scopeLatestVersion()`.

Pick Option A if the version-tracking behavior is desired; pick Option B if it is unused.

- [ ] **Step 3: Add regression test**

Create `tests/Unit/Models/DeadCastCleanupTest.php`:

```php
<?php

namespace Tests\Unit\Models;

use App\Models\EnhancedDiligenceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeadCastCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_enhanced_diligence_record_has_no_dead_casts(): void
    {
        $record = EnhancedDiligenceRecord::factory()->create();
        $this->assertArrayNotHasKey('responses', $record->getCasts());
        $this->assertArrayNotHasKey('documents_received', $record->getCasts());
    }
}
```

Run: `php artisan test --compact tests/Unit/Models/DeadCastCleanupTest.php`
Expected: `Tests: 1 passed (2 assertions)`

- [ ] **Step 4: Commit**

```bash
git add app/Models/EnhancedDiligenceRecord.php app/Models/ReportGenerated.php database/migrations/2026_06_22_000001_add_version_and_notes_to_reports_generated.php tests/Unit/Models/DeadCastCleanupTest.php
git commit -m "fix: remove dead casts and align ReportGenerated schema"
```

---

## Phase 5: Verification & Style

### Task 5.1: Run model-focused test suite and Pint

**Files:** None (verification only)

- [ ] **Step 1: Run model tests**

Run: `php artisan test --compact tests/Unit/Models/`
Expected: All tests pass.

- [ ] **Step 2: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: No changes needed (or Pint auto-fixes and re-test).

- [ ] **Step 3: Commit style fixes if any**

```bash
git add -A
git commit -m "style: apply Pint formatting after model audit fixes"
```

---

### Task 5.2: Run full test suite

**Files:** None (verification only)

- [ ] **Step 1: Run full suite**

Run: `php artisan test --compact`
Expected: All tests pass.

- [ ] **Step 2: Verify migrations rollback and forward cleanly**

Run:
```bash
php artisan migrate:rollback --no-interaction
php artisan migrate --no-interaction
```
Expected: Both commands succeed.

- [ ] **Step 3: Final commit if any changes**

```bash
git add -A
git commit -m "chore: final verification after model and factory audit"
```

---

## Deferred Items

These findings were verified as intentional or out of scope:

| Issue | Reason |
|-------|--------|
| `CurrencyPosition` legacy aliases (`balance`, `avg_cost_rate`, etc.) | Intentional backwards-compatibility accessors/mutators bridging old and new column names. |
| `Transaction` guarded FKs (`customer_id`, `user_id`, `branch_id`, etc.) | Intentionally excluded from `$fillable` for security; set via relationships or explicit assignment. |
| `User::password` in `$fillable` | Laravel convention; handled correctly. |
| `ChartOfAccount::normal_balance` not fillable | Likely intentional default; add only if mass-assignment is required. |
