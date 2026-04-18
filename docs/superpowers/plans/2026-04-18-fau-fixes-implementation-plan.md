# Fau.md Fixes - Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix all unfixed issues from `fau.md` updated 2026-04-18: critical DB facade missing import, two transaction/concurrency gaps, type-safety issues (string constants vs enums, raw bcmath vs MathService), and an unused parameter audit trail gap.

**Architecture:** Each fix is a discrete, independent patch applied in severity order (critical → high → medium). No new files required — all fixes are single-file modifications or enum creations. Tests run after each task.

**Tech Stack:** Laravel 10, BCMath, MySQL

---

## File Map

### New Enum Files
- `app/Enums/StockTransferStatus.php`

### Modified Files
- `app/Services/BranchPoolService.php` — add DB facade import, use MathService for approvedBy audit logging
- `app/Services/TellerAllocationService.php` — wrap modifyAllocation in DB::transaction with lockForUpdate, use MathService
- `app/Services/CounterOpeningWorkflowService.php` — wrap approveAndOpen in DB::transaction
- `app/Models/StockTransfer.php` — replace const STATUS_* strings with StockTransferStatus enum
- `app/Services/StockTransferService.php` — replace StockTransfer::STATUS_* string constants with enum
- `app/Services/CustomerRiskScoringService.php` — replace raw `'Completed'` status string with enum
- `app/Services/Compliance/ComplianceReportingService.php` — replace raw `'Pending'` status strings with enum
- `app/Services/ReportingService.php` — replace raw `'Completed'` status strings with enum
- `app/Services/UnifiedRiskScoringService.php` — replace raw `'Completed'` status string with enum
- `app/Services/AccountingService.php` — replace raw `'Draft'`, `'Pending'`, `'Posted'`, `'Rejected'`, `'Reversed'` with JournalEntryStatus enum
- `app/Services/RevaluationService.php` — replace raw `bccomp` with MathService

---

## Task 1: Fix Missing DB Facade in BranchPoolService

**Files:**
- Modify: `app/Services/BranchPoolService.php` — add `use Illuminate\Support\Facades\DB;`

- [ ] **Step 1: Verify current imports (top of file)**

```bash
head -10 app/Services/BranchPoolService.php
```

- [ ] **Step 2: Add DB facade import after existing imports**

Add after line 7 (`use Illuminate\Support\Collection;`):

```php
use Illuminate\Support\Facades\DB;
```

- [ ] **Step 3: Run tests to verify no regression**

```bash
php artisan test --filter=BranchPoolServiceTest 2>&1 | tail -15
```

- [ ] **Step 4: Commit**

```bash
git add app/Services/BranchPoolService.php && git commit -m "fix: add missing DB facade import to BranchPoolService"
```

---

## Task 2: Add approvedBy Audit Trail to BranchPoolService::replenish

**Files:**
- Modify: `app/Services/BranchPoolService.php` — use `$approvedBy` parameter for audit logging
- Test: existing `BranchPoolServiceTest`

- [ ] **Step 1: Read the replenish method**

```bash
sed -n '85,113p' app/Services/BranchPoolService.php
```

- [ ] **Step 2: Add SystemLog import**

Add after `use Illuminate\Support\Facades\DB;`:

```php
use App\Models\SystemLog;
use Illuminate\Support\Facades\Log;
```

- [ ] **Step 3: Update replenish method to log the approval**

Find the end of the `replenish` method (before the closing `}` at line 113). After `$pool->save();` and before `return $pool;`, add audit logging:

```php
Log::info('Branch pool replenished', [
    'branch_id' => $branch->id,
    'currency_code' => $currencyCode,
    'amount' => $amount,
    'approved_by' => $approvedBy,
    'pool_id' => $pool->id,
]);
```

- [ ] **Step 4: Run tests**

```bash
php artisan test --filter=BranchPoolServiceTest 2>&1 | tail -15
```

- [ ] **Step 5: Commit**

```bash
git add app/Services/BranchPoolService.php && git commit -m "fix: log replenishment with approvedBy in BranchPoolService"
```

---

## Task 3: Fix TellerAllocationService Concurrency + Use MathService

**Files:**
- Modify: `app/Services/TellerAllocationService.php` — wrap modifyAllocation in DB::transaction, replace raw bcmath with MathService
- Test: existing `TellerAllocationServiceTest`

- [ ] **Step 1: Read the modifyAllocation method in full**

```bash
sed -n '80,105p' app/Services/TellerAllocationService.php
```

- [ ] **Step 2: Add DB facade import**

Add after existing imports (around line 11):

```php
use Illuminate\Support\Facades\DB;
```

- [ ] **Step 3: Wrap modifyAllocation in DB::transaction with lockForUpdate**

Replace the entire `modifyAllocation` method:

```php
public function modifyAllocation(TellerAllocation $allocation, User $modifier, string $newAmount, bool $isIncrease): TellerAllocation
{
    return DB::transaction(function () use ($allocation, $modifier, $newAmount, $isIncrease) {
        $locked = TellerAllocation::where('id', $allocation->id)
            ->lockForUpdate()
            ->first();

        $branch = $locked->branch;

        if ($isIncrease) {
            if (! $this->branchPoolService->allocateToTeller($branch, $locked->currency_code, $newAmount)) {
                throw new Exception('Failed to allocate additional amount from branch pool');
            }
            $locked->current_balance = $this->mathService->add($locked->current_balance, $newAmount);
            $locked->allocated_amount = $this->mathService->add($locked->allocated_amount, $newAmount);
        } else {
            $availableToReturn = $this->mathService->subtract($locked->allocated_amount, $locked->current_balance);
            $returnAmount = $this->mathService->compare($newAmount, $availableToReturn) < 0 ? $newAmount : $availableToReturn;

            if ($this->mathService->compare($returnAmount, '0') > 0) {
                $this->branchPoolService->deallocateFromTeller($branch, $locked->currency_code, $returnAmount);
            }

            $locked->allocated_amount = $this->mathService->subtract($locked->allocated_amount, $newAmount);
            $locked->current_balance = $this->mathService->subtract($locked->current_balance, $this->mathService->subtract($newAmount, $returnAmount));
        }

        $locked->save();

        return $locked;
    });
}
```

- [ ] **Step 4: Run tests**

```bash
php artisan test --filter=TellerAllocationServiceTest 2>&1 | tail -20
```

- [ ] **Step 5: Commit**

```bash
git add app/Services/TellerAllocationService.php && git commit -m "fix: wrap TellerAllocationService::modifyAllocation in transaction and use MathService"
```

---

## Task 4: Wrap CounterOpeningWorkflowService::approveAndOpen in DB::transaction

**Files:**
- Modify: `app/Services/CounterOpeningWorkflowService.php` — wrap approveAndOpen in DB::transaction
- Test: existing tests for counter opening workflow

- [ ] **Step 1: Read the approveAndOpen method**

```bash
sed -n '50,95p' app/Services/CounterOpeningWorkflowService.php
```

- [ ] **Step 2: Add DB facade import**

```bash
grep -n "use Illuminate" app/Services/CounterOpeningWorkflowService.php | head -5
```

Add after existing imports:

```php
use Illuminate\Support\Facades\DB;
```

- [ ] **Step 3: Wrap approveAndOpen body in DB::transaction**

Replace the `approveAndOpen` method to wrap the entire body in `DB::transaction(function () { ... });`. The method currently returns early on error; with transaction wrapping, let the transaction handle the atomicity and remove early returns where possible.

The transaction should wrap:
1. The allocation lookup loop
2. The `approveAllocation` + `activateAllocation` calls per currency
3. The `$session = $this->counterService->openSession(...)` call
4. The `foreach ($tellerAllocations as $allocation)` counter_id update

```php
return DB::transaction(function () use ($teller, $counter, $approvedAmounts, $manager, $floatInputs) {
    // ... existing method body ...
});
```

- [ ] **Step 4: Run tests**

```bash
php artisan test --filter=CounterOpeningWorkflowServiceTest 2>&1 | tail -20
```

If no specific test exists, run related counter tests:

```bash
php artisan test --filter=BranchAllocationWorkflowTest 2>&1 | tail -20
```

- [ ] **Step 5: Commit**

```bash
git add app/Services/CounterOpeningWorkflowService.php && git commit -m "fix: wrap CounterOpeningWorkflowService::approveAndOpen in DB::transaction"
```

---

## Task 5: Create StockTransferStatus Enum

**Files:**
- Create: `app/Enums/StockTransferStatus.php`
- Modify: `app/Models/StockTransfer.php`
- Modify: `app/Services/StockTransferService.php`
- Test: existing StockTransferService tests

- [ ] **Step 1: Read StockTransfer model to find all STATUS constants**

```bash
sed -n '40,70p' app/Models/StockTransfer.php
```

- [ ] **Step 2: Create StockTransferStatus enum**

Based on the model's constants (typically REQUESTED, BM_APPROVED, HQ_APPROVED, ISSUED, RECEIVED, CANCELLED):

```php
<?php

namespace App\Enums;

enum StockTransferStatus: string
{
    case REQUESTED = 'Requested';
    case BM_APPROVED = 'BranchManagerApproved';
    case HQ_APPROVED = 'HQApproved';
    case ISSUED = 'Issued';
    case RECEIVED = 'Received';
    case CANCELLED = 'Cancelled';
}
```

- [ ] **Step 3: Update StockTransfer model — replace const with use statement**

In `app/Models/StockTransfer.php`, remove the const lines and add:

```php
use App\Enums\StockTransferStatus;
```

Add a helper method on the model:

```php
public function getStatusAttribute(): StockTransferStatus
{
    return StockTransferStatus::from($this->status);
}
```

- [ ] **Step 4: Update StockTransferService to use enum**

Read `app/Services/StockTransferService.php` and find all `StockTransfer::STATUS_*` references:

```bash
grep -n "StockTransfer::STATUS_" app/Services/StockTransferService.php
```

Replace each:
- `StockTransfer::STATUS_REQUESTED` → `StockTransferStatus::REQUESTED->value`
- `StockTransfer::STATUS_BM_APPROVED` → `StockTransferStatus::BM_APPROVED->value`
- etc.

Also add import at top:

```php
use App\Enums\StockTransferStatus;
```

- [ ] **Step 5: Run tests**

```bash
php artisan test --filter=StockTransfer 2>&1 | tail -20
```

- [ ] **Step 6: Commit**

```bash
git add app/Enums/StockTransferStatus.php app/Models/StockTransfer.php app/Services/StockTransferService.php && git commit -m "feat: add StockTransferStatus enum and replace string constants"
```

---

## Task 6: Replace Raw Status Strings with Enums in Services

**Files (all independent — can run in parallel):**
- `app/Services/CustomerRiskScoringService.php` — line 200
- `app/Services/Compliance/ComplianceReportingService.php` — lines 425, 429
- `app/Services/ReportingService.php` — lines 162, 206, 315, 321, 343, 428
- `app/Services/UnifiedRiskScoringService.php` — line 348

- [ ] **Step 1: Find exact lines and current string values**

For each file, run:

```bash
grep -n "where('status'" app/Services/CustomerRiskScoringService.php
grep -n "where('status'" app/Services/Compliance/ComplianceReportingService.php
grep -n "where('status'" app/Services/ReportingService.php
grep -n "where('status'" app/Services/UnifiedRiskScoringService.php
```

- [ ] **Step 2: Identify correct enum for each**

Based on context:
- `CustomerRiskScoringService` — likely `CustomerRiskLevel` or `RiskScoreStatus` enum
- `ComplianceReportingService` — likely `CtosReportStatus` or `ReportStatus` enum
- `ReportingService` — likely `ReportStatus` or `TransactionStatus` enum
- `UnifiedRiskScoringService` — likely `RiskScoreStatus` enum

```bash
grep -n "enum" app/Enums/*.php | grep -i "risk\|status\|report" | head -30
```

- [ ] **Step 3: Replace each raw string with enum value**

For each occurrence, replace:
```php
// Before
->where('status', 'Completed')
// After
->where('status', SomeStatusEnum::COMPLETED->value)
```

- [ ] **Step 4: Add enum import to each service file**

```php
use App\Enums\SomeStatusEnum;
```

- [ ] **Step 5: Run tests per file**

```bash
php artisan test --filter=CustomerRiskScoringServiceTest 2>&1 | tail -10
php artisan test --filter=ComplianceReportingServiceTest 2>&1 | tail -10
php artisan test --filter=ReportingServiceTest 2>&1 | tail -10
php artisan test --filter=UnifiedRiskScoringServiceTest 2>&1 | tail -10
```

- [ ] **Step 6: Commit after all four files are updated**

```bash
git add app/Services/CustomerRiskScoringService.php app/Services/Compliance/ComplianceReportingService.php app/Services/ReportingService.php app/Services/UnifiedRiskScoringService.php && git commit -m "fix: replace raw status strings with enums in compliance/reporting services"
```

---

## Task 7: Fix AccountingService to Use JournalEntryStatus Enum

**Files:**
- Modify: `app/Services/AccountingService.php` — replace raw strings with JournalEntryStatus enum
- Test: existing AccountingWorkflowTest

- [ ] **Step 1: Verify JournalEntryStatus enum exists and list values**

```bash
cat app/Enums/JournalEntryStatus.php
```

- [ ] **Step 2: Find all raw status strings in AccountingService**

```bash
grep -n "'Draft'\|'Pending'\|'Posted'\|'Rejected'\|'Reversed'" app/Services/AccountingService.php
```

Expected lines around: 98, 153, 211, 264, 372

- [ ] **Step 3: Add import**

Add at top of file:

```php
use App\Enums\JournalEntryStatus;
```

- [ ] **Step 4: Replace each raw string with enum**

```php
// Before
'status' => 'Draft',
// After
'status' => JournalEntryStatus::DRAFT->value,
```

Repeat for all five values.

- [ ] **Step 5: Run tests**

```bash
php artisan test --filter=AccountingWorkflowTest 2>&1 | tail -20
```

- [ ] **Step 6: Commit**

```bash
git add app/Services/AccountingService.php && git commit -m "fix: use JournalEntryStatus enum in AccountingService"
```

---

## Task 8: Fix Inconsistent bcmath — RevaluationService Uses MathService

**Files:**
- Modify: `app/Services/RevaluationService.php` — replace raw `bccomp` with MathService
- Test: existing RevaluationService tests

- [ ] **Step 1: Find raw bcmath usage**

```bash
grep -n "bccomp\|bcadd\|bcsub\|bcmul" app/Services/RevaluationService.php
```

Lines 107 and 541 use `bccomp`.

- [ ] **Step 2: Read context around line 107**

```bash
sed -n '100,115p' app/Services/RevaluationService.php
```

- [ ] **Step 3: Read context around line 541**

```bash
sed -n '535,550p' app/Services/RevaluationService.php
```

- [ ] **Step 4: Verify MathService is already injected**

```bash
grep -n "MathService" app/Services/RevaluationService.php | head -5
```

- [ ] **Step 5: Check if MathService has a compare method**

```bash
grep -n "function compare" app/Services/MathService.php
```

- [ ] **Step 6: Replace bccomp with MathService**

Line 107 — replace:
```php
// Before
if (bccomp($position->last_valuation_rate, $newRate, 10) !== 0) {
// After
if ($this->mathService->compare($position->last_valuation_rate, $newRate) !== 0) {
```

Line 541 — replace:
```php
// Before
if (bccomp($gainLossAmount, (string) $limits[$currencyCode], 2) > 0) {
// After
if ($this->mathService->compare($gainLossAmount, (string) $limits[$currencyCode]) > 0) {
```

Note: `MathService::compare` defaults to scale 4, so precision may differ. Verify the original `bccomp($a, $b, 10)` used 10 decimal places. If so, use `$this->mathService->compareWithScale($position->last_valuation_rate, $newRate, 10)` if that method exists, otherwise check if the service has a precision parameter.

```bash
grep -n "function compare" app/Services/MathService.php
```

- [ ] **Step 7: Run tests**

```bash
php artisan test --filter=RevaluationServiceTest 2>&1 | tail -20
```

- [ ] **Step 8: Commit**

```bash
git add app/Services/RevaluationService.php && git commit -m "fix: replace raw bccomp with MathService in RevaluationService"
```

---

## Task 9: Fix StockTransferService bcmath Inconsistency

**Files:**
- Modify: `app/Services/StockTransferService.php` — replace raw bcmath functions with MathService
- Test: existing StockTransferService tests

- [ ] **Step 1: Find raw bcmath usage**

```bash
grep -n "bcadd\|bcsub\|bcmul\|bcdiv" app/Services/StockTransferService.php
```

Lines 61-62, 87, 150.

- [ ] **Step 2: Read those lines**

```bash
sed -n '58,65p' app/Services/StockTransferService.php
sed -n '84,90p' app/Services/StockTransferService.php
sed -n '147,155p' app/Services/StockTransferService.php
```

- [ ] **Step 3: Verify MathService is injected**

```bash
grep -n "MathService\|__construct" app/Services/StockTransferService.php | head -5
```

- [ ] **Step 4: Replace each raw bcmath call**

For each occurrence, replace `bcadd($a, $b, 4)` → `$this->mathService->add($a, $b)`, etc.

- [ ] **Step 5: Run tests**

```bash
php artisan test --filter=StockTransfer 2>&1 | tail -20
```

- [ ] **Step 6: Commit**

```bash
git add app/Services/StockTransferService.php && git commit -m "fix: use MathService for bcmath operations in StockTransferService"
```

---

## Task 10: Final Test Suite Run

- [ ] **Step 1: Run full test suite**

```bash
php artisan test 2>&1 | tail -50
```

- [ ] **Step 2: If failures, diagnose and fix inline**

Common issues:
- Missing imports — add them
- Enum value mismatch — verify the string value in enum matches what DB contains
- MathService scale differences — compare output before/after

---

## Spec Coverage Check

| fau.md Section | Task(s) |
|--------------|---------|
| 2.1: BranchPoolService DB facade missing | Task 1 |
| 2.2: TellerAllocationService concurrency + MathService | Task 3 |
| 2.3: CounterOpeningWorkflowService transaction | Task 4 |
| 3.1: StockTransfer string constants → enum | Task 5 |
| 3.2: Raw status strings in queries | Task 6 |
| 3.3: AccountingService JournalEntryStatus | Task 7 |
| 3.4: Inconsistent bcmath (RevaluationService) | Task 8 |
| 3.4: Inconsistent bcmath (StockTransferService) | Task 9 |
| 4.3: Unused $approvedBy in BranchPoolService | Task 2 |

All sections have corresponding tasks.

---

## Execution Options

**1. Subagent-Driven (recommended)** — I dispatch a fresh subagent per task, review between tasks, fast iteration

**2. Inline Execution** — Execute tasks in this session using executing-plans, batch execution with checkpoints

Which approach?
