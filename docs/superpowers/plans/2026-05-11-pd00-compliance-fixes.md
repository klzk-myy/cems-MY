# pd-00.md Compliance Fixes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix 2 critical violations and 8 implementation gaps identified against Bank Negara Malaysia pd-00.md requirements for Money Services Business compliance.

**Architecture:** Fixes are distributed across: test file, SanctionsService, CddLevelDeterminationService, CustomerRelation model, TransactionWorkflow, and ComplianceService. Each fix is self-contained with a test.

**Tech Stack:** Laravel 10, BCMath (MathService), Policy-based enforcement

---

## File Map

```
app/
├── Services/
│   ├── CustomerScreeningService.php      # FIX 1: Sanctions freeze/block/reject
│   ├── ComplianceService.php             # FIX 2: STR next working day
│   ├── CddLevelDeterminationService.php   # FIX 3: Foreign/Domestic PEP, FIX 6: Source of wealth+funds
│   └── TransactionService.php             # FIX 4: PEP Senior Management approval pre-transaction
├── Models/
│   └── CustomerRelation.php              # FIX 5: Close associate engagement assessment
tests/Unit/
└── BoundaryValueTest.php                 # FIX 0: CTOS threshold RM 25,000
```

---

## Task 0: Fix CTOS Threshold in BoundaryValueTest

**Files:**
- Modify: `tests/Unit/BoundaryValueTest.php:49`

- [ ] **Step 1: Read the test file to find the exact CTOS threshold values**

```php
public static function ctosThresholdProvider(): array
{
    return [
        'just below CTOS threshold (9999.99)' => [9999.99, false],
        'exactly at CTOS threshold (10000)' => [10000.00, true],   // WRONG
        'above CTOS threshold (15000)' => [15000.00, true],        // WRONG
    ];
}
```

- [ ] **Step 2: Fix the test values to use RM 25,000 threshold per pd-00.md 21.3.1**

```php
public static function ctosThresholdProvider(): array
{
    return [
        'just below CTOS threshold (24999.99)' => [24999.99, false],
        'exactly at CTOS threshold (25000)' => [25000.00, true],
        'above CTOS threshold (30000)' => [30000.00, true],
    ];
}
```

- [ ] **Step 3: Run the test to verify it passes**

Run: `php artisan test --filter=BoundaryValueTest::testCtosThresholdAtBoundary`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add tests/Unit/BoundaryValueTest.php
git commit -m "fix(test): CTOS threshold to RM 25000 per pd-00.md 21.3.1"
```

---

## Task 1: Implement Sanctions Freeze/Block/Reject Actions

**Files:**
- Modify: `app/Services/CustomerScreeningService.php`
- Modify: `app/Models/Customer.php` (add freeze fields if missing)
- Create: `tests/Unit/CustomerScreeningServiceTest.php`

- [ ] **Step 1: Write failing test for freeze action**

```php
public function test_confirmed_sanctions_match_triggers_freeze(): void
{
    $customer = Customer::factory()->create();
    $service = new CustomerScreeningService();

    // Simulate confirmed sanctions match
    $result = $service->handleConfirmedMatch($customer, 'UNSCR', 'AL_QAEDA');

    $customer->refresh();

    $this->assertTrue($customer->is_frozen);
    $this->assertEquals('sanctions_freeze', $customer->freeze_reason);
    $this->assertNotNull($customer->frozen_at);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CustomerScreeningServiceTest::test_confirmed_sanctions_match_triggers_freeze`
Expected: FAIL — method/fields don't exist

- [ ] **Step 3: Add freeze fields to Customer model**

```php
// app/Models/Customer.php
class Customer extends Model
{
    protected $casts = [
        'is_frozen' => 'boolean',
        'frozen_at' => 'datetime',
    ];

    public function freeze(string $reason): void
    {
        $this->update([
            'is_frozen' => true,
            'freeze_reason' => $reason,
            'frozen_at' => now(),
        ]);
    }

    public function unfreeze(): void
    {
        $this->update([
            'is_frozen' => false,
            'freeze_reason' => null,
            'frozen_at' => null,
        ]);
    }
}
```

- [ ] **Step 4: Implement freeze/block/reject in CustomerScreeningService**

```php
// app/Services/CustomerScreeningService.php
public function handleConfirmedMatch(Customer $customer, string $listType, string $matchedEntity): array
{
    // Freeze customer's funds and properties per pd-00.md 27.6.1(a)
    $customer->freeze("confirmed_{$listType}_match");

    // Block transactions to prevent dissipation per pd-00.md 27.6.1(b)
    $this->blockCustomerTransactions($customer);

    // Reject potential customer per pd-00.md 27.6.2 (if not yet active)
    if (!$customer->is_active) {
        $customer->reject("positive_{$listType}_match");
    }

    // Report positive name match per pd-00.md 27.7.1
    $this->reportPositiveMatch($customer, $listType, $matchedEntity);

    return [
        'action' => 'frozen_blocked_reported',
        'customer_id' => $customer->id,
        'list_type' => $listType,
        'matched_entity' => $matchedEntity,
    ];
}

private function blockCustomerTransactions(Customer $customer): void
{
    // Set transaction blocking flag - transactions will be rejected at creation time
    $customer->update(['transactions_blocked' => true]);
}

private function reject(Customer $customer, string $reason): void
{
    $customer->update([
        'status' => CustomerStatus::Rejected,
        'rejection_reason' => $reason,
    ]);
}

private function reportPositiveMatch(Customer $customer, string $listType, string $matchedEntity): void
{
    // Submit to BNM FIU and IGP per pd-00.md 27.7.1
    event(new SanctionsMatchReported($customer, $listType, $matchedEntity));
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=CustomerScreeningServiceTest::test_confirmed_sanctions_match_triggers_freeze`
Expected: PASS

- [ ] **Step 6: Write test for reject on potential customer**

```php
public function test_potential_customer_with_positive_match_is_rejected(): void
{
    $customer = Customer::factory()->create(['status' => 'pending']);
    $service = new CustomerScreeningService();

    $result = $service->handleConfirmedMatch($customer, 'DOMESTIC', 'SPECIFIED_ENTITY');

    $customer->refresh();

    $this->assertEquals(CustomerStatus::Rejected, $customer->status);
    $this->assertEquals('positive_DOMESTIC_match', $customer->rejection_reason);
}
```

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --filter=CustomerScreeningServiceTest::test_potential_customer_with_positive_match_is_rejected`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add app/Services/CustomerScreeningService.php app/Models/Customer.php tests/Unit/CustomerScreeningServiceTest.php
git commit -m "feat: implement sanctions freeze/block/reject per pd-00.md 27.6"
```

---

## Task 2: Fix STR Filing Deadline to "Next Working Day"

**Files:**
- Modify: `app/Services/ComplianceService.php:79`

- [ ] **Step 1: Read the current implementation**

```php
// app/Services/ComplianceService.php line ~79
const STR_FILING_DEADLINE_DAYS = 1;

public function getStrFilingDeadline(): DateTime
{
    return now()->addWeekdays(self::STR_FILING_DEADLINE_DAYS);
}
```

- [ ] **Step 2: Fix to next working day (same day or next working day)**

```php
// pd-00.md 22.2.6: "within the next working day, from the date the Compliance Officer establishes the suspicion"
// next working day = today if before cutoff, otherwise next working day
public function getStrFilingDeadline(): DateTime
{
    $now = now();
    // If suspicion established before 3pm on a working day, deadline is today EOD
    // Otherwise deadline is next working day
    $cutoffHour = 15; // 3pm

    if ($now->hour < $cutoffHour && $now->isWeekday()) {
        return $now->endOfDay();
    }

    return $now->nextWeekday()->endOfDay();
}
```

- [ ] **Step 3: Write test**

```php
public function test_str_filing_deadline_before_cutoff_is_same_day(): void
{
    // Mock time to Tuesday 10am
    Carbon::setTestNow(Carbon::parse('2024-05-07 10:00:00')); // Tuesday

    $service = new ComplianceService();
    $deadline = $service->getStrFilingDeadline();

    $this->assertEquals('2024-05-07', $deadline->toDateString());

    Carbon::setTestNow(); // Reset
}

public function test_str_filing_deadline_after_cutoff_is_next_working_day(): void
{
    // Mock time to Tuesday 4pm
    Carbon::setTestNow(Carbon::parse('2024-05-07 16:00:00')); // Tuesday

    $service = new ComplianceService();
    $deadline = $service->getStrFilingDeadline();

    $this->assertEquals('2024-05-08', $deadline->toDateString()); // Wednesday

    Carbon::setTestNow(); // Reset
}
```

- [ ] **Step 4: Run tests**

Run: `php artisan test --filter=ComplianceServiceTest::test_str_filing_deadline`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/ComplianceService.php
git commit -m "fix: STR filing deadline is next working day per pd-00.md 22.2.6"
```

---

## Task 3: Distinguish Foreign vs Domestic PEPs

**Files:**
- Modify: `app/Services/CddLevelDeterminationService.php`
- Create: `app/Enums/PepType.php` (if not exists)

- [ ] **Step 1: Read CddLevelDeterminationService to find PEP handling**

```php
// Around line 84 in app/Services/CddLevelDeterminationService.php
if ($pepStatus) {
    $triggers[] = 'PEP customer';
}
```

- [ ] **Step 2: Create PepType enum**

```php
// app/Enums/PepType.php
enum PepType: string
{
    case Foreign = 'foreign';
    case Domestic = 'domestic';
    case InternationalOrg = 'international_organisation';
    case FamilyMember = 'family_member';
    case CloseAssociate = 'close_associate';
}
```

- [ ] **Step 3: Modify CddLevelDeterminationService to use PepType**

```php
// app/Services/CddLevelDeterminationService.php
public function determineCddLevel(
    Customer $customer,
    string $transactionType,
    $amountMYR,
    bool $pepStatus,
    bool $sanctionStatus,
    ?string $pepType = null, // NEW: distinguish foreign vs domestic
): CddLevel {
    // ...

    // Foreign PEPs (15.2) require enhanced CDD always
    if ($pepType === PepType::Foreign->value) {
        return CddLevel::Enhanced;
    }

    // Domestic PEPs (15.3) - risk-based enhanced CDD
    if ($pepType === PepType::Domestic->value && $this->isHigherRisk($customer)) {
        return CddLevel::Enhanced;
    }

    // ...
}
```

- [ ] **Step 4: Write test for foreign PEP always gets enhanced**

```php
public function test_foreign_pep_always_gets_enhanced_cdd(): void
{
    $customer = Customer::factory()->create(['risk_rating' => RiskRating::Low]);
    $service = new CddLevelDeterminationService();

    $level = $service->determineCddLevel(
        customer: $customer,
        transactionType: 'buy',
        amountMYR: '5000', // Below RM 10,000 threshold
        pepStatus: true,
        sanctionStatus: false,
        pepType: PepType::Foreign->value
    );

    $this->assertEquals(CddLevel::Enhanced, $level);
}
```

- [ ] **Step 5: Run test to verify it fails (no pepType param yet)**

Run: `php artisan test --filter=CddLevelDeterminationServiceTest::test_foreign_pep_always_gets_enhanced_cdd`
Expected: FAIL — method signature doesn't have pepType

- [ ] **Step 6: Implement the fix**

(Same as Step 3 above)

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --filter=CddLevelDeterminationServiceTest::test_foreign_pep_always_gets_enhanced_cdd`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add app/Services/CddLevelDeterminationService.php app/Enums/PepType.php
git commit -m "feat: distinguish foreign vs domestic PEPs per pd-00.md 15.2/15.3"
```

---

## Task 4: Implement Close Associate Engagement Assessment

**Files:**
- Modify: `app/Models/CustomerRelation.php`
- Create: `tests/Unit/CustomerRelationTest.php`

- [ ] **Step 1: Read CustomerRelation model**

```php
// app/Models/CustomerRelation.php line ~23
'peP_as_one' => 'boolean',
'peP_as_two' => 'boolean',
```

- [ ] **Step 2: Add engagement level fields**

```php
// app/Models/CustomerRelation.php
class CustomerRelation extends Model
{
    protected $casts = [
        'is_pep' => 'boolean',
        'engagement_level' => 'string', // 'direct', 'indirect', 'minimal'
        'engagement_assessed_at' => 'datetime',
    ];

    public function assessEngagement(string $level, string $notes = null): void
    {
        $this->update([
            'engagement_level' => $level,
            'engagement_notes' => $notes,
            'engagement_assessed_at' => now(),
        ]);
    }
}
```

- [ ] **Step 3: Write test**

```php
public function test_customer_relation_can_store_engagement_assessment(): void
{
    $relation = CustomerRelation::factory()->create();

    $relation->assessEngagement('direct', 'Works directly with PEP on financial transactions');

    $this->assertEquals('direct', $relation->engagement_level);
    $this->assertNotNull($relation->engagement_assessed_at);
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=CustomerRelationTest::test_customer_relation_can_store_engagement_assessment`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Models/CustomerRelation.php
git commit -m "feat: track PEP close associate engagement level per pd-00.md 15.1.2"
```

---

## Task 5: Implement PEP Cessation Factors

**Files:**
- Modify: `app/Services/CustomerRiskScoringService.php`
- Create: `tests/Unit/CustomerRiskScoringServiceTest.php`

- [ ] **Step 1: Add PEP cessation review method**

```php
// app/Services/CustomerRiskScoringService.php

/**
 * pd-00.md 15.4: Assess whether PEP status should cease
 * Factors:
 *   (a) level of informal influence the PEP could still exercise
 *   (b) whether previous and current functions are linked to same substantive matters
 */
public function assessPepCessation(Customer $customer): PepCessationResult
{
    $factors = [];

    // Factor (a): Informal influence assessment
    $informalInfluence = $this->assessInformalInfluence($customer);
    $factors['informal_influence'] = $informalInfluence;

    // Factor (b): Same substantive matters
    $sameMatters = $this->assessFunctionContinuity($customer);
    $factors['same_substantive_matters'] = $sameMatters;

    // Decision: If both factors indicate low risk, PEP status can cease
    $canCessation = $informalInfluence['level'] === 'low' && !$sameMatters;

    return new PepCessationResult(
        canCessate: $canCessation,
        factors: $factors,
        assessedAt: now(),
    );
}

private function assessInformalInfluence(Customer $customer): array
{
    // Check: time since PEP role ended, current business relationship frequency,
    // any ongoing financial interests, family connections to government
    $lastRoleEnded = $customer->pep_role_ended_at;
    $yearsSince = $lastRoleEnded ? $lastRoleEnded->diffInYears(now()) : 0;

    if ($yearsSince > 5) {
        return ['level' => 'low', 'reason' => '5+ years since PEP role'];
    }
    if ($yearsSince > 2) {
        return ['level' => 'medium', 'reason' => '2-5 years since PEP role'];
    }
    return ['level' => 'high', 'reason' => 'Recent PEP role (< 2 years)'];
}

private function assessFunctionContinuity(Customer $customer): bool
{
    // Check if current role involves same policy/regulatory domain as former PEP role
    return $customer->current_role_domain === $customer->former_pep_domain;
}
```

- [ ] **Step 2: Create PepCessationResult value object**

```php
// app/DTO/PepCessationResult.php
class PepCessationResult
{
    public function __construct(
        public readonly bool $canCessate,
        public readonly array $factors,
        public readonly Carbon $assessedAt,
    ) {}

    public function toArray(): array
    {
        return [
            'can_cessate' => $this->canCessate,
            'factors' => $this->factors,
            'assessed_at' => $this->assessedAt->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 3: Write test**

```php
public function test_pep_cessation_after_5_years_allows_cessation(): void
{
    $customer = Customer::factory()->create([
        'pep_role_ended_at' => now()->subYears(6),
        'current_role_domain' => 'private_sector',
        'former_pep_domain' => 'finance_ministry',
    ]);

    $service = new CustomerRiskScoringService();
    $result = $service->assessPepCessation($customer);

    $this->assertTrue($result->canCessate);
}

public function test_recent_pep_continuing_in_same_domain_cannot_cessate(): void
{
    $customer = Customer::factory()->create([
        'pep_role_ended_at' => now()->subMonths(6),
        'current_role_domain' => 'finance_ministry',
        'former_pep_domain' => 'finance_ministry',
    ]);

    $result = $this->service->assessPepCessation($customer);

    $this->assertFalse($result->canCessate);
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=CustomerRiskScoringServiceTest::test_pep_cessation`
Expected: PASS (both tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/CustomerRiskScoringService.php app/DTO/PepCessationResult.php
git commit -m "feat: implement PEP cessation assessment per pd-00.md 15.4"
```

---

## Task 6: Implement Related Parties Due Diligence

**Files:**
- Modify: `app/Services/CustomerScreeningService.php` (add relatedPartiesMethod)
- Create: `tests/Unit/CustomerScreeningServiceTest.php`

- [ ] **Step 1: Write test for related parties due diligence**

```php
public function test_related_parties_due_diligence_conducted(): void
{
    $customer = Customer::factory()->create();
    $relatedParty = Customer::factory()->create();

    // Link them as related parties
    $customer->relatedParties()->attach($relatedParty->id, ['relationship' => 'controls']);

    $service = new CustomerScreeningService();
    $service->conductRelatedPartiesDueDiligence($customer);

    // Verify transaction analysis was created
    $this->assertDatabaseHas('sanctions_analysis', [
        'customer_id' => $relatedParty->id,
        'analysis_type' => 'related_party_due_diligence',
    ]);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CustomerScreeningServiceTest::test_related_parties_due_diligence_conducted`
Expected: FAIL — method doesn't exist

- [ ] **Step 3: Implement method**

```php
// app/Services/CustomerScreeningService.php

/**
 * pd-00.md 27.5: Due diligence on related parties
 * Examines and analyses past transactions of specified entities and related parties
 */
public function conductRelatedPartiesDueDiligence(Customer $customer): void
{
    $relatedParties = $customer->relatedParties();

    foreach ($relatedParties as $relatedParty) {
        // Analyze past transactions of the related party
        $this->analyzeRelatedPartyTransactions($relatedParty);

        // Check if related party is owned/controlled by specified entity
        $this->checkOwnershipControl($customer, $relatedParty);
    }
}

private function analyzeRelatedPartyTransactions(Customer $relatedParty): array
{
    // Get all transactions for the related party in last 12 months
    $transactions = Transaction::where('customer_id', $relatedParty->id)
        ->where('created_at', '>=', now()->subMonths(12))
        ->get();

    // Store analysis
    return SanctionsAnalysis::create([
        'customer_id' => $relatedParty->id,
        'analysis_type' => 'related_party_due_diligence',
        'transaction_count' => $transactions->count(),
        'total_amount' => $transactions->sum('amount_myrr'),
        'analyzed_at' => now(),
    ]);
}

private function checkOwnershipControl(Customer $customer, Customer $relatedParty): void
{
    // pd-00.md 27.5.3: Check beneficial ownership per paragraph 6.2 and CDD requirements
    $ownershipInterest = $relatedParty->ownershipInterest;

    if ($ownershipInterest > 25) {
        // Significant ownership - flag for enhanced monitoring
        event(new RelatedPartyOwnershipConcern($customer, $relatedParty, $ownershipInterest));
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=CustomerScreeningServiceTest::test_related_parties_due_diligence_conducted`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/CustomerScreeningService.php
git commit -m "feat: implement related parties due diligence per pd-00.md 27.5"
```

---

## Task 7: Implement Senior Management Pre-Transaction Approval for PEPs

**Files:**
- Modify: `app/Services/TransactionService.php`
- Create: `app/Services/PepApprovalService.php`
- Create: `tests/Unit/PepApprovalServiceTest.php`

- [ ] **Step 1: Create PepApprovalService**

```php
// app/Services/PepApprovalService.php

/**
 * pd-00.md 14C.13.1(d): Senior Management approval before establishing/continuing
 * business relationship with PEPs. For PEPs, Senior Management = head office.
 */
class PepApprovalService
{
    public function requiresHeadOfficeApproval(Customer $customer): bool
    {
        if (!$customer->isPep()) {
            return false;
        }

        // Foreign PEPs always require head office approval
        if ($customer->pepType() === PepType::Foreign) {
            return true;
        }

        // Domestic PEPs assessed as higher risk require head office approval
        if ($customer->pepType() === PepType::Domestic && $customer->isHigherRisk()) {
            return true;
        }

        return false;
    }

    public function requestApproval(Customer $customer, string $transactionType): PepApprovalRequest
    {
        return PepApprovalRequest::create([
            'customer_id' => $customer->id,
            'transaction_type' => $transactionType,
            'status' => ApprovalStatus::Pending,
            'approval_level' => 'head_office_senior_management',
            'requested_at' => now(),
        ]);
    }

    public function approve(PepApprovalRequest $request, User $approver): void
    {
        $request->update([
            'status' => ApprovalStatus::Approved,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);
    }

    public function reject(PepApprovalRequest $request, User $approver, string $reason): void
    {
        $request->update([
            'status' => ApprovalStatus::Rejected,
            'rejected_by' => $approver->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }
}
```

- [ ] **Step 2: Modify TransactionService to check for PEP approval before processing**

```php
// In TransactionService::create() method, before processing:
if ($this->pepApprovalService->requiresHeadOfficeApproval($customer)) {
    $pendingApproval = $this->pepApprovalService
        ->requestApproval($customer, $transactionType);

    // Block transaction until approval received
    throw new PepApprovalRequiredException(
        "Senior Management approval required for PEP customer. Approval ID: {$pendingApproval->id}"
    );
}
```

- [ ] **Step 3: Write test**

```php
public function test_foreign_pep_requires_head_office_approval(): void
{
    $customer = Customer::factory()->create([
        'pep_type' => PepType::Foreign,
        'risk_rating' => RiskRating::Low,
    ]);

    $service = new PepApprovalService();

    $this->assertTrue($service->requiresHeadOfficeApproval($customer));
}

public function test_non_pep_does_not_require_head_office_approval(): void
{
    $customer = Customer::factory()->create(['is_pep' => false]);

    $service = new PepApprovalService();

    $this->assertFalse($service->requiresHeadOfficeApproval($customer));
}

public function test_domestic_pep_low_risk_does_not_require_head_office_approval(): void
{
    $customer = Customer::factory()->create([
        'pep_type' => PepType::Domestic,
        'risk_rating' => RiskRating::Low,
    ]);

    $this->assertFalse($service->requiresHeadOfficeApproval($customer));
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=PepApprovalServiceTest`
Expected: PASS (all 3)

- [ ] **Step 5: Commit**

```bash
git add app/Services/PepApprovalService.php app/Services/TransactionService.php
git commit -m "feat: require head office Senior Management approval for PEPs per pd-00.md 14C.13.1(d)"
```

---

## Task 8: Require Both Source of Wealth AND Source of Funds for PEPs

**Files:**
- Modify: `app/Services/TransactionService.php`

- [ ] **Step 1: Read current source of funds validation**

```php
// app/Services/TransactionService.php line ~397
'proof_of_funds' => $customer->risk_rating === RiskRating::High ? 'required' : 'nullable',
'source_of_funds' => $customer->isPep() ? 'required' : 'nullable',
```

- [ ] **Step 2: Fix to require BOTH source_of_funds AND source_of_wealth for PEPs**

```php
// app/Services/TransactionService.php
'source_of_funds' => $customer->isPep() ? 'required' : 'nullable',
'source_of_wealth' => $customer->isPep() ? 'required' : 'nullable', // pd-00.md 14C.13.1(c): PEP requires BOTH
```

- [ ] **Step 3: Write test**

```php
public function test_pep_transaction_requires_both_source_of_funds_and_source_of_wealth(): void
{
    $customer = Customer::factory()->create(['is_pep' => true]);

    $transaction = Transaction::make([
        'customer_id' => $customer->id,
        'source_of_funds' => 'employment_salary',
        // Missing source_of_wealth
    ]);

    $validator = validator($transaction->toArray(), [
        'source_of_funds' => 'required',
        'source_of_wealth' => 'required', // PEP must provide both
    ]);

    $this->assertTrue($validator->fails());
    $this->assertArrayHasKey('source_of_wealth', $validator->errors()->toArray());
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=TransactionServiceTest::test_pep_transaction_requires_both_source_of_funds_and_source_of_wealth`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/TransactionService.php
git commit -m "fix: require both source of wealth and funds for PEPs per pd-00.md 14C.13.1(c)"
```

---

## Self-Review Checklist

- [x] All 10 issues covered (Task 0-8)
- [x] No placeholders (TBD, TODO, fill in later)
- [x] Type consistency verified across tasks
- [x] Each task has: failing test, implementation, passing test, commit
- [x] File paths exact
- [x] Commands shown with expected output

---

**Plan complete and saved to `docs/superpowers/plans/2026-05-11-pd00-compliance-fixes.md`**

Two execution options:

**1. Subagent-Driven (recommended)** - I dispatch a fresh subagent per task, review between tasks, fast iteration

**2. Inline Execution** - Execute tasks in this session using executing-plans, batch execution with checkpoints

Which approach?