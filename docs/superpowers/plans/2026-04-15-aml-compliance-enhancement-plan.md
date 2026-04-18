# AML Compliance Enhancement Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Implement 5 AML compliance modules replacing current dual-system issues.

**Architecture:** Laravel services with DI, event-driven, queue jobs for background processing.

---

## IMPLEMENTATION PHASES

### PHASE 1: CustomerRelationService
**Purpose:** Track PEP family members, close associates, beneficial owners.

#### Task 1.1: Create Database Migration
```php
// database/migrations/2026_04_15_000010_create_customer_relations_table.php
Schema::create('customer_relations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_id')->constrained()->onDelete('cascade');
    $table->foreignId('related_customer_id')->nullable()->constrained('customers');
    $table->enum('relation_type', [
        'spouse', 'child', 'parent', 'sibling',
        'close_associate', 'business_partner',
        'beneficial_owner', 'director', 'signatory',
        'related_entity'
    ]);
    $table->string('related_name');
    $table->string('id_type')->nullable();
    $table->string('id_number_encrypted')->nullable();
    $table->date('date_of_birth')->nullable();
    $table->string('nationality')->nullable();
    $table->text('address')->nullable();
    $table->boolean('is_pep')->default(false);
    $table->json('additional_info')->nullable();
    $table->timestamps();
    $table->index('customer_id');
});
```

#### Task 1.2: Create CustomerRelation Model
```php
// app/Models/CustomerRelation.php
class CustomerRelation extends Model
{
    protected $fillable = [
        'customer_id', 'related_customer_id', 'relation_type',
        'related_name', 'id_type', 'id_number_encrypted',
        'date_of_birth', 'nationality', 'address', 'is_pep',
        'additional_info',
    ];
    protected $casts = [
        'date_of_birth' => 'date',
        'is_pep' => 'boolean',
        'additional_info' => 'array',
    ];
    
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function relatedCustomer(): BelongsTo { return $this->belongsTo(Customer::class, 'related_customer_id'); }
}
```

#### Task 1.3: Create CustomerRelationService
```php
// app/Services/CustomerRelationService.php
class CustomerRelationService
{
    public function addRelation(int $customerId, array $data): CustomerRelation
    public function removeRelation(int $relationId): void
    public function updateRelation(int $relationId, array $data): CustomerRelation
    public function getRelations(Customer $customer): Collection
    public function getRelatedCustomers(Customer $customer): Collection
    public function isPepAssociate(Customer $customer): bool
    public function isHighRiskRelation(Customer $customer): bool
    public function calculateRelationRiskScore(Customer $customer): int
}
```

#### Task 1.4: Update Customer Model
Add to Customer.php:
```php
public function pepRelations()
{
    return $this->hasMany(CustomerRelation::class, 'customer_id');
}

public function associateRelations()
{
    return $this->hasMany(CustomerRelation::class, 'related_customer_id');
}

public function isPepAssociate(): bool
{
    return $this->pepRelations()->where('is_pep', true)->exists();
}
```

#### Task 1.5: Create Events
- app/Events/CustomerRelationAdded.php
- app/Events/CustomerRelationRemoved.php

#### Task 1.6: Unit Tests
```php
// tests/Unit/CustomerRelationServiceTest.php
class CustomerRelationServiceTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_add_relation_creates_relation_record()
    public function test_get_relations_returns_customer_relations()
    public function test_is_pep_associate_returns_true_when_pep_relation_exists()
    public function test_remove_relation_deletes_record()
}
```

---

### PHASE 2: WatchlistApiService
**Purpose:** Real-time sanctions screening with enhanced fuzzy matching.

#### Task 2.1: Create ScreeningResult Model
```php
// database/migrations/2026_04_15_000011_create_screening_results_table.php
Schema::create('screening_results', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_id')->nullable()->constrained();
    $table->foreignId('transaction_id')->nullable()->constrained();
    $table->string('screened_name');
    $table->foreignId('sanction_entry_id')->nullable()->constrained('sanction_entries');
    $table->enum('match_type', ['exact', 'levenshtein', 'soundex', 'metaphone', 'token']);
    $table->decimal('match_score', 5, 2);
    $table->enum('action_taken', ['clear', 'flag', 'block']);
    $table->enum('result', ['clear', 'flag', 'block']);
    $table->json('matched_fields')->nullable();
    $table->timestamps();
    $table->index(['result', 'created_at']);
});
```

#### Task 2.2: Create ScreeningResult Model
```php
// app/Models/ScreeningResult.php
class ScreeningResult extends Model
{
    protected $fillable = [
        'customer_id', 'transaction_id', 'screened_name',
        'sanction_entry_id', 'match_type', 'match_score',
        'action_taken', 'result', 'matched_fields',
    ];
    
    public function customer(): BelongsTo
    public function transaction(): BelongsTo
    public function sanctionEntry(): BelongsTo
    public function isBlocked(): bool
    public function isFlagged(): bool
}
```

#### Task 2.3: Create WatchlistApiService
```php
// app/Services/WatchlistApiService.php
class WatchlistApiService
{
    protected float $blockThreshold = 0.90;  // 90%
    protected float $flagThreshold = 0.75;     // 75%
    
    public function screenNameEnhanced(string $name): array
    {
        // Combines: levenshtein, soundex, metaphone, token
    }
    
    public function screenCustomer(Customer $customer): array
    {
        // Returns: action (clear/flag/block), score, match info
    }
    
    public function screenTransaction(Transaction $transaction): array
    public function freezeTransactionIfMatched(Transaction $transaction, array $result): void
    public function getScreeningHistory(Customer $customer): Collection
}
```

#### Task 2.4: Add Enhanced Fuzzy Matching
Implement in WatchlistApiService:
- screenNameSoundex() - phonetic matching (Soundex)
- screenNameMetaphone() - better phonetic for non-English
- screenNameToken() - compound name matching

#### Task 2.5: Update config/sanctions.php
Add:
```php
'realtime_screening_enabled' => env('SANCTIONS_REALTIME_ENABLED', true),
'screening_block_threshold' => env('SANCTIONS_BLOCK_THRESHOLD', 0.90),
'screening_flag_threshold' => env('SANCTIONS_FLAG_THRESHOLD', 0.75),
```

#### Task 2.6: Unit Tests
```php
// tests/Unit/WatchlistApiServiceTest.php
class WatchlistApiServiceTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_screen_name_with_soundex_match()
    public function test_screen_name_with_token_match()
    public function test_screen_customer_returns_block_when_high_match()
    public function test_screening_result_saved_to_history()
}
```

---

### PHASE 3: CtrReportService
**Purpose:** CTR at RM 25,000 with daily aggregation.

#### Task 3.1: Update config/compliance.php
Add:
```php
'ctr_threshold' => env('CTR_THRESHOLD', 25000),
'ctr_warning_threshold' => env('CTR_WARNING_THRESHOLD', 20000),
```

#### Task 3.2: Create CtrReportService
```php
// app/Services/CtrReportService.php
class CtrReportService
{
    public function checkThreshold(Transaction $transaction): array
    {
        // Returns: exceeded (>=25000), approaching (>=20000)
    }
    
    public function getDailyTotal(Customer $customer, string $date): string
    public function getDailyCtrAggregates(string $date): Collection
    public function generateCtrReport(string $date): array
}
```

#### Task 3.3: Unit Tests
```php
// tests/Unit/CtrReportServiceTest.php
class CtrReportServiceTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_check_threshold_returns_not_exceeded_for_small_amount()
    public function test_check_threshold_returns_approaching_at_20000()
    public function test_check_threshold_returns_exceeded_at_25000()
    public function test_get_daily_aggregates_returns_customers_over_threshold()
}
```

---

### PHASE 4: StrAutomationService
**Purpose:** Auto-generate STR when structuring/smurfing detected. STR goes to PendingApproval.

#### Task 4.1: Create NarrativeGenerator
```php
// app/Services/NarrativeGenerator.php
class NarrativeGenerator
{
    public function generateFromTriggers(array $triggers): string
    public function generateFromAlert(Alert $alert): string
}
```

#### Task 4.2: Create StrAutomationService
```php
// app/Services/StrAutomationService.php
class StrAutomationService
{
    public function evaluateAutoStrTriggers(Alert $alert): ?StrReport
    {
        // Returns null if no triggers, or StrReport draft
    }
    
    protected function getApplicableTriggers(Alert $alert): array
    {
        // Structuring: 3+ sub-RM3k in 1 hour
        // Smurfing: network pattern
        // RiskEscalation: score jump 2+ tiers
        // SanctionMatch: instant critical
    }
    
    protected function generateStrDraft(Alert $alert, array $triggers): StrReport
    {
        // Status = PendingApproval (NOT auto-submitted)
    }
    
    protected function hasRecentStrForPattern(Alert $alert): bool
}
```

#### Task 4.3: Create StrDraftAutoGenerated Event
```php
// app/Events/StrDraftAutoGenerated.php
class StrDraftAutoGenerated
{
    public function __construct(public StrReport $strReport) {}
}
```

#### Task 4.4: Register Events
Add to EventServiceProvider:
```php
StrDraftAutoGenerated::class => [ComplianceEventListener::class],
```

#### Task 4.5: Unit Tests
```php
// tests/Unit/StrAutomationServiceTest.php
class StrAutomationServiceTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_evaluate_returns_null_for_non_triggering_alert()
    public function test_evaluate_returns_draft_for_structuring_alert()
    public function test_auto_generated_str_has_pending_approval_status()
}
```

---

### PHASE 5: UnifiedRiskScoringService
**Purpose:** Replace dual scoring systems (CustomerRiskScoringService + RiskScoringEngine).

#### Task 5.1: Create UnifiedRiskScoringService
```php
// app/Services/UnifiedRiskScoringService.php
class UnifiedRiskScoringService
{
    protected array $factorWeights = [
        'velocity' => 25,
        'structuring' => 25,
        'geographic' => 20,
        'amount' => 15,
        'pep' => 20,
        'sanctions' => 100,
        'edd_history' => 10,
        'document' => 10,
        'behavioral' => 15,
    ];
    
    public function calculateRiskScore(Customer $customer): array
    {
        // Returns: total_score, risk_tier, factors, cdd_level, edd_required
    }
    
    // Risk Tiers:
    // Critical >= 80 (sanctions match)
    // High >= 60
    // Medium >= 30
    // Low < 30
}
```

#### Task 5.2: Create RiskScoreCalculated Event
```php
// app/Events/RiskScoreCalculated.php
class RiskScoreCalculated
{
    public function __construct(public Customer $customer, public array $profile) {}
}
```

#### Task 5.3: Update TransactionCreatedListener
```php
// app/Listeners/TransactionCreatedListener.php
public function handle(TransactionCreated $event)
{
    $this->monitoringService->monitorTransaction($event->transaction);
    $this->riskScoringService->calculateRiskScore($event->transaction->customer);
}
```

#### Task 5.4: Register Event
Add to EventServiceProvider:
```php
RiskScoreCalculated::class => [ComplianceEventListener::class],
```

#### Task 5.5: Unit Tests
```php
// tests/Unit/UnifiedRiskScoringServiceTest.php
class UnifiedRiskScoringServiceTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_calculate_risk_score_returns_low_for_new_customer()
    public function test_calculate_risk_score_returns_high_for_pep_customer()
    public function test_calculate_risk_score_returns_critical_for_sanctioned()
    public function test_pep_relation_adds_to_risk_score()
    public function test_determine_cdd_level_returns_enhanced_for_high_risk()
}
```

---

### PHASE 6: Final Cleanup

#### Task 6.1: Add is_pep_associate to customers
```php
// database/migrations/xxxx_add_pep_associate_to_customers_table.php
Schema::table('customers', function (Blueprint $table) {
    $table->boolean('is_pep_associate')->default(false)->after('pep_status');
});
```

#### Task 6.2: Update CTOS_THRESHOLD
```php
// app/Services/ComplianceService.php:58
public const CTOS_THRESHOLD = '25000';
```

---

## FILE SUMMARY

| File | Action |
|------|--------|
| app/Models/CustomerRelation.php | CREATE |
| app/Services/CustomerRelationService.php | CREATE |
| app/Events/CustomerRelationAdded.php | CREATE |
| app/Events/CustomerRelationRemoved.php | CREATE |
| app/Models/ScreeningResult.php | CREATE |
| app/Services/WatchlistApiService.php | CREATE |
| app/Services/CtrReportService.php | CREATE |
| app/Services/NarrativeGenerator.php | CREATE |
| app/Services/StrAutomationService.php | CREATE |
| app/Events/StrDraftAutoGenerated.php | CREATE |
| app/Services/UnifiedRiskScoringService.php | CREATE |
| app/Events/RiskScoreCalculated.php | CREATE |
| app/Models/Customer.php | MODIFY |
| app/Listeners/TransactionCreatedListener.php | MODIFY |
| app/Providers/EventServiceProvider.php | MODIFY |
| config/compliance.php | MODIFY |
| config/sanctions.php | MODIFY |
| app/Services/ComplianceService.php | MODIFY |
| database/migrations/*_create_customer_relations_table.php | CREATE |
| database/migrations/*_create_screening_results_table.php | CREATE |
| database/migrations/*_add_pep_associate_to_customers_table.php | CREATE |
| tests/Unit/CustomerRelationServiceTest.php | CREATE |
| tests/Unit/WatchlistApiServiceTest.php | CREATE |
| tests/Unit/CtrReportServiceTest.php | CREATE |
| tests/Unit/StrAutomationServiceTest.php | CREATE |
| tests/Unit/UnifiedRiskScoringServiceTest.php | CREATE |

---

## TESTING VERIFICATION

Run: `php artisan test --filter="CustomerRelationServiceTest|WatchlistApiServiceTest|CtrReportServiceTest|StrAutomationServiceTest|UnifiedRiskScoringServiceTest"`

All 5 test suites should pass.

---

## PLAN COMPLETE

**Two execution options:**

**1. Subagent-Driven (recommended)** - I dispatch a fresh subagent per phase, review between phases.

**2. Inline Execution** - Execute tasks in this session using executing-plans, batch execution with checkpoints.

Which approach?
