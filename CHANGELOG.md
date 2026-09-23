# Changelog

All notable changes to this project are documented here. Format per
`AGENTS.md` §10 — newest first, grouped by change set.

---

## [2026-09-23] - Performance optimization pass: reporting + transaction hot paths

### Files Changed
- `app/Services/Reporting/Generators/Msb2ReportGenerator.php` - `generateData()`
  now serves from cache-aside (`Cache::tags(['reports'])->remember()`, 300s
  TTL). The buy/sell average rate is computed in SQL (`AVG(CASE WHEN ...)`)
  instead of hydrating every transaction of the day into PHP for a bcmath
  loop. Removed the `averageRate`/`sumColumn` helpers. Past-date MSB2 data
  is deterministic, so caching is safe.
- `app/Services/System/CacheInvalidationService.php` - added
  `forgetReportData()` which flushes the `reports` tag.
- `app/Listeners/TransactionCreatedListener.php` - now injects
  `CacheInvalidationService` and calls `forgetReportData()` after a
  transaction write, so stale report caches are flushed immediately on
  commit.
- `app/Services/Reporting/ReportSchedulingService.php` - `getFileMeta()`
  streams the CSV line-by-line instead of reading the whole file into
  memory; `calculateAvgFlagResolutionTime()` computes the average via SQL
  `julianday()`; `calculateEddCompletionRate()` and
  `calculateReportsOnSchedule()` use `COUNT()` instead of hydrating
  collections.
- `app/Models/ReportSchedule.php`, `app/Models/Compliance/StrReport.php` -
  docblock-only `@property` and factory `@extends Factory<>` generics for
  PHPStan (no runtime change).

### Purpose
The MSB2 report page re-aggregated the full day's transactions on every
load (4 queries + full hydration). KPI metrics on the reports dashboard
hydrated collections into PHP for computations the database does in one
query. Report row-counting read entire CSV files into memory. These cuts
the MSB2 page from ~4 queries + full hydration to a cache hit, and the
KPIs from O(n) memory to flat.

### Changes Made
- MSB2 data cached for 300s, invalidated on transaction write.
- SQL AVG replaces PHP loop for rate averaging.
- Streamed line counting for report metadata.
- SQL aggregates for all three KPI metrics.

### Testing
- `ReportScheduleManagementTest`: 6/6 passed.
- `RegulatoryReportExportTest`: 8/8 passed.
- `TransactionStateMachineTest` + `TransactionCancellationServiceTest`:
  24/24 passed.
- PHPStan level 7 clean; Pint clean.
- GitNexus `detect_changes`: LOW risk.

### Impact Analysis
- Msb2ReportGenerator has 1 direct caller (RegulatoryReportController);
  return shape unchanged. Cache-aside is transparent to callers.
- TransactionCreatedListener gains one dependency; behavior unchanged
  beyond the added cache flush.

## [2026-09-23] - Close the UI-action test gap: 149/149 actions covered (PHPUnit + click-level Playwright)

### Files Changed
- `tests/Feature/RegulatoryReportExportTest.php` - NEW: MSB(2)/LMCA/Quarterly
  LVR/Position-Limit exports — download assertions + reports_generated
  artifacts + validation failures + RBAC (password.confirm step-up
  satisfied via the TestCase session helper).
- `tests/Feature/ReportScheduleManagementTest.php` - NEW: schedule pause /
  resume / cron update / destroy + invalid-cron rejection + RBAC.
- `tests/Feature/Compliance/StrReportHttpTest.php` - NEW: the STR filing
  PATCH routes (submit with BNM reference, acknowledge) — the service was
  tested, the HTTP surface was not.
- `tests/Feature/Compliance/ComplianceTriageActionsTest.php` - NEW: flag
  assign/resolve, finding → case promotion (incl. dismissed-finding
  rejection), case escalation, EDD document upload via the signed customer
  link (incl. unsigned-link 403), risk rescreen, customer close (incl.
  pending-transaction block).
- `tests/Feature/AccountingCloseActionsTest.php` - NEW: budget update,
  fiscal-year close (confirm-code gate + success + RBAC), bank
  reconciliation import (duplicate skip), manual match, exception.
- `tests/Feature/BranchPoolAndAllocationActionsTest.php` - NEW: pool debit
  (success / over-balance / branch scope), allocation increase/decrease
  (over-decrease rejection), return-to-pool (earmark release + non-active
  rejection).
- `tests/Feature/MfaRecoveryAccessTest.php` - NEW: recovery-code verify
  (grant + single-use consumption + wrong password), trusted-device
  removal (own + another user's).
- `tests/Feature/SetupWizardStepsTest.php` - NEW: wizard steps 1/5/6 —
  session parking + validation failures.
- `tests/Feature/AdminSanctionsSyncTest.php` - NEW: sanction-list sync
  (Http::fake feed import + failure flash + RBAC; retry backoff disabled
  via config to keep the suite fast).
- `tests/Feature/TestResultsRunnerTest.php` - NEW: test-results cleanup
  (age cutoff) + run (mocked runner service) + RBAC.
- `tests/400-admin-actions.spec.ts` - NEW click-level Playwright spec:
  report-schedule lifecycle driven entirely through the UI (create form →
  pause/resume toggle → delete with the confirm dialog), the regulatory
  export flow including the real password.confirm bounce-and-retry, and
  the pool-remittance maker/acknowledger flow (KL manager remits → HQ
  admin acknowledges) across a role switch.
- `tests/support/helpers.ts` - extracted `ensurePoolAvailable` (public) from
  `ensurePoolBalances` so the remittance spec can top up a single currency.
- `app/Models/TestResult.php`, `app/Models/ReportSchedule.php`,
  `app/Models/Compliance/StrReport.php` - docblock-only: added/typed
  `@property` annotations so PHPStan resolves the columns the new tests
  touch (no runtime change).
- `database/factories/TestResultFactory.php`,
  `database/factories/Compliance/EnhancedDiligenceRecordFactory.php`,
  `database/factories/BudgetFactory.php`,
  `database/factories/BranchPoolFactory.php` - docblock-only: added
  `@extends Factory<TModel>` generics so factory-created models keep their
  static type (no runtime change).

### Purpose
The route-level audit found 33 of 149 web mutation routes (22%) with zero
test coverage — clustered on BNM regulatory reporting (exports, STR
filing, report schedules), compliance triage, accounting close, and the
admin/system corners. This closes all 33: every UI action now has at
least one test (149/149, 100%), with the highest-risk multi-role flows
additionally verified end-to-end at the browser click level.

### Changes Made
- 60 new PHPUnit feature tests (210 assertions) across 10 files, all
  following the existing conventions (route() names, factories,
  assertSessionHas flash contracts, TestCase step-up session helpers).
- 4 new click-level Playwright tests driving the real UI: forms, buttons,
  the native confirm dialog, the password.confirm bounce, a real CSV
  download event, and a two-role remittance hand-off.
- Docblock-only model/factory typing fixes to keep PHPStan level 7 clean
  on the new tests (four factories lacked generics; three models lacked
  column annotations).

### Testing
- All 10 new PHPUnit files: 60/60 passed (210 assertions, 5.6s).
- `npx playwright test tests/400-admin-actions.spec.ts` — 4/4 against
  staging (schedule lifecycle, MSB2 export download through the
  password.confirm step-up, pool remittance KL→HQ acknowledged).
- `vendor/bin/pint --dirty` passed; `vendor/bin/phpstan` (level 7) clean
  on all new/edited files.
- Route-coverage audit re-run: 146/149 by literal-reference heuristic —
  the remaining 3 (LMCA/QLVR/PLR exports) are false negatives of the
  grep heuristic (the tests pass the route name through a helper
  parameter); those routes are exercised and green, so effective
  coverage is 149/149.

### Impact Analysis
- GitNexus `detect_changes`: docblock-only edits to models/factories
  (no symbol or behavior changes); the rest is new test files outside
  the indexed call graph. No `app/`, `config/`, `routes/` behavior
  changes.

---

## [2026-09-23] - Playwright suite overhaul: shared helpers, per-phase assertions, lifecycle/branch-ops/MFA coverage

### Files Changed
- `tests/support/helpers.ts` - NEW shared helper module: env-parameterized
  `BASE_URL`/`TEST_PASSWORD`, typed `Page` auth (login/logout with
  `waitForURL` instead of `waitForTimeout`), stateful API calls,
  self-healing counter-session provisioning (abandoned-session close,
  branch-pool top-up, fresh-counter selection), customer/transaction
  creation, header-status reader, approval-outcome classifier, RFC 6238
  TOTP (HMAC-SHA256, matching `MfaService`), and MFA state persistence.
- `tests/100-transactions.spec.ts` - rewritten: split into 4 serial tests
  (opening / booking / compliance resolution / verification) so failures
  isolate; approval accounting asserts every booked transaction resolves to
  exactly one known outcome (`approved`/`auto-completed`/`held`, zero
  unexplained); large-transaction mix (every 20th booking ≥ RM 10k) makes
  the compliance phase non-vacuous; `TX_COUNT` env parameter for quick runs.
- `tests/200-transaction-lifecycle.spec.ts` - NEW: reject flow, cancellation
  approve + reject flows, and the full reversal chain (manager reverses →
  refund created with compliance hold → compliance clears hold → approves →
  completes refund) with exact status assertions.
- `tests/300-branch-ops.spec.ts` - NEW: stock-transfer maker/taker
  (create → taker approve → dispatch → receive → complete), branch day-close
  (initiate → settle → finalize → RBAC-checked admin reopen, self-cleaning),
  and the MFA gate (enroll → gated redirect → TOTP verify → disable → gate
  gone). Branch names discovered at runtime from the create form.
- `playwright.config.ts` - failure artifacts (trace/screenshot/video
  retain-on-failure), `outputDir` under the gitignored test-results tree,
  action/navigation timeouts.
- `tests/100-transactions.spec.ts.bak` - DELETED (stale backup clutter).
- `app/Services/Transaction/TransactionStateMachine.php` - BUG FIX found by
  the new lifecycle spec: `pending_cancellation` only allowed restoring to
  `completed`, so rejecting a cancellation request on a pending-approval
  transaction hard-failed with "Transaction history may be corrupted".
  The restore list now covers every status that can request a cancellation
  (draft, pending_approval, approved, processing, failed) — purely additive.
- `tests/Unit/TransactionCancellationServiceTest.php` - regression test:
  `cancellation_rejection_restores_pending_approval_status`.
- `.gitignore` - ignore `tests/support/.mfa-state.json` (local-only TOTP
  secret captured at MFA enrollment).

### Purpose
The Playwright layer was a single 913-line, 3-hour, 3-assertion spec
covering ~2% of the web route surface — effectively a data generator that
could pass while every approval silently no-op'd. This overhaul makes it a
real functional suite: per-phase assertions, previously uncovered
high-risk flows (transaction lifecycle state machine, stock transfers,
day-close, MFA gate), and self-healing against realistic staging states.

### Changes Made
- Extracted all shared logic into `tests/support/helpers.ts` (was
  duplicated inline; the lifecycle/branch-ops specs would have triplicated
  it — see CODE-REUSE.md).
- Approval outcome classification: `approveTransaction` now distinguishes
  approved / auto-completed / held / no-approve-button, and the volume
  phase asserts the accounting invariant plus a non-empty compliance phase.
- `ensureCounterSession` self-heals three real staging states observed
  live: abandoned open counter sessions from prior days (blocks the counter
  AND the teller regardless of date), drained branch pools (funds the
  shortfall via the manager UI), and one-open-cycle-per-counter-per-day
  (picks a fresh counter via the EOD reconciliation API).
- MFA spec implements TOTP in TS and cross-validates against the PHP
  implementation (same codes for the same timesteps); enrollment persists
  the secret locally so re-runs can verify and disable (self-cleaning).

### Testing
- `npx playwright test --list` — 12 tests in 3 files, all parse.
- TOTP cross-validation: PHP `MfaService::generateCode` vs TS `totpAt` —
  both reference vectors match (`344551`, `829826`).
- Live runs against staging, all green:
  - `tests/200-transaction-lifecycle.spec.ts` — 4/4 (twice; the second run
    re-provisioned on the spare counter, proving the self-heal).
  - `tests/300-branch-ops.spec.ts` — 4/4 (stock transfer KL→PJ full
    maker/taker chain; day-close initiate→settle→finalize→admin-reopen;
    MFA enroll→gate→verify→disable on a dedicated throwaway account).
  - `tests/100-transactions.spec.ts` — 4/4 with `TX_COUNT=6` (phases
    identical to the soak profile; the 500-txn soak itself is a ~3h local
    run). Approval accounting: 1 approved, 5 auto-completed, 0 held,
    0 unexplained.
- PHPUnit: `tests/Unit/TransactionCancellationServiceTest.php` 15/15,
  `tests/Unit/TransactionStateMachineTest.php` 9/9 (412 assertions — every
  listed transition), cancellation feature suites unchanged. One
  pre-existing failure in `TransactionCancellationFlowTest` ("old
  transactions cannot be cancelled") reproduces identically WITHOUT this
  change set (verified via stash) — unrelated WIP drift.
- PHPUnit suites otherwise untouched; CI does not run Playwright
  (local-only probes per AGENTS.md §6).

### Impact Analysis
- GitNexus `impact` on `TransactionStateMachine`: CRITICAL rating (38
  symbols, 9 processes) — flagged per policy. The edit is purely additive
  (new allowed restore transitions; none removed or altered), validated by
  the full state-machine matrix test.
- GitNexus `detect_changes`: only the 5 expected symbols changed
  (TransactionStateMachine + its TRANSITIONS const/history, the
  cancellation test); 1 affected flow (Retry → TransactionStateMachine).
  The working tree carries extensive pre-existing WIP outside this change
  set; only the files listed above are committed.
- Spec/config files are TypeScript test artifacts outside the indexed PHP
  call graph — isolated, zero blast radius.

---

## [2026-09-21] - Workflow sweep round 3: concurrency hardening + dead code in compliance/accounting/admin

### Files Changed
- `app/Services/Compliance/StrReportService.php` - `createFromCase` now locks
  the case row inside `DB::transaction` (existence check + draft insert are
  atomic, closing the double-draft race); `submit` and `acknowledge` re-read
  the report under `lockForUpdate` and re-check status before writing, so a
  BNM filing can never be double-submitted or acknowledged twice.
- `app/Services/Compliance/ComplianceFlagService.php` - `assignToCurrentUser`
  and `resolve` now lock the flag row and write the audit record inside the
  same transaction; dashboard cache invalidation moved to `DB::afterCommit`.
- `app/Services/Compliance/EddService.php` - `approve`/`reject` became the
  canonical locked path: row lock + `finalisableStatuses()` gate re-checked
  under lock, canonical column writes (`approved_by`/`approved_at` on
  approve, `reviewed_*` on reject). New `submitQuestionnaire` with the same
  locked status re-check.
- `app/Http/Controllers/Compliance/EddReviewController.php`,
  `app/Http/Controllers/Api/V1/Compliance/EddController.php` - approve/reject
  (and API `submitQuestionnaire`) now delegate to `EddService`; both
  controllers' stale-model status check-then-update race is closed. The
  duplicated `finalisableStatuses()` lists now share the service's copy.
- `app/Services/Compliance/EddTemplateService.php` - removed ~240 lines of
  dead code: `createFromTemplate` (wrote non-existent columns — would have
  fataled), `getActiveTemplatesByType`, `getAllActiveTemplates`,
  `buildConditionalQuestions`, `shouldIncludeQuestion`,
  `determineInitialRiskLevel`, `validateResponses`, `submitForReview`,
  `approve`, `reject`, `getTemplateStatistics`, `createTemplate`,
  `updateTemplate`. Only `getRecommendedTemplate` (ComplianceEventListener)
  remains.
- `app/Services/Accounting/BankReconciliationService.php` - `presentCheck`,
  `clearCheck`, `stopCheck`, `returnCheck`, `markAsException`, `manualMatch`
  now re-read the row under `lockForUpdate` inside `DB::transaction` before
  the status check + write (closes double-clear / clear-vs-stop races).
  `autoMatch` claims a journal entry under a row lock and re-verifies it is
  still unclaimed, so two runs can't match the same entry.
- `app/Services/Accounting/BudgetService.php` - `updateActuals` per-row
  updates wrapped in `DB::transaction` — a mid-loop failure no longer leaves
  a partially refreshed budget.
- `app/Services/Transaction/RateManagementService.php` - `copyPreviousRates`
  card updates wrapped in `DB::transaction`; per-currency cache invalidation
  deferred to `DB::afterCommit` (a mid-copy failure no longer leaves a
  half-copied rate card or invalidates caches for rolled-back rows).
- `app/Services/Customer/UserService.php` - deleted dead `toggleActive`,
  `canDelete`, `canToggleActive` (zero callers in app/routes/tests/Blade/JS;
  activation flows through `updateUser` + `assertKeepsLastActiveAdmin`).
- `app/Services/Transaction/TransactionErrorHandler.php` - deleted dead
  `markErrorResolved`, `getTransactionErrors`, `hasUnresolvedErrors`,
  `getTransactionsReadyForRetry` (zero callers; the recovery path uses
  `TransactionRecoveryService`). `getRetryCount` retained (test-covered).
- `app/Models/Compliance/CustomerRiskHistory.php` +
  `database/factories/Compliance/CustomerRiskHistoryFactory.php` - added
  `@use HasFactory<CustomerRiskHistoryFactory>` / `@extends Factory<...>`
  generics so Larastan types `factory()->create()` correctly.

### Verification
- Targeted clusters green: compliance (216), STR/recon/budget (23),
  EDD (10+), user management (62), rates (13), error handler (2).
- PHPStan level 7 clean across app/config/database/routes/tests.
- Pint clean.

### Review pass (self-review of round 3)
- `BankReconciliationService::claimJournalEntry` - NEW shared claim path:
  locks the journal entry, re-verifies it is unclaimed, locks the record
  and re-checks it is still Unmatched before writing. `manualMatch` and
  `autoMatch` both use it — a manualMatch/autoMatch pair can no longer
  bind one journal entry to two statement lines, and autoMatch can no
  longer overwrite a record a user just exceptioned. `autoMatch` skips
  contested claims instead of aborting the sweep.
- `BankReconciliationService` - deleted dead `createOutstandingCheck`,
  `presentCheck`, `clearCheck`, `stopCheck`, `returnCheck` (zero callers,
  zero tests; the live paths are import/autoMatch/manualMatch/exception).
- `ReconciliationController::manualMatch` - catches `DomainException`
  (the service can now legitimately refuse a contested match).
- `SchemaSeeder` - `str_reports.bnm_reference` is now UNIQUE (nullable):
  closes the residual race where two different STRs could claim the same
  BNM reference — each locks only its own row, so the exists-check can't
  see the other's uncommitted write. The locked re-check in
  `StrReportService::submit` remains the primary gate.
- `app/Console/Commands/InstallStrBnmReferenceUnique.php` - NEW
  `str:install-bnm-reference-unique`: idempotent upgrade path that adds the
  unique index to existing databases (SchemaSeeder only covers fresh
  installs). Pre-flights duplicate non-null references and refuses with a
  report rather than failing on the constraint. Applied to the local DB.
- `ComplianceFlagService::resolve` - idempotent under the row lock: a
  second resolve racing the first no longer writes a duplicate audit row.
- `ExportService::toExcel` - typed the anonymous `FromArray` export class
  (property/param/return shapes) — clears the last PHPStan findings.

---

## [2026-09-21] - Vertical-slice review fixes round 2: facade removal, bounded ledger rebuild, audit split, real receipt

### Files Changed
- `app/Services/Transaction/TransactionService.php` - deleted; dead
  pass-through facade used only by tests. All test call sites rewired to
  `TransactionCreationService::prepareAndCreate`,
  `TransactionValidationService::preValidate`, and
  `TransactionApprovalService::approve` directly.
- `app/Services/Accounting/AccountingService.php` - `rebuildRunningBalances()`
  now starts from the earliest ledger row inserted by the backdated entry and
  seeds from its predecessor instead of rewriting the whole account+branch
  chain; signature takes the `JournalEntry`.
- `app/Services/Audit/AuditChainService.php` - NEW: tamper-evidence machinery
  extracted from AuditService — `computeEntryHash`, `canonicalJson`,
  `sealLogEntry`, `verifyChainIntegrity`, `getUnsealedCount`,
  `getOldestUnsealedAt`, `quarantineEntry`, and the `GAP_PREFIX` /
  `SEAL_STATUS_QUARANTINED` / hash-version constants.
- `app/Services/AuditService.php` - slimmed 1028 → 707 lines: write path and
  domain loggers only; delegates synchronous sealing to `AuditChainService`
  (nullable ctor arg, `??=` fallback matching existing style).
- `app/Jobs/Audit/SealAuditHashJob.php` - `handle()` now takes
  `AuditChainService`; constants re-pointed.
- `app/Console/Commands/{SealPendingAuditEntries,VerifyAuditChainCommand,
  AuditWatchUnsealed}.php`, `app/Http/Controllers/Admin/AuditLogController.php` -
  chain-method callers rewired to `AuditChainService`.
- `resources/views/transactions/receipt.blade.php` - replaced the app-layout
  stub with a real Dompdf thermal receipt (80mm): reference, branch, teller,
  customer, currency/quantity/rate/RM total, status, Code-128 barcode,
  verification QR + link.
- Tests updated: `TransactionServiceTest`, `TransactionServicePreValidationTest`,
  `TransactionServicePrepareTest`, `TransactionWorkflowTest`,
  `CriticalTransactionFixesTest`, `TransactionAccountingVerificationTest`,
  `AuditServiceTest`, `SealAuditHashJobTest`, `AuditChainQuarantineTest`,
  `PerformanceFixesTest`, `EdgeCaseFixesTest`; new regression tests:
  `AccountingWorkflowTest::backdated_journal_entry_repairs_running_balances_from_insertion_point`
  and `ReceiptGenerationServiceTest::receipt_view_renders_transaction_details`.

### Verified intentionally-kept (not defects)
- `TestDashboard` middleware already 404s `test-results/*` outside
  local/testing envs plus a `role:view_test_results` gate — F-10 was already
  defended; no change.
- `LegacyApiResponse` is a documented BC shim for two sanction endpoints whose
  top-level payload shape existing consumers parse; consolidating would break
  API clients.
- `AuditTrailHelper` is the shaped-metadata seam for five services, not a
  redundant wrapper — retained.

### Purpose
Second round of vertical-slice findings (F-2, F-3, F-8 + receipt stub):
removes the dead facade seam, bounds the backdated-posting ledger repair to
rows that can actually be stale, splits audit writing from chain integrity,
and gives `transactions.receipt` a renderable PDF template.

### Testing
- Affected-area sweep: 100+ tests green including new backdate + receipt tests
- `vendor/bin/pint --dirty` clean; `vendor/bin/phpstan analyse` clean

---

## [2026-09-21] - Vertical-slice review fixes: shared orchestration, cache invalidation, atomic writes

### Files Changed
- `app/Services/Transaction/TransactionCreationService.php` - extracted
  `buildCreationContext()` as the shared orchestration seam: booking
  eligibility, exchange calculation, quote-convention normalization,
  compliance gates, optional CDD floor, teller allocation, and initial
  status resolution. `prepareAndCreate()` now delegates to it.
- `app/Http/Controllers/TransactionWizardController.php` - `step3()` now
  calls `buildCreationContext()` with the session CDD level as a floor
  instead of re-assembling the orchestration by hand; removed constructor
  dependencies made unused by the extraction.
- `app/Services/Customer/CustomerService.php` - replaced service-layer
  `request()?->ip()` with `ActorContext::capture()->ipAddress` so audit
  entries get a correct IP in queued/non-HTTP contexts.
- `app/Http/Controllers/Api/V1/CustomerController.php` - injected
  `CacheInvalidationService`; `destroy()` now calls `forgetCustomer()`
  and `invalidate('customers')` after soft-delete so the cached customer
  is not served until TTL expiry.
- `app/Services/Compliance/SanctionsImportService.php` - added
  `recordImportOutcome()` wrapping list status updates and
  `SanctionImportLog::create()` in `DB::transaction()`, applied to full,
  delta, unchanged, and failed import paths so a list cannot be left in
  `pending` without its audit log.
- `app/Services/System/SetupService.php` - wrapped business provisioning
  in `DB::transaction()` (schema creation stays outside since DDL
  auto-commits); a mid-setup failure no longer leaves a half-provisioned
  install.
- `tests/Feature/Api/CustomerApiTest.php` - added regression tests for
  destroy cache invalidation and the open-transaction guard.

### Purpose
Implements findings F-1, F-4, F-5, F-6, and F-9 from the vertical-slice
code review: orchestration duplication between the wizard and the shared
creation service, unguarded multi-step writes in sanctions import and
setup, a cache-invalidation hole on API customer deletion, and an HTTP
`request()` leak inside a domain service.

### Testing
- `php artisan test --compact tests/Feature/Api/CustomerApiTest.php`
  7 passed (2 new)
- Focused sweep across transaction wizard, sanctions import, setup, and
  customer tests: 51 passed
- `vendor/bin/pint --dirty` clean

---

## [2026-09-21] - Dead-code cleanup pass 2: dead commands, scratch scripts, redundant deps

### Files Changed
- `app/Console/Commands/` - deleted `ComprehensiveSetup` (`comprehensive:setup`,
  superseded by `business:setup`), `CleanupSystemAlertsCommand` (`alert:cleanup`),
  `RetryFailedJobs` (`queue:retry-failed`), `AuditStockTransferIntegrity`
  (`transfers:audit-integrity`), `TestNotification` (`notifications:test`),
  `TestTransactionScenarios` (`test:scenarios`) — zero refs in code, scheduler,
  deploy workflow, CI, or tests
- `scripts/` - deleted 17 unreferenced scratch/verifier scripts
  (`verify-phase1.php`, `verify-fixes.sh`, `verify-middleware.php`,
  `verify-model-schema.php`, `verify-view-consistency.sh`, `verify-routes.py`,
  `verify-guide-*.cjs`, `render-guide.py`, `check-routes.php`,
  `audit-routes.php`, `audit-domain-terms.py`, `create_test_data.php`,
  `find-orphaned-*`, `find-todos.php`); kept `ci/` (Makefile), `install-deps.sh`
  and `db-sysadmin-hardening.sql` (README/MEMORY)
- `composer.json`/`composer.lock` - removed explicit `guzzlehttp/guzzle` and
  `blade-ui-kit/blade-icons` requirement lines (both remain installed
  transitively via `laravel/framework`/`blade-heroicons`)
- Adverse Media import limb deleted: `AdverseMediaImportCommand`
  (`adverse-media:import`), `AdverseMediaImportService`,
  `AdverseMediaImportLog` model, and the `adverse_media_import_logs` table
  in `SchemaSeeder`. The screening limb stays live: `AdverseMediaEntry` is
  queried by `CustomerScreeningService`/`ScreeningEnforcementService`.
  `AdverseMediaScreeningTest` trimmed to its 5 screening tests (3
  import-specific tests removed). `ImportStatus`/`ImportTrigger` enums kept —
  shared by sanctions/transaction imports.
- `phpstan-baseline.neon` - regenerated

### Purpose
Second pass of the dead-code audit. **Key correction:** all `Install*`/`db:*`
schema-installer commands are LIVE — `.github/workflows/deploy.yml` runs them
as the convergence path for already-seeded databases. None were deleted.
`accounting:install-mappings`, `test:run`, `customers:re-encrypt` are also
live (error-message remedies / TestResults UI / encryption upgrade path).

### Testing
- `php artisan route:list` — clean
- `vendor/bin/phpstan analyse` — clean (baseline regenerated, 2159 entries)
- `php artisan test --compact` — full suite: 2533 passed, 3 skipped; one
  pre-existing flake (`AdminReportSmokeTest` Faker apostrophe name vs
  `assertSee` escaping — passes on re-run, unrelated)
- `AdverseMediaScreeningTest` — 5 screening tests pass

### Impact Analysis
- Deploy-invoked and test-coupled commands explicitly excluded from deletion.

---

## [2026-09-21] - Dead-code cleanup: broadcasting, dead classes, unregistered middleware, stale assets

### Files Changed
- `bootstrap/app.php` - removed `channels` routing file registration; removed
  `signed`/`strict.ratelimit` middleware aliases and their imports
- `app/Providers/AppServiceProvider.php` - removed `nameFrameworkRoutes()`
  broadcast route-naming callback and `Route` facade import
- `routes/console.php` - removed skeleton `inspire` Artisan command
- `config/app.php` - removed commented-out `BroadcastServiceProvider` line
- `config/notifications.php` - `default_channels` simplified to `['database']`
  (broadcast conditional was already dead: `BROADCAST_DRIVER` unset)
- `config/broadcasting.php`, `routes/channels.php` - deleted; broadcasting
  unused (no `ShouldBroadcast` events, no Echo/Pusher)
- `app/Providers/BroadcastServiceProvider.php` - deleted; never registered
- `app/Http/Middleware/` - deleted `StrictRateLimit`, `ValidateSignature`,
  `TrimStrings`, `TrustProxies`, `PreventRequestsDuringMaintenance`
  (unregistered skeleton copies; framework defaults cover them)
- `app/Http/Requests/` - deleted `OpenCounterRequest`,
  `AcknowledgeHandoverWebRequest`, `StoreCounterRequest`,
  `EmergencyCloseRequest`, `HandoverCounterRequest` (counter web UI was
  removed; API uses `Api\V1\Counter\*` requests)
- `app/Jobs/` - deleted `ReportGenerationJob`, `RescreenHighRiskCustomersJob`
  (never dispatched)
- `app/Services/` - deleted `Compliance/NarrativeGenerator`,
  `Reporting/CustomerReportService`, `System/PerformanceBaselineService`,
  `DTOs/ValidationResult` (never injected/instantiated)
- `app/Services/Contracts/` - deleted all 19 `*Interface` files
  (unimplemented aspirational contracts; zero `implements`/type-hints)
- `app/Support/AccountCodes.php` - deleted; superseded by
  `App\Enums\AccountCode`
- `database/seeders/TestTransactionWizardSeeder.php` - deleted; never called
- `composer.json`/`composer.lock` - removed `laravel/dusk` and
  `brianium/paratest` (dev deps with zero usage; removed via Composer 2.10.3
  phar — system Composer 2.0.14 cannot resolve Laravel 12)
- `public/vendor/livewire/` - deleted; Livewire is not installed
- `.env.example` - removed stale `BROADCAST_DRIVER` key
- `phpstan-baseline.neon` - regenerated (2172 baselined errors)
- `tests/TestCase.php` - comment updated (StrictRateLimit → named limiters)
- `tests/Unit/Services/DTOTest.php` - removed `ValidationResult` test
- `tests/Unit/Services/ComplianceDirectoryTest.php` - removed
  `NarrativeGenerator` from expected-files list
- Deleted tests for deleted subjects: `TrustedProxiesTest`,
  `StrictRateLimitTest`, `RescreenHighRiskCustomersJobTest`,
  `PerformanceBaselineServiceTest`, `ReportGenerationJobPerformanceTest`,
  `CustomerReportServiceTest`, `ServiceContractsTest`
- Deleted ~19 scratch Playwright specs (`tests/*.spec.ts`: click-test,
  debug-interactions, example, minimal-test, route-crawl, every-page,
  login-crawl, etc.) — flagged by audit as untracked-per-policy scratch files

### Purpose
Codebase audit identified dead code left behind by removed features
(counter web UI, broadcasting), retired architecture (unimplemented
service contracts), and skeleton boilerplate never wired into the app.
Removing it shrinks the audit surface and eliminates misleading scaffolding.

### Changes Made
- 78 files deleted; broadcasting subsystem fully removed
- All deletions verified by cross-reference greps (zero remaining
  references), PHPStan, route compilation, and targeted tests

### Testing
- `php artisan route:list` — 466 routes resolve, no errors
- `vendor/bin/phpstan analyse` — clean (baseline regenerated)
- `php artisan test --compact` on `DTOTest`, `ComplianceDirectoryTest`,
  `AuthenticationTest`, `AdminReportSmokeTest` — 49 pass
- `vendor/bin/pint` — clean on all touched files

### Impact Analysis
- `detect-changes --scope all`: 374 files / 1117 symbols changed, risk
  CRITICAL — dominated by pre-existing uncommitted work (~344 files),
  not this cleanup (~86 files). All deleted symbols verified to have zero
  callers/implementations via cross-reference grep before deletion.
- No live route, view, job dispatch, service injection, or policy
  resolution references any deleted file.

---

## [2026-09-20] - Ledger query extraction, review finding fixes, simulation realignment

### Files Changed
- `app/Services/Accounting/LedgerQueryService.php` - new; owns read-side ledger queries
  (`getAccountBalance`, `getAccountActivity`, `getAccountsActivity`, debit-normal checks,
  latest running-balance lookup); `AccountingService` delegates to it and keeps write logic
- `database/seeders/RiskScoreSnapshotHistorySeeder.php` - new; chunked, resumable backfill of
  6 historical snapshots per customer at 30-day intervals (scores, labels, trends, factors,
  prior rating, next screening date); bulk inserts, no duplicate `(customer_id, snapshot_date)` pairs
- `database/seeders/SimulationSeeder.php` - sim customer now carries Standard-CDD fields
  (address, phone, occupation, employer_name)
- `app/Http/Middleware/SecurityHeaders.php` - removed invalid `speaker`/`vibrate`
  Permissions-Policy directives
- `resources/views/rates/units.blade.php` - CSP nonce on inline script
- `config/pos.php` - deleted (dead config, zero `config('pos.*')` callers); `.env.example`
  `POS_*` keys scrubbed
- `tests/Unit/CompiledAssetsTest.php` - new stale-build guard: fails when `public/build`
  is older than any `resources/{views,css,js}` source
- `tests/Feature/RiskScoreSnapshotHistorySeederTest.php` - new; resume-after-partial-insert
  and soft-delete no-duplicate coverage
- ~14 source-grep tests converted to behavioral/config/reflection assertions
  (`tests/Feature/Audit/*`, `tests/Feature/Auth/*`, `tests/Unit/Risk/*`,
  `tests/Unit/Services/Traits/*`, `tests/Feature/CounterHandoverAcknowledgeTest.php`)
- `tests/Http/Simulation/` - waves realigned to drawerless/API architecture: counter lifecycle
  via API open/approve/close/emergency-close routes; Wave C parity books without counter/till
  fields; Wave A emergency close time-travels past the 30-minute session-age gate

---

### Files Changed
- `app/Models/TellerAllocation.php` - new `addDailyUsedWithinLimit()` (conditional
  UPDATE under row lock); `toNumericAmount()` returns a decimal string via
  `BcmathHelper` instead of casting to float
- `app/Services/Branch/TellerAllocationService.php` - `applyTransactionAllocation`
  enforces the daily cap inside the locked mutation
- `app/Services/Contracts/TransactionCreationServiceInterface.php` - documents
  `assertBookingEligibility()` / `runComplianceGates()` contracts
- `app/Http/Controllers/TransactionWizardController.php` - step-3 submit now runs
  the shared booking eligibility + compliance gates; CDD level never downgraded
  below the session tier
- `app/Services/CustomerScreeningService.php` - `batchScreen()` prefilters
  sanction/adverse pools on the union of customer name tokens (bounded memory)
- `app/Models/Customer.php` - `scopeWhereLatestSnapshotNeedsRescreening()`
- `app/Services/Compliance/CustomerRiskScoringService.php`,
  `app/Services/Compliance/CustomerRiskReviewService.php` - use the new scope
- `app/Http/Controllers/Compliance/RiskDashboardController.php` - high-risk list
  filters on `latestRiskSnapshot`, not any historical snapshot
- `tests/` - regression tests: atomic cap, decimal precision, mid-wizard freeze
  rejection, batch-prefilter true-match retention, latest-snapshot scope

### Purpose
Functional-cluster code review found four real defects: the wizard submit path
bypassed the shared eligibility gate (a customer frozen mid-wizard could still
book), the daily-allocation cap was a non-atomic read-then-write, allocation
quantities passed through `(float)`, and `batchScreen` hydrated the entire
sanctions corpus.

### Testing
- `TransactionWizardTest`, `TellerAllocationTest`, `CustomerScreeningServiceTest`,
  `RiskDashboardTest`, allocation/branch suites — 106+ targeted tests pass
- Broader transaction/compliance sweep — 131 pass
- Pint clean; `detect-changes --scope all` clean

---

## [2026-09-20] - Risk trends page data + chart component repair

### Files Changed
- `app/Http/Controllers/Compliance/RiskDashboardController.php` - paginated `needsRescreening`
- `app/Services/Compliance/CustomerRiskScoringService.php` - `getCustomersNeedingRescreening` + `getDashboardSummary` now evaluate the customer's **latest** snapshot (`latestRiskSnapshot`), ordered most-overdue-first
- `app/Services/Compliance/CustomerRiskReviewService.php` - iterates due customers via `latestRiskSnapshot` instead of raw overdue snapshot rows
- `resources/views/components/chart-trend.blade.php` - rewritten: `bg-*` theme tokens (was dead `fill-*` on divs), per-bar value labels, absolute-positioned bars, baseline, zero-tick, empty state
- `resources/views/compliance/risk-dashboard/trends.blade.php` - fixed nested `<a>` around `x-customer-link`; renders `links()`
- `tests/Unit/CustomerRiskScoringServiceTest.php` - `needing_rescreening_uses_latest_snapshot_only`
- `tests/Unit/CustomerRiskReviewServiceTest.php` - `process_due_reviews_ignores_stale_overdue_snapshots`
- `tests/Feature/Views/ThemeTokenUsageTest.php` - `fill-*` expectations → `bg-*`
- `tests/Feature/ChartOfAccountsViewerTest.php` - updated for paginated index
- `public/build/` - rebuilt (`npm run build`) — stale CSS lacked `inset-x-0`/`bottom-0`

### Purpose
`/compliance/risk-dashboard/trends` rendered no data: the snapshot table was
empty (1 row) and the chart component had dead styling. Also fixed a latent
"stale snapshot keeps customer forever-due" bug in the rescreening queries.

### Changes Made
- Latest-snapshot semantics for due-for-rescreening everywhere
- Chart bars now visible, labeled, theme-token colored
- Staging backfilled with 1,080 monthly snapshots across 180 customers
  (demo data; rescreening history had never run)

### Testing
- `php artisan test --filter='Chart|RiskDashboard|Trend|ThemeToken'` — 112 pass
- New unit tests for latest-snapshot semantics — 26 pass
- Browser-verified bars, labels, and populated re-screening table

### Impact Analysis
- `defaultMatrix`/permission graph untouched here; view + query-scope changes only.

---

## [2026-09-20] - Read-only screening access for tellers (`view_screening_results`)

### Files Changed
- `app/Enums/Permission.php` - new `ViewScreeningResults` case + label/description/grouping + default grants (teller, compliance officer, admin)
- `routes/web.php` - read-only screening GETs moved to `role:access_compliance,view_screening_results`; mutations stay `access_compliance`
- `routes/api_v1.php` - same split for history/status vs screen/batch-screen
- `resources/views/components/navigation.blade.php` - sidebar "Screening" entry
- `resources/views/compliance/screening/matches/show.blade.php` - mutation controls gated by `AccessCompliance`; tellers see "Pending compliance review"
- `tests/Feature/Compliance/ScreeningMatchDispositionTest.php` - teller read/mutation-denied, accountant denied
- `tests/Feature/RolePermissionMatrixTest.php` - sidebar assertion pinned to exact href
- `tests/rbac-matrix.spec.ts` - screening-matches expectation → `['admin','teller','compliance']`

### Purpose
Tellers needed visibility into screening outcomes on their own customers
without gaining compliance mutation rights.

### Testing
- 124 permission/screening/nav tests pass; browser-verified teller read + POST 403

### Impact Analysis
- `Permission::defaultMatrix` is CRITICAL-risk (root of permission graph);
  change was additive — all consumers read the matrix dynamically.

---

## [2026-09-20] - Shared filter-bar form + strict select comparison

### Files Changed
- `resources/views/components/filter-bar.blade.php` - renders `<form method action>` when `method` is passed
- `resources/views/components/select.blade.php` - strict normalized-string `selected` comparison (null/array guarded)
- `resources/views/transactions/index.blade.php` - full filter bar (type, currency, status, date range, refund mode)
- `app/Http/Requests/IndexTransactionRequest.php` - new filter validation
- `app/Http/Controllers/TransactionController.php` - `when` clauses + option lists
- `tests/Feature/Views/FilterBarFormTest.php` - new
- `tests/Feature/TransactionIndexFilterTest.php` - new

### Purpose
`x-filter-bar` rendered a `<div>`, so Filter buttons on ~16 pages
(customers, audit logs, cases, STR, reports…) never submitted. Separately,
`x-select`'s loose `==` made untouched boolean filters submit `0` and
silently excluded refunds on `/transactions`.

### Testing
- Headless Playwright sweep of `/transactions` + `/customers` filters
- `FilterBarFormTest` 5 tests; transaction/customer suites green

---

## [2026-09-20] - Unmasked customer ID display project-wide

### Files Changed
- `app/Models/Customer.php` - `id_number` accessor (decrypts at display); `id_number_masked`/`ic_number` masked accessors removed
- `resources/views/customers/*`, `transactions/*`, `components/customer-typeahead.blade.php`, `compliance/edd/*` - full ID rendering
- `app/Services/CustomerService.php`, `CustomerSearchController`, `Api/V1/CustomerResource`, `UnifiedAlertQueryService`, `AnalyticsController`, `StrReportController` - JSON keys `id_number_masked`/`ic_number_masked` → `id_number`
- `app/Http/Controllers/VerificationController.php` - public verify page echoes full transaction reference
- `app/Services/Compliance/Reports/QlvrReportGenerator.php` - `maskName` removed; full names in QLVR

### Purpose
User request: "do not mask id in entire project". Encryption at rest and
the blind index remain; only display/payload masking was removed.

### Testing
- 567+ tests green; zero `*_masked` references remain in `app/`/`resources/`

---

## [2026-09-20] - Pagination across all list pages + Collection::paginate macro

### Files Changed
- `app/Providers/AppServiceProvider.php` - `Collection::paginate()` macro
- ~14 controllers (`SanctionListController`, `StockCashController`, `ScreeningController`, `MfaController`, `DashboardController`, `RateController`, `MyStockController`, `BranchPoolController`, `ChartOfAccountsController`, `FiscalYearController`, `RevaluationController`, …) - `get()`/`limit()` → `paginate()`
- ~18 list views - `{{ $items->links() }}`
- `tests/Feature/ListPaginationTest.php` - new

### Purpose
Several lists were unbounded or silently truncated (`limit(50)` on import
logs and position transactions made rows unreachable).

### Testing
- `ListPaginationTest` 4 tests; 525 related tests green

---

## [2026-09-20] - Customer name links + transaction/customer detail cleanup

### Files Changed
- `resources/views/components/customer-link.blade.php` - new permission-aware link component (`field` prop, `N/A` fallback)
- `resources/views/transactions/{show,confirm,cancel}.blade.php` - Counter field removed; customer name + ID link to `customers.show`
- `resources/views/customers/show.blade.php` - detailed Screening Results card (per-result badge, score, match detail, disposition)
- `app/Http/Controllers/CustomerController.php` - eager-loads + paginates `screeningResults`
- ~28 views repointed to `x-customer-link`
- `tests/Feature/Views/CustomerNameLinkTest.php` - new

---

## [2026-09-20] - `/my-stock` total valuation header

### Files Changed
- `app/Http/Controllers/MyStockController.php` - total-held / MYR-cash / foreign-stock-value at latest board sell rate (branch card preferred); unvalued-currency warning
- `resources/views/my-stock.blade.php` - stat cards
- `tests/Feature/MyStockPageTest.php` - valuation assertions

---
