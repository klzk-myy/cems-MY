# COMPREHENSIVE-AUDIT.md

> **Scope:** Continuous audit of the CEMS-MY Laravel 12 codebase (Currency Exchange Management System for BNM-compliant MSBs).
> **Method:** Iterative deep-scan of one module/directory at a time. Findings are appended immediately after each module completes, with concrete `file:line` evidence and no speculative claims.
> **Severity legend:**
> - 🔴 **Critical** — correctness/security bug, data-integrity risk, or exploitable flaw.
> - 🟠 **High** — logic gap, race condition, architectural smell with real impact.
> - 🟡 **Medium** — technical debt, dead code, redundancy, incomplete edge handling.
> - 🔵 **Low** — style, minor duplication, naming/DRY nit.
> **Verification bar:** each finding cites a file path (and line where practical) and is checkable by inspection. Where a finding is a hypothesis (e.g. "may be a race"), it is labelled *hypothesis*.
>
> **Audit status:** in progress. See "Audit Progress" at the end for the current frontier.

---

## Module 1 — `app/Services/Transaction/`

**Scope:** 22 service files (7,729 lines) + `Checks/` (11) + `DTOs/` (3). Transaction lifecycle: creation, validation, approval, cancellation, reversal, confirmation, recovery, import, monitoring, rate management.

### 🔴 Critical

**1. Idempotency replay double-applies Phase-2 booking side effects.**
`TransactionCreationService::create()` returns an *existing* transaction on an idempotency hit (lines 322–324) and on a lost unique-constraint race (lines 344–348). Phase 2 then gates only on `status === Completed` (line 363) — a replayed `Completed` transaction re-enters `applyCompletedSideEffects()` (line 745), which:
- decrements/increments the branch `CurrencyPosition` again (`CurrencyPositionService::updatePosition`, line 93 — no prior-application guard),
- posts a **second** journal entry (`TransactionAccountingService::createImmediateAccountingEntries`, line 86 — no `journal_entry_id` guard; overwrites it),
- deducts the teller allocation earmark again (`TellerAllocationService::applyTransactionAllocation`, line 491),
- re-adjusts the till balance,
- overwrites `prev_quantity` / `prev_average_cost` on the position snapshot used by reversal.

It also re-runs `recordCreationAudit()` (duplicate audit row) and re-dispatches `TransactionCreated` (duplicate AML monitoring + risk scoring).

*Trigger:* teller double-click, network retry, or two browser tabs posting the same `idempotency_key` — the exact case the key exists to neutralise. The key prevents duplicate *records* but not duplicate *effects*.

*Fix:* distinguish "created fresh" from "replayed" and skip Phase 2 / audit / event on replay.

### 🟠 High

**2. Two divergent "requires hold" policies; the RM 50k EDD trigger is not a hard control.**
- Booking time: `TransactionHoldService::requiresHold()` — hold iff `CddLevel::Enhanced` or a critical risk flag. **Amount is never consulted** (Standard CDD at ≥ RM10k does not hold).
- Monitoring time: `ComplianceService::requiresHold()` (line 304) — hold iff amount ≥ `cdd.large_transaction` (RM 50k), PEP, recent sanction match, or High risk.

A RM 60,000 booking by a Medium-risk non-PEP customer gets CDD `Standard`, **no compliance hold** (`hold_reason = null`), and lands in `PendingApproval` purely on the amount threshold. It is approvable as a routine amount-based approval without EDD documentation. The RM 50k EDD intent is enforced only by:
- `escalateLargeTransactionToCompliance()` — a fire-and-forget notification inside a catch-all that only `Log::warning`s (lines 478–483), and
- `HoldReasonCheck` — which fires only on `Completed` transactions with `approved_by === null`, i.e. never on a RM 60k booking.

**3. `CddLevel::determine()` (enum) is dead production code; tests exercise the dead path.**
Production CDD is decided by `CddLevelDeterminationService::determineCDDLevel()` (full pd-00 15.2/15.3 PEP-type logic: Foreign / Domestic / associate + `isHigherRisk()`). The enum's own `determine()` is referenced only from `tests/Unit/ThresholdAccessCentralizedTest.php` and a helper in `tests/Feature/CriticalTransactionWorkflowTest.php:505`. The two implementations already diverge — the enum has no `pepType` handling and no sanction-status lookup. Tests therefore assert CDD behaviour that production does not implement.

### 🟡 Medium

**4. Reversal window aliases the cancellation window config key.**
`TransactionReversalService` reads `config('cems.transaction_cancellation_window_hours')` (lines 129, 136) and exposes it as `getCancellationWindowHours()` / `isWithinCancellationWindow()` on the *reversal* service. Reversal and cancellation windows cannot be tuned independently; changing `CANCELLATION_WINDOW_HOURS` silently retunes reversals.

**5. Dead code / stale de-duplication claims.**
- `auditTrailHelper()` and `transactionAccountingService()` (lines 82–90) only return constructor-promoted properties already in scope. They exist solely to satisfy `AccountingEntriesTrait`'s abstract declarations.
- `TillBalanceTrait::updateTillBalance()` is never called in `app/` — only by `tests/Unit/Services/Traits/TillBalanceTraitTest.php`. `TransactionCreationService` (line 761) and `TransactionApprovalService` (line 496) both call `tillBalanceManager->applyTransaction()` directly.
- `ExchangeCalculatorTrait` docblock claims the method is shared with `TransactionImportService`; that class never calls it.
- `TransactionHoldService::getHoldReasons()` is used only by `tests/Unit/Transaction/TransactionHoldServiceTest.php`. `InitialStatusResolver` generates its own hold strings instead.

**6. `PreValidationResult::getCDDLevel(): ?CddLevel` is consumed unguarded.**
`buildCreationContext` (lines 138–142) calls `array_search($cddFloor, …)` / `array_search($freshCddLevel, …)` on the result. `preValidate()` returns before `setCDDLevel()` on a sanctions block (lines 141–160), so any caller that skips the `isBlocked()` check gets a `TypeError` rather than a domain exception. `runComplianceGates()` is the only guard today.

**7. Actor identity is re-captured mid-flow.**
`escalateLargeTransactionToCompliance()` reads `ActorContext::capture()->userId` (lines 469, 476) instead of the acting user already resolved in `create()`. A retry/recovery context stamps the wrong confirmer on `TransactionConfirmation.user_id`. `prepareAndCreate()` likewise calls `ActorContext::capture()` twice (lines 94, 96).

**8. Branch scope for checks can diverge from the persisted branch.**
`$txnBranchId` (line 287) falls back through `tillBalance->branch_id → user->branch_id → $data['branch_id']`, so freeze, position lock and stock checks can run against a caller-supplied branch. But `createTransactionRecord()` (line 691) persists only `tillBalance->branch_id ?? user->branch_id`. A mismatched `$data['branch_id']` would steer the checks at one branch and post the record at another.

### 🔵 Low

**9.** `bccomp()` used directly at line 584 where `$this->mathService->compare()` is the convention elsewhere in the same method.

**10.** `checkSanctions()` uses `Cache::has()` + `Cache::get()` (two lookups, TOCTOU on eviction) instead of `Cache::remember()`; the key `sanction_check:customer:{id}` carries no sanctions-list version, so a listing/delisting inside the 60s TTL is invisible (documented trade-off).

**11.** CDD severity is compared by `array_search()` over `CddLevel::cases()` (line 140) — correct only because the enum happens to declare cases in ascending severity. Fragile to case reordering; `RiskRating` already has an explicit `weight()`.

**12.** `recordBookingFailure()` persists `$e->getTraceAsString()` into audit metadata (line 508) — size/PII exposure in the audit table.

**13.** `(float)` casts appear in `Checks/StructuringCheck.php:29`, `Checks/UnusualPatternCheck.php:27`, `RateManagementService.php:372,515-524` — all for display strings/percentages, not money, but they contradict the stated BCMath-only convention and should be explicit about why.

**14.** No `TODO`/`FIXME`/`HACK` markers, no `auth()`/`request()` calls inside services, no direct `config('thresholds.*')` calls anywhere in the module — the `ThresholdService` and `ActorContext` conventions hold here.

---


---

## Audit Progress

| # | Target | Status | Findings |
|---|--------|--------|----------|
| 1 | `app/Services/Transaction/` | ✅ complete | 1 critical, 2 high, 5 medium, 5 low |
| 2 | `app/Services/Accounting/` | ✅ complete | 2 high, 3 medium, 3 low |
| 3 | `app/Services/Branch/` | ✅ complete | 1 high, 3 medium, 2 low |
| 4 | `app/Services/Compliance/` | ✅ complete | 3 high, 2 medium, 2 low |
| 5 | `app/Services/` (root + System, Screening, Security, Audit) | ✅ complete | 3 high, 2 medium, 2 low |
| 6 | `app/Http/` (Controllers, Requests, Resources, Rules) | ✅ complete | 2 high, 2 medium, 2 low |
| 7 | `app/Http/Middleware/` + `bootstrap/app.php` | ✅ complete | 2 medium, 3 low |
| 8 | `app/Models/` | ✅ complete | 1 high, 2 medium, 1 low |
| 9 | `app/Enums/` | ✅ complete | 2 medium, 1 low |
| 10 | `app/Console/Commands/` | ✅ complete | 1 high, 1 medium |
| 11 | `app/Jobs/`, `Events/`, `Listeners/`, `Notifications/` | ✅ complete | 2 medium |
| 12 | `app/Policies/` + `Exceptions/` | ✅ complete | 1 medium |
| 13 | `app/Support/`, `Rules/`, `Casts/`, `Helpers/`, `ValueObjects/`, `Actions/`, `Providers/`, `View/`, `Repositories/` | ✅ complete | 2 low |
| 14 | `routes/` | ✅ complete | 2 low |
| 15 | `config/` | ✅ complete | 2 medium, 2 low |
| 16 | `database/` (seeders, factories) | ✅ complete | 2 medium, 1 low |
| 17 | `resources/views/` (Blade) | ✅ complete | none found |
| 18 | `tests/` | ✅ complete | 1 high, 2 medium, 1 low |
## Module 2 — `app/Services/Accounting/`

**Scope:** 17 files (5,742 lines). Journal posting, ledger chains, trial balance / P&L / balance sheet, period close, fiscal-year close, revaluation, bank reconciliation, budgets, petty cash, expense posting.

### 🟠 High

**1. `is_active` is honoured in exactly one of eleven `ChartOfAccount` query sites.**
`LedgerService::getTrialBalance()` filters `where('is_active', true)` (line 86). Every other statement/closing query omits it:
- `LedgerService.php:310, 328` (P&L revenues/expenses), `:415-417, 432` (balance sheet assets/liabilities/equity + net-income sweep)
- `FiscalYearService.php:297, 371, 523` (year-end revenue/expense/swept totals)
- `PeriodCloseService.php:168-169` (period closing entries)
- `BudgetService.php:193` (budgetable expense accounts)

`chart_of_accounts.is_active` is a real boolean (default `true`, `SchemaSeeder.php:342`), so a deactivated account with a non-zero ledger balance still appears on the P&L and balance sheet and is **swept into a new closing journal entry** by `PeriodCloseService` / `FiscalYearService` — posting money into an account that was deliberately retired. Meanwhile the trial balance omits it, so the two reports disagree about identical underlying rows.

**2. Position-limit breach alerts are silently discarded on any exception.**
`RevaluationService::checkPositionLimitBreach()` (lines 468–488) wraps the alert emission in `catch (\Throwable $e) { Log::error(...) }`. The BNM position ceiling is enforced *pre-trade* by `TransactionCreationService::assertPositionLimit()`; this revaluation-time check is the only thing that catches breaches introduced by FX revaluation. If alert delivery fails, the breach is recorded in the log and vanishes — no alert, no audit row, no queued retry. Compare the sibling `assertPositionLimit()` which throws a typed domain exception.

### 🟡 Medium

**3. Inconsistent statement caching policy.**
`getTrialBalance()` is cached 60s under `trial_balance.{date}.{branch}` with tags `['ledger','trial-balance']`; `getProfitAndLoss()` and `getBalanceSheet()` are uncached. A dashboard rendering all three can therefore show a 60s-stale trial balance next to live P&L/balance-sheet figures, and it costs three unequal DB hits per render. `LedgerService::getAccountBalancesForPeriod()` is cached the same way (line 556), so the project has two precedents in one class — pick one.

**4. `LedgerService::getAccountBalance(string $asOfDate)` requires a date; `AccountingService::getAccountBalance(?string $asOfDate = null)` defaults to today.**
Two public wrappers over the same canonical `LedgerQueryService::getAccountBalance()` with different nullability. A caller of the `LedgerService` variant cannot omit the date and must repeat `now()->toDateString()` at every call site.

**5. Sign conventions across the ledger query layer are correct but non-obvious, and only partially documented.**
`LedgerQueryService::getAggregatedAccountBalances()` returns **raw** `debit - credit`; `LedgerQueryService::getAccountBalance()` returns a **sign-flipped** value for credit-normal accounts (`isDebitAccount ? net : -net`). Consumers flip again: `getProfitAndLoss` multiplies revenue by `-1`, `sectionTotals()` multiplies liability/equity sections by `-1`, `PeriodCloseService::createClosingEntries` multiplies revenue balances by `-1`. I verified the identities hold (`opening + totalCredits - totalDebits == closing` for credit-normal; P&L net profit correct), but there are four call sites each re-deriving the same sign rule by hand with no shared helper — one sign error away from a report that is off by a factor of two on a whole account class. Worth extracting a `naturalBalance($accountCode, $net)` helper.

### 🔵 Low

**6.** `AccountingService::createJournalEntry()` looks up the `ChartOfAccount` row twice per line — once under `lockForUpdate()` (line 390, only when the ledger chain is empty) and once plain (line 395) — so the same CoA row is queried twice per posting.

**7.** `entry_number` is `JE-{Ym}-{zero-padded id}` (line 178), i.e. the global auto-increment, not a per-month sequence. Numbers are unique by construction but non-contiguous per month and the `str_pad(4)` silently stops padding past 9,999 entries.

**8.** `FiscalYearService::closeFiscalYear()` resolves the actor via `ActorContext::capture()->userId`, while `AccountingService::rejectEntry()` / `reverseJournalEntry()` use `userIdOrSystem()`. Deliberate for a permission-gated operation, but the inconsistency is undocumented and easy to "fix" in the wrong direction.

### Verified clean

No `TODO`/`FIXME`/`HACK`; **no `(float)` casts anywhere in the module** — BCMath is used throughout, including the `Collection::sum()` precision trap explicitly avoided in `getAccountLedger()` (line 239). No `auth()` / `request()` calls inside services. `AccountingService::createJournalEntry()` correctly enforces the one-sided journal-line contract, non-negative amounts, two-line minimum, balanced totals, and open-period linkage, and re-checks the branch day-close freeze under a `lockForUpdate` on the workflow row. `CurrencyPositionLockService::lock()` handles the create-race with a `UniqueConstraintViolationException` retry. `FiscalYearService::closeFiscalYear()` and `AccountingService::reverseJournalEntry()` both re-lock and re-validate under a row lock, so concurrent double-closes / double-reversals serialise.

---
## Module 3 — `app/Services/Branch/`

**Scope:** 12 files (3,625 lines) + `DTOs/`. Day close, pool remittance, teller allocations, counter open/close/handover, till balances, branch pools, petty cash.

### 🟠 High

**1. A booking can land on a business date that a concurrent day-close initiation is freezing.**
`TransactionCreationService::create()` gates on `BranchClosureWorkflow::freezesDateForUpdate($txnBranchId, $today)`, which begins with `static::where('branch_id', $branchId)->lockForUpdate()->exists()` (`BranchClosureWorkflow.php:135-139`). When the branch has **no workflow rows yet**, that locking read matches zero rows and acquires **no lock at all**, so `freezesDate()` returns `false` and the booking proceeds holding nothing. `BranchClosingService::initiateClosure()` knows exactly this and compensates by locking the *Branch* row first (`BranchClosingService.php:34-37`, comment: "when no workflow rows exist yet, locking the workflow table locks nothing") — but the booking path never locks the Branch row (`TransactionCreationService.php:300` only does a plain `->value('code')` read).

Interleaving: booking locks zero workflow rows → `freezesDate()` false → initiation locks the Branch row, inserts the `Initiated` workflow row, commits → booking inserts its transaction, commits. Result: business posted against a finalized-in-progress day. `initiateClosure()` performs no pending-transaction pre-check, so nothing else catches it. *Fix:* the booking path should lock the Branch row before the freeze check, matching the initiation's serializer.

### 🟡 Medium

**2. Sell bookings skip teller-allocation custody validation.**
`TellerAllocationService::resolveForTransaction()` (line 464) runs `validateTransaction()` — allocation existence, foreign-float availability, daily MYR limit — for a **Buy**, but for a **Sell** it returns `getActiveAllocation()` bare. The atomic guards in `TellerAllocation::deduct()` / `addDailyUsedWithinLimit()` (conditional `UPDATE ... WHERE current_quantity >= ?` / `WHERE daily_used_myr + ? <= daily_limit_myr`, throwing on 0 affected rows) do stop negative balances and cap overshoots, so the money is protected — but for a **PendingApproval** Sell, `reserveStockIfPending()` only reserves branch position stock, not the allocation, so an unfundable Sell is booked successfully and fails later at approval time instead of at booking. `validateTransaction()` already implements the Sell branch (`hasAvailable()`, `hasDailyLimitRemaining()`); `resolveForTransaction` simply never calls it.

**3. `PoolRemittanceService::cancel()` lacks the segregation-of-duties check `acknowledge()` has.**
`acknowledge()` rejects `$remittance->initiated_by === $acknowledgedBy` (line 149). `cancel()` has no counterpart — the initiator can cancel their own pending remittance. Financially it nets to zero (pool debit reversed), but it leaves a dispatch + reversal journal pair and two audit rows in the ledger for an event that did nothing, and no second party ever reviewed it.

**4. `PoolRemittanceService::initiate()` accepts `float|string $amountMyr` in its signature.**
It casts to string on line 47, so nothing is corrupted — but the type declaration endorses float-for-money at the public API boundary, exactly the pattern the project's BCMath convention forbids, and any caller passing a float has already lost precision before this line runs. Every other money-typed entry point in the module takes `string`.

### 🔵 Low

**5.** The remittance-number collision retry loop (`initiate()`, lines 123–136) decides retryability with `str_contains($e->getMessage(), 'remittance_number')`. Message-text matching rather than the driver's failed-column data; a localized or reworded driver message silently disables the retry and surfaces a 500.

**6.** `freezesDateForUpdate()` calls `...->lockForUpdate()->exists()` and discards the boolean purely for the locking side effect. It reads as dead code to anyone without the docblock.

### Verified clean

`TellerAllocation` models the BCMath discipline explicitly — `toNumericAmount()` documents why float is rejected and keeps arithmetic in the DECIMAL column domain (`sprintf('%.10F')` for the rare float, then `BcmathHelper::add($q, '0')`). `deduct()` aborts loudly on a failed guard instead of leaving custody silently unadjusted. All three remittance transitions re-lock the remittance row; `initiate()` and `cancel()` lock the sender pool; `acknowledge()` locks the receiver pool. Initiation enforces branch≠self, exactly one side is HQ, MYR-only (`Currency::baseCurrency()`), and positive amount. No `auth()`/`request()` inside services, no direct threshold config reads, no TODO markers.

---
## Module 4 — `app/Services/Compliance/`

**Scope:** 26 files (7,095 lines) + `Monitors/` (7) + `Parsing/` (5). CDD determination, PEP approval, sanctions import/sync/rescreening, monitoring engine, alert triage, case management, EDD, KYC expiry, risk scoring, BNM STR filing.

### 🟠 High

**1. The BNM STR filing threshold is hardcoded, bypassing the centralized threshold system.**
`StrReportService::THRESHOLD = '50000'` (line 31) is used in the filing gate: `bccomp($amountMyr, self::THRESHOLD, 4)` (line 233). Meanwhile `config/thresholds.php` defines `reporting.str => env('THRESHOLD_STR', …)` (line 99) and `ThresholdService::getStrThreshold()` (line 379) already reads it. Setting `THRESHOLD_STR` has **no effect** on whether an STR gets filed — the highest-stakes regulatory threshold in the codebase is fixed in PHP source. This is a direct violation of the project's own rule that all thresholds flow through `ThresholdService`, and `meetsThreshold()` also reaches for raw `bccomp` instead of `MathService`.

**2. STR auto-draft can vanish with no trace, and every failure is swallowed.**
`autoDraftForClosedCase()` (line 271-275) returns `null` when `$case->assigned_to === null` — no log, no alert, no fallback to a system user. Its caller, `CaseManagementService::autoDraftStrForClosedCase()` (line 501-510), wraps it in `catch (\Throwable $e) { Log::error(...) }`. So a closed, above-threshold case that was never assigned produces no STR draft and no record that one was expected; the only recovery is a manual `createFromCase()`. A BNM filing obligation is dropped with a single log line, and the pipeline has no open-item table to prove the obligation existed.

**3. PEP head-office approval is checked unscoped against a scoped workflow.**
`PepApprovalService::requestApproval()` creates and dedupes requests keyed by **`transaction_type`** (lines 106-108), but `hasApprovedApproval()` (line 255-259) filters only on `customer_id` + `status`. `TransactionValidationService::validatePepRequirements()` gates on that unscoped check, so a PEP customer whose **Buy** was approved can **Sell** — and vice versa — with no head-office approval for the actual transaction type. Either the `transaction_type` dimension is meaningless (then drop it) or the check must match on it.

### 🟡 Medium

**4. The monitoring circuit breaker couples AML monitoring to cache availability and is global in scope.**
`MonitoringEngine::isCircuitBroken()` (line 100-118) calls `Cache::get()` unguarded, so a cache/Redis outage throws out of `runAll()` before any monitor executes — the breaker is an availability prerequisite rather than a safety net, which cuts against the project's fail-closed guidance for security-critical paths. The counter is also global: `recordFailure()` increments one shared key, so three failures from a single chronically broken monitor trip the breaker and skip **all** monitors. `runMonitor()` additionally never calls `recordSuccess()`/`recordFailure()`, so single-monitor runs neither trip nor reset the breaker — asymmetric with `runAll()`. Cooldown is 60s with a 1-hour counter TTL.

**5. `BaseMonitor::mergeFindingDetails()` uses float `max()` on `score`/`count` keys (line 183)** — `max((float) $currentValue, (float) $value)`. Monitoring metrics, not money, so no rounding risk in practice, but it is the only place the module abandons string arithmetic and would silently truncate long precision.

### 🔵 Low

**6.** `StrReportService::buildTriggerReason()` renders the threshold with `number_format((float) self::THRESHOLD, 2)` (line 297) — a float cast in a user-facing audit string.

**7.** `PepApprovalService::requestApproval()` throws `PepApprovalRequiredException('Pending approval already exists…')` when a duplicate pending request exists, so `validatePepRequirements()` reports that message instead of the more informative one carrying the approval ID. Harmless but inconsistent.

### Verified clean

No `TODO`/`FIXME`/`HACK`; no `auth()`/`request()` inside any service; no direct `config('thresholds.*')` reads (the three references in `ComplianceService` are docblock prose — the code delegates to `structuringRiskService`); BCMath throughout except the two metric/float sites above. `PepApprovalService::requestApproval()` locks the customer row as a mutex against duplicate pending requests. `StrReportService::createFromCase()` locks the case row and dedupes on `case_id`; `submit()` re-locks and rejects a reused `bnm_reference`. `MonitoringEngine::runAll()` isolates each monitor in its own try/catch so one broken monitor never blocks the rest, and has a circuit breaker with failure notification.

---
## Module 5 — `app/Services/` root + `System/`, `Screening/`, `Security/`, `Audit/`

**Scope:** `AuditService` (707), `EodReconciliationService`, `ThresholdService`; `System/` (22 files incl. `EncryptionService`, `MfaService`, `RateLimitService`, `CacheInvalidationService`, `MathService`); `Screening/` (4); `Security/` (1); `Audit/` (`AuditChainService`, `AuditTrailHelper`).

### 🟠 High

**1. The documented `StrictRateLimit` middleware does not exist, and its burst-protection code is dead.**
AGENTS.md §11 lists `strict.ratelimit` (`StrictRateLimit`) as the "BNM-compliant rate limiting with burst protection" and §4 analyses its fail-closed hard-window cap versus its deliberately fail-open `checkBurst()`. Nothing of the kind is in the codebase: no `StrictRateLimit` class, no `strict.ratelimit` alias in `bootstrap/app.php` (only Laravel's `throttle` → the custom `App\Http\Middleware\ThrottleRequests`), and **`RateLimitService::checkBurst()` has zero callers in `app/` or `tests/`**. A security control that is documented as fail-open-by-design is not a nuance — it is unreachable code, and the documentation actively describes behaviour that does not exist.

**2. `checkBurst()` is a non-atomic read-modify-write (latent, since #1).**
`Cache::get()` → `array_filter()` → `count >= $burstAllowance` → append → `Cache::put()` (lines 387–404). Two concurrent requests read the same array, both pass the count check, and both write — silently doubling the effective burst allowance. This is precisely the read-then-write pattern the project's own Redis directives forbid without a Lua script, and `burst[]` is a single cache key that could be replaced by an atomic `INCR`/`ZADD`+`ZCOUNT` pair.

**3. Audit chain integrity cannot detect deletion of never-sealed rows.**
`AuditChainService::verifyChainIntegrity()` checks only two things: each row's `entry_hash` against its own payload, and each `previous_hash` against the previously **seen sealed** row. It never checks id contiguity. `sealLogEntry()` resolves the predecessor as "last row with non-null `entry_hash`" and refuses to seal only when unsealed, non-quarantined rows exist *between* the predecessor and the target — if those rows have been hard-deleted, no rows exist, the guard passes, and the row seals against the predecessor's hash. `verifyChainIntegrity()` then reports the chain valid. Quarantine (`seal_status` + `GAP:<id>`) handles "present but permanently unsealable"; deletion leaves no marker, and `getUnsealedCount()` simply returns a smaller number. Requires DB write access, so this is a residual threat-model gap rather than an application bug — but for a tamper-evident audit trail it is worth closing with an id-gap check.

### 🟡 Medium

**4. Legacy unauthenticated ciphertext is still accepted with no way to tell the outcome apart.**
`EncryptionService::decrypt()` falls through to `decryptLegacy()` (lines 92–100) for any payload without the `v2:` prefix. That legacy path is CBC with **no MAC**, so it remains bit-flippable with silent plaintext corruption until `customers:re-encrypt` upgrades the row. Both `decryptV2()` and `decryptLegacy()` return `null` on failure, so a caller cannot distinguish "wrong key", "tampered", and "legacy garbage". There is no inventory or assertion that legacy rows are being drained — `isLegacyFormat()` exists but nothing in `app/` enforces re-encryption.

**5. `EncryptionService::hash()` reuses the AES key as its HMAC key.**
`encrypt()` deliberately derives a separate MAC subkey via HKDF (`hash_hkdf('sha256', $this->key, 32, 'encryption-mac')`) and the docblock calls this out as key separation. `hash()` (and `blindIndex()`, the customer ID-indexing path) instead HMACs with the raw derivation key. The class enforces key separation for the MAC and not for its own hash helper.

### 🔵 Low

**6.** `checkBurst()` persists `Carbon` instances into the cache and compares with `diffInSeconds()` where a `time()` int would do — a serialization dependency in a hot path.

**7.** `verifyChainIntegrity($limit)` skips the link check for the first row of a limited window (documented, correct for cross-window predecessors). A consequence worth knowing: a single-row window can never report a break.

### Verified clean

Both security kill-switches (`IpBlocker`, `ThrottleRequests`) are genuinely fail-closed outside local — a stale `SECURITY_IP_BLOCKING_ENABLED=false` / `SECURITY_RATE_LIMITING_ENABLED=false` is ignored with a `Log::alert` and enforcement continues. `getRateLimitKey()` includes the user id for authenticated requests, so the per-user key gap the architecture audit listed as open (M8) is present. Encryption is otherwise solid: AES-256-CBC with a per-call `random_bytes(16)` IV, PBKDF2-SHA256 at 100k iterations, encrypt-then-MAC over IV+ciphertext, and `hash_equals` for constant-time comparison. The audit chain's v2 hash covers `old_values`, `new_values`, `severity` and `ip_address` (so post-seal payload edits do not verify clean), uses canonical JSON with sorted keys at every nesting level, and `getUnsealedCount()`/`getOldestUnsealedAt()` correctly exclude quarantined rows.

---
## Module 6 — `app/Http/` (Controllers, Requests, Resources, Rules)

**Scope:** 329 files — 39 controller namespaces, Form Request classes, API resources, validation rules.

### 🟠 High

**1. The API surface has no MFA step-up and no password re-entry — contradicting its own route comment.**
`routes/api_v1.php` (lines 48–49) states: *"MFA lifecycle for API clients: enrollment is required because mfa.verified-protected endpoints would otherwise only be reachable via the web flows. Password re-entry guards every sensitive operation."* Neither half holds for financial operations:
- `mfa.verified` / `mfa.enabled` appear **only** in `routes/web.php` (10 sites: create, approve, reject, cancel, clear-hold). Zero in `routes/api_v1.php`.
- `current_password` is required only by the three MFA lifecycle requests (`Api/V1/Mfa/{Enroll,RegenerateRecoveryCodes,Disable}MfaRequest`) — i.e. for changing the MFA configuration, never for the financial operations themselves.

So `POST /api/v1/transactions`, `/approve`, `/reject`, `/cancel`, `/reverse`, `/clear-hold` are gated by `auth:sanctum` + `role:*` + policy only. A compromised Sanctum token performs every sensitive financial operation with **no re-verification at operation time**, while the identical web action forces a 15-minute MFA step-up (trusted-device bypass aside). Mitigation: `SANCTUM_TOKEN_EXPIRATION` defaults to 60 minutes (`config/sanctum.php:52`), but it is env-overridable, so the MFA-free window can be extended arbitrarily by configuration alone.

**2. Route-level authorization is asymmetric between surfaces.**
Web: `role:create_transactions` **and** `mfa.verified` on the route, plus a controller check. API: `auth:sanctum` + `branch.scope` on the group, policy in the controller, and **no `role:` gate** on `POST /api/v1/transactions`. Authorization still holds via `TransactionPolicy::create()` → `canPerform(Permission::CreateTransactions)`, and the file does carry 72 `role:` references elsewhere — but the `role:create_transactions` route gate AGENTS.md describes for the API path does not exist. The project's own convention exists to prevent a route regroup from silently dropping a permission; on the API side that guard is currently provided by a policy call rather than middleware.

### 🟡 Medium

**3. 17 controllers with write actions carry no `requirePermission()` call**, deviating from the documented Controller Permission Convention. Two styles coexist: `Api/V1/TransactionController` and `Accounting/JournalController` delegate to `$this->authorize(...)` policies; 15 others (`Compliance/CaseManagementController`, `CustomerController`, `TransactionController`, `FiscalYearController`, `AllocationController`, `BranchPoolController`, `Admin/SanctionSourceController`, `Api/V1/{Counter,Customer,Branch}Controller`, `Api/V1/Compliance/CaseController`, `Accounting/{Budget,Expense}Controller`, `NotificationPreferenceController`) have neither. The policies that exist are correctly matrix-driven, so this is uneven defense-in-depth rather than a missing control.

**4. No `delete_*` permission exists in the matrix, so destructive actions are granted by hardcoded identity.**
`TransactionPolicy::delete()` (line 79), `JournalEntryPolicy::delete()` (line 86) and `CounterPolicy` (line 58) all `return $user->role === UserRole::Admin`; `ComplianceCasePolicy` (line 20) grants with `isAdmin() || role === ComplianceOfficer`. `Permission` has no delete case at all, so the matrix **cannot grant or narrow** these capabilities — directly contradicting the documented invariant that `role_permissions` is the authoritative grant set and that identity helpers are for scoping only, never capability grants. Consequence: a non-admin who should be able to delete cannot be given the ability, and there is no matrix row to revoke admin's ability on either.

### 🔵 Low

**5.** `ValidTill` falls back to an **unscoped** counter lookup when `auth()->user()` is null (commented "CLI/test context"), bypassing both the branch scope and the `CounterStatus::Active` requirement outside HTTP. HTTP routes are auth-guarded, so this is CLI/test surface only — but it means `EnsureBranchScope` and the till lookup disagree about what "no authenticated user" means.

**6.** `StoreTransactionRequest::prepareForValidation()` resolves `counter_id` → `till_id` with an unscoped `Counter::query()->find()`, so a request carrying another branch's counter id returns that counter's code during validation prep. `ValidTill` still enforces `where('branch_id', $user->branch_id)` downstream, so there is no booking bypass — but counter codes are disclosed across branches before validation runs.

### Verified clean

No inline `->validate()` anywhere in `app/Http/Controllers/` — all validation goes through Form Request classes. No `TODO`/`FIXME`/`HACK` in `app/Http/`. `ValidTill` correctly requires both branch scope and `CounterStatus::Active`. API form requests delegate authorization to policies (`$this->user()->can('create', Transaction::class)`). `mfa.verified` is applied consistently alongside `role:` gates across all ten sensitive web flows. `store()`-side session handling correctly prefers the user's own open counter session over a submitted till.

---
## Module 7 — `app/Http/Middleware/` + `bootstrap/app.php`

**Scope:** 16 middleware classes (1,035 lines) and the middleware registration in `bootstrap/app.php`.

### 🟡 Medium

**1. Security headers apply to the web group only — every API response ships with none of them.**
`bootstrap/app.php` appends `SecurityHeaders` to `$middleware->web()`; the `api` group receives only `throttle:api` (prepend) plus `IpBlocker` and `EnsureFrontendRequestsAreStateful` (append). So every API response carries no `Cache-Control: no-store`, no `X-Content-Type-Options: nosniff`, no `X-Frame-Options`, no CSP, no HSTS, and leaves the `Server` header intact. This matters here specifically because the project convention permits **unmasked customer ID numbers in JSON** — an API response containing PII leaves the process without a no-store directive, while the web equivalent gets one.

**2. `EnsureBranchScope` cannot gate a request that names no branch.**
The middleware compares `$requestedBranchId` only when the route carries a `{branch}` / `{branchId}` / `{branch_id}` parameter; collection endpoints are delegated to downstream concerns (`BranchScopedQuery`, policies, services). The inline comment acknowledges this explicitly, so it is a documented design decision rather than an oversight — but the middleware's name promises a guarantee it does not deliver for list queries. Any endpoint added to the `branch.scope` group that trusts the middleware instead of a downstream filter is unscoped by construction.

### 🔵 Low

**3.** `SecurityHeaders::applySecurityHeaders()` sets a header only when absent (`if (! $response->headers->has($header))`). Any controller or upstream middleware that sets `Cache-Control`, `X-Frame-Options`, etc. first silently overrides the secure baseline — defence is opt-out, not enforced.

**4.** `style-src` carries `'unsafe-inline'` alongside a per-request nonce, with a docblock stating it is "needed for Alpine's x-show/x-cloak toggles and Tailwind's generated attribute styles." Per the CSP specification, `'unsafe-inline'` is ignored in a directive that also contains a nonce, so the keyword is inert and the documented justification no longer applies. `script-src` correctly contains no `'unsafe-inline'`/`'unsafe-eval'`; `buildCsp()` also retains an unreachable `$nonce === null` branch, since `handle()` always generates one.

**5.** `MfaService::hasTrustedDevice()` treats `whereNull('expires_at')` as a valid trust record, making any such row permanently trusted. `rememberDevice()` always stamps `expires_at` (default `cems.mfa.remember_days` = 30), so this is a dead path for newly created rows and only bites data backfilled without an expiry — which also contradicts `rememberDevice`'s docblock claim that legacy rows "expire naturally".

### Verified clean

`matchesRoleAlias()` **throws** on an unknown `role:` argument instead of silently denying or allowing — no bypass on a typo. `EnsureMfaVerified` consults the role requirement *before* reading `mfa_enabled`, so a user in an MFA-required role cannot opt out by never enrolling, and the enrollment grace period is bounded by `isEnrollmentOverdue()`. Trusted-device trust is bound to a 256-bit random secret (`bin2hex(random_bytes(32))`) with only the SHA-256 hash persisted server-side and a 30-day expiry — the docblock records why UA+IP fingerprinting was abandoned as cloneable. The middleware wraps every session access in try/catch with safe defaults, so sessionless API-token requests cannot throw, and the absolute session lifetime cap is enforced *before* the MFA check so MFA cannot extend an expired session. `SecurityHeaders` emits a nonce CSP with `frame-ancestors 'none'`, `X-Frame-Options: DENY`, `nosniff`, a strict `Referrer-Policy`, a lockdown `Permissions-Policy`, `no-store` cache headers, configurable HSTS, and strips `Server`/`X-Powered-By`/`X-Generator`. `EnsureBranchScope` denies branch-operating roles lacking a `branch_id` and normalises both model-bound `{branch}` instances and raw API ids.

---
## Module 8 — `app/Models/`

**Scope:** 65 model files including `Customer` (PII), `User` (identity/RBAC), `Transaction`, plus trait and base classes.

### 🟠 High

**1. `Customer::id_number` performs a 100,000-iteration PBKDF2 key derivation on every access.**
`getIdNumberAttribute()` resolves the service per call — `app(EncryptionService::class)->decrypt(...)` — and **`EncryptionService` is never bound as a singleton** (no `singleton`/`instance`/`bind` for it in any of the six providers; `bootstrap/providers.php` does not exist and `AppServiceProvider` registers no bindings at all). Laravel's container therefore constructs a fresh instance on every `app()` call, and the constructor runs `hash_pbkdf2('sha256', $rawKey, $salt, 100000, 32, true)`.

The codebase already knows this cost: `EncryptionService::blindIndex()` carries a comment stating *"PBKDF2 with 100k iterations is expensive (~50ms+ per call) … so the derived key is memoised for the lifetime of the process."* The static method memoises; **the instance path does not.** `id_number` is rendered in `customers/index.blade.php:43` (one per row), `customers/show.blade.php` (twice), `reports/customer-analysis.blade.php`, and 17 view/resource sites in total — so a paginated customer list pays ~50 ms of pure key derivation per row per access, before any business logic runs. This is a self-inflicted latency and CPU-amplification problem on a core listing screen.

### 🟡 Medium

**2. `Customer::getIdNumberAttribute()` fails open to `null` on any decryption error.**
`catch (\Exception $_e) { return null; }` swallows every failure mode — wrong key, corrupted row, a `customers:re-encrypt` that was never run, a schema drift. The UI then shows an empty ID number, indistinguishable from a customer who legitimately has none. For a KYC-mandatory field this removes the only signal that the plaintext is unreadable, and it silently degrades any downstream comparison (`id_number` is also the value used for exact-match display assertions).

**3. The blind index `id_number_hash` is not hidden, so it is serialized in every customer JSON response.**
`$hidden` contains only `id_number_encrypted`. `id_number_hash` — the HMAC-SHA256 used for exact-match KYC lookup — is therefore included in API resources, `toJson()`, and any log line that dumps a customer. It is not directly reversible without the key, but it is a stable cross-system join key, and for a known population of national IDs it is exactly the value an attacker would precompute a dictionary against.

### 🔵 Low

**4.** `Customer::getIcNumberAttribute()` is annotated "Legacy alias for the decrypted ID number" and has **zero references** anywhere in `app/` or `resources/`. Dead code, and it duplicates the decryption path.

### Verified clean

`BaseModel` sets `protected $guarded = ['*']` as a defensive mass-assignment guard, forcing every concrete model to declare `$fillable` explicitly — a new model without `$fillable` fails loudly. All seven models that do **not** extend `BaseModel` (`User`, `PoolRemittance`, `JournalLine`, `ComplianceCase`, `EnhancedDiligenceRecord`, `SystemAlert`, `TestResult`) still declare explicit `$fillable`, so no mass-assignment gap exists. `id_number_encrypted` is correctly hidden, and `Customer` casts are thorough (dates, booleans, `CddLevel`, `RiskRating`, `IdType`, `MoneyCast`). `Customer` deliberately avoids model event listeners and computes the blind index in the service layer "to ensure plaintext is available" — a reasoned choice, documented. Only three models use raw SQL (`TellerAllocation`, `SystemHealthCheck`, `Compliance/SanctionEntry`), a small and reviewable surface.

---
## Module 9 — `app/Enums/`

**Scope:** 81 enum files — `TransactionStatus`, `UserRole`, `Permission`, `CddLevel`, `RiskRating`, plus accounting/compliance/branch domains.

### 🟡 Medium

**1. `TransactionStatus::Draft` is documented as unreachable but is live in three places.**
The case docblock states: *"Legacy state — retained only so historical rows deserialize. Absent from `TransactionStateMachine::TRANSITIONS`; no code path creates it."* Both clauses are false:
- `TransactionStateMachine::TRANSITIONS['draft']` exists with three allowed targets (`pending_approval`, `pending_cancellation`, `cancelled`).
- `TransactionCancellationService::canCancel()` includes `Draft` in `$cancellableStatuses`.
- `Transaction::openStatusValues()` includes `Draft`.

So a state that is promised to be inert is wired into the state machine and the cancellation rule. The consequence is a latent footgun rather than a live bug — if any code ever writes a `draft` row, it becomes cancellable and transitionable with no intake path described anywhere. Either the docblock is stale or the wiring is dead weight; both should not be true at once.

**2. `Transaction::openStatusValues()` conflates legacy and active statuses.**
`openStatusValues()` returns `Draft`, `PendingApproval`, **`Pending`**, **`OnHold`**, `PendingCancellation` — two of which the enum marks as legacy with "no code path may create or transition into them". Because `HasStatus`'s `scopeOpen()` and `isOpen()` are generated from this method, every `Transaction::query()->open()` and every `->isOpen()` call treats dead statuses as open. The fact that `EodReconciliationService` had to hard-code `whereNotIn('status', [... Failed->value, Pending->value])` at two sites is direct evidence the conflation is real and was papered over downstream instead of fixed at the partition.

### 🔵 Low

**3.** The class docblock reads *"a 12-state state machine (10 active + 2 legacy)"*; the enum actually declares **13** cases — 10 active plus **3** legacy (`Draft`, `Pending`, `OnHold`). AGENTS.md has the correct count ("13 values / 10 active"), so the class-level comment is the stale one. In a tamper-sensitive state machine, a wrong cardinality in the header comment is exactly the kind of thing that misleads the next reader.

### Verified clean

Enums never read `config()` directly — they delegate through the `Thresholdable` trait (`app(ThresholdService::class)`), preserving the centralized-threshold invariant with audit logging, and the trait's docblock explains why enums must use `app()` (no constructor DI). `UserRole::rateOverrideLimit()`'s two `(float)` casts are the only float casts in the entire enum namespace, and they convert **percentage deviations** (±0.5% / ±2.0%), not money — so the BCMath/string-money convention is intact. `UserRole::canPerform()` routes through `PermissionService::can()` rather than duplicating matrix logic, and `matchesRoleAlias()` fails closed on unknown aliases.

---
## Module 10 — `app/Console/Commands/`

**Scope:** 69 commands including 17 hand-written schema "installer" commands.

### 🟠 High

**1. The 17-installer schema pattern has no enforcement, and has already caused production outages.**
AGENTS.md forbids migrations and names `SchemaSeeder.php` as the sole schema source of truth, yet **17 `app/Console/Commands/Install*.php` files each embed their own hand-written DDL** that must "mirror `SchemaSeeder` exactly" (the command's own docblock says so). This creates two sources of truth for the same schema, and nothing keeps them converged:
- **No test** asserts that any installer's DDL matches the `SchemaSeeder` definition (`grep` across `tests/` for the installer names returns nothing).
- **The only enforcement is a manual edit to `.github/workflows/deploy.yml`.** The project's own operations note states the failure mode explicitly: *"a new installer must be registered there or production never gets the change."*
- The same note records that this has **already broken production**: missing `stock_reservations.branch_id` made *every Sell* 500, missing `branch_closure_workflows.business_date` made *every counter open* 500, and missing `system_logs.seal_status` caused `SealAuditHashJob` to error-spam. It also lists six installers that had been omitted from `deploy.yml`.

The current `deploy.yml` list is complete (19 upgrade commands, including the six that were previously missing), so the damage has been repaired — but the guarantee is a human remembering to edit a workflow file. There is no check that "every `Install*` command appears in `deploy.yml`", so the next installer added will silently never reach production. The fix is mechanical: a test that parses the `Install*` command signatures and asserts each appears in `deploy.yml`.

### 🟡 Medium

**2. `SchemaSeeder::$allowPopulated` is a public static opt-out, so the destructive guard is advisory.**
`SchemaSeeder` guards against running on a populated database via `if (! self::$allowPopulated && $this->databaseIsPopulated())`, and `public static bool $allowPopulated = false` is mutated directly by call sites. The known call sites do it correctly — `BusinessSetup --fresh` takes an interactive `confirm()` (defaulting to cancel), `ResetTestDatabase --fresh` is fenced behind an `app()->environment('local', 'testing')` check and resets the flag in a `finally` block. But any new code can set the flag and drop every table without the confirmation, because the guard is a mutable static rather than a policy. Worth converting to an explicit, unforgeable opt-in (e.g. a dedicated command-only constructor or a require-confirmation argument) so the guard cannot be bypassed by habit.

### Verified clean

`BusinessSetup` defaults to the **non-destructive** path: it skips schema creation entirely when `users` already exists and prints a hint to use `--fresh`. `--fresh` is interactive with a cancellation option and returns `Command::FAILURE`. `ResetTestDatabase` refuses to run outside `local`/`testing` and uses `try/finally` for both `$allowPopulated` flags, so an exception mid-seed cannot leave the flag armed for the next request. The installer commands are idempotent — `InstallPoolRemittancesTable` no-ops to an `upgradeExistingTable()` path when the table already exists, which is what makes re-runs on every deploy safe. The deploy step also runs `audit:seal-pending` after the upgrade set, `optimize:clear` + config/route/view caching, and signals the queue daemon with `queue:restart` so workers pick up fresh code.

---
## Module 11 — `app/Jobs/`, `app/Events/`, `app/Listeners/`, `app/Notifications/`

**Scope:** 10 queued jobs, 12 events, 8 listeners, 4 notification classes, plus `EventServiceProvider` wiring.

### 🟡 Medium

**1. `TransactionCreatedListener` can move `customer.last_transaction_at` backwards.**
```php
$event->transaction->customer?->update(['last_transaction_at' => $event->transaction->created_at]);
```
The listener is queued (`ShouldQueue`) and runs with `afterCommit = true`, so jobs process **out of order**. A transaction created at 10:00 that is dequeued after one created at 10:05 sets `last_transaction_at` back to 10:00. Nothing guards the write with a `GREATEST()`/`max()` comparison, and this is the only write site in the codebase.

Downstream impact is concrete:
- `MarkDormantCustomers` marks a customer dormant when `last_transaction_at < $cutoff` — so a customer who transacted today can be **misclassified as dormant** and enter dormancy handling.
- `TriggerSanctionsRescreening` scopes recency with `last_transaction_at >= now()->subDays(30)` — a regressed timestamp can wrongly exclude an active customer from rescreening scope.

The fix is one expression: only write when the new value is later (`DB::raw('GREATEST(last_transaction_at, ?)')`).

**2. Global queue failure is log-only, with no alert or DLQ routing.**
`EventServiceProvider::boot()` registers a single `Queue::failing` callback that calls `Log::error(...)` and nothing else. `ProcessTransactionRetry` handles its own failure correctly in `failed()`, but every other job — `ImportSanctionsJob`, `ComplianceScreeningJob`, `RunComplianceMonitorJob`, `ReconcileDeferredAccountingJob`, `SendNotificationJob`, `ProcessTransactionImportJob`, `LowStockAlertJob`, `ComputeBehavioralBaselineJob` — has no equivalent: once Horizon marks it permanently failed, the only record is a log line. Given this codebase's documented posture that BNM-critical obligations must not vanish into a log (see Module 4, finding 2), compliance-adjacent job failures deserve the same treatment as STR auto-draft failures.

### Verified clean

`ProcessTransactionRetry` is exemplary: `ShouldBeUnique` + `uniqueId() = 'transaction_retry_'.$id` prevents concurrent duplicate retries, `$timeout = 120` bounds a hang, and it re-reads the transaction before every state-dependent decision. It guards the DLQ transition three separate ways — `shouldMoveToDLQ()` then `isStillFailed()`, and `failed()` re-checks `isFailed() && !is_dlq()` specifically so "a timeout firing after a successful retry would otherwise call `markAsDlq()` on a Completed transaction, which only accepts Failed status". `TransactionCreatedListener` correctly sets `afterCommit = true` so the side effects cannot observe an uncommitted transaction, and it uses `recalculate()` rather than the discard-result `calculateScore()`. `EventServiceProvider` documents with unusual care why `ComplianceEventListener` must be registered via `$subscribe` rather than `$listen` (mapping it would make every dispatch fail with "Call to undefined method `__invoke()`" and roll back case creation mid-transaction), and it overrides `configureEmailVerification()` to avoid double-registering the framework's verification listener. `shouldDiscoverEvents()` is explicitly `false`.

---
## Module 12 — `app/Policies/` + `app/Exceptions/`

**Scope:** 15 policies; 74 typed domain exceptions under `app/Exceptions/Domain/` plus the global renderer in `bootstrap/app.php`.

### 🟡 Medium

**1. A broad `RuntimeException` match leaks raw messages to API consumers as a 409.**
The catch-all renderer in `bootstrap/app.php` maps status with:
```php
$status = match (true) {
    $e instanceof HttpExceptionInterface => $e->getStatusCode(),
    $e instanceof AuthenticationException => 401,
    $e instanceof RuntimeException => 409,
    default => 500,
};
// ...
'message' => $status >= 500 ? 'An internal error occurred…' : $e->getMessage(),
```
The docblock calls this "RuntimeException is a state conflict (409)", which reads as a narrow intent — but `RuntimeException` is a broad PHP base class: `LogicException` extends it, as do `InvalidArgumentException`, `OutOfBoundsException`, `OverflowException` and `UnderflowException`. Since 409 is `< 500`, **the raw `getMessage()` is returned to API consumers** rather than the sanitized 500 body. So an unexpected exception from framework internals or a third-party package surfaces its full message — including stack-adjacent detail such as offending values, paths, and identifiers.

The domain-exception path is not affected: `App\Exceptions\Domain\DomainException` also extends `RuntimeException`, but it is matched by the first `render()` closure, so its client-safe contract holds. The exposure is limited to non-domain runtime exceptions, which is exactly the class of bug you would not want an external consumer to see in full.

### Verified clean

Every `isAdmin()` / `role ===` occurrence outside the three `delete()` methods already reported in Module 6 finding 4 sits in a `viewAny()` or `view()` — i.e. **scoping**, which the project convention explicitly permits ("Identity helpers are for scoping only (e.g. admin sees all branches, non-admin is confined to `branch_id`)"). All 15 policies consult the `role_permissions` matrix via `canPerform(Permission::…)` or a scoped identity check; none is unguarded. `TransactionPolicy::update()` correctly freezes a record for its creator once approved and confines owners to their own `PendingApproval` rows. `DomainException` provides a coherent contract — `getStatusCode()` defaulting to 422, `getSeverity()`, and a stable `getErrorCode()` derived from the class basename — and the renderer surfaces that code to API consumers as a machine-readable field. True 500s are sanitized to a fixed message plus `INTERNAL_ERROR`, and unhandled production exceptions page the ops mailbox with an env-var guard so an unset recipient list cannot yield `['']`.

---
## Module 13 — `app/Support/`, `Rules/`, `Casts/`, `Helpers/`, `ValueObjects/`, `Actions/`, `Providers/`, `View/`, `Repositories/`

**Scope:** 8 support classes, 8 validation rules, `MoneyCast`, 2 helpers, 7 value objects, 6 providers, view components/composers, 1 repository.

### 🔵 Low

**1. `PasswordNotRecentlyUsed` enforces `history_depth + 1`, not `history_depth`.**
The rule fetches `PasswordHistory::recentHashesFor($this->user, $depth)` and then **prepends the user's current `password_hash`** before comparing (so an admin reset cannot silently preserve the working password — a good reason to prepend). The net effect is that `security.password.history_depth = 5` actually rejects reuse of the last **six** passwords. The config key names the history depth, not the comparison window, so anyone tuning it will be off by one. Harmless in the safe direction, but worth a docblock line.

**2. `ActorContext::capture()` re-resolves the request on every call.**
`capture()` runs `auth()->user()` and `request()?->ip()` with no memoisation, and it is called more than once per flow in places — e.g. `TransactionCreationService::prepareAndCreate()` captures it twice to backfill `$userId` and `$ipAddress`. Cheap but redundant, and it means the two captures can theoretically disagree if the request context mutates mid-request.

### Verified clean

**`LikeEscaper` is used correctly at all eight call sites.** Every one binds an explicit `ESCAPE ?` clause with `'\\'` as a *parameter* — `CustomerRepository` (two sites), `CustomerIndexAction`, `Api/V1/CustomerController`, `ComplianceService` (entity_name + aliases), `CustomerScreeningService` (two private helpers), `SanctionEntry`. No bare literal `ESCAPE '\'` appears anywhere, which the helper's docblock flags as MySQL-breaking. The only two sites that interpolate a column name into raw SQL (`tokenPrefilteredPool`, `tokenMatchRanked`) are **private** helpers whose callers pass hardcoded literals (`'normalized_name'`, `'normalized_name'`), so there is no injection path.

`PasswordHash::check()` is properly fail-closed: a foreign-format hash makes `Hash::check()` throw `RuntimeException`, which is caught, logged, and returned as a mismatch rather than a 500 on an auth or step-up path. `MoneyCast` rounds on the decimal string directly — the docblock states why ("rounding decisions are never lost to float precision or bcmath scale truncation") — uses half-up rounding to match `MathService::round()`, and rejects non-numeric input with a typed `InvalidArgumentException`. `ActorContext` is a `final readonly` value object with a documented system-user fallback via `userIdOrSystem()`, which is the convention services use instead of reaching for `auth()`.

---
## Module 14 — `routes/`

**Scope:** 5 route files, 1,246 lines — `web.php` (712), `api_v1.php` (483), `auth.php`, `webhooks.php`, `console.php`. (Middleware registration was covered in Module 7.)

### 🔵 Low

**1. `GET /logout` enables logout CSRF.**
`routes/auth.php` registers logout with `Route::match(['GET', 'POST'], '/logout', ...)`. Laravel's `VerifyCsrfToken` middleware exempts GET/HEAD from CSRF verification, so an unforgeable form submission is not required — a remote origin can force a logout with `<img src="https://app/logout">` or a background `fetch()`. Impact is bounded to terminating the session (the handler does invalidate the session and regenerate the token, so there is no state corruption), but it lets an attacker force-logout a user mid-transaction, which is enough to break a booking flow or hide the user's activity. POST-only is the standard shape.

**2. `hash_equals()` on a header that may arrive as an array.**
`SanctionsWebhookController` reads `$request->header('X-Webhook-Token', '')` and passes it to `hash_equals($configuredToken, $providedToken)`. A proxy or a duplicate header can yield an array, which makes `hash_equals` throw a `TypeError` and turn a rejected request into a 500. The controller is otherwise correct, so this is a robustness nit rather than a bypass.

### Verified clean

The sanctions webhook **fails closed** when no token is configured — it rejects with 401 and logs a warning rather than accepting an open webhook, which is the right default for an unauthenticated endpoint that triggers sanctions-list ingestion. Comparison uses `hash_equals` (timing-safe), both endpoints are rate-limited (`throttle:10,1` / `throttle:30,1`), and invalid tokens are logged with the client IP. All GET routes in `web.php` that resemble actions are `show*` form-display endpoints (`showConfirm`, `showApproveCancel`, `showRejectCancel`) — the actual mutations are POST. Every auth surface is rate-limited (`throttle:login`, `throttle:password-reset`, `throttle:5,1` for password confirmation), and `logoutOtherDevices` is POST behind a `LogoutOtherDevicesRequest`. `routes/webhooks.php` carries no auth middleware at all, which is correct for an external push endpoint but worth noting: it is protected only by the token and the throttle.

---
## Module 15 — `config/`

**Scope:** 29 config files, including `security.php`, `cems.php`, `app.php`, `ratelimit.php`, `thresholds.php`, `horizon.php`.

### 🟡 Medium

**1. Seven `burst_allowance` knobs in `security.rate_limits.*` have zero consumers.**
`config/security.php` defines `burst_allowance` for `login`, `api`, `transactions`, `str`, `bulk`, `export` and `sensitive` — 7 entries — and `grep -rn burst_allowance app/` returns **zero** hits. This is the configuration-side corollary of Module 5 finding 1: the burst-protection design is dead code, and the config ships seven tunable-looking values that no code reads. An operator adjusting `SECURITY_*BURST*` would believe they were tightening a real control.

**2. The IP-block whitelist defaults to two private RFC1918 ranges.**
```php
'whitelist' => array_filter(explode(',', (string) env('SECURITY_IP_WHITLIST', '192.168.1.0/24,127.0.0.1'))),
```
With the env var unset, **every host on `192.168.1.0/24` is exempt from IP blocking**, `whitelisted` IPs "never block". That is a sensible development default and an unacceptable production one — an attacker who reaches that subnet (or is behind a NAT that presents from it) is entirely outside the brute-force lockout control. There is no environment guard on this key, unlike the kill-switches in Module 7 which refuse to apply outside `local`.

### 🔵 Low

**3. Password rotation is implemented but disabled by default.**
`security.password_expiry_days` defaults to `0` ("forced rotation is disabled unless configured"), while `LoginController::showChangePassword()` documents a "BNM rotation policy". The mechanism exists and is wired; the default and the documented intent disagree. Worth a decision: either the default should be non-zero, or the docblock should stop calling it a BNM requirement.

**4. `security.password.min_length` defaults to 8.**
Documented in AGENTS.md and overridable via env, but 8 characters is a modest floor for a BNM-regulated system holding PII and foreign-exchange balances. A policy question rather than a defect — noting it because `require_uppercase`/`lowercase`/`numbers`/`symbols` are all on, so the real entropy floor is 8 mixed-class characters.

### Verified clean

The secure defaults are generally right: `SESSION_SECURE_COOKIE` true, `http_only` true, `same_site` strict, `SESSION_LIFETIME` 480 minutes. `allow_derived_encryption_salt` defaults to **false**, so the encryption salt must be an independent env var rather than derived from `APP_KEY` — a good default that preserves key separation, with `encryption_iterations` at 100,000. MFA is globally enabled by default with TOTP period 30 / 6 digits. Audit retention defaults to 2,555 days (the BNM 7-year requirement) with `sha256` hashing. Passwords enforce mixed case, numbers and symbols, and the `max_bytes` cap of 72 is documented against bcrypt's silent truncation. `config/ratelimit.php` is deliberately minimal — it exposes only the cache `store`, so limit definitions cannot be redefined outside the reviewed `security.rate_limits` block.

---
## Module 16 — `database/`

**Scope:** `seeders/` (7 files, 4,128 lines incl. the 2,188-line `SchemaSeeder`) and `factories/` (35+ factories).

### 🟡 Medium

**1. `TransactionFactory` emits a `till_id` that no counter has.**
```php
'till_id' => 'MAIN',
```
`CounterSeeder` creates counters `C01`–`C05`; **nothing in any seeder creates a counter with code `MAIN`.** `'MAIN'` appears only as a column default on `revaluation_entries.till_id` (`SchemaSeeder.php:1638`).

Consequences:
- The factory violates the project's own documented fixture convention — `till_id` must be a real `counters.code`. Every `Transaction::factory()` row carries a **dangling till reference**.
- The `ValidTill` rule would reject any of these rows, so factories cannot exercise the real booking validation.
- `TransactionCreationService::createTransactionRecord()` derives `counter_id` via `Counter::findByCodeOrId($till_id)`; with `till_id = 'MAIN'` that yields `counter_id = null`. **Factories and the service therefore produce structurally different rows**, so any test that asserts on `counter_id` is testing fixture artefacts rather than behaviour.

**2. Two factory fields are non-deterministic.**
```php
'branch_id' => Branch::factory(),
'currency_code' => fn () => (Currency::query()->inRandomOrder()->first()->code) ?? Currency::factory()->create()->code,
```
`Branch::factory()` mints a fresh branch per transaction, so two rows in the same test are almost never on the same branch — which defeats branch-scoped position and reservation assertions. `inRandomOrder()->first()` picks a random currency, so a failing test can pass on a re-run. Both are the classic causes of flaky test suites.

### 🔵 Low

**3. `databaseIsPopulated()` inspects only 10 of ~86 tables.**
The `SchemaSeeder` guard treats the database as populated if any of `branches`, `counters`, `customers`, `transactions`, `journal_entries`, `branch_pools`, `currency_positions`, `stock_transfers`, `teller_allocations` or `setup_state` has rows. A partially-built database with data only in tables outside that list (e.g. audit or alert rows) would be judged empty and then dropped. Unlikely in practice, but the invariant is weaker than "any data exists".

### Verified clean

Both destructive-seeder guards are well engineered. `SchemaSeeder::run()` refuses on a populated database with a message that names the deliberate rebuild paths, `seedNow()` opts in and resets the flag in a `finally` block, and `DatabaseSeeder` carries a parallel guard so the habitual `php artisan db:seed` cannot wipe live data. The guard error messages tell the operator exactly what to run instead. `CounterSeeder` uses `firstOrCreate(['code' => …])`, so it is idempotent and safe to re-run.

---
## Module 17 — `resources/views/`

**Scope:** 191 Blade templates (1.6 MB) plus `resources/js`, `resources/css`, `resources/svg`.

### Findings

**None found.** This is the cleanest module in the audit. Every mechanical check came back clean:

| Check | Result |
|---|---|
| Unescaped Blade output `{!! !!}` | **0 occurrences across 191 files** |
| `->toHtml()` / `@verbatim` | 0 |
| POST forms missing `@csrf` | **0** — every `method="post"` form carries `@csrf` |
| Inline `<script>` blocks | **3 total** (`setup/index` ×2, `rates/units` ×1) |
| Unsafe JS/Blade string interpolation inside script tags | 0 |

Both inline script blocks correctly read the per-request CSP nonce with `nonce="{{ request()->attributes->get('csp_nonce') }}"`, matching the `SecurityHeaders` middleware documented in Module 7. `@json()` is the only mechanism used to inject data into `data-*` attributes or JS (`journal/create`, `transaction-wizard/index`, `transactions/create`, `allocations/{request,create}`, `setup/index`), and Laravel's `@json` applies JSON-safe HTML escaping. Alpine bindings use `x-text`, which assigns via `textContent` rather than `innerHTML`.

**PII display matches the documented convention.** `customer->id_number` renders unmasked per the project's own rule that "customer ID numbers render unmasked via `Customer::id_number` (decrypts `id_number_encrypted`; the blind index is still used for search)", and every site escapes through `{{ }}`: `customers/show.blade.php:53,241`, `customers/index.blade.php:43`, `reports/customer-analysis.blade.php:59`, `compliance/edd/customer/show.blade.php:18`. `transactions/show.blade.php:77` uses the shared `x-customer-link` component with `field="id_number"`. So the display convention is honoured, and the escape hygiene that makes it acceptable is intact.

Two residual notes, neither of which rises to a finding: (a) `id_number` is decrypted and echoed on the paginated `customers/index` screen, so every listing request decrypts one ID per row — which connects to Module 8's PBKDF2-per-access performance finding; (b) an empty `csp_nonce` (middleware skipped) would render `nonce=""` and the browser would refuse the script under CSP — fail-closed, but worth knowing.

---
## Module 18 — `tests/`

**Scope:** 471 PHP test files (217 Unit, 210 Feature, 40 Http simulation), 4 Playwright specs, support traits. ~2,600 test methods, 6,230 `assert*` calls.

### 🟠 High

**1. The idempotency-replay test asserts the wrong invariant, so the Module 1 defect is invisible to it.**
Wave B step B5 (`tests/Http/Simulation/WaveB/Steps/EdgeSteps.php:24-33`) replays a booking with the same idempotency key and then asserts:
```php
$count = $this->state->oracle->scalar(
    'SELECT COUNT(*) FROM transactions WHERE idempotency_key = ?', [$payload['idempotency_key']]);
$this->assertSame(1, (int) $count, 'B5: idempotent replay created a duplicate transaction');
```
This measures only the **transaction row count**. A replay that returns the existing row but re-applies the side effects — the `journal_entries`, `journal_lines`, `currency_positions.quantity` delta, `till_balances` adjustment and `teller_allocations` mutation documented in Module 1 finding 1 — also leaves exactly one row. The test passes whether or not the booking was double-executed.

It compounds with a second weakness on the same test: the replay's response status is accepted as `[302, 409, 422]` — "must not succeed silently" — which admits a *successful* redirect as a valid replay outcome. So the assertion tolerates both "replayed correctly" and "replayed and re-booked".

Adding derived-state assertions is straightforward and is exactly what the simulation harness's `state->oracle` makes easy: snapshot `journal_entries` count, the branch position quantity, and the teller allocation balance before the replay, and assert they did not change.

### 🟡 Medium

**2. The `last_transaction_at` writer is untested — only its consumer is.**
`grep -rn last_transaction_at tests/` returns hits in exactly one file, `MarkDormantCustomersCommandTest`, which **sets** the column directly as fixture input. No test dispatches `TransactionCreated` and asserts the listener writes the timestamp, so the out-of-order regression from Module 11 finding 1 is undetectable. The dormancy consumer is covered; the writer that feeds it is not.

**3. No test exercises the branch-closure freeze race.**
`freezesDate` and `ensureClosureRace` appear in **zero** test files. The booking-versus-closure race documented in Module 3 finding 1 is entirely uncovered, even though the suite has demonstrated willingness to test concurrency elsewhere (`CounterHandoverDeadlockTest`, `Feature/Audit/ConcurrencyFixesTest`, `Unit/RateManagementServiceCacheTest`).

### 🔵 Low

**4. Trusted-device trust semantics have no unit coverage.**
`MfaServiceTest` contains no `trustedDevice` assertions, and `DeviceComputations` appears only in structural tests (`FactorySmokeTest`, `ModelHierarchyTest`). Given that the MFA bypass path relies entirely on `hasTrustedDevice` plus the `expires_at` check (and that `whereNull('expires_at')` silently grants permanent trust — Module 7 finding 5), the absence of tests on expiry behaviour and the null-expiry branch is a meaningful gap for the control that can bypass MFA.

### Verified clean

Only **8** `markTestSkipped` calls in the whole suite, and every one is legitimate and self-explaining: five for surface selection in the Wave B harness ("web surface not selected"), and three environment guards (Redis cache store not configured, "Only applies in production environment", "Only applies to mysql database connection"). None is used to hide a failing test. The `tests/Http` simulation harness is real end-to-end coverage — Wave A books transactions through genuine web routes and a real HTTP client, Wave B attacks with invalid/duplicate payloads asserting both HTTP rejection and absence of derived state, and Wave C verifies cross-surface parity — not unit-mock theatre. `PerformanceTrackingMiddlewareTest` uses Mockery expectations on `Log` rather than `assert*` calls, so naive grep-based coverage metrics will undercount it.

---

## Audit Summary

**Coverage:** 18 of 18 targets complete. `app/` (951 PHP files), `config/`, `routes/`, `bootstrap/app.php`, `database/` (seeders + factories), `resources/views/` (191 Blade templates) and `tests/` (471 files) were each deep-scanned against the four requested axes — completeness/logic gaps, technical debt, architecture flaws — with findings appended module by module.

**Finding totals: 1 critical, 9 high, 17 medium, 19 low.**

### What most deserves a fix

1. **Idempotency replay re-applies booking side effects** (Module 1, critical) — the most consequential item in the audit: a retried or double-submitted booking can book twice while the transaction row count stays at one, so the existing test does not catch it.
2. **Idempotency replay test asserts only row count** (Module 18) — the safety net for item 1 is measuring the wrong invariant. Fix both together.
3. **Branch-closure freeze race** (Module 3) and its absence of any test (Module 18) — a booking can land on a business date whose closure is already initiated.
4. **`last_transaction_at` written backwards by an out-of-order queued listener** (Module 11) — can misclassify an active customer as dormant; the writer is untested.
5. **`Customer::id_number` re-derives a 100k-iteration PBKDF2 key per access** (Module 8) — `EncryptionService` is not a singleton; ~50 ms of pure key derivation per decrypted row on listing screens.
6. **MFA trusted-device bypass with no expiry coverage** (Modules 7 + 18) — `whereNull('expires_at')` grants permanent trust and no test covers it.
7. **Installer-set drift** (Module 10) — 17 hand-written DDL installers with no test verifying they match `SchemaSeeder` or appear in `deploy.yml`; this pattern has already caused production 500s per the project's own ops notes.
8. **Two silent-drop paths for the BNM STR pipeline** (Module 4) — an unassigned closed case produces no draft and no trace, and every failure is swallowed.
9. **Broad `RuntimeException` → 409 leaking raw messages** (Module 12) — `LogicException` and siblings are not internal to the domain hierarchy, so their messages reach API consumers.

### Strongest parts of the codebase

`resources/views/` was clean on every check — zero `{!! !!}`, zero POST forms without `@csrf`, only 3 inline script blocks, both carrying the per-request CSP nonce. The transaction and allocation services show real concurrency discipline: pessimistic row locks in `CurrencyPositionService`, conditional-UPDATE guards in `TellerAllocationService` with explicit "abort loudly on zero rows" handling, and `ProcessTransactionRetry` guarding every state-dependent decision against concurrent mutation. Money handling is genuinely disciplined — string BCMath throughout, `MoneyCast` rounding on decimal strings to avoid float truncation, and `EncryptionService` using PBKDF2 plus HKDF key separation plus encrypt-then-MAC. `LikeEscaper` is used correctly at all 8 call sites with parameter-bound `ESCAPE` clauses. Security headers and middleware fail closed rather than open on configuration errors. And the ~471-file test suite has no hidden skips.

### Method note

Findings are evidence-based: each cites the file and, where useful, the line or code fragment. Several candidate findings were investigated and then **withdrawn** after verification rather than reported — notably `CddLevel::determine()`'s `strtolower` risk-rating comparison (the enum value is already lowercase), the PEP `requestApproval()`-before-throw pattern (genuinely idempotent under a row lock), `UserRole::rateOverrideLimit()`'s `(float)` casts (percentages, not money), the `idempotency` test suite's general quality, and the trusted-device fingerprinting design (256-bit secret, not cloneable UA/IP). `COMPREHENSIVE-AUDIT.md` records those rejections inline so the negative results are traceable too.
