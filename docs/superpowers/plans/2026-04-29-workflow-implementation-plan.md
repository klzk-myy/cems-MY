# Workflow Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement all 18 workflow issues (6 missing workflows + 7 flaws + 5 redundancies)

**Architecture:** Services layer (core business logic), Controllers (API/Web delegation), Models, Commands (scheduled jobs), Views (web UI)

**Tech Stack:** Laravel 10, PHP 8.1, BCMath, MySQL

---

## File Map

### New Files (by component)

**Services:**
- `app/Services/KycDocumentExpiryService.php`
- `app/Services/EmergencyCounterService.php`
- `app/Services/BranchClosingService.php`
- `app/Services/MonthEndCloseService.php`
- `app/Services/CustomerRiskReviewService.php`
- `app/Services/TransactionApprovalService.php`
- `app/Services/CounterHandoverService.php`

**Commands:**
- `app/Console/Commands/CustomerRiskReviewCommand.php`
- `app/Console/Commands/ReservationExpireCommand.php`
- `app/Console/Commands/MonthEndCloseCommand.php`
- `app/Console/Commands/BranchCloseCommand.php`
- `app/Console/Commands/EmergencyCloseCommand.php`

**Controllers:**
- `app/Http/Controllers/Api/V1/CounterHandoverController.php`
- `app/Http/Controllers/Api/V1/BranchClosingController.php`
- `app/Http/Controllers/Api/V1/MonthEndCloseController.php`
- `app/Http/Controllers/Api/V1/CustomerRiskReviewController.php`

**Models:**
- `app/Models/BranchClosureWorkflow.php`
- `app/Models/CounterHandover.php`
- `app/Models/EmergencyClosure.php`

**Exceptions:**
- `app/Exceptions/Domain/CddDocumentExpiredException.php`

---

## IMPLEMENTATION TASKS

### Task 1: Fix Flaw A - Direct Cancel Bypass

**Files:**
- Modify: `app/Services/TransactionCancellationService.php:64`
- Modify: `app/Http/Controllers/Transaction/TransactionCancellationController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/TransactionCancellationTest.php`

- [ ] **Step 1: Remove direct cancel bypass in TransactionCancellationService**

In `cancelTransaction()`, remove the path that sets status directly to `Cancelled`. All cancellations must go through `PendingCancellation` state.

```php
// In requestCancellation() method, ensure it properly transitions to PendingCancellation
// Remove any direct $transaction->update(['status' => TransactionStatus::Cancelled]) without going through state machine
```

- [ ] **Step 2: Update TransactionCancellationController**

Remove `cancel()` method that calls direct cancel path. Keep only `showCancel()` and `cancel()` that calls `requestCancellation()`.

- [ ] **Step 3: Update routes**

Remove or update `POST /transactions/{transaction}/cancel` route to require manager approval (PendingCancellation flow).

- [ ] **Step 4: Add test**

```php
def test_direct_cancel_blocked():
    # Attempting to cancel without manager approval should throw exception
    with pytest.raises(DualControlViolationException):
        transactionService->cancelTransaction($transaction, $user)
```

---

### Task 2: Fix Flaw C - Allocation Rejection Balance Release

**Files:**
- Modify: `app/Services/TellerAllocationService.php`
- Modify: `app/Http/Controllers/Api/V1/TellerAllocationController.php`
- Modify: `app/Enums/TellerAllocationStatus.php`
- Modify: `app/Models/TellerAllocation.php` (add rejection fields)
- Test: `tests/Unit/TellerAllocationServiceTest.php`

- [ ] **Step 1: Add REJECTED to TellerAllocationStatus enum**

```php
enum TellerAllocationStatus {
    case PENDING;
    case APPROVED;
    case ACTIVE;
    case RETURNED;
    case REJECTED;
}
```

- [ ] **Step 2: Add rejection fields to TellerAllocation model**

```php
protected $fillable = [
    // ... existing
    'rejected_at',
    'rejected_by',
    'rejection_reason',
];
```

- [ ] **Step 3: Add rejectAllocation() method to TellerAllocationService**

```php
public function rejectAllocation(TellerAllocation $allocation, User $rejector, ?string $reason = null): void
{
    $pool = $this->branchPoolService->getOrCreateForBranch($allocation->branch, $allocation->currency_code);
    $pool->releaseFunds($allocation->allocated_amount);
    
    $allocation->update([
        'status' => TellerAllocationStatus::REJECTED,
        'rejected_at' => now(),
        'rejected_by' => $rejector->id,
        'rejection_reason' => $reason,
    ]);
}
```

- [ ] **Step 4: Update BranchPoolService for releaseFunds()**

If `releaseFunds()` doesn't exist, add it:
```php
public function releaseFunds(string $amount): void
{
    $this->reserved_balance = bcsub($this->reserved_balance, $amount, 4);
    $this->available_balance = bcadd($this->available_balance, $amount, 4);
    $this->save();
}
```

- [ ] **Step 5: Update TellerAllocationController reject()**

Call `tellerAllocationService->rejectAllocation()` instead of direct update.

- [ ] **Step 6: Add test**

```php
def test_rejection_releases_pool_balance():
    poolBeforeReject = poolService->getBalance($allocation)
    service->rejectAllocation($allocation, $manager)
    poolAfterReject = poolService->getBalance($allocation)
    assert poolAfterReject.available == poolBeforeReject.available + $allocation->allocated_amount
```

---

### Task 3: Fix Flaw E - copyPrevious Spread Fix

**Files:**
- Create: database migration for `spread_applied` column
- Modify: `app/Services/RateManagementService.php`
- Modify: `app/Services/RateApiService.php`
- Modify: `app/Http/Controllers/Api/V1/RateController.php`
- Modify: `app/Http/Controllers/RateController.php`
- Test: `tests/Unit/RateManagementServiceTest.php`

- [ ] **Step 1: Create migration for spread_applied**

```php
Schema::table('exchange_rates', function (Blueprint $table) {
    $table->string('spread_applied', 10)->nullable()->after('rate_sell');
});
```

- [ ] **Step 2: Add spread_applied to ExchangeRate model fillable**

```php
protected $fillable = [
    // ... existing
    'spread_applied',
];
```

- [ ] **Step 3: Store spread_applied when rates are set**

In `RateManagementService::overrideRate()` and `RateManagementService::setRateFromApi()`:
```php
$rate->spread_applied = $spread;
$rate->save();
```

- [ ] **Step 4: Use stored spread in copyPrevious**

In `RateController::copyPrevious()`:
```php
$spread = $previousRate->spread_applied ?? config('thresholds.rates.spread', '0.02');
```

- [ ] **Step 5: Add test for spread preservation**

```php
def test_copy_previous_uses_stored_spread():
    $rate = createRateWithSpread('0.03');
    $copied = rateController->copyPrevious($rate);
    assert $copied->spread_applied == '0.03'
```

---

### Task 4: Fix Flaw F - Journal Entry Simplification

**Files:**
- Modify: `app/Services/AccountingService.php`
- Modify: `app/Http/Controllers/AccountingController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/JournalEntryWorkflowTest.php`

- [ ] **Step 1: Simplify createJournalEntry()**

Remove Draft/Post workflow. All entries create directly as Posted:
```php
public function createJournalEntry(array $data, bool $isSystemCreated = false): JournalEntry
{
    $this->validateBalanced($data['entries']);
    
    $entry = JournalEntry::create([
        'entry_date' => $data['entry_date'],
        'period_id' => $data['period_id'],
        'description' => $data['description'],
        'status' => JournalEntryStatus::POSTED,
        'is_system_created' => $isSystemCreated,
        'created_by' => auth()->id(),
    ]);
    
    foreach ($data['entries'] as $entryData) {
        LedgerEntry::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $entryData['account_id'],
            // ... other fields
        ]);
    }
    
    return $entry;
}
```

- [ ] **Step 2: Ensure reverseJournalEntry() works**

Verify reversal creates new entries and marks original as Reversed:
```php
public function reverseJournalEntry(JournalEntry $entry, User $reverser): JournalEntry
{
    $reversal = $entry->replicate();
    $reversal->status = JournalEntryStatus::POSTED;
    $reversal->is_system_created = false;
    $reversal->reversal_of_id = $entry->id;
    $reversal->save();
    
    // Create reversing ledger entries (swap debit/credit)
    foreach ($entry->ledgerEntries as $le) {
        LedgerEntry::create([
            // ... swap debit/credit
        ]);
    }
    
    $entry->update(['status' => JournalEntryStatus::REVERSED]);
    
    return $reversal;
}
```

- [ ] **Step 3: Add reverse route and controller action**

- [ ] **Step 4: Update views**

Add "Reverse" button to journal entry view.

---

### Task 5: KYC Document Expiry Blocking

**Files:**
- Create: `app/Services/KycDocumentExpiryService.php`
- Create: `app/Exceptions/Domain/CddDocumentExpiredException.php`
- Modify: `app/Services/TransactionService.php`
- Modify: `app/Services/ComplianceService.php`
- Test: `tests/Unit/KycDocumentExpiryServiceTest.php`

- [ ] **Step 1: Create CddDocumentExpiredException**

```php
class CddDocumentExpiredException extends BusinessRuleException
{
    public function __construct(Customer $customer)
    {
        parent::__construct("Customer {$customer->name} has expired KYC documents");
    }
}
```

- [ ] **Step 2: Create KycDocumentExpiryService**

```php
class KycDocumentExpiryService
{
    public function mustBlockDueToExpiredDocuments(Customer $customer): bool
    {
        $requiredDocs = $this->getRequiredDocuments($customer->cdd_level);
        foreach ($requiredDocs as $docType) {
            $doc = $customer->documents()->where('type', $docType)->first();
            if (!$doc || $doc->isExpired()) {
                return true;
            }
        }
        return false;
    }
    
    public function getExpiredDocuments(Customer $customer): Collection
    {
        // return expired docs
    }
}
```

- [ ] **Step 3: Add grace period threshold**

In `ThresholdService`, add `getKycGracePeriodDays()` (default: 5).

- [ ] **Step 4: Call in TransactionService::createTransaction()**

```php
if ($this->kycDocumentExpiryService->mustBlockDueToExpiredDocuments($customer)) {
    throw new CddDocumentExpiredException($customer);
}
```

- [ ] **Step 5: Add test**

---

### Task 6: Periodic Customer Risk Review Job

**Files:**
- Create: `app/Services/CustomerRiskReviewService.php`
- Create: `app/Console/Commands/CustomerRiskReviewCommand.php`
- Create: `app/Jobs/CustomerRiskReviewJob.php`
- Modify: `app/Services/CustomerRiskScoringService.php`
- Modify: `app/Console/Kernel.php` (schedule)
- Test: `tests/Unit/CustomerRiskReviewServiceTest.php`

- [ ] **Step 1: Create CustomerRiskReviewService**

```php
class CustomerRiskReviewService
{
    public function processDueReviews(int $batchSize = 50): array
    {
        $due = RiskScoreSnapshot::needsRescreening()->take($batchSize)->get();
        $results = ['processed' => 0, 'changed' => 0, 'errors' => 0];
        
        foreach ($due as $snapshot) {
            try {
                $oldScore = $snapshot->risk_score;
                $this->recalculateForCustomer($snapshot->customer);
                $newScore = $snapshot->fresh()->risk_score;
                
                if ($oldScore != $newScore) {
                    $results['changed']++;
                }
                $results['processed']++;
            } catch (\Exception $e) {
                $results['errors']++;
            }
        }
        
        return $results;
    }
}
```

- [ ] **Step 2: Create Artisan command**

```php
class CustomerRiskReviewCommand extends Command
{
    public function handle(): int
    {
        $batchSize = thresholdService()->getRiskReviewBatchSize();
        $results = $this->service->processDueReviews($batchSize);
        $this->info("Processed: {$results['processed']}, Changed: {$results['changed']}, Errors: {$results['errors']}");
        return 0;
    }
}
```

- [ ] **Step 3: Schedule in Kernel.php**

```php
$schedule->command('customer:risk-review')->dailyAt('02:00');
```

- [ ] **Step 4: Add test**

---

### Task 7: Stock Reservation Expiry Job

**Files:**
- Create: `app/Console/Commands/ReservationExpireCommand.php`
- Create: `app/Jobs/ReservationExpireJob.php`
- Modify: `app/Services/CurrencyPositionService.php`
- Modify: `app/Console/Kernel.php` (schedule)
- Test: `tests/Unit/StockReservationTest.php`

- [ ] **Step 1: Create ReservationExpireCommand**

```php
class ReservationExpireCommand extends Command
{
    public function handle(): int
    {
        $expired = StockReservation::where('status', 'pending')
            ->where('expires_at', '<=', now())
            ->get();
        
        foreach ($expired as $reservation) {
            $this->positionService->releaseStockReservation($reservation);
            $this->notifyTeller($reservation);
        }
        
        $this->info("Expired {$expired->count()} reservations");
        return 0;
    }
}
```

- [ ] **Step 2: Schedule every 15 minutes**

```php
$schedule->command('reservation:expire')->everyFifteenMinutes();
```

- [ ] **Step 3: Add notification to teller**

```php
protected function notifyTeller(StockReservation $reservation)
{
    $transaction = $reservation->transaction;
    if ($transaction) {
        Notification::route('mail', $transaction->createdBy->email)
            ->notify(new ReservationExpiredNotification($reservation));
    }
}
```

- [ ] **Step 4: Add test**

---

### Task 8: Month-End Auto-Closing Sequence

**Files:**
- Create: `app/Services/MonthEndCloseService.php`
- Create: `app/Console/Commands/MonthEndCloseCommand.php`
- Create: `app/Http/Controllers/Api/V1/MonthEndCloseController.php`
- Create: `app/Http/Controllers/MonthEndCloseController.php`
- Modify: `routes/web.php`, `routes/api_v1.php`
- Test: `tests/Feature/MonthEndCloseTest.php`

- [ ] **Step 1: Create MonthEndCloseService**

```php
class MonthEndCloseService
{
    public function runMonthEndClosing(Carbon $date, User $initiator): array
    {
        $results = [];
        
        // 1. Pre-flight checks
        $checkResult = $this->preFlightChecks($date);
        if (!$checkResult['passed']) {
            throw new MonthEndPreCheckFailedException($checkResult['failures']);
        }
        
        // 2. Run revaluation
        $results['revaluation'] = $this->revaluationService->run($date);
        
        // 3. Generate reports
        $results['reports'] = $this->generateReports($date);
        
        // 4. Close period
        $results['period'] = $this->closePeriod($date);
        
        // 5. Audit log
        $this->auditService->log(...);
        
        return $results;
    }
}
```

- [ ] **Step 2: Create Artisan command**

```php
class MonthEndCloseCommand extends Command
{
    public function handle(): int
    {
        $date = Carbon::parse($this->option('date') ?? now()->subMonth()->endOfMonth());
        $results = $this->service->runMonthEndClosing($date, auth()->user());
        $this->info("Month-end close completed: " . json_encode($results));
        return 0;
    }
}
```

- [ ] **Step 3: Schedule on 1st of month at 01:00**

```php
$schedule->command('accounting:month-end')->monthlyOn(1, '01:00');
```

- [ ] **Step 4: Add API and Web controllers**

- [ ] **Step 5: Add test**

---

### Task 9: Emergency Counter Closure

**Files:**
- Create: `app/Services/EmergencyCounterService.php`
- Create: `app/Http/Controllers/Api/V1/EmergencyCloseController.php`
- Modify: `app/Http/Controllers/CounterController.php`
- Modify: `app/Enums/CounterSessionStatus.php`
- Modify: `routes/web.php`, `routes/api_v1.php`
- Test: `tests/Feature/EmergencyCounterCloseTest.php`

- [ ] **Step 1: Add EmergencyClosed to CounterSessionStatus enum**

```php
enum CounterSessionStatus {
    case Open;
    case Closed;
    case PendingHandover;
    case HandedOver;
    case EmergencyClosed;  // NEW
}
```

- [ ] **Step 2: Create EmergencyCounterService**

```php
class EmergencyCounterService
{
    public function initiateEmergencyClose(Counter $counter, User $teller, string $reason): EmergencyClosure
    {
        // Validate constraints
        $this->validateConstraints($counter, $teller);
        
        $closure = EmergencyClosure::create([
            'counter_id' => $counter->id,
            'session_id' => $counter->activeSession->id,
            'teller_id' => $teller->id,
            'reason' => $reason,
            'closed_at' => now(),
        ]);
        
        // Update session
        $counter->activeSession->update([
            'status' => CounterSessionStatus::EmergencyClosed,
        ]);
        
        // Release allocation
        $this->allocationService->returnToPool($counter->activeSession->allocation);
        
        // Notify manager
        $this->notifyManager($closure);
        
        return $closure;
    }
    
    private function validateConstraints(Counter $counter, User $teller): void
    {
        // 4-hour cooldown
        $recent = EmergencyClosure::where('counter_id', $counter->id)
            ->where('created_at', '>=', now()->subHours(4))
            ->exists();
        if ($recent) {
            throw new EmergencyCloseCooldownException();
        }
        
        // 30-min session minimum
        $session = $counter->activeSession;
        if ($session->created_at->diffInMinutes(now()) < 30) {
            throw new EmergencyCloseSessionTooNewException();
        }
    }
}
```

- [ ] **Step 3: Add API endpoints**

```php
POST /api/v1/counters/{counterId}/emergency-close
GET /api/v1/counters/{counterId}/emergency/{closureId}/variance
POST /api/v1/counters/{counterId}/emergency/{closureId}/acknowledge
```

- [ ] **Step 4: Add web routes and views**

- [ ] **Step 5: Add test**

---

### Task 10: Branch Closing Workflow

**Files:**
- Create: `app/Services/BranchClosingService.php`
- Create: `app/Models/BranchClosureWorkflow.php`
- Create: `app/Http/Controllers/Api/V1/BranchClosingController.php`
- Create: `app/Http/Controllers/BranchClosingController.php`
- Modify: `routes/web.php`, `routes/api_v1.php`
- Test: `tests/Feature/BranchClosingWorkflowTest.php`

- [ ] **Step 1: Create BranchClosureWorkflow model**

```php
class BranchClosureWorkflow extends Model
{
    protected $fillable = [
        'branch_id', 'initiated_by', 'status', 'checklist',
        'settlement_at', 'finalized_at'
    ];
    
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
```

- [ ] **Step 2: Create BranchClosingService**

```php
class BranchClosingService
{
    public function initiateClosure(Branch $branch, User $initiator): BranchClosureWorkflow
    {
        $workflow = BranchClosureWorkflow::create([
            'branch_id' => $branch->id,
            'initiated_by' => $initiator->id,
            'status' => 'initiated',
        ]);
        
        return $workflow;
    }
    
    public function getChecklist(BranchClosureWorkflow $workflow): array
    {
        return [
            'counters_closed' => $this->checkCountersClosed($workflow->branch),
            'allocations_returned' => $this->checkAllocationsReturned($workflow->branch),
            'transfers_complete' => $this->checkTransfersComplete($workflow->branch),
            'no_pending_approvals' => $this->checkNoPendingApprovals($workflow->branch),
        ];
    }
    
    public function settle(BranchClosureWorkflow $workflow): void
    {
        // Transfer pool balances to HQ
        // Resolve outstanding obligations
        $workflow->update(['status' => 'settling', 'settlement_at' => now()]);
    }
    
    public function finalize(BranchClosureWorkflow $workflow): void
    {
        $workflow->branch->update(['is_active' => false]);
        $workflow->update(['status' => 'finalized', 'finalized_at' => now()]);
    }
}
```

- [ ] **Step 3: Add API endpoints**

- [ ] **Step 4: Add web views**

- [ ] **Step 5: Add test**

---

### Task 11: Handover Phase 2 - Acknowledge Endpoint

**Files:**
- Create: `app/Services/CounterHandoverService.php`
- Create: `app/Http/Controllers/Api/V1/CounterHandoverController.php`
- Modify: `app/Services/CounterService.php`
- Modify: `routes/web.php`, `routes/api_v1.php`
- Test: `tests/Feature/CounterHandoverAcknowledgeTest.php`

- [ ] **Step 1: Add acknowledgeHandover() to CounterService if not exists**

Verify method exists at lines 476-520 with correct logic.

- [ ] **Step 2: Create CounterHandoverService**

```php
class CounterHandoverService
{
    public function acknowledgeHandover(CounterHandover $handover, User $incomingTeller, bool $verified, ?string $notes): void
    {
        if ($handover->to_user_id !== $incomingTeller->id) {
            throw new UnauthorizedException('You are not the incoming teller');
        }
        
        if ($handover->status !== CounterSessionStatus::PendingHandover) {
            throw new InvalidStateException('Handover is not pending acknowledgment');
        }
        
        $handover->session->update([
            'status' => CounterSessionStatus::Open,
            'physical_count_verified' => $verified,
            'handover_notes' => $notes,
        ]);
        
        $handover->update(['acknowledged_at' => now()]);
    }
}
```

- [ ] **Step 3: Create CounterHandoverController**

```php
POST /api/v1/counters/{counterId}/handover/{handoverId}/acknowledge
```

- [ ] **Step 4: Add web routes and view**

- [ ] **Step 5: Add test**

---

### Task 12: Flaw B - TransactionApprovalService Consolidation

**Files:**
- Create: `app/Services/TransactionApprovalService.php`
- Modify: `app/Http/Controllers/Transaction/TransactionApprovalController.php`
- Modify: `app/Http/Controllers/Api/V1/TransactionApprovalController.php`
- Test: `tests/Unit/TransactionApprovalServiceTest.php`

- [ ] **Step 1: Create TransactionApprovalService**

Extract shared logic from both controllers into single service.

- [ ] **Step 2: Update both controllers to delegate**

- [ ] **Step 3: Add test**

---

### Task 13: Redundancy #1 - Merge Counter Services

**Files:**
- Modify: `app/Services/CounterService.php`
- Modify: `app/Services/CounterOpeningWorkflowService.php`
- Modify: `app/Http/Controllers/CounterController.php`

Decide whether to merge or keep as coordinator pattern. If merging:
- Move `initiateOpeningRequest()` from `CounterOpeningWorkflowService` → `CounterService`
- Remove `CounterOpeningWorkflowService` if redundant

---

### Task 14: Redundancy #3 - Unify CurrencyPositionService

**Files:**
- Modify: `app/Services/CurrencyPositionService.php`
- Modify: `app/Services/BranchPoolService.php`
- Modify: `app/Services/CounterService.php`

Ensure `CurrencyPositionService` is single source of truth. `BranchPoolService` and `CounterService` delegate to it for position tracking.

---

### Task 15: Redundancy #4 - CTOS Threshold Single Source

**Files:**
- Modify: `app/Services/ComplianceService.php`
- Modify: `app/Services/CtosReportService.php`
- Modify: `app/Services/ThresholdService.php`

Ensure `ThresholdService::getCtosThreshold()` is the only place that defines the CTOS threshold. Remove duplicate checks in `ComplianceService::requiresCtos()` and `CtosReportService::qualifiesForCtos()`.

---

## IMPLEMENTATION VERIFICATION

After each task:
- [ ] Run `php artisan route:list --path=api/v1` to verify routes
- [ ] Run `php artisan routes:validate` to check consistency
- [ ] Run related unit tests
- [ ] Run related feature tests

---

## PLAN SELF-REVIEW CHECKLIST

1. **Spec coverage:** Each requirement in the spec has a corresponding task? Yes/No
2. **Placeholder scan:** No TBD/TODO/placeholder steps? Yes/No
3. **Type consistency:** All method names, parameters consistent across tasks? Yes/No
4. **File paths:** All paths exact and match codebase? Yes/No
5. **Test coverage:** Each new feature has test? Yes/No
