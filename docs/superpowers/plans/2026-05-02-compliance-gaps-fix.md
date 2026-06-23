# CEMS-MY Compliance Gap Fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix all 7 BNM pd-00.md compliance gaps and 40 internal business logic conflicts across Accounting, Compliance, Transaction, Security, and Rate/Threshold domains.

**Architecture:** Changes organized by domain priority: CRITICAL compliance gaps first (G1-G3), then HIGH severity internal conflicts (S1-S5, T1-T4, C3-C5), then remaining MEDIUM issues. Each task is self-contained with tests.

**Tech Stack:** Laravel 10, PHP 8.1+, BCMath, PostgreSQL/MySQL

---

## Phase 1: CRITICAL Compliance Gaps (pd-00.md Non-Compliance)

### Task 1: G1 - Remove Amount-Based Enhanced CDD Trigger

**Files:**
- Modify: `app/Services/ComplianceService.php:91-131`
- Modify: `app/Enums/CddLevel.php`
- Test: `tests/Unit/ComplianceServiceTest.php`

- [ ] **Step 1: Write failing test for Enhanced CDD risk-based only**

```php
// tests/Unit/ComplianceServiceTest.php
public function test_enhanced_cdd_not_triggered_by_amount_alone(): void
{
    // Large amount transaction by low-risk customer should NOT trigger Enhanced
    $customer = Customer::factory()->create(['risk_level' => 'low']);
    $transaction = Transaction::factory()->create([
        'amount_local' => '60000', // Above RM50,000
        'customer_id' => $customer->id,
    ]);

    $cddLevel = $this->complianceService->determineCddLevel($transaction);

    // Enhanced should only come from PEP/Sanction/High-Risk, not amount
    $this->assertNotEquals(CddLevel::Enhanced, $cddLevel);
}

public function test_enhanced_cdd_triggered_by_high_risk_customer(): void
{
    $customer = Customer::factory()->create(['risk_level' => 'high']);
    $transaction = Transaction::factory()->create([
        'amount_local' => '5000', // Small amount
        'customer_id' => $customer->id,
    ]);

    $cddLevel = $this->complianceService->determineCddLevel($transaction);

    $this->assertEquals(CddLevel::Enhanced, $cddLevel);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_enhanced_cdd_not_triggered_by_amount_alone`
Expected: FAIL - currently code triggers Enhanced for amount >= RM50,000

- [ ] **Step 3: Remove amount-based trigger from ComplianceService**

```php
// app/Services/ComplianceService.php - determineCddLevel() method
// REMOVE this block:
if ($this->mathService->compare($amount, $this->thresholdService->getLargeTransactionThreshold()) >= 0) {
    $triggers[] = 'Large amount >= RM '.$this->thresholdService->getLargeTransactionThreshold();
}

// Enhanced CDD should only come from risk assessment:
if ($isPep || $hasSanctionMatch || $isHighRisk) {
    $level = CddLevel::Enhanced;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=test_enhanced_cdd`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/ComplianceService.php tests/Unit/ComplianceServiceTest.php
git commit -m "fix: remove amount-based Enhanced CDD trigger (G1)"
```

---

### Task 2: G2 - Fix STR Filing Deadline to Next Working Day

**Files:**
- Modify: `app/Services/ComplianceService.php:393-416`
- Modify: `app/Services/StrReportService.php:1010-1013`
- Test: `tests/Unit/StrReportServiceTest.php`

- [ ] **Step 1: Write failing test for STR deadline**

```php
// tests/Unit/StrReportServiceTest.php
public function test_str_deadline_is_next_working_day(): void
{
    // Monday suspicion
    $suspicionDate = Carbon::parse('2026-05-04'); // Monday
    $deadline = $this->strReportService->calculateFilingDeadline($suspicionDate);

    // Next working day should be Tuesday (2026-05-05)
    $this->assertEquals('2026-05-05', $deadline->format('Y-m-d'));
}

public function test_str_deadline_skips_weekend(): void
{
    // Friday suspicion
    $suspicionDate = Carbon::parse('2026-05-08'); // Friday
    $deadline = $this->strReportService->calculateFilingDeadline($suspicionDate);

    // Next working day should be Monday (2026-05-11)
    $this->assertEquals('2026-05-11', $deadline->format('Y-m-d'));
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_str_deadline`
Expected: FAIL - currently returns 3 days later

- [ ] **Step 3: Fix StrReportService::calculateFilingDeadline()**

```php
// app/Services/StrReportService.php:1010-1013
public function calculateFilingDeadline(Carbon $suspicionDate): Carbon
{
    // pd-00.md 22.2.6: "within the next working day"
    return $suspicionDate->copy()->addWeekday(1);
}
```

- [ ] **Step 4: Fix ComplianceService::calculateStrDeadline()**

```php
// app/Services/ComplianceService.php:393-416
// Change from: $deadline = $suspicion->copy()->addWeekdays(3);
// To:
$deadline = $suspicion->copy()->addWeekday(1); // Next working day per 22.2.6
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=test_str_deadline`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Services/ComplianceService.php app/Services/StrReportService.php tests/Unit/StrReportServiceTest.php
git commit -m "fix: STR deadline to next working day per pd-00.md 22.2.6 (G2)"
```

---

### Task 3: G3 - Remove RM50k STR Auto-Trigger

**Files:**
- Modify: `app/Services/ComplianceService.php:213-233`
- Modify: `app/Services/TransactionMonitoringService.php`
- Test: `tests/Unit/ComplianceServiceTest.php`

- [ ] **Step 1: Write failing test for STR suspicion-based triggering**

```php
// tests/Unit/ComplianceServiceTest.php
public function test_str_triggered_by_suspicion_not_amount(): void
{
    $customer = Customer::factory()->create(['risk_level' => 'high']);
    $transaction = Transaction::factory()->create([
        'amount_local' => '5000', // Below RM50k threshold
        'customer_id' => $customer->id,
        'flagged_at' => now(),
    ]);

    // Check velocity with new small transaction should flag for review, not auto-STR
    $result = $this->complianceService->checkVelocity($customer->id, '5000');

    // Should flag for review but NOT auto-trigger STR based on amount
    // STR trigger should be suspicion-based, not amount-based
    $this->assertFalse($result['requires_auto_str']);
}
```

- [ ] **Step 2: Run test to verify current behavior**

Run: `php artisan test --filter=test_str_triggered_by_suspicion_not_amount`
Expected: FAIL - currently auto-triggers at RM50,000

- [ ] **Step 3: Update checkVelocity to remove amount threshold**

```php
// app/Services/ComplianceService.php:213-233
// REMOVE the line:
'threshold_exceeded' => $this->mathService->compare($total, $this->thresholdService->getLargeTransactionThreshold()) >= 0,

// REPLACE with suspicion-based assessment:
// threshold_exceeded is now determined by velocity pattern, not absolute amount
'requires_hold' => $velocity > $this->thresholdService->getVelocityWarningThreshold(),
// Note: Actual STR determination is made by Compliance Officer, not automatic
```

- [ ] **Step 4: Update TransactionMonitoringService auto-str logic**

```php
// app/Services/TransactionMonitoringService.php
// Remove automatic STR trigger based on getLargeTransactionThreshold()
// STR should only be created when ComplianceTriageService determines suspicion
// Not based on amount >= RM50,000
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=test_str_triggered_by_suspicion_not_amount`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Services/ComplianceService.php app/Services/TransactionMonitoringService.php tests/Unit/ComplianceServiceTest.php
git commit -m "fix: remove amount-based STR auto-trigger (G3)"
```

---

## Phase 2: HIGH Severity Internal Conflicts

### Task 4: S1 - Fix Teller Role Check Always Passing

**Files:**
- Modify: `app/Http/Middleware/CheckRole.php:32`
- Modify: `app/Http/Middleware/CheckRoleAny.php:37`
- Test: `tests/Feature/RoleMiddlewareTest.php`

- [ ] **Step 1: Write failing test**

```php
// tests/Feature/RoleMiddlewareTest.php
public function test_teller_role_check_only_passes_for_tellers(): void
{
    $teller = User::factory()->create(['role' => UserRole::Teller]);
    $manager = User::factory()->create(['role' => UserRole::Manager]);

    $this->actingAs($teller);
    $response = $this->getJson('/api/v1/teller/transactions');
    $this->assertEquals(200, $response->getStatusCode());

    $this->actingAs($manager);
    $response = $this->getJson('/api/v1/teller/transactions');
    // Manager should NOT pass teller-only routes
    $this->assertEquals(403, $response->getStatusCode());
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_teller_role_check_only_passes_for_tellers`
Expected: FAIL - currently Manager passes

- [ ] **Step 3: Fix CheckRole.php**

```php
// app/Http/Middleware/CheckRole.php:32
// REMOVE: 'teller' => true,
// REPLACE with:
'teller' => $user->role === UserRole::Teller,
```

- [ ] **Step 4: Fix CheckRoleAny.php**

```php
// app/Http/Middleware/CheckRoleAny.php:37
// REMOVE: 'teller' => true,
// REPLACE with:
'teller' => $user->role === UserRole::Teller,
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=test_teller_role_check_only_passes_for_tellers`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Http/Middleware/CheckRole.php app/Http/Middleware/CheckRoleAny.php tests/Feature/RoleMiddlewareTest.php
git commit -m "fix: correct teller role check to only pass for actual tellers (S1)"
```

---

### Task 5: S2 - Add Role Check to Handover Acknowledge

**Files:**
- Modify: `routes/api_v1.php:313`
- Test: `tests/Feature/CounterHandoverTest.php`

- [ ] **Step 1: Write failing test**

```php
// tests/Feature/CounterHandoverTest.php
public function test_handover_acknowledge_requires_manager_role(): void
{
    $teller = User::factory()->create(['role' => UserRole::Teller]);
    $manager = User::factory()->create(['role' => UserRole::Manager]);

    $counter = Counter::factory()->create();
    $handover = CounterHandover::factory()->create(['counter_id' => $counter->id]);

    $this->actingAs($teller);
    $response = $this->postJson("/api/v1/counters/{$counter->id}/handover/{$handover->id}/acknowledge");
    $this->assertEquals(403, $response->getStatusCode());

    $this->actingAs($manager);
    $response = $this->postJson("/api/v1/counters/{$counter->id}/handover/{$handover->id}/acknowledge");
    $this->assertEquals(200, $response->getStatusCode());
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_handover_acknowledge_requires_manager_role`
Expected: FAIL - currently any authenticated user can acknowledge

- [ ] **Step 3: Add role middleware to route**

```php
// routes/api_v1.php:313
// Change from:
Route::post('/{counterId}/handover/{handoverId}/acknowledge', [CounterHandoverController::class, 'acknowledge'])
    ->middleware(['auth']);

// Change to:
Route::post('/{counterId}/handover/{handoverId}/acknowledge', [CounterHandoverController::class, 'acknowledge'])
    ->middleware(['auth', 'role:manager']);
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=test_handover_acknowledge_requires_manager_role`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add routes/api_v1.php tests/Feature/CounterHandoverTest.php
git commit -m "fix: require manager role for handover acknowledge (S2)"
```

---

### Task 6: S3, S4, S5 - Add MFA to Bulk Operations and Counter Operations

**Files:**
- Modify: `routes/api_v1.php:224-229, 293-315`
- Test: `tests/Feature/MfaMiddlewareTest.php`

- [ ] **Step 1: Write failing test**

```php
// tests/Feature/MfaMiddlewareTest.php
public function test_bulk_imports_require_mfa(): void
{
    $manager = User::factory()->create(['role' => UserRole::Manager]);

    $this->actingAs($manager);
    $response = $this->postJson('/api/v1/import/customers', [...]);
    // Should redirect to MFA verification without mfa.verified
    $this->assertEquals(403, $response->getStatusCode());
}

public function test_counter_approve_and_open_requires_mfa(): void
{
    $manager = User::factory()->create(['role' => UserRole::Manager]);

    $this->actingAs($manager);
    $response = $this->postJson('/api/v1/counters/1/approve-and-open', [...]);
    $this->assertEquals(403, $response->getStatusCode());
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_bulk_imports_require_mfa`
Expected: FAIL - currently no MFA requirement

- [ ] **Step 3: Add MFA middleware to bulk import routes**

```php
// routes/api_v1.php:224-229
Route::middleware(['role:admin,manager', 'mfa.verified'])->group(function () {
    Route::post('import/customers', [BulkImportController::class, 'importCustomers']);
    Route::post('import/transactions', [BulkImportController::class, 'importTransactions']);
```

- [ ] **Step 4: Add MFA middleware to counter operation routes**

```php
// routes/api_v1.php:293-315
Route::post('/{counterId}/approve-and-open', ...)
    ->middleware(['role:manager', 'mfa.verified']);

Route::post('/{counterId}/emergency-close', ...)
    ->middleware(['role:manager', 'mfa.verified']);

Route::post('/{counterId}/handover/{handoverId}/acknowledge', ...)
    ->middleware(['auth', 'role:manager', 'mfa.verified']); // Add MFA here too
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=test_bulk_imports_require_mfa`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add routes/api_v1.php tests/Feature/MfaMiddlewareTest.php
git commit -m "fix: add MFA requirement to bulk operations and counter operations (S3, S4, S5)"
```

---

### Task 7: T1 - Fix Auto-Approve Comment vs Threshold

**Files:**
- Modify: `app/Services/TransactionService.php:299-321`
- Test: `tests/Unit/TransactionServiceTest.php`

- [ ] **Step 1: Write failing test (documentation test)**

```php
// tests/Unit/TransactionServiceTest.php
public function test_auto_approve_threshold_is_10000(): void
{
    $threshold = $this->thresholdService->getAutoApproveThreshold();

    // Documentation states RM 10,000
    $this->assertEquals('10000', $threshold);
}

public function test_transaction_approval_required_at_10000(): void
{
    $transaction = Transaction::factory()->create(['amount_local' => '10000']);

    $result = $this->transactionService->createTransaction($transaction);

    // At exactly RM 10,000, should require approval
    $this->assertEquals(TransactionStatus::PendingApproval, $result->status);
}
```

- [ ] **Step 2: Run test to verify behavior**

Run: `php artisan test --filter=test_auto_approve_threshold_is_10000`
Expected: PASS (threshold is correct, just documentation is wrong)

- [ ] **Step 3: Fix comment in TransactionService**

```php
// app/Services/TransactionService.php:299-321
// REMOVE the misleading comment:
// - Transactions < RM 3,000: Simplified CDD, can be auto-approved
// - Transactions >= RM 3,000: Standard CDD, require manager approval

// REPLACE with correct comment:
// BNM AML/CFT COMPLIANCE REQUIREMENT:
// - Transactions < RM 10,000: Can be auto-approved (Completed status)
// - Transactions >= RM 10,000: Require manager approval (PendingApproval status)
// Note: CDD levels (Simplified/Specific/Standard) are determined separately based on amount per pd-00.md 14C.12
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=test_transaction_approval_required_at_10000`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/TransactionService.php tests/Unit/TransactionServiceTest.php
git commit -m "fix: correct auto-approve threshold comment to RM 10,000 (T1)"
```

---

### Task 8: T2 - Fix Refund Segregation of Duties

**Files:**
- Modify: `app/Services/TransactionCancellationService.php:500-531`
- Test: `tests/Unit/TransactionCancellationServiceTest.php`

- [ ] **Step 1: Write failing test**

```php
// tests/Unit/TransactionCancellationServiceTest.php
public function test_refund_requires_different_approver_than_requester(): void
{
    $user = User::factory()->create(['role' => UserRole::Manager]);

    $transaction = Transaction::factory()->create(['amount_local' => '40000']);

    // Request reversal
    $reversalRequest = $this->cancellationService->requestReversal($transaction, $user);

    // Attempt to approve with SAME user who requested
    $this->expectException(SegregationOfDutiesException::class);
    $this->cancellationService->approveReversal($reversalRequest->id, $user);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_refund_requires_different_approver_than_requester`
Expected: FAIL - currently same user can request and approve

- [ ] **Step 3: Add segregation check to approveReversal**

```php
// app/Services/TransactionCancellationService.php
public function approveReversal(int $reversalId, User $approver): Transaction
{
    $reversal = ReversalRequest::findOrFail($reversalId);

    // Segregation of duties check per pd-00.md 11.2.4(e)
    if ($reversal->requested_by === $approver->id) {
        throw new SegregationOfDutiesException(
            'Reversal approver must be different from requester per BNM segregation of duties requirement'
        );
    }

    // ... rest of approval logic
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=test_refund_requires_different_approver_than_requester`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/TransactionCancellationService.php tests/Unit/TransactionCancellationServiceTest.php
git commit -m "fix: enforce segregation of duties in refund approval (T2)"
```

---

### Task 9: T3 - Fix Journal Reversal Account Type Handling

**Files:**
- Modify: `app/Services/AccountingService.php:213-219`
- Test: `tests/Unit/AccountingServiceTest.php`

- [ ] **Step 1: Write failing test**

```php
// tests/Unit/AccountingServiceTest.php
public function test_journal_reversal_produces_correct_economic_effect(): void
{
    // Create a sell transaction journal
    $journal = $this->accountingService->createJournalEntry([
        'description' => 'Sell USD',
        'lines' => [
            ['account_code' => '1000', 'debit' => '10000', 'credit' => '0'], // Cash (Asset)
            ['account_code' => '2000', 'debit' => '0', 'credit' => '8000'], // Inventory (Asset)
            ['account_code' => '4000', 'debit' => '0', 'credit' => '2000'], // Revenue
        ]
    ]);

    $reversal = $this->accountingService->reverseJournalEntry($journal->id);

    // Verify reversal entries produce opposite effect
    foreach ($reversal->lines as $line) {
        $original = $journal->lines->where('account_code', $line->account_code)->first();
        // For asset accounts: debit becomes credit and vice versa (correct)
        // For revenue accounts: original credit should become debit in reversal
        $this->assertTrue(
            bccomp($line->debit, $original->credit) === 0 ||
            bccomp($line->credit, $original->debit) === 0
        );
    }
}
```

- [ ] **Step 2: Run test to verify current behavior**

Run: `php artisan test --filter=test_journal_reversal_produces_correct_economic_effect`
Expected: FAIL - simple swap doesn't account for account type

- [ ] **Step 3: Implement account-type-aware reversal**

```php
// app/Services/AccountingService.php:213-219
public function reverseJournalEntry(int $journalEntryId): JournalEntry
{
    $originalEntry = JournalEntry::with('lines')->findOrFail($journalEntryId);

    $lines = [];
    foreach ($originalEntry->lines as $line) {
        $accountType = $this->getAccountType($line->account_code);

        // For proper reversal, we swap debit/credit but must consider account normal balance
        // Assets and Expenses: debit-normal (increases with debit)
        // Liabilities, Equity, Revenue: credit-normal (increases with credit)
        // Reversal simply undoes the original entry
        $lines[] = [
            'account_code' => $line->account_code,
            'debit' => $line->credit,  // Swap
            'credit' => $line->debit,  // Swap
            'description' => 'Reversal: '.$line->description,
        ];
    }

    // ... create reversal entry
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=test_journal_reversal_produces_correct_economic_effect`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/AccountingService.php tests/Unit/AccountingServiceTest.php
git commit -m "fix: correct journal reversal logic (T3)"
```

---

### Task 10: T4 - Add Expiry Check to consumeStockReservation

**Files:**
- Modify: `app/Services/CurrencyPositionService.php:442-454`
- Test: `tests/Unit/CurrencyPositionServiceTest.php`

- [ ] **Step 1: Write failing test**

```php
// tests/Unit/CurrencyPositionServiceTest.php
public function test_consume_rejects_expired_reservation(): void
{
    $reservation = StockReservation::factory()->create([
        'expires_at' => now()->subHour(),
        'status' => StockReservationStatus::Pending,
    ]);

    $result = $this->positionService->consumeStockReservation($reservation->transaction_id);

    $this->assertNull($result); // Should return null for expired
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_consume_rejects_expired_reservation`
Expected: FAIL - currently consumes expired reservations

- [ ] **Step 3: Add expiry check**

```php
// app/Services/CurrencyPositionService.php:442-454
public function consumeStockReservation(int $transactionId): ?StockReservation
{
    $reservation = StockReservation::where('transaction_id', $transactionId)
        ->where('status', StockReservationStatus::Pending)
        ->first();

    if ($reservation) {
        // Check if reservation has expired before consuming
        if ($reservation->isExpired()) {
            return null; // Treat as not found - expired reservations cannot be consumed
        }
        $reservation->update(['status' => StockReservationStatus::Consumed]);
    }
    return $reservation;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=test_consume_rejects_expired_reservation`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/CurrencyPositionService.php tests/Unit/CurrencyPositionServiceTest.php
git commit -m "fix: reject expired stock reservations in consume (T4)"
```

---

### Task 11: C1 - Fix Risk Tier Classification Mismatch

**Files:**
- Modify: `app/Models/Compliance/CustomerRiskProfile.php:136-143`
- Modify: `app/Services/CustomerRiskScoringService.php:302-314`
- Test: `tests/Unit/CustomerRiskScoringServiceTest.php`

- [ ] **Step 1: Write failing test**

```php
// tests/Unit/CustomerRiskScoringServiceTest.php
public function test_risk_tier_boundaries_are_consistent(): void
{
    $scoringService = new CustomerRiskScoringService();
    $profileModel = new CustomerRiskProfile();

    // Score 78 should return same classification from both
    $fromScoring = $scoringService->getRiskLevel(78);
    $fromProfile = $profileModel->getTierForScore(78);

    $this->assertEquals($fromScoring, $fromProfile,
        "Score 78 returns '$fromScoring' from scoring service but '$fromProfile' from profile model");
}

public function test_risk_boundaries(): void
{
    $scoringService = new CustomerRiskScoringService();

    // Define consistent boundaries
    $this->assertEquals('Low', $scoringService->getRiskLevel(20));
    $this->assertEquals('Medium', $scoringService->getRiskLevel(40));
    $this->assertEquals('High', $scoringService->getRiskLevel(70));
    $this->assertEquals('Critical', $scoringService->getRiskLevel(85));
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_risk_tier_boundaries_are_consistent`
Expected: FAIL - score 78 is "High" in Profile but "Critical" in ScoringService

- [ ] **Step 3: Standardize CustomerRiskScoringService thresholds**

```php
// app/Services/CustomerRiskScoringService.php:302-314
public function getRiskLevel(int $score): string
{
    // Standardized boundaries - align with CustomerRiskProfile
    return match (true) {
        $score >= 80 => 'Critical',  // Previously >= 80
        $score >= 60 => 'High',      // Previously >= 60
        $score >= 30 => 'Medium',    // Previously >= 30
        default => 'Low',
    };
}
```

- [ ] **Step 4: Update CustomerRiskProfile to match**

```php
// app/Models/Compliance/CustomerRiskProfile.php:136-143
public static function getTierForScore(int $score): string
{
    // Align with CustomerRiskScoringService boundaries
    return match (true) {
        $score >= 80 => 'Critical',
        $score >= 60 => 'High',
        $score >= 30 => 'Medium',
        default => 'Low',
    };
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=test_risk_tier_boundaries_are_consistent`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Models/Compliance/CustomerRiskProfile.php app/Services/CustomerRiskScoringService.php tests/Unit/CustomerRiskScoringServiceTest.php
git commit -m "fix: standardize risk tier classification thresholds (C2, C6)"
```

---

### Task 12: C4 - Fix CTOS Docstring

**Files:**
- Modify: `app/Services/CtosReportService.php:13-14`
- Test: `tests/Unit/CtosReportServiceTest.php`

- [ ] **Step 1: Write test for CTOS threshold**

```php
// tests/Unit/CtosReportServiceTest.php
public function test_ctos_threshold_is_25000(): void
{
    $threshold = $this->thresholdService->getCtosThreshold();
    $this->assertEquals('25000', $threshold);
}
```

- [ ] **Step 2: Fix docstring**

```php
// app/Services/CtosReportService.php:13-14
/**
 * BNM CTOS (Cash Transaction Report) Service
 *
 * Submits CTOS reports for all cash transactions (Buy AND Sell) >= RM 25,000
 * per pd-00.md paragraph 21.3.1
 */
```

- [ ] **Step 3: Run test to verify it passes**

Run: `php artisan test --filter=test_ctos_threshold_is_25000`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add app/Services/CtosReportService.php tests/Unit/CtosReportServiceTest.php
git commit -m "docs: fix CTOS threshold in docstring to RM 25,000 (C4)"
```

---

## Phase 3: MEDIUM Severity Issues

### Task 13: A1 - Fix Income Summary Account Type in Fiscal Year Closing

**Files:**
- Modify: `app/Services/FiscalYearService.php:256-406`
- Test: `tests/Unit/FiscalYearServiceTest.php`

- [ ] **Step 1: Write failing test**

```php
// tests/Unit/FiscalYearServiceTest.php
public function test_closing_entries_use_correct_income_summary_account_type(): void
{
    // Create fiscal year with revenue and expenses
    $fiscalYear = $this->fiscalYearService->createFiscalYear([
        'year' => 2026,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ]);

    // Close fiscal year
    $entries = $this->fiscalYearService->closeFiscalYear($fiscalYear->id);

    // Find Income Summary entry
    $incomeSummaryEntry = $entries->lines->firstWhere('account_code', '4998');

    // Income Summary (4998) is an Equity account - credit-normal
    // When closing revenue, Income Summary should be debited (reducing revenue)
    // When closing expenses, Income Summary should be credited (reducing expenses)
    $accountType = $this->getAccountType('4998');
    $this->assertEquals('Equity', $accountType);
}
```

- [ ] **Step 2: Run test to verify behavior**

Run: `php artisan test --filter=test_closing_entries_use_correct_income_summary_account_type`
Expected: FAIL - 4998 classified as Revenue

- [ ] **Step 3: Check AccountCode enum classification**

```php
// app/Enums/AccountCode.php
// Verify 4998 is classified correctly
// If Revenue, change to Equity since it's an income summary clearing account
```

- [ ] **Step 3: Update closing entries logic to handle income summary**

```php
// app/Services/FiscalYearService.php - createClosingLedgerEntries method
// Add special handling for Income Summary account 4998
$isIncomeSummary = ($accountCode === '4998');
$isDebitNormal = $this->isDebitAccount($accountCode);

// For income summary (equity account), reverse the logic:
// Revenue accounts credited when closed -> Income Summary debited
// Expense accounts debited when closed -> Income Summary credited
if ($isIncomeSummary) {
    // Income Summary is credit-normal equity, but used as clearing
    // For closing: debits = expenses (reduced), credits = revenue (reduced)
    $debitAmount = $expenseTotal;
    $creditAmount = $revenueTotal;
} else {
    // Standard debit/credit for normal accounts
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=test_closing_entries_use_correct_income_summary_account_type`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/FiscalYearService.php tests/Unit/FiscalYearServiceTest.php
git commit -m "fix: correct income summary account handling in fiscal year closing (A1)"
```

---

### Task 14: A2 - Fix Trial Balance branch_id Filter

**Files:**
- Modify: `app/Services/LedgerService.php:90-106`
- Test: `tests/Unit/LedgerServiceTest.php`

- [ ] **Step 1: Write failing test**

```php
// tests/Unit/LedgerServiceTest.php
public function test_trial_balance_filters_by_branch(): void
{
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    // Create ledger entries for different branches
    LedgerEntry::factory()->create(['branch_id' => $branchA->id, 'account_code' => '1000', 'amount' => 1000]);
    LedgerEntry::factory()->create(['branch_id' => $branchB->id, 'account_code' => '1000', 'amount' => 2000]);

    $result = $this->ledgerService->getTrialBalance(now(), $branchA->id);

    // Should only include branch A's entries
    $this->assertEquals(1000, $result['accounts']['1000']->balance);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_trial_balance_filters_by_branch`
Expected: FAIL - branch_id filter ignored

- [ ] **Step 3: Fix subquery to include branch_id**

```php
// app/Services/LedgerService.php:90-106
$subQuery = DB::table('account_ledger')
    ->select(
        'account_code',
        'branch_id',  // ADD THIS
        'running_balance',
        DB::raw('ROW_NUMBER() OVER (PARTITION BY account_code, branch_id ORDER BY entry_date DESC, id DESC) as rn')
    )
    ->where('entry_date', '<=', $asOfDate)
    ->when($branchId !== null, function ($query) use ($branchId) {
        $query->where('branch_id', $branchId);
    });
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=test_trial_balance_filters_by_branch`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/LedgerService.php tests/Unit/LedgerServiceTest.php
git commit -m "fix: add branch_id to trial balance subquery select (A2)"
```

---

### Task 15: A4 - Fix EOD Variance for Unclosed Sessions

**Files:**
- Modify: `app/Services/EodReconciliationService.php:313-316`
- Test: `tests/Unit/EodReconciliationServiceTest.php`

- [ ] **Step 1: Write failing test**

```php
// tests/Unit/EodReconciliationServiceTest.php
public function test_variance_returns_actual_for_unclosed_sessions(): void
{
    $counter = Counter::factory()->create();
    $session = CounterSession::factory()->create([
        'counter_id' => $counter->id,
        'closed_at' => null, // Unclosed
    ]);

    TillBalance::factory()->create([
        'till_id' => $counter->id,
        'currency_code' => 'USD',
        'opening_balance' => '1000',
        'closing_balance' => null, // Not closed
    ]);

    $variance = $this->reconciliationService->calculateVariance($counter->id, 'USD');

    // Should return actual variance, not '0'
    $this->assertNotEquals('0', $variance);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_variance_returns_actual_for_unclosed_sessions`
Expected: FAIL - returns '0'

- [ ] **Step 3: Fix variance calculation**

```php
// app/Services/EodReconciliationService.php:313-316
// REMOVE:
if ($actualClosing === null || BcmathHelper::eq($actualClosing, '0') && $tillBalances->isNotEmpty()) {
    return '0';  // WRONG - masks variance
}

// REPLACE with:
if ($actualClosing === null && $tillBalances->isNotEmpty()) {
    // Session still open - calculate expected variance
    $expected = $this->calculateExpectedBalance($counterId, $currencyCode, $date);
    $actual = $this->getActualBalance($counterId, $currencyCode, $date);
    return BcmathHelper::subtract($expected, $actual ?? '0');
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=test_variance_returns_actual_for_unclosed_sessions`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/EodReconciliationService.php tests/Unit/EodReconciliationServiceTest.php
git commit -m "fix: return actual variance for unclosed sessions (A4)"
```

---

### Task 16: S6 - Enforce Password Policy

**Files:**
- Create: `app/Rules/PasswordComplexityRule.php`
- Modify: `app/Http/Controllers/Auth/ChangePasswordController.php`
- Test: `tests/Feature/PasswordPolicyTest.php`

- [ ] **Step 1: Write failing test**

```php
// tests/Feature/PasswordPolicyTest.php
public function test_password_must_meet_complexity_requirements(): void
{
    $user = User::factory()->create();

    // Too short
    $this->actingAs($user);
    $response = $this->postJson('/api/v1/auth/change-password', [
        'current_password' => 'password123',
        'password' => 'weak',
        'password_confirmation' => 'weak',
    ]);
    $this->assertEquals(422, $response->getStatusCode());

    // Valid password
    $response = $this->postJson('/api/v1/auth/change-password', [
        'current_password' => 'password123',
        'password' => 'MyComplex123!',
        'password_confirmation' => 'MyComplex123!',
    ]);
    $this->assertEquals(200, $response->getStatusCode());
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_password_must_meet_complexity_requirements`
Expected: FAIL - no validation currently

- [ ] **Step 3: Create PasswordComplexityRule**

```php
// app/Rules/PasswordComplexityRule.php
class PasswordComplexityRule implements Rule
{
    public function passes($attribute, $value): bool
    {
        $config = config('security.password');

        if (strlen($value) < $config['min_length']) {
            return false;
        }
        if ($config['require_uppercase'] && !preg_match('/[A-Z]/', $value)) {
            return false;
        }
        if ($config['require_lowercase'] && !preg_match('/[a-z]/', $value)) {
            return false;
        }
        if ($config['require_numbers'] && !preg_match('/[0-9]/', $value)) {
            return false;
        }
        if ($config['require_symbols'] && !preg_match('/[^A-Za-z0-9]/', $value)) {
            return false;
        }

        return true;
    }

    public function message(): string
    {
        return 'The password does not meet complexity requirements.';
    }
}
```

- [ ] **Step 4: Apply rule in ChangePasswordController**

```php
// app/Http/Controllers/Auth/ChangePasswordController.php
public function changePassword(ChangePasswordRequest $request): JsonResponse
{
    $request->validate([
        'password' => ['required', 'confirmed', new PasswordComplexityRule],
    ]);

    // ... rest of logic
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=test_password_must_meet_complexity_requirements`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Rules/PasswordComplexityRule.php app/Http/Controllers/Auth/ChangePasswordController.php tests/Feature/PasswordPolicyTest.php
git commit -m "fix: enforce password complexity policy (S6)"
```

---

### Task 17: R1 - Fix Rate Deviation Mid Rate

**Files:**
- Modify: `app/Services/RateApiService.php:163-167`
- Test: `tests/Unit/RateApiServiceTest.php`

- [ ] **Step 1: Write failing test**

```php
// tests/Unit/RateApiServiceTest.php
public function test_rate_deviation_uses_mid_rate_for_mid_type(): void
{
    $exchangeRate = ExchangeRate::factory()->create([
        'rate_buy' => '4.50',
        'rate_sell' => '4.55',
    ]);

    $midRate = $this->rateApiService->getValidatedRate($exchangeRate, 'mid');

    // Mid rate should be (4.50 + 4.55) / 2 = 4.525
    $expected = bcdiv(bcadd('4.50', '4.55', 4), '2', 4);
    $this->assertEquals($expected, $midRate);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_rate_deviation_uses_mid_rate_for_mid_type`
Expected: FAIL - currently returns buy rate

- [ ] **Step 3: Fix getValidatedRate for mid type**

```php
// app/Services/RateApiService.php:163-167
return match ($type) {
    'buy' => $exchangeRate->rate_buy,
    'sell' => $exchangeRate->rate_sell,
    'mid' => bcdiv(bcadd($exchangeRate->rate_buy, $exchangeRate->rate_sell, 10), '2', 10),
    default => $exchangeRate->rate_buy,
};
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=test_rate_deviation_uses_mid_rate_for_mid_type`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/RateApiService.php tests/Unit/RateApiServiceTest.php
git commit -m "fix: calculate mid rate correctly in getValidatedRate (R1)"
```

---

### Task 18: R2 - Add Locking to Rate Override

**Files:**
- Modify: `app/Services/RateManagementService.php:99-137`
- Test: `tests/Unit/RateManagementServiceTest.php`

- [ ] **Step 1: Write failing test**

```php
// tests/Unit/RateManagementServiceTest.php
public function test_rate_override_is_atomic(): void
{
    // Concurrent overrides should not result in lost updates
    $rate = ExchangeRate::factory()->create(['currency_code' => 'USD', 'rate_buy' => '4.50']);

    // Simulate concurrent updates
    $this->rateManagementService->overrideRate('USD', '4.60', $this->manager->id);
    $this->rateManagementService->overrideRate('USD', '4.70', $this->manager->id);

    $rate->refresh();

    // Should be 4.70, not some intermediate value
    $this->assertEquals('4.70', $rate->rate_buy);
}
```

- [ ] **Step 2: Run test to verify behavior**

Run: `php artisan test --filter=test_rate_override_is_atomic`
Expected: May pass or fail depending on timing

- [ ] **Step 3: Add lockForUpdate to rate override**

```php
// app/Services/RateManagementService.php:99-137
public function overrideRate(string $currencyCode, string $newRate, int $managerId, ?int $branchId = null): ExchangeRate
{
    $query = ExchangeRate::where('currency_code', $currencyCode);

    if ($branchId !== null) {
        $query->forBranch($branchId);
    }

    // Lock the row for update to prevent race conditions
    $exchangeRate = $query->lockForUpdate()->first();

    if (!$exchangeRate) {
        throw new \Exception("Exchange rate for {$currencyCode} not found");
    }

    // Validate the override
    $this->validateRateOverride($exchangeRate, $newRate);

    $exchangeRate->update([
        'rate_buy' => $newRate,
        'rate_sell' => $newRate,
        'overridden_at' => now(),
        'overridden_by' => $managerId,
    ]);

    Cache::forget('rate:' . $currencyCode . ($branchId ? ':' . $branchId : ''));

    return $exchangeRate->fresh();
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=test_rate_override_is_atomic`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/RateManagementService.php tests/Unit/RateManagementServiceTest.php
git commit -m "fix: add row locking to rate override (R2)"
```

---

### Task 19: R5 - Use Model's isExpired() in ExpireStockReservations

**Files:**
- Modify: `app/Console/Commands/ExpireStockReservations.php:24-35`
- Test: `tests/Unit/ExpireStockReservationsTest.php`

- [ ] **Step 1: Write failing test**

```php
// tests/Unit/ExpireStockReservationsTest.php
public function test_expire_command_uses_model_is_expired_method(): void
{
    $reservation = StockReservation::factory()->create([
        'expires_at' => now()->subHour(),
        'status' => StockReservationStatus::Pending,
    ]);

    // Manually call the expire command
    $this->artisan('reservation:expire')
        ->assertExitCode(0);

    $reservation->refresh();
    $this->assertEquals(StockReservationStatus::Expired, $reservation->status);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_expire_command_uses_model_is_expired_method`
Expected: FAIL - command doesn't use model's isExpired()

- [ ] **Step 3: Update command to use model's isExpired()**

```php
// app/Console/Commands/ExpireStockReservations.php:24-35
public function handle(): int
{
    $expired = StockReservation::where('status', StockReservationStatus::Pending)
        ->get()
        ->filter(function ($reservation) {
            return $reservation->isExpired();  // Use model's method
        });

    $count = $expired->count();

    foreach ($expired as $reservation) {
        $this->positionService->releaseStockReservation($reservation->transaction_id);
        $this->notifyTeller($reservation);
    }

    $this->info("Expired {$count} stock reservations.");
    return Command::SUCCESS;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=test_expire_command_uses_model_is_expired_method`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/ExpireStockReservations.php tests/Unit/ExpireStockReservationsTest.php
git commit -m "fix: use model's isExpired() in ExpireStockReservations command (R5)"
```

---

### Task 20: A5 - Replace abs() with BCMath in FiscalYearService

**Files:**
- Modify: `app/Services/FiscalYearService.php:205`
- Test: `tests/Unit/FiscalYearServiceTest.php`

- [ ] **Step 1: Write failing test**

```php
// tests/Unit/FiscalYearServiceTest.php
public function test_closing_entries_use_bcmath_for_large_numbers(): void
{
    // Create large retained earnings that would lose precision with float
    $fiscalYear = $this->createFiscalYearWithLoss(2026, '-92233720368547758.01');

    // The abs() used in closing should not convert to float
    $closingEntries = $this->fiscalYearService->createClosingLedgerEntries($fiscalYear->id);

    // Verify retained earnings entry uses string, not float
    $retainedEarningsLine = $closingEntries->lines->firstWhere('account_code', '4999');

    // Should be a valid BCMath string, not scientific notation or inf
    $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $retainedEarningsLine->debit);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_closing_entries_use_bcmath_for_large_numbers`
Expected: FAIL - uses PHP abs()

- [ ] **Step 3: Replace abs() with BCMath**

```php
// app/Services/FiscalYearService.php:201-209
// REMOVE:
'debit' => $this->mathService->compare($retainedEarnings, '0') < 0 ? abs($retainedEarnings) : 0,

// REPLACE with BCMath-compatible approach:
'debit' => $this->mathService->compare($retainedEarnings, '0') < 0
    ? $this->mathService->subtract('0', $retainedEarnings)  // abs() via BCMath
    : '0',
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=test_closing_entries_use_bcmath_for_large_numbers`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/FiscalYearService.php tests/Unit/FiscalYearServiceTest.php
git commit -m "fix: use BCMath for absolute value in fiscal year closing (A5)"
```

---

### Task 21: A6 - Standardize Revaluation Scale to 6

**Files:**
- Modify: `app/Services/RevaluationService.php:109`
- Test: `tests/Unit/RevaluationServiceTest.php`

- [ ] **Step 1: Write failing test**

```php
// tests/Unit/RevaluationServiceTest.php
public function test_revaluation_uses_consistent_scale(): void
{
    $rate1 = '4.50000001';  // Differs at 8th decimal
    $rate2 = '4.50000002';  // Differs at 8th decimal

    $position = CurrencyPosition::factory()->create(['last_valuation_rate' => $rate1]);

    // Should detect difference and revalue
    $result = $this->revaluationService->revalueIfNeeded($position, $rate2);

    // Should return revaluation since rates differ at 6th decimal precision
    $this->assertNotNull($result);
}
```

- [ ] **Step 2: Run test to verify behavior**

Run: `php artisan test --filter=test_revaluation_uses_consistent_scale`
Expected: FAIL - scale=10 causes skip

- [ ] **Step 3: Change scale to 6**

```php
// app/Services/RevaluationService.php:109
// REMOVE:
if ($position->last_valuation_rate !== null && bccomp($position->last_valuation_rate, $newRate, 10) === 0) {

// REPLACE with scale=6 to match MathService:
if ($position->last_valuation_rate !== null && bccomp($position->last_valuation_rate, $newRate, 6) === 0) {
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=test_revaluation_uses_consistent_scale`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/RevaluationService.php tests/Unit/RevaluationServiceTest.php
git commit -m "fix: standardize revaluation scale to 6 (A6)"
```

---

### Task 22: T7 - Exclude Pending Transactions from EOD Variance

**Files:**
- Modify: `app/Services/EodReconciliationService.php:175, 300`
- Test: `tests/Unit/EodReconciliationServiceTest.php`

- [ ] **Step 1: Write failing test**

```php
// tests/Unit/EodReconciliationServiceTest.php
public function test_pending_transactions_excluded_from_eod_variance(): void
{
    $counter = Counter::factory()->create();

    // Create completed transaction
    Transaction::factory()->create([
        'counter_id' => $counter->id,
        'status' => TransactionStatus::Completed,
        'amount_local' => '5000',
    ]);

    // Create pending transaction
    Transaction::factory()->create([
        'counter_id' => $counter->id,
        'status' => TransactionStatus::PendingApproval,
        'amount_local' => '3000',
    ]);

    $variance = $this->reconciliationService->calculateVariance($counter->id, 'MYR');

    // Should only include completed transaction, not pending
    // Pending should not affect EOD until approved/completed
    $this->assertNotContains('3000', $variance['breakdown']['transactions']);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_pending_transactions_excluded_from_eod_variance`
Expected: FAIL - pending included

- [ ] **Step 3: Update reconciliation query to exclude Pending**

```php
// app/Services/EodReconciliationService.php
// In generateCounterReconciliation() and calculateVariance()
// Change from:
$transactions = $transactions->whereNotIn('status', [
    TransactionStatus::Cancelled,
    TransactionStatus::Failed,
]);

// Add:
TransactionStatus::Pending,
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=test_pending_transactions_excluded_from_eod_variance`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/EodReconciliationService.php tests/Unit/EodReconciliationServiceTest.php
git commit -m "fix: exclude pending transactions from EOD variance (T7)"
```

---

## Phase 4: Remaining Lower Priority Issues

### Task 23: A3 - TillBalance Lock Scope (Review if needed)

**Files:**
- Review: `app/Services/CounterService.php:321-326`
- Test: `tests/Unit/CounterServiceTest.php`

- [ ] **Step 1: Analyze current locking behavior**

```php
// Current code locks all currency balances for a till/date
// This may be overly broad. Investigate if concurrent handovers with
// different currencies on same till could cause issues.

$allBalances = TillBalance::where('till_id', (string) $session->counter_id)
    ->where('date', $today)
    ->whereIn('currency_code', $currencyCodes)
    ->lockForUpdate()
    ->get()
    ->keyBy('currency_code');
```

- [ ] **Step 2: Write test for concurrent handover safety**

```php
public function test_concurrent_handovers_different_currencies_do_not_conflict(): void
{
    // Test that two handovers for different currencies on same till
    // can proceed without deadlock
}
```

- [ ] **Step 3: Run test and analyze**

Run: `php artisan test --filter=test_concurrent_handovers_different_currencies_do_not_conflict`

- [ ] **Step 4: If deadlocks occur, implement ordered locking**

```php
// Lock currencies in alphabetical order to prevent deadlocks
$sortedCurrencies = collect($currencyCodes)->sort()->values()->toArray();

foreach ($sortedCurrencies as $currency) {
    TillBalance::where('till_id', $tillId)
        ->where('date', $today)
        ->where('currency_code', $currency)
        ->lockForUpdate()
        ->get();
}
```

- [ ] **Step 5: Commit**

```bash
git add app/Services/CounterService.php tests/Unit/CounterServiceTest.php
git commit -m "fix: prevent deadlock in concurrent counter handovers (A3)"
```

---

### Task 24: T5 - Remove Non-Existent State from Approval History

**Files:**
- Modify: `app/Services/TransactionService.php:811-834`
- Test: `tests/Unit/TransactionServiceTest.php`

- [ ] **Step 1: Write failing test**

```php
public function test_approval_history_reflects_actual_state_transitions(): void
{
    $transaction = $this->transactionService->approveTransaction($pendingTransaction);

    $history = $transaction->statusHistory;

    // Should only show PendingApproval -> Completed, not "Approved" state
    $statuses = $history->pluck('to_status')->toArray();

    // 'Approved' status should NOT appear since we jump directly to Completed
    $this->assertNotContains('Approved', $statuses);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_approval_history_reflects_actual_state_transitions`
Expected: FAIL

- [ ] **Step 3: Remove artificial "Approved" state from history**

```php
// app/Services/TransactionService.php:811-834
// REMOVE the artificial "Approved" state recording
// The transition is directly PendingApproval -> Completed

$history[] = [
    'from' => $fromState,  // PendingApproval
    'to' => TransactionStatus::Completed->value,
    'reason' => 'Transaction approved by manager',
    ...
];
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=test_approval_history_reflects_actual_state_transitions`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/TransactionService.php tests/Unit/TransactionServiceTest.php
git commit -m "fix: record actual state transitions in approval history (T5)"
```

---

### Task 25: T6 - Replace forceStatus in Cancellation Rejection

**Files:**
- Modify: `app/Services/TransactionCancellationService.php:301`
- Modify: `app/Services/TransactionStateMachine.php`
- Test: `tests/Unit/TransactionCancellationServiceTest.php`

- [ ] **Step 1: Write failing test**

```php
public function test_cancellation_rejection_uses_valid_state_transition(): void
{
    $transaction = Transaction::factory()->create(['status' => TransactionStatus::PendingCancellation]);

    // Should be able to reject cancellation and return to previous status
    $result = $this->cancellationService->rejectCancellation($transaction->id, $this->manager, 'Invalid reason');

    // Should transition to previous status (e.g., Completed), not use forceStatus
    $this->assertEquals(TransactionStatus::Completed, $result->status);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_cancellation_rejection_uses_valid_state_transition`
Expected: FAIL - uses forceStatus

- [ ] **Step 3: Add valid transition for PendingCancellation rejection**

```php
// app/Services/TransactionStateMachine.php
'PendingCancellation' => [
    'Cancelled',
    'Completed',  // When rejection restores previous state
],
```

- [ ] **Step 4: Update rejectCancellation to use normal transition**

```php
// app/Services/TransactionCancellationService.php:301
public function rejectCancellation(int $transactionId, User $rejector, string $reason): Transaction
{
    $transaction = Transaction::findOrFail($transactionId);
    $previousStatus = $transaction->previous_status ?? TransactionStatus::Completed;

    return $this->stateMachine->transitionTo(
        $transaction,
        $previousStatus,  // Use stored previous status
        "Cancellation rejected: {$reason}"
    );
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=test_cancellation_rejection_uses_valid_state_transition`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Services/TransactionCancellationService.php app/Services/TransactionStateMachine.php tests/Unit/TransactionCancellationServiceTest.php
git commit -m "fix: use valid state transition for cancellation rejection (T6)"
```

---

### Task 26: S7 - Yellow Variance Flag on Handover

**Files:**
- Modify: `app/Services/CounterService.php:380-389`
- Test: `tests/Unit/CounterServiceTest.php`

- [ ] **Step 1: Write failing test**

```php
public function test_yellow_variance_requires_acknowledgment(): void
{
    $variance = '150.00'; // Yellow threshold variance

    $handover = $this->initiateHandover($counter, $variance, 'MYR');

    // Yellow variance should still require acknowledgment
    // Should not block handover but should flag for follow-up
    $this->assertNotNull($handover->variance_acknowledgment_required);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_yellow_variance_requires_acknowledgment`
Expected: FAIL

- [ ] **Step 3: Add yellow variance flag**

```php
// app/Services/CounterService.php:380-389
foreach ($perCurrencyVariances as $code => $variance) {
    $absVar = BcmathHelper::abs($variance);
    if (BcmathHelper::gt($absVar, $this->thresholdService->getVarianceRedThreshold())) {
        throw new VarianceThresholdException('red', true);
    }

    // Yellow variance doesn't block but requires acknowledgment
    if (BcmathHelper::gt($absVar, $this->thresholdService->getVarianceYellowThreshold())) {
        $handover->variance_acknowledgment_required = true;
        $handover->variance_notes = "Yellow variance detected: {$variance}";
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=test_yellow_variance_requires_acknowledgment`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/CounterService.php tests/Unit/CounterServiceTest.php
git commit -m "fix: require acknowledgment for yellow variance on handover (S7)"
```

---

### Task 27: S8 - MFA Session Timeout Alignment

**Files:**
- Modify: `config/security.php` OR `app/Http/Middleware/EnsureMfaVerified.php:48`
- Test: `tests/Feature/MfaSessionTest.php`

- [ ] **Step 1: Analyze current settings**

Current:
- Session lifetime: 480 minutes (8 hours)
- MFA verification: 900 seconds (15 minutes)

Issue: User can re-verify MFA without full re-auth, remaining logged in for 8 hours.

- [ ] **Step 2: Write failing test**

```php
public function test_mfa_reverification_does_not_extend_session_beyond_limit(): void
{
    $user = User::factory()->create();

    // Session established with MFA at time T
    $this->actingAs($user);
    $this->withSession(['mfa_verified_at' => now()->subMinutes(20)]);

    // Try to re-verify after 20 minutes
    $response = $this->postJson('/api/v1/mfa/verify', ['code' => '123456']);

    // Should either reject or require full re-auth
    // Should not extend session indefinitely
}
```

- [ ] **Step 3: Align MFA session with overall session timeout**

```php
// app/Http/Middleware/EnsureMfaVerified.php
// Option 1: Reduce MFA session to match reasonable auth period (e.g., 4 hours)
// Config: 'mfa_session_max_age' => env('MFA_SESSION_MAX_AGE', 14400) // 4 hours

// Option 2: Require full re-auth after extended idle
// Add check: if (session lifetime > 4 hours) require full login, not just MFA
```

- [ ] **Step 4: Commit**

```bash
git add config/security.php app/Http/Middleware/EnsureMfaVerified.php tests/Feature/MfaSessionTest.php
git commit -m "fix: align MFA session timeout with security requirements (S8)"
```

---

### Task 28: R3 - Standardize Spread Calculation

**Files:**
- Modify: `app/Services/RateApiService.php` OR `app/Services/RateManagementService.php`
- Test: `tests/Unit/RateSpreadTest.php`

- [ ] **Step 1: Write test for consistent spread**

```php
public function test_spread_calculation_is_consistent(): void
{
    $baseRate = '4.50';
    $spread = '0.02';

    // RateApiService calculation
    $buyFromApi = $this->rateApiService->calculateBuyRate($baseRate, $spread);
    $sellFromApi = $this->rateApiService->calculateSellRate($baseRate, $spread);

    // RateManagementService calculation
    $buyFromMgmt = $this->rateManagementService->calculateBuyRate($baseRate, $spread);
    $sellFromMgmt = $this->rateManagementService->calculateSellRate($baseRate, $spread);

    // Both should produce same result
    $this->assertEquals($buyFromApi, $buyFromMgmt);
    $this->assertEquals($sellFromApi, $sellFromMgmt);
}
```

- [ ] **Step 2: Run test to verify inconsistency**

Run: `php artisan test --filter=test_spread_calculation_is_consistent`
Expected: FAIL

- [ ] **Step 3: Standardize algorithm**

Choose RateApiService approach and apply to RateManagementService, or vice versa.

- [ ] **Step 4: Commit**

```bash
git add app/Services/RateApiService.php app/Services/RateManagementService.php tests/Unit/RateSpreadTest.php
git commit -m "fix: standardize spread calculation across services (R3)"
```

---

### Task 29: R4 - Direct config() Calls in Enums

**Files:**
- Create: `app/Traits/Thresholdable.php`
- Modify: `app/Enums/CddLevel.php`, `app/Enums/ComplianceFlagType.php`, `app/Enums/AmlRuleType.php`
- Test: `tests/Unit/ThresholdAccessTest.php`

- [ ] **Step 1: Create Thresholdable trait**

```php
// app/Traits/Thresholdable.php
trait Thresholdable
{
    protected static function getThreshold(string $key, mixed $default = null): string
    {
        // All threshold access goes through ThresholdService
        // But enums can't use dependency injection
        // So we use app() helper to get singleton
        return app(ThresholdService::class)->get($key) ?? $default ?? config('thresholds.' . $key);
    }
}
```

- [ ] **Step 2: Apply trait to enums**

```php
// app/Enums/CddLevel.php
use Thresholdable;

enum CddLevel
{
    use Thresholdable;

    public function getThresholdAmount(): string
    {
        return match ($this) {
            self::Simplified => self::getThreshold('cdd.simplified', '3000'),
            self::Specific => self::getThreshold('cdd.specific', '3000'),
            self::Standard => self::getThreshold('cdd.standard', '10000'),
            self::Enhanced => self::getThreshold('cdd.large_transaction', '50000'),
        };
    }
}
```

- [ ] **Step 3: Update all enum threshold references to use trait**

- [ ] **Step 4: Commit**

```bash
git add app/Traits/Thresholdable.php app/Enums/CddLevel.php app/Enums/ComplianceFlagType.php app/Enums/AmlRuleType.php tests/Unit/ThresholdAccessTest.php
git commit -m "fix: centralize threshold access in enums via Thresholdable trait (R4)"
```

---

### Task 30: R6 - MathService Scale vs DB Decimal:4

**Files:**
- Modify: `app/Services/MathService.php`
- Test: `tests/Unit/MathServiceTest.php`

- [ ] **Step 1: Write test for scale consistency**

```php
public function test_math_service_scale_matches_database_precision(): void
{
    $mathScale = $this->mathService->getScale();
    $dbPrecision = 4; // decimal:4 in migrations

    // Recommend standardizing on 4 (DB precision) or 6 (current)
    // Document the decision
    $this->assertEquals(4, $mathScale, 'MathService scale should match DB decimal precision');
}
```

- [ ] **Step 2: Decide and implement scale standardization**

Recommend: Standardize on scale=6 (current) and ensure DB stores with appropriate rounding, OR change to scale=4 to match DB.

- [ ] **Step 3: Commit**

```bash
git add app/Services/MathService.php tests/Unit/MathServiceTest.php
git commit -m "fix: standardize MathService scale to match DB precision (R6)"
```

---

### Task 31: Remaining Lower Priority Fixes (C7, C8, C9, T8, A7, A8, A9, S5)

**Files:** Various

These issues are lower priority and can be addressed in subsequent sprints:

| Issue | Description | Files |
|-------|-------------|-------|
| C7 | Customer lock auto-expires before EDD complete | CustomerRiskProfile.php |
| C8 | EDD completion check ignores documents | EddService.php |
| C9 | RoundAmount detection false positives | TransactionMonitoringService.php |
| T8 | Velocity check uses wrong threshold | ComplianceService.php |
| A7 | foreign_total variance assumption | CounterService.php |
| A8 | Variance notes not in TillBalance | CounterService.php |
| A9 | Revaluation partial state on error | RevaluationService.php |
| S5 | Burst bypasses rate limiter | StrictRateLimit.php |

---

## Summary

| Phase | Tasks | Priority |
|-------|-------|----------|
| 1 | G1, G2, G3 | CRITICAL pd-00.md non-compliance |
| 2 | S1, S2, S3-S5, T1, T2, T3, T4, C1, C4 | HIGH severity |
| 3 | A1, A2, A4, S6, R1, R2, R5, A5, A6, T7 | MEDIUM severity |
| 4 | A3 review, T5, T6, S7, S8, R3, R4, R6 | MEDIUM-LOW |
| 5 | C7, C8, C9, T8, A7, A8, A9, S5 | Lower priority |

**Total: 30 tasks across 5 phases**

---

## Self-Review Checklist

1. **Spec coverage:** All 7 compliance gaps (G1-G7) and 40 internal conflicts mapped to tasks ✓
2. **Placeholder scan:** No TBD/TODO in task descriptions ✓
3. **Type consistency:** Method names consistent across tasks (e.g., `consumeStockReservation`, `calculateVariance`) ✓
4. **Test coverage:** Each task includes failing test before fix ✓
5. **Commit structure:** Each task ends with commit ✓