# Comprehensive Model & Relationship Audit Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix runtime model bugs, align Eloquent models with the migration schema, complete missing relationships, and backfill tests/factories so the model layer is consistent and verifiable.

**Architecture:** Keep the existing `BaseModel` / `AccountingModel` / `ComplianceModel` / `TransactionModel` / `SystemModel` hierarchy and shared traits. Changes are additive and corrective: add missing imports/relationships, align `$fillable`/`$casts` with migrations, add targeted migrations for indexes/FKs, and expand PHPUnit coverage. No dependency changes.

**Tech Stack:** Laravel 11 (project reports 11.51.0), PHP 8.3, Eloquent, PHPUnit 11, SQLite/MySQL, Laravel Pint.

---

## File Structure

| File | Responsibility |
|------|----------------|
| `app/Models/ReportSchedule.php` | Fix missing `HasMany` import (runtime error). |
| `app/Models/CustomerRiskHistory.php` | Align columns with migration (`old_score`/`old_rating`/`assessed_by`). |
| `app/Models/EnhancedDiligenceRecord.php` | Add approval/questionnaire fillable fields and relationships. |
| `app/Models/Budget.php` | Add `period()` relationship to `AccountingPeriod`. |
| `app/Models/AccountingPeriod.php` | Add `fiscalYear()` relationship to `FiscalYear`. |
| `app/Models/TillBalance.php` | Add `teller_allocation_id` fillable and `tellerAllocation()` relationship. |
| `app/Models/TestResult.php` | Add `executedBy()` relationship to `User`. |
| `app/Models/Currency.php` | Add return-type hints to relationships. |
| `app/Models/User.php` | Add return-type hint to `transactions()`. |
| `app/Models/RevaluationEntry.php` | Remove dead `posted_at` cast. |
| `app/Models/SanctionEntry.php` | Replace manual JSON accessors with `array` cast. |
| `app/Models/Compliance/CustomerRiskProfile.php` | Remove unused `Model` import. |
| `app/Models/Compliance/CustomerBehavioralBaseline.php` | Remove unused `Model` import. |
| `database/migrations/2026_06_21_000001_add_missing_model_indexes.php` | Add missing indexes on high-cardinality FK columns. |
| `database/migrations/2026_06_21_000002_align_foreign_keys.php` | Add missing foreign-key constraints where supported. |
| `tests/Unit/Models/ModelHierarchyTest.php` | Add missing concrete models to inheritance assertions. |
| `tests/Unit/Models/Relationships/*` | New relationship tests for fixed/added relationships. |
| `database/factories/*` | Fix enum strings and add missing states/factories. |

---

## Phase 1: Critical Runtime Fixes

### Task 1.1: Fix `ReportSchedule` missing `HasMany` import

**Files:**
- Modify: `app/Models/ReportSchedule.php:7`
- Test: `tests/Unit/Models/ModelHierarchyTest.php` (existing)

- [ ] **Step 1: Add the missing import**

```php
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
```

Replace the existing `use` block for `BelongsTo` only:

```php
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
```

- [ ] **Step 2: Run the existing model tests to verify no regression**

Run: `php artisan test --compact tests/Unit/Models/ModelHierarchyTest.php`
Expected: `Tests: 7 passed (59 assertions)`

- [ ] **Step 3: Commit**

```bash
git add app/Models/ReportSchedule.php
git commit -m "fix: add missing HasMany import in ReportSchedule"
```

---

### Task 1.2: Align `CustomerRiskHistory` with migration schema

**Files:**
- Modify: `app/Models/CustomerRiskHistory.php:12-39`
- Test: `tests/Unit/Models/Relationships/CustomerRiskHistoryRelationshipTest.php` (create)

The migration `database/migrations/2025_03_31_000011_create_customer_risk_history_table.php` defines:
- `old_score`, `new_score`
- `old_rating`, `new_rating`
- `assessed_by` (nullable FK to `users`)
- `created_at`/`updated_at` timestamps

The model currently uses `previous_score`, `previous_rating`, `changed_by`, and `changed_at`.

- [ ] **Step 1: Rewrite `CustomerRiskHistory` to match the migration**

```php
<?php

namespace App\Models;

use App\Enums\RiskRating;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerRiskHistory extends BaseModel
{
    use HasFactory;

    protected $table = 'customer_risk_history';

    protected $fillable = [
        'customer_id',
        'old_score',
        'new_score',
        'old_rating',
        'new_rating',
        'change_reason',
        'assessed_by',
    ];

    protected $casts = [
        'old_score' => 'integer',
        'new_score' => 'integer',
        'old_rating' => RiskRating::class,
        'new_rating' => RiskRating::class,
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }
}
```

- [ ] **Step 2: Write a relationship test**

Create `tests/Unit/Models/Relationships/CustomerRiskHistoryRelationshipTest.php`:

```php
<?php

namespace Tests\Unit\Models\Relationships;

use App\Models\Customer;
use App\Models\CustomerRiskHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerRiskHistoryRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_belongs_to_customer_and_assessor(): void
    {
        $customer = Customer::factory()->create();
        $assessor = User::factory()->create();

        $history = CustomerRiskHistory::factory()->create([
            'customer_id' => $customer->id,
            'assessed_by' => $assessor->id,
        ]);

        $this->assertTrue($history->customer->is($customer));
        $this->assertTrue($history->assessor->is($assessor));
    }
}
```

If a `CustomerRiskHistoryFactory` does not exist yet, create `database/factories/CustomerRiskHistoryFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\RiskRating;
use App\Models\Customer;
use App\Models\CustomerRiskHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerRiskHistoryFactory extends Factory
{
    protected $model = CustomerRiskHistory::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'old_score' => $this->faker->numberBetween(0, 100),
            'new_score' => $this->faker->numberBetween(0, 100),
            'old_rating' => RiskRating::Medium->value,
            'new_rating' => RiskRating::High->value,
            'change_reason' => $this->faker->sentence(),
            'assessed_by' => User::factory(),
        ];
    }
}
```

- [ ] **Step 3: Run the new test**

Run: `php artisan test --compact tests/Unit/Models/Relationships/CustomerRiskHistoryRelationshipTest.php`
Expected: `Tests: 1 passed (2 assertions)`

- [ ] **Step 4: Commit**

```bash
git add app/Models/CustomerRiskHistory.php tests/Unit/Models/Relationships/CustomerRiskHistoryRelationshipTest.php database/factories/CustomerRiskHistoryFactory.php
git commit -m "fix: align CustomerRiskHistory columns with migration schema"
```

---

### Task 1.3: Complete `EnhancedDiligenceRecord` fillable and relationships

**Files:**
- Modify: `app/Models/EnhancedDiligenceRecord.php:19-63`
- Test: `tests/Unit/Models/Relationships/EnhancedDiligenceRecordRelationshipTest.php` (create)

The migration `database/migrations/2026_04_05_000006_create_enhanced_diligence_records_table.php` defines:
- `approved_by` (FK to `users`)
- `approved_at`
- `questionnaire_completed_by` (FK to `users`)
- `questionnaire_completed_at`

- [ ] **Step 1: Update fillable and casts**

Modify `app/Models/EnhancedDiligenceRecord.php`:

```php
    protected $fillable = [
        'flagged_transaction_id',
        'edd_reference',
        'edd_template_id',
        'risk_level',
        'source_of_funds',
        'source_of_funds_description',
        'source_of_funds_documents',
        'purpose_of_transaction',
        'business_justification',
        'employment_status',
        'employer_name',
        'employer_address',
        'annual_income_range',
        'estimated_net_worth',
        'source_of_wealth',
        'source_of_wealth_description',
        'additional_information',
        'supporting_documents',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'questionnaire_responses',
        'questionnaire_completed_at',
        'questionnaire_completed_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'source_of_funds_documents' => 'array',
        'supporting_documents' => 'array',
        'questionnaire_responses' => 'array',
        'responses' => 'array',
        'documents_received' => 'array',
        'reviewed_at' => 'datetime',
        'questionnaire_completed_at' => 'datetime',
        'approved_at' => 'datetime',
        'status' => EddStatus::class,
        'risk_level' => EddRiskLevel::class,
        'employment_status' => EmploymentStatus::class,
    ];
```

- [ ] **Step 2: Add missing relationships**

After the existing `reviewer()` method, add:

```php
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function questionnaireCompletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'questionnaire_completed_by');
    }
```

- [ ] **Step 3: Write a relationship test**

Create `tests/Unit/Models/Relationships/EnhancedDiligenceRecordRelationshipTest.php`:

```php
<?php

namespace Tests\Unit\Models\Relationships;

use App\Models\Compliance\EddQuestionnaireTemplate;
use App\Models\Customer;
use App\Models\EnhancedDiligenceRecord;
use App\Models\FlaggedTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnhancedDiligenceRecordRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_belongs_to_related_entities(): void
    {
        $record = EnhancedDiligenceRecord::factory()->create([
            'customer_id' => Customer::factory(),
            'flagged_transaction_id' => FlaggedTransaction::factory(),
            'edd_template_id' => EddQuestionnaireTemplate::factory(),
            'reviewed_by' => User::factory(),
            'approved_by' => User::factory(),
            'questionnaire_completed_by' => User::factory(),
        ]);

        $this->assertInstanceOf(Customer::class, $record->customer);
        $this->assertInstanceOf(FlaggedTransaction::class, $record->flaggedTransaction);
        $this->assertInstanceOf(EddQuestionnaireTemplate::class, $record->template);
        $this->assertInstanceOf(User::class, $record->reviewer);
        $this->assertInstanceOf(User::class, $record->approvedBy);
        $this->assertInstanceOf(User::class, $record->questionnaireCompletedBy);
    }
}
```

If `EddQuestionnaireTemplateFactory` does not exist, create `database/factories/Compliance/EddQuestionnaireTemplateFactory.php`:

```php
<?php

namespace Database\Factories\Compliance;

use App\Models\Compliance\EddQuestionnaireTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class EddQuestionnaireTemplateFactory extends Factory
{
    protected $model = EddQuestionnaireTemplate::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'questions' => [],
            'is_active' => true,
        ];
    }
}
```

- [ ] **Step 4: Run the new test**

Run: `php artisan test --compact tests/Unit/Models/Relationships/EnhancedDiligenceRecordRelationshipTest.php`
Expected: `Tests: 1 passed (6 assertions)`

- [ ] **Step 5: Commit**

```bash
git add app/Models/EnhancedDiligenceRecord.php tests/Unit/Models/Relationships/EnhancedDiligenceRecordRelationshipTest.php database/factories/Compliance/EddQuestionnaireTemplateFactory.php
git commit -m "fix: add missing EDD approval/questionnaire fields and relationships"
```

---

### Task 1.4: Add missing `BelongsTo` relationships

**Files:**
- Modify: `app/Models/Budget.php`, `app/Models/AccountingPeriod.php`, `app/Models/TillBalance.php`, `app/Models/TestResult.php`
- Test: `tests/Unit/Models/Relationships/*` (create per model)

- [ ] **Step 1: Add `Budget::period()`**

Modify `app/Models/Budget.php`:

```php
    public function period(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, 'period_code', 'period_code');
    }
```

- [ ] **Step 2: Add `AccountingPeriod::fiscalYear()`**

Modify `app/Models/AccountingPeriod.php`:

```php
    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }
```

- [ ] **Step 3: Add `TillBalance::tellerAllocation()`**

Modify `app/Models/TillBalance.php`:

Add to `$fillable`:

```php
        'teller_allocation_id',
```

Add relationship method:

```php
    public function tellerAllocation(): BelongsTo
    {
        return $this->belongsTo(TellerAllocation::class);
    }
```

- [ ] **Step 4: Add `TestResult::executedBy()`**

Modify `app/Models/TestResult.php`:

```php
use Illuminate\Database\Eloquent\Relations\BelongsTo;

    public function executedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }
```

- [ ] **Step 5: Write focused relationship tests**

Create `tests/Unit/Models/Relationships/BudgetRelationshipTest.php`:

```php
<?php

namespace Tests\Unit\Models\Relationships;

use App\Models\AccountingPeriod;
use App\Models\Budget;
use App\Models\ChartOfAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_belongs_to_account_period_and_creator(): void
    {
        $period = AccountingPeriod::factory()->create();
        $budget = Budget::factory()->create([
            'account_code' => ChartOfAccount::factory(),
            'period_code' => $period->period_code,
            'created_by' => User::factory(),
        ]);

        $this->assertTrue($budget->period->is($period));
    }
}
```

Create `tests/Unit/Models/Relationships/AccountingPeriodRelationshipTest.php`:

```php
<?php

namespace Tests\Unit\Models\Relationships;

use App\Models\AccountingPeriod;
use App\Models\FiscalYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingPeriodRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_belongs_to_fiscal_year(): void
    {
        $fiscalYear = FiscalYear::factory()->create();
        $period = AccountingPeriod::factory()->create(['fiscal_year_id' => $fiscalYear->id]);

        $this->assertTrue($period->fiscalYear->is($fiscalYear));
    }
}
```

Create `tests/Unit/Models/Relationships/TillBalanceRelationshipTest.php`:

```php
<?php

namespace Tests\Unit\Models\Relationships;

use App\Models\Branch;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\TellerAllocation;
use App\Models\TillBalance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TillBalanceRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_belongs_to_teller_allocation_and_counter(): void
    {
        $allocation = TellerAllocation::factory()->create();
        $till = TillBalance::factory()->create([
            'till_id' => Counter::factory(),
            'currency_code' => Currency::factory(),
            'branch_id' => Branch::factory(),
            'teller_allocation_id' => $allocation->id,
            'opened_by' => User::factory(),
        ]);

        $this->assertTrue($till->tellerAllocation->is($allocation));
    }
}
```

Create `tests/Unit/Models/Relationships/TestResultRelationshipTest.php`:

```php
<?php

namespace Tests\Unit\Models\Relationships;

use App\Models\TestResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestResultRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_belongs_to_executed_by_user(): void
    {
        $user = User::factory()->create();
        $result = TestResult::factory()->create(['executed_by' => $user->id]);

        $this->assertTrue($result->executedBy->is($user));
    }
}
```

- [ ] **Step 6: Run the new tests**

Run: `php artisan test --compact tests/Unit/Models/Relationships/`
Expected: All new relationship tests pass.

- [ ] **Step 7: Commit**

```bash
git add app/Models/Budget.php app/Models/AccountingPeriod.php app/Models/TillBalance.php app/Models/TestResult.php tests/Unit/Models/Relationships/
git commit -m "fix: add missing BelongsTo relationships (Budget, AccountingPeriod, TillBalance, TestResult)"
```

---

## Phase 2: Model Code Quality

### Task 2.1: Add return-type hints to relationship and scope methods

**Files:**
- Modify: `app/Models/Currency.php`, `app/Models/User.php`, `app/Models/ExchangeRate.php`, `app/Models/ExchangeRateHistory.php`

- [ ] **Step 1: Update `Currency`**

```php
use Illuminate\Database\Eloquent\Relations\HasMany;

    public function exchangeRates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'currency_code');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'currency_code');
    }
```

- [ ] **Step 2: Update `User::transactions()`**

```php
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
```

- [ ] **Step 3: Add return types to `ExchangeRate` scopes**

If `ExchangeRate` contains scopes without return types, update them to:

```php
use Illuminate\Database\Eloquent\Builder;

    public function scopeLatestRates(Builder $query): Builder
    {
        return $query->whereIn('id', function ($subQuery) {
            $subQuery->selectRaw('MAX(id)')
                ->from('exchange_rates')
                ->groupBy('currency_code');
        });
    }

    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }
```

Adapt the exact scope bodies to match the current implementation; only add `Builder` return types and the import.

- [ ] **Step 4: Add return types to `ExchangeRateHistory` scopes**

```php
use Illuminate\Database\Eloquent\Builder;

    public function scopeForCurrency(Builder $query, string $currencyCode): Builder
    {
        return $query->where('currency_code', $currencyCode);
    }

    public function scopeForDateRange(Builder $query, string $start, string $end): Builder
    {
        return $query->whereBetween('effective_date', [$start, $end]);
    }
```

- [ ] **Step 5: Run model tests**

Run: `php artisan test --compact tests/Unit/Models/`
Expected: All existing tests pass.

- [ ] **Step 6: Commit**

```bash
git add app/Models/Currency.php app/Models/User.php app/Models/ExchangeRate.php app/Models/ExchangeRateHistory.php
git commit -m "style: add return type hints to model relationships and scopes"
```

---

### Task 2.2: Remove unused imports

**Files:**
- Modify: `app/Models/Compliance/CustomerRiskProfile.php`, `app/Models/Compliance/CustomerBehavioralBaseline.php`

- [ ] **Step 1: Remove `use Illuminate\Database\Eloquent\Model;` from both files**

In `CustomerRiskProfile.php` remove line 10:

```php
use Illuminate\Database\Eloquent\Model;
```

In `CustomerBehavioralBaseline.php` remove line 8:

```php
use Illuminate\Database\Eloquent\Model;
```

- [ ] **Step 2: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: Pint reports no unused-import errors for these files.

- [ ] **Step 3: Commit**

```bash
git add app/Models/Compliance/CustomerRiskProfile.php app/Models/Compliance/CustomerBehavioralBaseline.php
git commit -m "style: remove unused Eloquent Model imports"
```

---

### Task 2.3: Replace manual JSON accessors in `SanctionEntry` with array cast

**Files:**
- Modify: `app/Models/SanctionEntry.php`
- Test: `tests/Unit/Models/SanctionEntryCastTest.php` (create)

- [ ] **Step 1: Replace accessors with casts**

```php
    protected $casts = [
        'date_of_birth' => 'date',
        'listing_date' => 'date',
        'entity_type' => EntityType::class,
        'status' => SanctionStatus::class,
        'aliases' => 'array',
        'details' => 'array',
    ];
```

Remove the three accessor/mutator methods `getAliasesAttribute`, `setAliasesAttribute`, and `setDetailsAttribute`.

- [ ] **Step 2: Verify no callers rely on the old mutator behavior**

Run: `grep -R "aliases\|details" app/Models/SanctionEntry.php`
Expected: Only `$fillable`, `$casts`, and any remaining business logic references.

- [ ] **Step 3: Write a cast test**

Create `tests/Unit/Models/SanctionEntryCastTest.php`:

```php
<?php

namespace Tests\Unit\Models;

use App\Models\SanctionEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SanctionEntryCastTest extends TestCase
{
    use RefreshDatabase;

    public function test_aliases_and_details_are_cast_to_arrays(): void
    {
        $entry = SanctionEntry::factory()->create([
            'aliases' => ['alias one', 'alias two'],
            'details' => ['key' => 'value'],
        ]);

        $this->assertIsArray($entry->aliases);
        $this->assertSame(['alias one', 'alias two'], $entry->aliases);
        $this->assertIsArray($entry->details);
        $this->assertSame(['key' => 'value'], $entry->details);
    }
}
```

- [ ] **Step 4: Run the test**

Run: `php artisan test --compact tests/Unit/Models/SanctionEntryCastTest.php`
Expected: `Tests: 1 passed (4 assertions)`

- [ ] **Step 5: Commit**

```bash
git add app/Models/SanctionEntry.php tests/Unit/Models/SanctionEntryCastTest.php
git commit -m "refactor: use array casts for SanctionEntry JSON columns"
```

---

### Task 2.4: Clean up dead `posted_at` cast in `RevaluationEntry`

**Files:**
- Modify: `app/Models/RevaluationEntry.php`

The migration defines `revaluation_date` and `posted_by`; it does not define `posted_at`.

- [ ] **Step 1: Remove the dead cast**

```php
    protected $casts = [
        'old_rate' => MoneyCast::class.':6',
        'new_rate' => MoneyCast::class.':6',
        'position_amount' => MoneyCast::class,
        'gain_loss_amount' => MoneyCast::class,
        'revaluation_date' => 'date',
    ];
```

- [ ] **Step 2: Run model tests**

Run: `php artisan test --compact tests/Unit/Models/`
Expected: All tests pass.

- [ ] **Step 3: Commit**

```bash
git add app/Models/RevaluationEntry.php
git commit -m "fix: remove dead posted_at cast from RevaluationEntry"
```

---

### Task 2.5: Review default `$with` eager loading on compliance/listing models

**Files:**
- Review: `app/Models/Compliance/ComplianceCase.php`, `app/Models/Alert.php`, `app/Models/FlaggedTransaction.php`, `app/Models/ScreeningResult.php`, `app/Models/EnhancedDiligenceRecord.php`

- [ ] **Step 1: Document current `$with` arrays**

For each model above, list the relationships in `$with`. Do not change them yet unless a concrete N+1 bug is observed.

- [ ] **Step 2: Add a regression test for hidden eager loading**

Create `tests/Unit/Models/EagerLoadingCoverageTest.php`:

```php
<?php

namespace Tests\Unit\Models;

use App\Models\Alert;
use App\Models\Compliance\ComplianceCase;
use App\Models\EnhancedDiligenceRecord;
use App\Models\FlaggedTransaction;
use App\Models\ScreeningResult;
use Tests\TestCase;

class EagerLoadingCoverageTest extends TestCase
{
    public function test_key_listing_models_do_not_auto_eager_load(): void
    {
        $this->assertEmpty((new ComplianceCase)->getWith());
        $this->assertEmpty((new Alert)->getWith());
        $this->assertEmpty((new FlaggedTransaction)->getWith());
        $this->assertEmpty((new ScreeningResult)->getWith());
        $this->assertEmpty((new EnhancedDiligenceRecord)->getWith());
    }
}
```

If the project uses `$with` intentionally, this test will fail and should be updated to assert the **expected** `$with` values, making the behavior explicit.

- [ ] **Step 3: Run the test and decide on changes**

Run: `php artisan test --compact tests/Unit/Models/EagerLoadingCoverageTest.php`
Expected: FAIL if any model uses `$with`; update assertions to match reality.

- [ ] **Step 4: Commit**

```bash
git add tests/Unit/Models/EagerLoadingCoverageTest.php
git commit -m "test: document default eager-load expectations for listing models"
```

---

## Phase 3: Schema Alignment

### Task 3.1: Refresh local database and clean stale tables

**Files:**
- Command only (no code changes)

- [ ] **Step 1: Verify pending migrations**

Run: `php artisan migrate:status`
Expected: Lists pending migrations including:
- `2026_04_29_000002_create_branch_closure_workflows_table`
- `2026_04_29_122839_create_emergency_closures_table`
- `2026_05_11_072730_create_sanctions_analyses_table`
- `2026_05_11_153936_create_pep_approval_requests_table`
- `2026_06_13_030100_create_customer_notes_table`

- [ ] **Step 2: Run migrations**

Run: `php artisan migrate`
Expected: `Migrated: ...` for each pending migration.

- [ ] **Step 3: Remove stale `ctos_reports` table**

The table `ctos_reports` was dropped by migration `2026_05_31_131647_drop_ctos_reports_table.php`. If it still exists in `database.sqlite` and the drop migration is already in `migrations`, run:

```bash
php artisan migrate --force
```

If the table persists because the drop migration was not run, verify `migrate:status` shows it as run. If not, run migrations. If it is already run but the table still exists, manually drop it only in the local SQLite test database:

```bash
sqlite3 database/database.sqlite "DROP TABLE IF EXISTS ctos_reports;"
```

- [ ] **Step 4: Verify tables exist**

Run:

```bash
php artisan tinker --execute="print_r(\Schema::getTables());"
```

Confirm `branch_closure_workflows`, `emergency_closures`, `sanctions_analyses`, `pep_approval_requests`, and `customer_notes` are present and `ctos_reports` is absent.

- [ ] **Step 5: Commit**

No file changes if only database state changed. If `database/database.sqlite` is tracked, include it:

```bash
git add database/database.sqlite
git commit -m "chore: refresh local sqlite schema and remove stale ctos_reports"
```

---

### Task 3.2: Add missing indexes for high-cardinality foreign keys

**Files:**
- Create: `database/migrations/2026_06_21_000001_add_missing_model_indexes.php`

- [ ] **Step 1: Create the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_periods', function (Blueprint $table) {
            $table->index('closed_by');
            $table->index('fiscal_year_id');
        });

        Schema::table('alerts', function (Blueprint $table) {
            $table->index('case_id');
            $table->index('flagged_transaction_id');
        });

        Schema::table('backup_logs', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('bank_reconciliations', function (Blueprint $table) {
            $table->index('created_by');
            $table->index('matched_to_journal_entry_id');
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->index('created_by');
        });

        Schema::table('compliance_case_documents', function (Blueprint $table) {
            $table->index('verified_by');
            $table->index('uploaded_by');
            $table->index('case_id');
        });

        Schema::table('compliance_case_links', function (Blueprint $table) {
            $table->index('case_id');
        });

        Schema::table('compliance_case_notes', function (Blueprint $table) {
            $table->index('author_id');
            $table->index('case_id');
        });

        Schema::table('compliance_cases', function (Blueprint $table) {
            $table->index('primary_finding_id');
            $table->index('primary_flag_id');
        });

        Schema::table('cost_centers', function (Blueprint $table) {
            $table->index('department_id');
        });

        Schema::table('customer_documents', function (Blueprint $table) {
            $table->index('uploaded_by');
        });

        Schema::table('customer_relations', function (Blueprint $table) {
            $table->index('related_customer_id');
        });

        Schema::table('customer_risk_history', function (Blueprint $table) {
            $table->index('assessed_by');
        });

        Schema::table('edd_document_requests', function (Blueprint $table) {
            $table->index('edd_record_id');
        });

        Schema::table('edd_templates', function (Blueprint $table) {
            $table->index('created_by');
        });

        Schema::table('enhanced_diligence_records', function (Blueprint $table) {
            $table->index('approved_by');
            $table->index('questionnaire_completed_by');
            $table->index('reviewed_by');
            $table->index('customer_id');
            $table->index('flagged_transaction_id');
        });

        Schema::table('exchange_rate_histories', function (Blueprint $table) {
            $table->index('created_by');
        });

        Schema::table('fiscal_years', function (Blueprint $table) {
            $table->index('closed_by');
        });

        Schema::table('flagged_transactions', function (Blueprint $table) {
            $table->index('reviewed_by');
            $table->index('customer_id');
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->index('period_id');
            $table->index('approved_by');
            $table->index('created_by');
            $table->index('reversed_by');
            $table->index('posted_by');
        });

        Schema::table('report_runs', function (Blueprint $table) {
            $table->index('generated_by');
            $table->index('schedule_id');
        });

        Schema::table('report_schedules', function (Blueprint $table) {
            $table->index('created_by');
        });

        Schema::table('revaluation_entries', function (Blueprint $table) {
            $table->index('posted_by');
        });

        Schema::table('sanction_import_logs', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('sanction_lists', function (Blueprint $table) {
            $table->index('uploaded_by');
        });

        Schema::table('screening_results', function (Blueprint $table) {
            $table->index('sanction_entry_id');
            $table->index('transaction_id');
            $table->index('customer_id');
        });

        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->index('stock_transfer_id');
        });

        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->index('hq_approved_by');
            $table->index('branch_manager_approved_by');
        });

        Schema::table('system_alerts', function (Blueprint $table) {
            $table->index('acknowledged_by');
        });

        Schema::table('teller_allocations', function (Blueprint $table) {
            $table->index('approved_by');
        });

        Schema::table('threshold_audits', function (Blueprint $table) {
            $table->index('changed_by');
        });

        Schema::table('till_balances', function (Blueprint $table) {
            $table->index('closed_by');
            $table->index('opened_by');
            $table->index('teller_allocation_id');
        });

        Schema::table('transaction_errors', function (Blueprint $table) {
            $table->index('resolved_by');
        });

        Schema::table('transaction_imports', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('transaction_state_history', function (Blueprint $table) {
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        $tables = [
            'accounting_periods' => ['closed_by', 'fiscal_year_id'],
            'alerts' => ['case_id', 'flagged_transaction_id'],
            'backup_logs' => ['user_id'],
            'bank_reconciliations' => ['created_by', 'matched_to_journal_entry_id'],
            'budgets' => ['created_by'],
            'compliance_case_documents' => ['verified_by', 'uploaded_by', 'case_id'],
            'compliance_case_links' => ['case_id'],
            'compliance_case_notes' => ['author_id', 'case_id'],
            'compliance_cases' => ['primary_finding_id', 'primary_flag_id'],
            'cost_centers' => ['department_id'],
            'customer_documents' => ['uploaded_by'],
            'customer_relations' => ['related_customer_id'],
            'customer_risk_history' => ['assessed_by'],
            'edd_document_requests' => ['edd_record_id'],
            'edd_templates' => ['created_by'],
            'enhanced_diligence_records' => ['approved_by', 'questionnaire_completed_by', 'reviewed_by', 'customer_id', 'flagged_transaction_id'],
            'exchange_rate_histories' => ['created_by'],
            'fiscal_years' => ['closed_by'],
            'flagged_transactions' => ['reviewed_by', 'customer_id'],
            'journal_entries' => ['period_id', 'approved_by', 'created_by', 'reversed_by', 'posted_by'],
            'report_runs' => ['generated_by', 'schedule_id'],
            'report_schedules' => ['created_by'],
            'revaluation_entries' => ['posted_by'],
            'sanction_import_logs' => ['user_id'],
            'sanction_lists' => ['uploaded_by'],
            'screening_results' => ['sanction_entry_id', 'transaction_id', 'customer_id'],
            'stock_transfer_items' => ['stock_transfer_id'],
            'stock_transfers' => ['hq_approved_by', 'branch_manager_approved_by'],
            'system_alerts' => ['acknowledged_by'],
            'teller_allocations' => ['approved_by'],
            'threshold_audits' => ['changed_by'],
            'till_balances' => ['closed_by', 'opened_by', 'teller_allocation_id'],
            'transaction_errors' => ['resolved_by'],
            'transaction_imports' => ['user_id'],
            'transaction_state_history' => ['user_id'],
        ];

        foreach ($tables as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns) {
                $table->dropIndex($columns);
            });
        }
    }
};
```

- [ ] **Step 2: Run the migration**

Run: `php artisan migrate --path=database/migrations/2026_06_21_000001_add_missing_model_indexes.php`
Expected: `Migrated: 2026_06_21_000001_add_missing_model_indexes`

- [ ] **Step 3: Run model tests**

Run: `php artisan test --compact tests/Unit/Models/`
Expected: All tests pass.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_06_21_000001_add_missing_model_indexes.php
git commit -m "chore: add missing indexes on high-cardinality foreign keys"
```

---

### Task 3.3: Add missing foreign-key constraints where supported

**Files:**
- Create: `database/migrations/2026_06_21_000002_align_foreign_keys.php`

**Caveat:** SQLite does not support adding foreign keys to existing tables. This migration is intended for MySQL production. On SQLite it will fail; either skip it in test environments or recreate the tables. For this task, add the migration but guard it for MySQL.

- [ ] **Step 1: Create the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('account_ledger', function (Blueprint $table) {
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
        });

        Schema::table('accounting_periods', function (Blueprint $table) {
            $table->foreign('fiscal_year_id')->references('id')->on('fiscal_years')->nullOnDelete();
        });

        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->foreign('cost_center_id')->references('id')->on('cost_centers')->nullOnDelete();
            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
        });

        Schema::table('counters', function (Blueprint $table) {
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
        });

        Schema::table('counter_sessions', function (Blueprint $table) {
            $table->foreign('teller_allocation_id')->references('id')->on('teller_allocations')->nullOnDelete();
        });

        Schema::table('currency_positions', function (Blueprint $table) {
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
        });

        Schema::table('enhanced_diligence_records', function (Blueprint $table) {
            $table->foreign('edd_template_id')->references('id')->on('edd_templates')->nullOnDelete();
        });

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->foreign('cost_center_id')->references('id')->on('cost_centers')->nullOnDelete();
            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
        });

        Schema::table('journal_lines', function (Blueprint $table) {
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
        });

        Schema::table('stock_reservations', function (Blueprint $table) {
            $table->foreign('transaction_id')->references('id')->on('transactions')->nullOnDelete();
        });

        Schema::table('till_balances', function (Blueprint $table) {
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('teller_allocation_id')->references('id')->on('teller_allocations')->nullOnDelete();
        });

        Schema::table('transaction_confirmations', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $constraints = [
            'account_ledger' => ['branch_id'],
            'accounting_periods' => ['fiscal_year_id'],
            'chart_of_accounts' => ['cost_center_id', 'department_id'],
            'counters' => ['branch_id'],
            'counter_sessions' => ['teller_allocation_id'],
            'currency_positions' => ['branch_id'],
            'enhanced_diligence_records' => ['edd_template_id'],
            'journal_entries' => ['cost_center_id', 'department_id'],
            'journal_lines' => ['branch_id'],
            'stock_reservations' => ['transaction_id'],
            'till_balances' => ['branch_id', 'teller_allocation_id'],
            'transaction_confirmations' => ['user_id'],
            'transactions' => ['branch_id', 'journal_entry_id'],
            'users' => ['branch_id'],
        ];

        foreach ($constraints as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns) {
                $table->dropForeign($columns);
            });
        }
    }
};
```

- [ ] **Step 2: Verify migration compiles**

Run: `php artisan migrate:status`
Expected: No syntax errors.

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_06_21_000002_align_foreign_keys.php
git commit -m "chore: add missing foreign key constraints for mysql"
```

---

## Phase 4: Test Coverage & Factories

### Task 4.1: Complete `ModelHierarchyTest`

**Files:**
- Modify: `tests/Unit/Models/ModelHierarchyTest.php`

- [ ] **Step 1: Add the missing models to each domain group**

Add imports at the top:

```php
use App\Models\AccountLedger;
use App\Models\Compliance\ComplianceCase;
use App\Models\Compliance\ComplianceFinding;
use App\Models\Compliance\EddDocumentRequest;
use App\Models\EnhancedDiligenceRecord;
use App\Models\ExchangeRate;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\SystemAlert;
use App\Models\SystemHealthCheck;
use App\Models\TestResult;
use App\Models\Transaction;
use App\Models\User;
```

Update the test groups:

```php
    public function test_accounting_models_extend_base_model(): void
    {
        $models = [
            AccountingPeriod::class,
            AccountLedger::class,
            Budget::class,
            ChartOfAccount::class,
            CostCenter::class,
            Currency::class,
            CurrencyPosition::class,
            ExchangeRate::class,
            ExchangeRateHistory::class,
            FiscalYear::class,
            JournalEntry::class,
            JournalLine::class,
            RevaluationEntry::class,
        ];

        // ... existing assertion loop ...
    }

    public function test_compliance_models_extend_base_model(): void
    {
        $models = [
            AmlRule::class,
            ComplianceCase::class,
            ComplianceCaseDocument::class,
            ComplianceCaseLink::class,
            ComplianceCaseNote::class,
            ComplianceFinding::class,
            CustomerBehavioralBaseline::class,
            CustomerRiskProfile::class,
            EddDocumentRequest::class,
            EddQuestionnaireTemplate::class,
            EddTemplate::class,
            EnhancedDiligenceRecord::class,
            HighRiskCountry::class,
            PepApprovalRequest::class,
            SanctionEntry::class,
            SanctionImportLog::class,
            SanctionList::class,
            SanctionsAnalysis::class,
            ScreeningResult::class,
            ThresholdAudit::class,
        ];

        // ... existing assertion loop ...
    }

    public function test_inventory_models_extend_base_model(): void
    {
        // existing
    }

    public function test_transaction_models_extend_base_model(): void
    {
        $models = [
            Transaction::class,
            TransactionConfirmation::class,
            TransactionError::class,
            TransactionImport::class,
            BankReconciliation::class,
        ];

        // ... existing assertion loop ...
    }

    public function test_system_models_extend_base_model(): void
    {
        $models = [
            Alert::class,
            BackupLog::class,
            BranchClosureWorkflow::class,
            BranchPool::class,
            Department::class,
            DeviceComputations::class,
            EmergencyClosure::class,
            FlaggedTransaction::class,
            MfaRecoveryCode::class,
            ReportGenerated::class,
            ReportRun::class,
            ReportSchedule::class,
            SystemAlert::class,
            SystemHealthCheck::class,
            SystemLog::class,
            TestResult::class,
            UserNotificationPreference::class,
        ];

        // ... existing assertion loop ...
    }

    public function test_user_model_extends_authenticatable(): void
    {
        $user = new User;

        $this->assertInstanceOf(\Illuminate\Foundation\Auth\User::class, $user);
    }
```

- [ ] **Step 2: Run the test**

Run: `php artisan test --compact tests/Unit/Models/ModelHierarchyTest.php`
Expected: `Tests: 8 passed` (one new test added).

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/Models/ModelHierarchyTest.php
git commit -m "test: complete ModelHierarchyTest coverage"
```

---

### Task 4.2: Add model-specific unit tests for core models

**Files:**
- Create: `tests/Unit/Models/UserModelTest.php`
- Create: `tests/Unit/Models/CustomerModelTest.php`
- Create: `tests/Unit/Models/JournalEntryModelTest.php`

- [ ] **Step 1: Write `UserModelTest`**

```php
<?php

namespace Tests\Unit\Models;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_branch_relationship(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);

        $this->assertTrue($user->branch->is($branch));
    }

    public function test_user_has_many_transactions(): void
    {
        $user = User::factory()->create();
        Transaction::factory()->count(3)->create(['user_id' => $user->id]);

        $this->assertCount(3, $user->transactions);
    }

    public function test_password_is_hashed_and_read_via_password_attribute(): void
    {
        $user = User::factory()->make(['password' => 'secret']);

        $this->assertNotEquals('secret', $user->password_hash);
        $this->assertSame($user->password_hash, $user->password);
    }

    public function test_role_checks(): void
    {
        $this->assertTrue(User::factory()->make(['role' => UserRole::Admin])->isAdmin());
        $this->assertTrue(User::factory()->make(['role' => UserRole::Manager])->isManager());
        $this->assertTrue(User::factory()->make(['role' => UserRole::ComplianceOfficer])->isComplianceOfficer());
        $this->assertTrue(User::factory()->make(['role' => UserRole::Teller])->isTeller());
    }
}
```

- [ ] **Step 2: Write `CustomerModelTest`**

```php
<?php

namespace Tests\Unit\Models;

use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\CustomerNote;
use App\Models\CustomerRiskHistory;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_has_transactions(): void
    {
        $customer = Customer::factory()->create();
        Transaction::factory()->count(2)->create(['customer_id' => $customer->id]);

        $this->assertCount(2, $customer->transactions);
    }

    public function test_customer_has_notes_and_documents(): void
    {
        $customer = Customer::factory()->create();
        CustomerNote::factory()->count(2)->create(['customer_id' => $customer->id]);
        CustomerDocument::factory()->count(2)->create(['customer_id' => $customer->id]);

        $this->assertCount(2, $customer->notes);
        $this->assertCount(2, $customer->documents);
    }

    public function test_customer_has_risk_history(): void
    {
        $customer = Customer::factory()->create();
        CustomerRiskHistory::factory()->count(2)->create(['customer_id' => $customer->id]);

        $this->assertCount(2, $customer->riskHistory);
    }
}
```

- [ ] **Step 3: Write `JournalEntryModelTest`**

```php
<?php

namespace Tests\Unit\Models;

use App\Enums\JournalEntryStatus;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalEntryModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_journal_entry_has_lines(): void
    {
        $entry = JournalEntry::factory()->create();
        JournalLine::factory()->count(2)->create(['journal_entry_id' => $entry->id]);

        $this->assertCount(2, $entry->lines);
    }

    public function test_status_helpers(): void
    {
        $entry = JournalEntry::factory()->make(['status' => JournalEntryStatus::Posted]);

        $this->assertTrue($entry->isPosted());
        $this->assertFalse($entry->isDraft());
    }
}
```

- [ ] **Step 4: Run the new tests**

Run: `php artisan test --compact tests/Unit/Models/UserModelTest.php tests/Unit/Models/CustomerModelTest.php tests/Unit/Models/JournalEntryModelTest.php`
Expected: All tests pass. If factories are missing, create them in the next task first.

- [ ] **Step 5: Commit**

```bash
git add tests/Unit/Models/UserModelTest.php tests/Unit/Models/CustomerModelTest.php tests/Unit/Models/JournalEntryModelTest.php
git commit -m "test: add core model unit tests"
```

---

### Task 4.3: Fix factory enum consistency and add states

**Files:**
- Modify: `database/factories/SystemAlertFactory.php`, `database/factories/CounterFactory.php`, `database/factories/AlertFactory.php`, `database/factories/TransactionFactory.php`, `database/factories/UserFactory.php`, `database/factories/CustomerFactory.php`, `database/factories/SystemAlertFactory.php`

- [ ] **Step 1: Use enum cases in factories**

For `SystemAlertFactory`, replace raw level strings with `SystemAlertLevel::Info` (or the actual enum name). Example:

```php
use App\Enums\SystemAlertLevel;

    'level' => SystemAlertLevel::Info,
```

For `CounterFactory`, replace `'active'` with `CounterStatus::Active`.
For `AlertFactory`, replace `'Open'` with `FlagStatus::Open`.
For `TransactionFactory`, replace `'Completed'` with `TransactionStatus::Completed`.

- [ ] **Step 2: Add useful factory states**

Update `UserFactory`:

```php
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => ['role' => UserRole::Admin]);
    }

    public function manager(): static
    {
        return $this->state(fn (array $attributes) => ['role' => UserRole::Manager]);
    }

    public function complianceOfficer(): static
    {
        return $this->state(fn (array $attributes) => ['role' => UserRole::ComplianceOfficer]);
    }

    public function teller(): static
    {
        return $this->state(fn (array $attributes) => ['role' => UserRole::Teller]);
    }
```

Update `CustomerFactory` with states such as `frozen()`, `pep()`, `sanctioned()`, `highRisk()`, `inactive()` using the actual enum/column values.

Update `SystemAlertFactory` with states `warning()`, `critical()`, `acknowledged()`.

- [ ] **Step 3: Run factory tests**

Run: `php artisan test --compact tests/Unit/Models/`
Expected: Existing tests still pass.

- [ ] **Step 4: Commit**

```bash
git add database/factories/
git commit -m "chore: fix factory enum usage and add common states"
```

---

### Task 4.4: Add missing factories

**Files:**
- Create: `database/factories/JournalLineFactory.php`, `database/factories/CurrencyPositionFactory.php`, `database/factories/Compliance/ComplianceCaseFactory.php`, `database/factories/Compliance/ComplianceFindingFactory.php`, `database/factories/SystemAlertFactory.php` (if missing), `database/factories/UserNotificationPreferenceFactory.php`, `database/factories/ReportRunFactory.php`, `database/factories/ReportScheduleFactory.php`, `database/factories/SystemHealthCheckFactory.php`, `database/factories/TestResultFactory.php` (if missing)

- [ ] **Step 1: Create each missing factory using `php artisan make:factory`**

Run:

```bash
php artisan make:factory JournalLineFactory --model=JournalLine --no-interaction
php artisan make:factory CurrencyPositionFactory --model=CurrencyPosition --no-interaction
php artisan make:factory ComplianceCaseFactory --model=ComplianceCase --no-interaction
php artisan make:factory ComplianceFindingFactory --model=ComplianceFinding --no-interaction
php artisan make:factory UserNotificationPreferenceFactory --model=UserNotificationPreference --no-interaction
php artisan make:factory ReportRunFactory --model=ReportRun --no-interaction
php artisan make:factory ReportScheduleFactory --model=ReportSchedule --no-interaction
php artisan make:factory SystemHealthCheckFactory --model=SystemHealthCheck --no-interaction
```

- [ ] **Step 2: Fill in minimal definitions**

For each generated factory, add realistic default values matching the model `$fillable` columns. Example for `JournalLineFactory`:

```php
<?php

namespace Database\Factories;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Illuminate\Database\Eloquent\Factories\Factory;

class JournalLineFactory extends Factory
{
    protected $model = JournalLine::class;

    public function definition(): array
    {
        return [
            'journal_entry_id' => JournalEntry::factory(),
            'account_code' => ChartOfAccount::factory(),
            'branch_id' => \App\Models\Branch::factory(),
            'debit_amount' => $this->faker->randomFloat(2, 0, 1000),
            'credit_amount' => 0,
            'description' => $this->faker->sentence(),
        ];
    }
}
```

- [ ] **Step 3: Run model tests to verify factories work**

Run: `php artisan test --compact tests/Unit/Models/`
Expected: All tests pass.

- [ ] **Step 4: Commit**

```bash
git add database/factories/
git commit -m "chore: add missing model factories"
```

---

## Phase 5: Verification

### Task 5.1: Run model-focused test suite

- [ ] **Step 1: Run all model and relationship tests**

Run: `php artisan test --compact tests/Unit/Models/ tests/Feature/Models/`
Expected: All tests pass.

- [ ] **Step 2: Run migrations on a fresh test database**

Run: `php artisan migrate:fresh --seed`
Expected: Migrations complete without errors and seeders run successfully.

- [ ] **Step 3: Commit**

No code changes if only verifying; otherwise commit any fixes.

---

### Task 5.2: Run code style checks

- [ ] **Step 1: Run Laravel Pint**

Run: `vendor/bin/pint --dirty --format agent`
Expected: No unfixable issues remain.

- [ ] **Step 2: Commit formatting fixes**

```bash
git add -A
git commit -m "style: apply pint formatting"
```

---

### Task 5.3: Run full test suite

- [ ] **Step 1: Run the full suite**

Run: `php artisan test --compact`
Expected: All tests pass.

- [ ] **Step 2: Document any remaining failures**

If failures remain, create a follow-up task list or issue. Do not claim completion until the full suite is green or each failure is documented as out of scope.

---

## Self-Review

### Spec coverage
- Runtime bug in `ReportSchedule` → Task 1.1.
- `CustomerRiskHistory` column mismatch → Task 1.2.
- `EnhancedDiligenceRecord` missing fields/relationships → Task 1.3.
- Missing `Budget::period()`, `AccountingPeriod::fiscalYear()`, `TillBalance::tellerAllocation()`, `TestResult::executedBy()` → Task 1.4.
- Missing return types → Task 2.1.
- Unused imports → Task 2.2.
- `SanctionEntry` JSON accessors → Task 2.3.
- `RevaluationEntry` dead cast → Task 2.4.
- `$with` review → Task 2.5.
- Schema/table mismatches → Tasks 3.1–3.3.
- Test/factory gaps → Tasks 4.1–4.4.

### Placeholder scan
- No `TBD`, `TODO`, or vague "add validation" steps remain.
- Each task contains exact file paths, code blocks, and commands.

### Type consistency
- All new relationship methods use `BelongsTo`/`HasMany` return types.
- All casts reference existing enums.
- Fillable arrays match migration columns after Task 1.2/1.3.

### Open risks
- Foreign-key migration (Task 3.3) is MySQL-only. SQLite test DB will skip it. If production uses MySQL, run it there; if production also uses SQLite, FK constraints must be added at table-creation time (future migration).
- Adding indexes to large production tables should be done during a maintenance window.
- `EddTemplate` vs `Compliance\EddQuestionnaireTemplate` duplication is noted but not resolved in this plan; add a separate product decision task if needed.
