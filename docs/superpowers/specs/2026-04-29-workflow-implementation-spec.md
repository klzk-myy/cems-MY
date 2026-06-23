# CEMS-MY Workflow Implementation Spec

> **Date:** 2026-04-29
> **Status:** Approved
> **Scope:** 6 Missing Workflows + 7 Workflow Flaws + 5 Redundancies

---

## PART 1: MISSING WORKFLOWS

### 1.1 KYC Document Expiry Blocking

**Problem:** Nothing blocks transactions when KYC documents are expired.

**Design:**
- New method: `ComplianceService::mustBlockDueToExpiredDocuments(Customer $customer): bool`
- Called in `TransactionService::createTransaction()` before CDD determination
- New exception: `CddDocumentExpiredException`
- 5-day grace period (configurable via `ThresholdService::getKycGracePeriodDays()`)
- Auto-generate compliance alert when document expires
- **API:** `POST /api/v1/transactions` blocks with expired KYC
- **Web:** Error message shown on transaction attempt

**Trigger:** Real-time on every transaction creation

---

### 1.2 Periodic Customer Risk Review Job

**Problem:** `next_screening_date` calculated but no CRON triggers rescreening.

**Design:**
- New Artisan Command: `customer:risk-review`
- Scheduled daily at 02:00 AM
- Queries `RiskScoreSnapshot::needsRescreening()`
- Batch process (50 per run, configurable via `ThresholdService`)
- Calls `CustomerRiskScoringService::recalculateForCustomer()`
- Summary report to compliance on completion
- High-risk escalations flagged immediately

**Trigger:** Scheduled daily at 02:00

---

### 1.3 Stock Reservation Expiry Job

**Problem:** `StockReservation.expires_at` exists but no cron releases expired reservations.

**Design:**
- New Artisan Command: `reservation:expire`
- Runs every 15 minutes
- Queries expired `Pending` reservations
- Calls `CurrencyPositionService::releaseStockReservation()`
- Notifies teller of cancellation
- Manager digest: "N reservations expired in 24h"
- Transaction linked to reservation transitions to `PendingCancellation`
- Metrics logged in `SystemLog`

**Trigger:** Every 15 minutes

---

### 1.4 Month-End Auto-Closing Sequence

**Problem:** Revaluation + period close are separate manual steps.

**Design:**
- New Service: `MonthEndCloseService`
- Entry point: `runMonthEndClosing(Carbon $date, User $initiator)`
- Sequence:
  1. Pre-flight: Verify all counters closed, no open sessions
  2. Run Revaluation
  3. Generate Reports (trial balance, P&L, balance sheet)
  4. Archive Reports (`period_type = 'monthly'`)
  5. Close Accounting Period
  6. Create Audit Log
- New Artisan Command: `accounting:month-end --date=YYYY-MM-DD`
- Scheduled: 1st of month at 01:00 AM
- Failure handling: rollback + alert compliance
- **API:** `POST /api/v1/accounting/month-end`
- **Web:** `GET /accounting/month-end`

**Trigger:** Scheduled (1st month 01:00) or manual

---

### 1.5 Branch Closing Workflow

**Problem:** `BranchController::destroy()` only soft-deletes, no settlement workflow.

**Design:**
- New Service: `BranchClosingService`
- New Model: `BranchClosureWorkflow`
- Pre-flight checks:
  - All counter sessions `Closed`
  - All teller allocations `Returned`
  - All stock transfers `Completed` or `Cancelled`
  - No pending `PendingApproval` transactions
- Settlement phase:
  - Pool balances → HQ pool
  - Outstanding obligations resolved
  - Pending CTOS/STR submitted
- User reassignment/deactivation
- Final: `is_active = false`

**Workflow State:** `Active → Initiated → Settlement → Finalizing → Closed`

**API Endpoints:**
- `POST /api/v1/branches/{id}/closure/initiate`
- `GET /api/v1/branches/{id}/closure/checklist`
- `POST /api/v1/branches/{id}/closure/settle`
- `POST /api/v1/branches/{id}/closure/finalize`
- `GET /api/v1/branches/{id}/closure/status`

**Web Views:**
- `GET /branches/{branch}/close`
- `GET /branches/{branch}/close/status`

**Trigger:** Manual (Admin initiates)

---

### 1.6 Emergency Counter Closure

**Problem:** No override for legitimate emergency closures (fire alarm, security).

**Design:**
- New Service: `EmergencyCounterService`
- New Enum: `CounterSessionStatus::EmergencyClosed`
- `initiateEmergencyClose(Counter $counter, User $teller, string $reason)`
- No variance calculation on emergency close
- Immediately releases teller allocation
- Post-emergency manager review (within 24h)
- Compliance monthly review of all `EmergencyClosed` sessions

**Constraints:**
- One emergency close per counter per 4 hours
- Cannot emergency close if session < 30 minutes
- MFA verification still required

**API Endpoints:**
- `POST /api/v1/counters/{counterId}/emergency-close`
- `GET /api/v1/counters/{counterId}/emergency/{closureId}/variance`
- `POST /api/v1/counters/{counterId}/emergency/{closureId}/acknowledge`

**Web Views:**
- `GET /counters/{counter}/emergency-close`
- `GET /counters/{counter}/emergency/{closureId}`

**Trigger:** Manual (teller in emergency)

---

## PART 2: WORKFLOW FLAWS

### 2.1 Flaw A: Direct Cancel Bypasses Dual Control

**Problem:** `cancelTransaction()` goes directly to `Cancelled` without `PendingCancellation` state.

**Design:**
- Remove direct bypass path
- ALL cancellations → `PendingCancellation` → `Cancelled`
- `cancelTransaction()` → `requestCancellation()`
- Remove `POST /transactions/{id}/cancel` route (use request-cancellation)
- Update views to "Request Cancellation" button

---

### 2.2 Flaw B: Handover Phase 2 Has No Controller

**Problem:** `acknowledgeHandover()` method exists but no route exposes it.

**Design:**
- New API endpoint: `POST /api/v1/counters/{counterId}/handover/{handoverId}/acknowledge`
- Web routes for acknowledge form
- Validate: session `PendingHandover`, user is incoming teller, physical count verified
- On success: session status → `Open`

**API:** `POST /api/v1/counters/{counterId}/handover/{handoverId}/acknowledge`
**Web:** `GET|POST /counters/{counter}/handover/{handoverId}/acknowledge`

---

### 2.3 Flaw C: Allocation Rejection Doesn't Release Pool Balance

**Problem:** Rejection sets status string but never releases pool balance.

**Design:**
- New method: `TellerAllocationService::rejectAllocation()`
- Release pool funds before updating allocation status
- Use `TellerAllocationStatus::REJECTED` enum
- Add `REJECTED` to enum if missing

**API:** `POST /api/v1/allocations/{id}/reject`

---

### 2.4 Flaw E: copyPrevious Uses Current Spread

**Problem:** Reconstructs rates using current spread, not historical.

**Design:**
- Add `spread_applied` column to `exchange_rates` table
- Store spread when rate is set (fetched, overridden, copied)
- Use stored spread on copyPrevious

**API:** `GET /api/v1/rates/{currency}/history/{date}` returns spread_applied

---

### 2.5 Flaw F: Journal Entry — Direct Posting

**Problem:** Journal entry workflow unclear.

**Design:**
- Accountant creates → Direct posting (no draft state)
- Entry immediately posted to ledger
- Reverse if needed (creates new reversing entries)
- Original marked `Reversed`

**API:**
- `POST /api/v1/accounting/journal` — Create and post
- `POST /api/v1/accounting/journal/{id}/reverse` — Reverse

**Web:**
- `GET /accounting/journal/create`
- `POST /accounting/journal`
- `POST /accounting/journal/{entry}/reverse`

---

## PART 3: REDUNDANCIES

### 3.1 Redundancy #1: Two Counter Opening Services

**Design:**
- Merge into single `CounterService`
- `CounterOpeningWorkflowService` removed (or kept as thin coordinator if workflow orchestration is complex)

### 3.2 Redundancy #2: Two Transaction Approval Controllers

**Design:**
- New `TransactionApprovalService` (or refactor existing service)
- Both web and API controllers delegate to service
- Single validation logic

### 3.3 Redundancy #3: Three Currency Position Services

**Design:**
- `CurrencyPositionService` = single source of truth
- `BranchPoolService` delegates to it
- `CounterService` delegates to it for opening floats

### 3.4 Redundancy #4: CTOS Threshold Checked Twice

**Design:**
- `ThresholdService::getCtosThreshold()` = single source
- `ComplianceService::requiresCtos()` removed
- `CtosReportService::qualifiesForCtos()` delegates to service

---

## REMOVED FROM SCOPE

- ~~Batch Transaction Workflow~~ — Removed
- ~~Batch Import Stock Reservation~~ — Removed
- ~~Flaw D (Batch Import bypasses stock reservation)~~ — Removed

---

## FILES TO CREATE

### New Services
- `app/Services/KycDocumentExpiryService.php`
- `app/Services/EmergencyCounterService.php`
- `app/Services/BranchClosingService.php`
- `app/Services/MonthEndCloseService.php`
- `app/Services/CustomerRiskReviewService.php`
- `app/Services/TransactionApprovalService.php`
- `app/Services/CounterHandoverService.php`

### New Commands
- `app/Console/Commands/CustomerRiskReviewCommand.php`
- `app/Console/Commands/ReservationExpireCommand.php`
- `app/Console/Commands/MonthEndCloseCommand.php`
- `app/Console/Commands/BranchCloseCommand.php`
- `app/Console/Commands/EmergencyCloseCommand.php`

### New Controllers
- `app/Http/Controllers/Api/V1/CounterHandoverController.php`
- `app/Http/Controllers/Api/V1/BranchClosingController.php`
- `app/Http/Controllers/Api/V1/MonthEndCloseController.php`
- `app/Http/Controllers/Api/V1/CustomerRiskReviewController.php`

### New Models
- `app/Models/BranchClosureWorkflow.php`
- `app/Models/CounterHandover.php`
- `app/Models/EmergencyClosure.php`
- `app/Models/TransactionImportBatch.php` (if batch import not removed)

### New Exceptions
- `app/Exceptions/Domain/CddDocumentExpiredException.php`

### New Enum Values
- `CounterSessionStatus::EmergencyClosed`

### New Database Columns
- `exchange_rates.spread_applied`
- `teller_allocations.rejection_reason`, `rejected_at`, `rejected_by`

---

## FILES TO MODIFY

### Services
- `app/Services/ComplianceService.php` — Add mustBlockDueToExpiredDocuments()
- `app/Services/TransactionService.php` — Call KYC expiry check
- `app/Services/TransactionCancellationService.php` — Remove direct cancel bypass
- `app/Services/CurrencyPositionService.php` — reserveForTransaction method
- `app/Services/TellerAllocationService.php` — rejectAllocation method
- `app/Services/AccountingService.php` — Simplify journal entry (direct post)
- `app/Services/RevaluationService.php` — Store spread_applied
- `app/Services/RateManagementService.php` — Store spread_applied
- `app/Services/CounterService.php` — Merge CounterOpeningWorkflowService
- `app/Services/CounterOpeningWorkflowService.php` — May be merged
- `app/Services/BranchPoolService.php` — Delegate to CurrencyPositionService

### Controllers
- `app/Http/Controllers/Transaction/TransactionApprovalController.php` — Delegate to service
- `app/Http/Controllers/Api/V1/TransactionApprovalController.php` — Delegate to service
- `app/Http/Controllers/Api/V1/TellerAllocationController.php` — Use rejectAllocation
- `app/Http/Controllers/Api/V1/RateController.php` — Use stored spread
- `app/Http/Controllers/RateController.php` — Use stored spread
- `app/Http/Controllers/CounterController.php` — Add emergency close, acknowledge handover
- `app/Http/Controllers/BranchController.php` — Add closure workflow
- `app/Http/Controllers/AccountingController.php` — Direct journal posting

### Models
- `app/Models/CounterSession.php` — Add EmergencyClosed status
- `app/Models/ExchangeRate.php` — Add spread_applied
- `app/Models/TellerAllocation.php` — Add rejection fields
- `app/Models/StockReservation.php` — Add transaction_id link

### Jobs
- `app/Jobs/CustomerRiskReviewJob.php` (scheduled)
- `app/Jobs/ReservationExpireJob.php` (scheduled)

### Middleware
- `app/Http/Middleware/EnsureMfaVerified.php` — Already exists, verify emergency use

### Config
- `config/thresholds.php` — Add `kyc_grace_period_days`, `risk_review_batch_size`

### Routes
- Remove `POST /transactions/{id}/cancel` (use request-cancellation)
- Add emergency close routes
- Add handover acknowledge routes
- Add branch closure routes
- Add month-end routes
- Add journal reversal routes

### Views
- `resources/views/accounting/journal/` — Direct posting UI
- `resources/views/counters/` — Emergency close, acknowledge
- `resources/views/branches/` — Closure workflow
- `resources/views/transactions/` — Cancellation flow updated

---

## TESTING REQUIREMENTS

### Unit Tests
- `tests/Unit/KycDocumentExpiryServiceTest.php`
- `tests/Unit/CustomerRiskReviewServiceTest.php`
- `tests/Unit/BranchClosingServiceTest.php`
- `tests/Unit/EmergencyCounterServiceTest.php`
- `tests/Unit/MonthEndCloseServiceTest.php`
- `tests/Unit/TransactionApprovalServiceTest.php`

### Feature Tests
- `tests/Feature/EmergencyCounterCloseTest.php`
- `tests/Feature/BranchClosingWorkflowTest.php`
- `tests/Feature/MonthEndCloseTest.php`
- `tests/Feature/CounterHandoverAcknowledgeTest.php`
- `tests/Feature/JournalEntryWorkflowTest.php` (updated)

### Integration Tests
- `tests/Feature/KycExpiryBlockingIntegrationTest.php`
- `tests/Feature/ReservationExpiryIntegrationTest.php`

---

## IMPLEMENTATION ORDER

### Phase 1: Foundation Fixes
1. Flaw A: Direct Cancel bypass
2. Flaw C: Allocation Rejection balance release
3. Flaw E: copyPrevious spread fix
4. Flaw F: Journal entry simplification

### Phase 2: Compliance (BNM Critical)
5. KYC Document Expiry Blocking
6. Periodic Customer Risk Review Job

### Phase 3: Operational Workflows
7. Stock Reservation Expiry Job
8. Month-End Auto-Closing
9. Emergency Counter Closure

### Phase 4: Complex Workflows
10. Branch Closing Workflow
11. Handover Phase 2 (acknowledge endpoint)

### Phase 5: Consolidation
12. Redundancy #1-4 (merge services)
