# AML Compliance Enhancement Design Specification

**Project:** CEMS-MY Currency Exchange Management System  
**Module:** AML Compliance Enhancement  
**Date:** 2026-04-15  
**Version:** 1.0  
**Status:** Approved for Implementation

## Scope Changes (User Confirmed)

**OUT OF SCOPE:**
- e-KYC (OCR, liveness detection, biometric verification) - REMOVED

**IN SCOPE:**
1. CustomerRelationService (PEP Relationship Tracking)
2. WatchlistApiService (Real-time Screening with Public Feeds only)
3. CtrReportService (CTR at RM 25,000 threshold)
4. StrAutomationService (Auto-STR Generation → Pending Approval)
5. RiskScoringConsolidation (Replace existing dual-system)

---

## 1. CustomerRelationService (PEP Relationship Tracking)

### Purpose
Track PEP (Politically Exposed Person) family members, close associates, and beneficial owners. Replace the broken `is_pep_associate` reference that doesn't exist in schema.

### Database Schema

**New Table: `customer_relations`**
```php
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
    $table->index(['customer_id', 'relation_type']);
});
```

**Customer Model Updates:**
```php
// Add to Customer.php
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

public function getAllRelatedCustomers(): Collection
{
    $related = $this->pepRelations->merge($this->associateRelations);
    return $related->pluck('related_customer_id')->filter()->unique();
}
```

### Service: CustomerRelationService

**Location:** `app/Services/CustomerRelationService.php`

**Methods:**
| Method | Purpose |
|--------|---------|
| `addRelation(Customer $customer, array $data): CustomerRelation` | Add new PEP relation |
| `removeRelation(int $relationId): void` | Remove relation |
| `updateRelation(int $relationId, array $data): CustomerRelation` | Update relation details |
| `getRelations(Customer $customer): Collection` | Get all relations for customer |
| `getRelatedCustomers(Customer $customer): Collection` | Get Customer models for related parties |
| `screenRelationAgainstSanctions(CustomerRelation $relation): array` | Screen relation against watchlists |
| `screenAllRelations(Customer $customer): array` | Screen all PEP associates |
| `calculateRelationRiskScore(Customer $customer): int` | Calculate risk from PEP connections (max +30) |
| `isHighRiskRelation(Customer $customer): bool` | Returns true if any relation is PEP |

### Events Fired
- `CustomerRelationAdded` - When new relation added
- `CustomerRelationRemoved` - When relation removed
- `RelationPepStatusChanged` - When related party flagged as PEP

---

## 2. WatchlistApiService (Real-Time Sanctions Screening)

### Purpose
Provide real-time sanctions screening using public feeds (BNM, UNSC, OFAC). Integrate into transaction workflow to freeze transactions on match.

### Architecture

```
WatchlistApiService
├── PublicFeedSync
│   ├── syncUnscList(): int  // UNSC JSON endpoint
│   ├── syncOfacList(): int   // OFAC SDN XML
│   ├── syncBnmList(): int    // BNM list (CSV manual/API)
│   └── syncAllPublicFeeds(): SyncResult
├── FuzzyMatching (Enhanced)
│   ├── screenNameLevenshtein(string $name): array
│   ├── screenNameSoundex(string $name): array      // NEW
│   ├── screenNameMetaphone(string $name): array    // NEW
│   ├── screenNameToken(string $name): array       // NEW - compound names
│   └── aggregateResults(array $results): MatchResult
├── RealtimeScreener
│   ├── screenCustomer(Customer $customer): ScreeningResult
│   ├── screenTransaction(Transaction $transaction): ScreeningResult
│   └── freezeTransactionIfMatched(Transaction $transaction, ScreeningResult $result): void
└── ScreeningAudit
    ├── logScreening(ScreeningResult $result): void
    ├── getScreeningHistory(Customer $customer): Collection
    └── generateComplianceReport(): array
```

### Screening Thresholds
| Match Score | Action |
|-------------|--------|
| >= 90% | BLOCK + Auto-freeze + Alert Critical |
| 75-89% | FLAG for review + Alert High |
| 60-74% | FLAG for monitoring |
| < 60% | Clear |

### Fuzzy Matching Details

**Soundex Implementation:**
```php
private function screenNameSoundex(string $name): array
{
    $soundex1 = soundex($name);
    $results = [];
    
    foreach ($this->getSanctionEntries() as $entry) {
        if (soundex($entry->entity_name) === $soundex1) {
            $results[] = [
                'entry' => $entry,
                'match_type' => 'soundex',
                'score' => 85  // Soundex matches are strong indicator
            ];
        }
    }
    
    return $results;
}
```

**Token Matching for Compound Names:**
```php
private function screenNameToken(string $name): array
{
    $tokens = array_filter(explode(' ', strtolower($name)));
    $results = [];
    
    foreach ($this->getSanctionEntries() as $entry) {
        $entryTokens = array_filter(explode(' ', strtolower($entry->entity_name)));
        $matchCount = count(array_intersect($tokens, $entryTokens));
        
        if ($matchCount >= 2 && $matchCount / max(count($tokens), count($entryTokens)) >= 0.6) {
            $results[] = [
                'entry' => $entry,
                'match_type' => 'token',
                'matched_tokens' => $matchCount,
                'score' => 70 + ($matchCount * 5)
            ];
        }
    }
    
    return $results;
}
```

### Integration Points

**Transaction Flow:**
```
TransactionCreated Event
         ↓
TransactionCreatedListener
         ↓
WatchlistApiService::screenCustomer($customer)  ← NEW
         ↓
    IF MATCH >= 90%
         ↓
    - Transaction::hold()  // Freeze transaction
    - AlertCreated event (Critical priority)
    - Notify Compliance Officer
    - Log to audit trail
```

**Configuration (`config/sanctions.php` additions):**
```php
return [
    // Existing config...
    
    // NEW: Real-time screening
    'realtime_screening_enabled' => env('SANCTIONS_REALTIME_ENABLED', true),
    'screening_block_threshold' => env('SANCTIONS_BLOCK_THRESHOLD', 90),
    'screening_flag_threshold' => env('SANCTIONS_FLAG_THRESHOLD', 75),
    
    // Public feed URLs (read-only)
    'feeds' => [
        'unsc' => 'https://www.un.org/securitycouncil/sanctions materials/sanctions-list-resources',
        'ofac' => 'https://www.treasury.gov/ofac/downloads/sdn.xml',
        'bnm' => env('BNM_SANCTIONS_URL'),  // Manual or API
    ],
    
    // Sync schedule
    'sync_schedule' => '0 3 * * *',  // Daily at 3 AM
];
```

### New Model: ScreeningResult

**Table: `screening_results`**
```php
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
    
    $table->index('customer_id');
    $table->index('transaction_id');
    $table->index(['result', 'created_at']);
});
```

---

## 3. CtrReportService (CTR at RM 25,000)

### Purpose
Generate and submit CTR (Cash Transaction Report) for transactions >= RM 25,000. Update threshold from RM 10,000.

### Service: CtrReportService

**Location:** `app/Services/CtrReportService.php`

**Key Changes from existing `CtosReportService`:**

| Item | Current (CTOS) | New (CTR) |
|------|----------------|-----------|
| Threshold | RM 10,000 | RM 25,000 |
| Scope | Cash Buy AND Sell | Cash Buy AND Sell |
| Aggregation | Single transaction | **Daily cumulative per customer** |
| Submission | Manual portal | **Electronic API** (when available) |
| Warning | None | **RM 20,000 alert** (80%) |

### Methods

```php
class CtrReportService
{
    public function __construct(
        protected MathService $math,
        protected TransactionService $transactionService,
    ) {}
    
    // Threshold check - called at transaction time
    public function checkThreshold(Transaction $transaction): ThresholdResult
    {
        $customer = $transaction->customer;
        $date = $transaction->created_at->toDateString();
        $todayTotal = $this->getDailyTotal($customer, $date);
        $newTotal = $this->math->add($todayTotal, $transaction->amount_local);
        
        return new ThresholdResult([
            'customer_id' => $customer->id,
            'date' => $date,
            'previous_total' => $todayTotal,
            'transaction_amount' => $transaction->amount_local,
            'new_total' => $newTotal,
            'threshold' => config('compliance.ctr_threshold'),
            'warning_threshold' => config('compliance.ctr_warning_threshold'),
            'exceeded' => $this->math->compare($newTotal, config('compliance.ctr_threshold')) >= 0,
            'approaching' => $this->math->compare($newTotal, config('compliance.ctr_warning_threshold')) >= 0,
        ]);
    }
    
    // Daily aggregation
    public function getDailyCtrAggregates(Date $date): Collection
    {
        return Transaction::whereDate('created_at', $date)
            ->where('type', 'Buy')  // Cash transactions only
            ->where('amount_local', '>=', config('compliance.ctr_warning_threshold'))
            ->get()
            ->groupBy('customer_id')
            ->map(fn($txns) => $this->aggregateCustomerTransactions($txns));
    }
    
    // Generate CTR report for BNM
    public function generateCtrReport(Date $date): CtrReport
    {
        $aggregates = $this->getDailyCtrAggregates($date);
        
        return new CtrReport([
            'report_date' => $date,
            'report_type' => 'CTR',
            'generated_at' => now(),
            'transactions' => $aggregates,
            'total_count' => $aggregates->count(),
            'total_amount' => $aggregates->sum('total_amount'),
        ]);
    }
    
    // Submit to BNM (when API available)
    public function submitToBnm(CtrReport $report): SubmissionResult
    {
        // Implementation when BNM provides API
    }
}
```

### Configuration Updates

**`config/compliance.php`:**
```php
return [
    // CTR Threshold (BNM mandated)
    'ctr_threshold' => env('CTR_THRESHOLD', 25000),
    'ctr_warning_threshold' => env('CTR_WARNING_THRESHOLD', 20000),  // 80% for alerts
    
    // CTR Settings
    'ctr_enabled' => env('CTR_ENABLED', true),
    'ctr_bn_submission_url' => env('CTR_BNM_API_URL'),
    'ctr_bn_api_key' => env('CTR_BNM_API_KEY'),
];
```

### Events Fired
- `CtrThresholdApproaching` - When customer hits RM 20,000
- `CtrThresholdExceeded` - When customer hits RM 25,000
- `CtrReportGenerated` - Daily CTR report generated

---

## 4. StrAutomationService (Auto-STR Generation)

### Purpose
Automatically generate STR drafts when structuring patterns or high-risk behavior detected. STR goes to pending approval workflow (not auto-submitted).

### Service: StrAutomationService

**Location:** `app/Services/StrAutomationService.php`

### Auto-STR Triggers

| Trigger | Condition | Auto-STR Priority |
|---------|-----------|-------------------|
| **Structuring** | 3+ sub-RM 3,000 transactions in 1 hour | HIGH |
| **Smurfing** | Network of linked customers with shared identifiers | CRITICAL |
| **Risk Escalation** | Customer risk score jumps 2+ tiers in 30 days | MEDIUM |
| **Velocity Spike** | Customer exceeds 3x normal 24h volume | HIGH |
| **Sanctions Match** | Any watchlist match | CRITICAL (transaction already frozen) |

### Methods

```php
class StrAutomationService
{
    public function __construct(
        protected StrReportService $strReportService,
        protected CustomerRiskScoringService $riskScoringService,
        protected NarrativeGenerator $narrativeGenerator,
    ) {}
    
    // Main evaluation - called when alert created
    public function evaluateAutoStrTriggers(Alert $alert): ?StrDraft
    {
        $triggers = $this->getApplicableTriggers($alert);
        
        if (empty($triggers)) {
            return null;
        }
        
        // Check if STR already exists for this pattern
        if ($this->hasRecentStrForPattern($alert)) {
            return null;
        }
        
        return $this->generateStrDraft($alert, $triggers);
    }
    
    // Generate STR draft from alert
    public function generateStrDraft(Alert $alert, array $triggers): StrDraft
    {
        $customer = $alert->customer;
        $transactions = $this->getRelatedTransactions($alert);
        
        $draft = $this->strReportService->createDraft([
            'customer_id' => $customer->id,
            'branch_id' => $alert->branch_id,
            'alert_id' => $alert->id,
            'transaction_ids' => $transactions->pluck('id')->toArray(),
            'reason' => $this->narrativeGenerator->generateFromTriggers($triggers),
            'supporting_documents' => $this->gatherSupportingDocs($alert),
            'status' => StrStatus::PendingApproval,  // NOT auto-submitted
        ]);
        
        // Link to alert
        $alert->update(['str_draft_id' => $draft->id]);
        
        return $draft;
    }
    
    // Get applicable triggers for alert
    protected function getApplicableTriggers(Alert $alert): array
    {
        $triggers = [];
        
        switch ($alert->alert_type) {
            case 'Structuring':
                $triggers[] = [
                    'type' => 'Structuring',
                    'description' => 'Structuring pattern detected',
                    'regulatory_reference' => 'BNM AML/CFT Guideline 7.4.3',
                ];
                break;
                
            case 'Smurfing':
                $triggers[] = [
                    'type' => 'Smurfing',
                    'description' => 'Smurfing network pattern detected',
                    'regulatory_reference' => 'BNM AML/CFT Guideline 7.4.4',
                ];
                break;
                
            case 'RiskEscalation':
                $triggers[] = [
                    'type' => 'RiskEscalation', 
                    'description' => 'Significant risk score increase',
                    'regulatory_reference' => 'BNM AML/CFT Guideline 7.3.1',
                ];
                break;
        }
        
        // Add additional triggers based on customer risk
        if ($alert->customer->isPep()) {
            $triggers[] = [
                'type' => 'PepInvolved',
                'description' => 'Customer is Politically Exposed Person',
                'regulatory_reference' => 'BNM AML/CFT Guideline 7.2.1',
            ];
        }
        
        if ($alert->customer->sanction_hit) {
            $triggers[] = [
                'type' => 'SanctionsMatch',
                'description' => 'Sanctions list match identified',
                'regulatory_reference' => 'BNM AML/CFT Guideline 7.1.1',
            ];
        }
        
        return $triggers;
    }
}
```

### NarrativeGenerator Service

**Location:** `app/Services/NarrativeGenerator.php`

```php
class NarrativeGenerator
{
    public function generateFromTriggers(array $triggers): string
    {
        $narrative = "Suspicious Activity Report\n\n";
        $narrative .= "The following suspicious behavior patterns were identified:\n\n";
        
        foreach ($triggers as $trigger) {
            $narrative .= "1. {$trigger['type']}: {$trigger['description']}\n";
            $narrative .= "   Regulatory Reference: {$trigger['regulatory_reference']}\n\n";
        }
        
        $narrative .= $this->generateTransactionSummary($triggers);
        $narrative .= "\n\nRecommendation: Filing of STR is required under BNM AML/CFT Guidelines.\n";
        
        return $narrative;
    }
    
    public function generateTransactionSummary(array $triggers): string
    {
        // Gather transactions from trigger data
        // Generate summary statistics
        // Format for regulatory reporting
    }
}
```

### Workflow Integration

**STR Status Workflow:**
```
STR Draft Created (PendingApproval)
         ↓
Compliance Officer Reviews
         ↓
    ┌────┴────┐
    ↓         ↓
Approve    Reject
    ↓         ↓
STR Submitted  STR Cancelled
    ↓
BNM Acknowledgement
    ↓
Case Management Updated
```

**UI Placement:**
- STR Studio page (`/compliance/str-studio`) will show:
  - **Pending My Approval** tab for auto-generated STRs
  - **Draft** tab for manually created STRs
  - **Submitted** tab for sent STRs
  - **Acknowledged** tab for BNM acknowledged STRs

### Events Fired
- `StrDraftAutoGenerated` - When auto-STR created from triggers
- `StrDraftRequiresApproval` - Notify compliance officer

---

## 5. RiskScoringConsolidation (Unified Risk Engine)

### Purpose
Replace the two conflicting risk scoring systems (`CustomerRiskScoringService` and `RiskScoringEngine`) with a single `UnifiedRiskScoringService`.

### Why Consolidation Needed

| Aspect | CustomerRiskScoringService | RiskScoringEngine |
|--------|---------------------------|-------------------|
| Lookback | 90 days | Varies |
| Factors | velocity, structuring, geographic, amount | 8 factors |
| Output | Score 0-140 (capped 100) | Score 0-100 |
| Trigger | Scheduled/manual | Event-driven |
| Used by | Dashboard | Compliance alerts |

**Bug:** `RiskScoringEngine:221` references `$customer->is_pep_associate` which **doesn't exist** in database.

### New Unified Service: UnifiedRiskScoringService

**Location:** `app/Services/UnifiedRiskScoringService.php`

### Factor Weights

| Factor | Max Score | Description |
|--------|-----------|-------------|
| Velocity | 25 | 24h transaction volume vs threshold |
| Structuring | 25 | Structuring patterns in 90 days |
| Geographic | 20 | High-risk country transactions |
| Amount | 15 | Transaction size vs customer profile |
| PEP | 20 | PEP status (self + associates) |
| Sanctions | 100 | **Instant Critical** if matched |
| EDD History | 10 | Past EDD findings |
| Document | 10 | Missing/expired CDD documents |
| Behavioral | 15 | Deviation from 90-day baseline |

### Risk Tier Thresholds

| Tier | Score Range | Action |
|------|-------------|--------|
| **Critical** | >= 80 OR Sanctions Match | Enhanced EDD + Manager Approval + Freeze |
| **High** | 60-79 | Enhanced EDD Required |
| **Medium** | 30-59 | Standard EDD Possible |
| **Low** | < 30 | Simplified CDD |

### CDD Level Mapping

| Risk Tier | CDD Level | EDD Required |
|----------|-----------|--------------|
| Critical | Enhanced | Yes (template: Pep/SanctionMatch) |
| High | Enhanced | Yes (template: HighRiskCountry/LargeTransaction) |
| Medium | Standard | Possible (template: UnusualPattern) |
| Low | Simplified | No |

### Service Implementation

```php
class UnifiedRiskScoringService
{
    protected array $factorWeights = [
        'velocity' => 25,
        'structuring' => 25,
        'geographic' => 20,
        'amount' => 15,
        'pep' => 20,
        'edd_history' => 10,
        'document' => 10,
        'behavioral' => 15,
    ];
    
    // Constants (configurable)
    protected const VELOCITY_THRESHOLD = '50000';  // RM 50,000
    protected const SUB_THRESHOLD = '3000';        // RM 3,000 structuring
    protected const LOOKBACK_DAYS = 90;
    
    public function calculateRiskScore(Customer $customer): CustomerRiskProfile
    {
        $factors = $this->calculateAllFactors($customer);
        $totalScore = $this->sumWeightedFactors($factors);
        
        // Sanctions override - instant critical
        if ($this->hasSanctionsMatch($customer)) {
            $totalScore = 100;
            $factors['sanctions'] = 100;
        }
        
        return new CustomerRiskProfile([
            'customer_id' => $customer->id,
            'total_score' => min($totalScore, 100),
            'risk_tier' => $this->determineRiskTier($totalScore),
            'factors' => $factors,
            'cdd_level' => $this->determineCddLevel($totalScore),
            'calculated_at' => now(),
            'next_screening_date' => $this->calculateNextScreeningDate($totalScore),
        ]);
    }
    
    protected function calculateAllFactors(Customer $customer): array
    {
        return [
            'velocity' => $this->calculateVelocityFactor($customer),
            'structuring' => $this->calculateStructuringFactor($customer),
            'geographic' => $this->calculateGeographicFactor($customer),
            'amount' => $this->calculateAmountFactor($customer),
            'pep' => $this->calculatePepFactor($customer),
            'sanctions' => $this->hasSanctionsMatch($customer) ? 100 : 0,
            'edd_history' => $this->calculateEddHistoryFactor($customer),
            'document' => $this->calculateDocumentFactor($customer),
            'behavioral' => $this->calculateBehavioralFactor($customer),
        ];
    }
    
    protected function calculatePepFactor(Customer $customer): int
    {
        $score = 0;
        
        // Self PEP
        if ($customer->pep_status) {
            $score += 20;
        }
        
        // PEP Relations (using new CustomerRelationService)
        $relatedPeps = $customer->pepRelations()
            ->where('is_pep', true)
            ->count();
        $score += min($relatedPeps * 10, 10);  // Max +10 from associates
        
        return min($score, 20);
    }
    
    protected function determineRiskTier(int $score): string
    {
        if ($score >= 80) {
            return 'Critical';
        } elseif ($score >= 60) {
            return 'High';
        } elseif ($score >= 30) {
            return 'Medium';
        }
        return 'Low';
    }
}
```

### Model Updates

**CustomerRiskProfile Model:**
```php
// Already exists in app/Models/Compliance/CustomerRiskProfile.php
// Add new fields:
protected $fillable = [
    // existing...
    'factors',        // JSON of factor scores
    'sanctions_hit',  // boolean
    'edd_required',   // boolean
    'edd_template',   // string
];

// Methods to add:
public function getFactorScore(string $factor): int
public function getTotalFactorScore(): int
public function getRiskFactors(): array
public function getRecommendedActions(): array
```

### Event-Driven Updates

**New Event: `RiskScoreCalculated`**
```php
class RiskScoreCalculated
{
    public function __construct(
        public Customer $customer,
        public CustomerRiskProfile $profile,
        public ?CustomerRiskProfile $previousProfile = null,
    ) {}
}
```

**Integration in Transaction Flow:**
```
TransactionCreated
         ↓
TransactionCreatedListener
         ↓
UnifiedRiskScoringService::calculateRiskScore($customer)  ← NEW
         ↓
    IF score changed significantly
         ↓
    RiskScoreCalculated Event
         ↓
    → Update customer risk fields
    → Create snapshot
    → Check if STR trigger met
    → Alert if escalation
```

---

## 6. Integration Summary

### Event Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                    COMPLIANCE ORCHESTRATOR                     │
│                 (ComplianceEventListener)                     │
└─────────────────────────────────────────────────────────────────┘
                                │
        ┌───────────────────────┼───────────────────────┐
        ↓                       ↓                       ↓
┌───────────────────┐  ┌───────────────────┐  ┌───────────────────┐
│  TRANSACTION     │  │  SCHEDULED       │  │  ON-DEMAND        │
│  CREATED EVENT   │  │  MONITORS        │  │  SCREENING        │
└───────────────────┘  └───────────────────┘  └───────────────────┘
        │                       │                       │
        ↓                       ↓                       ↓
┌───────────────────┐  ┌───────────────────┐  ┌───────────────────┐
│ WatchlistApiServ  │  │ SmurfingDetector │  │ CustomerRelation  │
│ - screenCustomer │  │ - networkAnalysis│  │ - addRelation     │
│ - checkRealtime  │  │ - linkedEntities │  │ - screenRelations │
└───────────────────┘  └───────────────────┘  └───────────────────┘
        │                       │                       │
        ↓                       ↓                       ↓
┌───────────────────────────────────────────────────────────────┐
│                    UNIFIED RISK SCORING                        │
│           UnifiedRiskScoringService (NEW)                    │
└───────────────────────────────────────────────────────────────┘
        │
        ├──→ AlertCreated Event (if threshold exceeded)
        │
        ├──→ StrAutomationService::evaluateAutoStrTriggers()
        │         ↓
        │    STR Draft Created → Pending Approval → STR Studio
        │
        └──→ CtrReportService::checkThreshold()
                 ↓
            CTR Report Generated → BNM Submission
```

### Files to Create

| File | Purpose |
|------|---------|
| `app/Services/CustomerRelationService.php` | PEP relationship management |
| `app/Services/WatchlistApiService.php` | Real-time screening with public feeds |
| `app/Services/CtrReportService.php` | CTR at RM 25k |
| `app/Services/StrAutomationService.php` | Auto-STR generation |
| `app/Services/NarrativeGenerator.php` | STR narrative generation |
| `app/Services/UnifiedRiskScoringService.php` | Consolidated risk scoring |
| `app/Models/CustomerRelation.php` | PEP relation model |
| `app/Models/ScreeningResult.php` | Screening audit model |
| `app/Events/CustomerRelationAdded.php` | New event |
| `app/Events/RiskScoreCalculated.php` | New event |
| `app/Listeners/RiskScoreEventListener.php` | Handle risk events |
| `app/Listeners/SanctionScreeningListener.php` | Handle screening results |

### Files to Modify

| File | Changes |
|------|---------|
| `app/Models/Customer.php` | Add `pepRelations()`, `isPepAssociate()` |
| `app/Services/SanctionScreeningService.php` | Add enhanced fuzzy matching |
| `app/Services/ComplianceService.php` | Update CTOS threshold to 25000 |
| `app/Services/TransactionService.php` | Add real-time screening call |
| `app/Services/TransactionMonitoringService.php` | Add smurfing detection |
| `app/Services/StrReportService.php` | Add `createDraft()` method |
| `app/Services/CustomerRiskScoringService.php` | **Deprecate** (replace with unified) |
| `app/Services/Compliance/RiskScoringEngine.php` | **Deprecate** (replace with unified) |
| `app/Providers/EventServiceProvider.php` | Register new events/listeners |
| `config/compliance.php` | Add CTR thresholds, remove old CTOS config |
| `config/sanctions.php` | Add real-time screening config |
| `app/Console/Kernel.php` | Add watchlist sync schedule |

### Database Migrations

| Order | Migration | Purpose |
|-------|-----------|---------|
| 1 | `create_customer_relations` | PEP/associate tracking |
| 2 | `create_screening_results` | Screening audit log |
| 3 | `add_screening_fields_to_sanction_entries` | Add DOB, nationality for matching |
| 4 | `add_pep_associate_to_customers` | Fix broken field reference |
| 5 | `update_ctos_threshold_to_25000` | Change CTR threshold |
| 6 | `add_sanctions_screened_at_to_customers` | Track last screening |

---

## 7. Testing Requirements

### Unit Tests
- `CustomerRelationServiceTest` - Relation CRUD, screening
- `WatchlistApiServiceTest` - Fuzzy matching combinations
- `CtrReportServiceTest` - Threshold calculations
- `StrAutomationServiceTest` - Trigger evaluation, draft generation
- `UnifiedRiskScoringServiceTest` - All factor calculations

### Integration Tests
- `ComplianceWorkflowTest` - Transaction → Screening → Alert → STR
- `CtrWorkflowTest` - Daily aggregation → Report generation
- `StrApprovalWorkflowTest` - Auto-STR → Pending → Approved → Submitted

### Performance Tests
- Screen 10,000 customers against 50,000 sanction entries < 30 seconds
- Risk score calculation for 1,000 customers < 10 seconds

---

## 8. Implementation Phases

### Phase 1: Foundation (CustomerRelationService)
- Database migration for `customer_relations`
- Customer model updates
- CustomerRelationService
- Basic CRUD for relations

### Phase 2: Screening (WatchlistApiService)
- Database migration for `screening_results`
- Enhanced fuzzy matching (Soundex, Metaphone, Token)
- Real-time screening integration
- Transaction freeze on match

### Phase 3: CTR (CtrReportService)
- Update threshold to RM 25,000
- Daily aggregation logic
- Warning alerts at RM 20,000
- CTR report generation

### Phase 4: Auto-STR (StrAutomationService)
- Trigger evaluation
- NarrativeGenerator
- Draft generation
- STR Studio integration (Pending Approval tab)

### Phase 5: Risk Consolidation (UnifiedRiskScoringService)
- Unified service implementation
- Factor calculation methods
- Event-driven updates
- Deprecate old services

---

## 9. Dependencies

### External Services
- **UNSC Sanctions List** - Public JSON endpoint (no auth)
- **OFAC SDN List** - Public XML (no auth)
- **BNM Sanctions List** - Manual CSV or API (if available)

### Internal Dependencies
- All existing services (TransactionService, ComplianceService, etc.)
- Event system (already in place)
- Queue job system (already in place)
- AuditService (already in place)

---

## 10. Risk Mitigation

| Risk | Mitigation |
|------|------------|
| False positive matches | Multi-factor scoring, human review before action |
| API unavailability | Cache last sync, manual fallback |
| Performance under load | Async processing via queue jobs |
| Data quality | Input validation, regular audit |
| Regulatory changes | Configurable thresholds, modular design |
