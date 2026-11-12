# Changelog

All notable changes to this project are documented here. Format per
`AGENTS.md` §10 — newest first, grouped by change set.

---

## [2026-11-12] - Round 4: cache consistency, per-month journal numbers, MFA expiry, dead code

### Files Changed
- `app/Services/Accounting/LedgerService.php` — P&L and balance sheet cached under the shared `ledger` tag
- `app/Services/Accounting/AccountingService.php` — per-month sequential `entry_number`
- `database/seeders/SchemaSeeder.php` — new `journal_entry_sequences` table (create + drop list)
- `app/Services/System/MfaService.php`, `app/Models/DeviceComputations.php` — a NULL `expires_at` no longer grants trust
- `app/Enums/CddLevel.php` — removed the dead `determine()` method
- `app/Services/Transaction/TransactionHoldService.php` — removed the dead `getHoldReasons()`
- `app/Services/Traits/TillBalanceTrait.php` — deleted (its only method was unused)
- `app/Http/Middleware/EnsureBranchScope.php` — explicit guarantee/delegation docblock
- Tests: `AccountingServiceTest`, `MfaServiceTest`, `ThresholdAccessCentralizedTest`, `TransactionHoldServiceTest`, `CriticalTransactionWorkflowTest`, `TransactionServiceTest`; deleted `tests/Unit/Services/Traits/TillBalanceTraitTest.php`

### Purpose
Close the last audit findings: the statement-caching inconsistency, the
non-sequential journal entry numbers, the MFA NULL-expiry trust hole, and the
dead code paths that had drifted away from production.

### Changes Made
- **Statement caching.** `getProfitAndLoss()` and `getBalanceSheet()` are now
  cached under the same `ledger` tag and 60s TTL as `getTrialBalance()`. Every
  ledger write flushes that tag from inside `updateLedger()`, so all three
  statements stay consistent after a posting.
- **Journal entry numbers.** New `journal_entry_sequences` table holds one
  counter per `YYYY-MM`; `allocateEntryNumber()` increments it under a row lock
  so concurrent postings in a month serialise. On first use for a month the
  counter continues from the highest existing `JE-{Ym}-` id, so deploying over
  an existing ledger never reissues a number. Numbers are now contiguous per
  month (`JE-202601-0001`, `JE-202601-0002`, `JE-202602-0001`).
- **MFA trusted devices.** `hasTrustedDevice()` and `getTrustedDevices()` now
  require `expires_at > now()`; `DeviceComputations::isValid()` treats a NULL
  expiry as invalid. `rememberDevice()` always stamps an expiry, so only
  backfilled legacy rows are affected — they must re-authenticate once.
- **Dead code removed.** `CddLevel::determine()` (superseded by
  `CddLevelDeterminationService`), `TransactionHoldService::getHoldReasons()`,
  and `TillBalanceTrait::updateTillBalance()` are gone, along with the tests
  that only exercised them. `CriticalTransactionWorkflowTest` now derives the
  expected CDD level from the production service.
- **`EnsureBranchScope`** documents precisely what it guarantees (routes that
  name a branch) and what it delegates (collection endpoints, via
  `ResolvesBranchScope`, policies and services).

### Testing
- `php artisan test --compact` (full suite) — 2819 passed, 3 skipped
- `php artisan test --compact tests/Unit/AccountingServiceTest.php tests/Unit/Services/System/MfaServiceTest.php tests/Unit/ThresholdAccessCentralizedTest.php tests/Unit/Transaction/TransactionHoldServiceTest.php tests/Feature/CriticalTransactionWorkflowTest.php` — 44 passed
- `vendor/bin/phpstan analyse` (whole app) — no errors
- `vendor/bin/pint --dirty --format agent` — passed

### Impact Analysis
- The new cache wrapping reuses the existing `ledger` tag and TTL; the
  invalidation path is unchanged and already fires on every posting.
- `allocateEntryNumber()` runs inside the existing `createJournalEntry()`
  transaction; `lockForUpdate()` serialises same-month postings. `entry_number`
  is display-only (no lookup parses it), and `SetupService` opening balances
  use a separate `OB-` prefix, so no number space collides.
- Removing `CddLevel::determine()` deletes behaviour production never called;
  `CddLevelDeterminationService` is covered by
  `CddLevelDeterminationServiceTest`.

---


### Files Changed
- `app/Services/Transaction/TransactionCreationService.php` — explicit CDD guard; `MathService::compare()`
- `app/Services/Transaction/RateManagementService.php` — annotated display-only float casts
- `app/Services/Transaction/Checks/StructuringCheck.php`, `Checks/UnusualPatternCheck.php` — annotated display-only float casts

### Purpose
Close the last low-risk audit items that need no business decision.

### Changes Made
- `buildCreationContext()` now throws a typed `TransactionValidationException`
  when the CDD assessment is missing instead of letting
  `array_search(null, CddLevel::cases())` raise a `TypeError`. The method is
  public and also called by the wizard and import paths.
- `ensureStockForSell()` uses `MathService::compare()` (same scale=4 as the
  previous direct `bccomp()` call).
- Annotated the display-only `(float)` casts in the rate and structuring
  checks as non-money formatting, matching the audit's request to make the
  exception to the BCMath-only convention explicit.

### Testing
- `php artisan test --compact` (full suite) — 2822 passed, 3 skipped
- `php artisan test --compact tests/Unit/Transaction tests/Feature/TransactionWizardTest.php tests/Feature/TransactionWorkflowTest.php` — 121 passed
- `vendor/bin/pint --dirty --format agent` — passed
- `vendor/bin/phpstan analyse <edited paths>` — no errors

### Impact Analysis
- The CDD guard is on a path already gated by `runComplianceGates()`; it only
  changes the failure mode (typed domain exception vs `TypeError`) for a
  hypothetical caller that bypasses that gate.

---


### Files Changed
- `app/Http/Controllers/Api/V1/{Counter,MonthEndClose,BranchClosing,Branch,CounterHandover,Screening,SanctionList}Controller.php`, `app/Http/Controllers/Api/V1/Compliance/FindingController.php` — controller-level permission checks
- `app/Http/Controllers/{TransactionWizardController,Accounting/BudgetController,Admin/SanctionSourceController,Compliance/FindingController,Compliance/SanctionListController,Compliance/ScreeningController,Compliance/ScreeningMatchController}.php` — controller-level permission checks
- `app/Rules/ValidTill.php` — fail closed without an authenticated, branch-assigned user
- `app/Services/System/SystemAlertService.php` — alert row commits before email delivery
- `database/seeders/SchemaSeeder.php` — protected guard + `runWithPopulatedGuard()`; wider populated-database check
- `app/Console/Commands/ResetTestDatabase.php` — uses the guard helper
- `app/Models/Customer.php` — removed the dead `getIcNumberAttribute()` alias
- `tests/Feature/Api/V1/BranchResourceAuthorizationTest.php`, `tests/Unit/Rules/ValidTillTest.php`, `tests/Unit/Services/SystemAlertServiceTest.php` — coverage
- `phpstan-baseline.neon` — `ValidTillTest` closure count 1 → 2

### Purpose
Close the remaining audit findings that carry real production value: uneven
controller-level authorization, a till rule that resolved counters without an
authenticated branch, an alert record that could roll back with a failed email
delivery, and a destructive seeder guard that any code could flip.

### Changes Made
- **Controller permission sweep.** 15 controllers with mutating actions now
  call `requirePermission()` matching their route's `role:` key
  (`ManageCounters`, `OperateCounters`, `ManageBranchClosing`, `ManageBranches`,
  `ManageAccounting`, `ManageSanctions`, `AccessCompliance`,
  `CreateTransactions`, `AccessAccounting`). The route middleware stays the
  outer gate; the controller check keeps the permission enforced if routes are
  regrouped. `NotificationPreferenceController` is intentionally untouched
  (every authenticated user manages their own preferences); `SetupController`
  and `Auth/*` are pre-auth or self-service by design.
- **`ValidTill` fails closed.** Previously an unauthenticated caller fell back
  to an unscoped counter lookup. It now refuses unless the user is
  authenticated and branch-assigned, then scopes the lookup to that branch.
- **`SystemAlertService::send()` commits the alert row first**, then delivers
  email outside that transaction and catches delivery failures — a mailbox
  outage can no longer roll back the compliance record.
- **`SchemaSeeder` guard hardened.** `$allowPopulated` is now `protected`; the
  only opt-in is `runWithPopulatedGuard(callable)`, used by `seedNow()` and
  `db:reset-test --fresh`. `databaseIsPopulated()` now inspects 20 tables
  instead of 10.
- Removed `Customer::getIcNumberAttribute()` (zero references; duplicated the
  decryption path).

### Testing
- `php artisan test --compact` (full suite) — 2822 passed, 3 skipped
- `php artisan test --compact tests/Feature/Api tests/Feature/Compliance tests/Feature/Web tests/Feature/Admin tests/Feature/Views` — 400 passed
- `php artisan test --compact tests/Feature/Api/V1/BranchResourceAuthorizationTest.php tests/Feature/Commands` — 25 passed
- `vendor/bin/phpstan analyse` (whole app) — no errors
- `vendor/bin/pint --dirty --format agent` — passed

### Impact Analysis
- The controller checks mirror the existing route middleware exactly, so no
  authorized flow changes behaviour; a new test bypasses `CheckRole` with
  `withoutMiddleware()` to prove the controller still refuses a teller calling
  `POST /api/v1/branches`.
- `ValidTill` is only used by the transaction form requests (all auth-guarded),
  so failing closed affects no CLI/seed path; `TransactionImportService` has
  its own till logic.
- `detect_changes` — `risk_level: critical` (aggregate): 73 symbols across
  27 files, 122 affected processes, no `partial`/`truncated` flags. The
  critical rating comes from touching 15 controllers, which are hubs in the
  execution graph; every touched action mirrors an existing route gate, and
  the full suite is green.

---


### Files Changed
- `config/security.php` - rotation default 90 days; removed 7 dead `burst_allowance` keys
- `.env.example` - `SECURITY_PASSWORD_EXPIRY_DAYS=90`
- `database/factories/UserFactory.php` - stamp `password_changed_at`
- `tests/Feature/Auth/LoginControllerTest.php` - stale-timestamp rotation coverage
- `app/Http/Requests/StoreTransactionRequest.php` - branch-scoped counter resolution
- `app/Http/Requests/Api/V1/Transaction/StoreTransactionRequest.php` - branch-scoped counter resolution
- `app/Http/Requests/Concerns/HasTransactionValidationRules.php` - shared `counterQuery()`
- `tests/Feature/Web/TransactionControllerStoreTest.php` - cross-branch counter regression
- `app/Services/Branch/PoolRemittanceService.php` - documented cancel/acknowledge SoD asymmetry
- `tests/Feature/PoolRemittanceTest.php` - receiver-cannot-cancel coverage
- `app/Services/Transaction/TransactionValidationService.php` - `checkSanctions` via `Cache::remember`
- `app/Services/Transaction/TransactionCreationService.php` - dropped stack trace from booking-failure context
- `app/Http/Middleware/SecurityHeaders.php` - removed the inert `style-src` nonce
- `app/Services/Accounting/LedgerService.php` - trial balance lists every account
- `tests/Unit/LedgerServiceTest.php` - inactive-account footing coverage
- `tests/Unit/SecurityHeadersTest.php` - style-src coverage

### Purpose
Continue closing the production gap audit. Forced password rotation was
implemented but disabled by default (`SECURITY_PASSWORD_EXPIRY_DAYS=0`),
contradicting the documented BNM policy. Counter resolution in the booking
form requests was unscoped, so another branch's counter id resolved to its
code and was echoed back through redirect input before validation. The CSP
carried `'unsafe-inline'` alongside a nonce in `style-src`, which CSP3 makes
inert — so the chart components' dynamic inline dimensions were blocked in
production.

### Changes Made
- `config/security.php`: `password_expiry_days` defaults to 90 (env override
  retained, `0` disables). `.env.example` updated to match. `UserFactory`
  stamps `password_changed_at` so factory users are not born expired; tests
  that exercise forced rotation set the timestamp to null/stale explicitly.
- Both transaction form requests resolve counters through a `counterQuery()`
  helper scoped to the acting user's branch; office roles without a branch
  (admin) keep the unscoped lookup. `ValidTill` remains the authoritative
  booking gate.
- `SecurityHeaders`: `style-src` no longer receives the per-request nonce, so
  `'unsafe-inline'` is effective and the chart components render in
  production. `script-src` keeps its nonce; `unsafe-inline`/`unsafe-eval`
  remain absent there.
- `PoolRemittanceService::cancel()` documents the deliberate asymmetry: the
  sender may withdraw their own pending remittance, the receiver may not
  acknowledge their own. A test now asserts the receiving branch is refused.
- `checkSanctions()` uses `Cache::remember()` (removes the has/get TOCTOU).
- `recordBookingFailure()` no longer persists `getTraceAsString()` into the
  `transaction_errors.error_context` JSON (size/PII exposure).
- Removed the seven `security.rate_limits.*.burst_allowance` keys (zero
  consumers).
- `LedgerService::getTrialBalance()` now lists every account instead of only
  active ones. `is_active` governs posting eligibility, not reporting: a
  retired account that still carries a ledger balance must appear, otherwise
  the trial balance's own debit/credit totals (and its `is_balanced` flag)
  disagree with the ledger. The P&L, balance sheet and closing sweeps already
  included every account, so the trial balance was the outlier.

### Testing
- `php artisan test --compact` (full suite) — 2817 passed, 3 skipped
- `php artisan test --compact tests/Feature/Auth tests/Feature/AuthenticationTest.php tests/Feature/SecurityTest.php` — 65 passed (also re-run under `SECURITY_PASSWORD_EXPIRY_DAYS=90`, the CI value)
- `php artisan test --compact tests/Feature/Compliance tests/Feature/Api tests/Unit/Rules tests/Feature/Views` — 401 passed
- `php artisan test --compact tests/Feature/Web/TransactionControllerStoreTest.php tests/Feature/Api/V1/TransactionControllerStoreTest.php tests/Feature/PoolRemittanceTest.php tests/Unit/SecurityHeadersTest.php` — 30 passed
- `vendor/bin/pint --dirty --format agent` — passed
- `vendor/bin/phpstan analyse <edited paths>` — no errors

### Impact Analysis
- Counter resolution: both form requests are leaf validation classes; the
  scoped lookup only narrows which counters can resolve. `ValidTill` already
  enforced branch scope on booking, so no booking path changes behaviour.
- `checkSanctions` is a cache read-aside on the sanctions screening path;
  `Cache::remember` is the same store/TTL, so the only change is atomicity.
- `SecurityHeaders` affects every response's CSP header; `style-src` is the
  only directive changed, and the previous nonce made `'unsafe-inline'` inert.

---


### Files Changed
- `app/Services/Transaction/TransactionCreationService.php` — booking replay flag
- `tests/Unit/Transaction/TransactionCreationServiceTest.php` — corrected invariant + lost-race regression
- `routes/auth.php` — logout is POST-only
- `tests/Feature/AuthenticationTest.php` — GET logout now rejected
- `app/Models/Customer.php` — blind indexes removed from serialization
- `tests/Unit/CustomerBlindIndexTest.php` — serialization coverage
- `bootstrap/app.php` — `SecurityHeaders` applied to the API group
- `tests/Feature/Infrastructure/ApiSecurityHeadersTest.php` — new
- `app/Services/Compliance/StrReportService.php` — threshold via `ThresholdService`
- `tests/Feature/Compliance/StrReportTest.php` — threshold-override coverage
- `app/Console/Commands/BusinessSetup.php` — dropped unimplemented `--with-demo`, corrected next-step routes

### Purpose
Close the P0/P1 findings from the full-repo production gap audit. The
critical finding: an idempotent replay of an already-`Completed` booking
fell through to Phase 2 and re-applied the position mutation, the journal
entry, the teller-allocation earmark and the till adjustment, and
re-emitted the creation audit / `TransactionCreated` event. The replay
early-returns sat inside the `DB::transaction` closure, so they only
exited the closure — Phase 2 ran on the replayed record anyway.

### Changes Made
- `TransactionCreationService::create()` now returns `[$transaction, $isReplay]`
  from the booking transaction. Phase 2 (side effects), the creation audit,
  the `TransactionCreated` event and the large-transaction escalation run
  only for freshly created bookings. Fresh-creation behaviour is unchanged.
- `routes/auth.php`: `Route::match(['GET','POST'])` → `Route::post` — a GET
  logout is a CSRF logout vector. Both UI logouts were already POST forms.
- `Customer::$hidden` now includes `id_number_hash` / `phone_hash`; blind
  indexes are lookup keys, not API payloads.
- `bootstrap/app.php`: `SecurityHeaders` prepended to the `api` group so API
  responses carry the same baseline (`X-Content-Type-Options`,
  `X-Frame-Options`, `Referrer-Policy`, CSP, HSTS in production).
- `StrReportService` injects `ThresholdService` and resolves the filing
  threshold through `reporting.str` (env + admin override) instead of a
  hardcoded `50000` constant; the public `THRESHOLD` const is removed.
- `BusinessSetup`: removed the `--with-demo` flag that only printed
  "not yet implemented", and replaced the next-step routes
  (`/exchange-rates`, `/counters/{code}/open`) that no longer exist with
  the real surfaces.

### Testing
- `php artisan test --compact tests/Unit/Transaction/TransactionCreationServiceTest.php` — 29 passed
- `php artisan test --compact tests/Feature/AuthenticationTest.php tests/Unit/CustomerBlindIndexTest.php tests/Feature/Compliance/StrReportTest.php tests/Feature/Infrastructure/ApiSecurityHeadersTest.php tests/Unit/SecurityHeadersTest.php` — 46 passed
- `php artisan test --compact tests/Feature/Api tests/Feature/Customers tests/Feature/Compliance tests/Unit/Transaction` — 304 passed
- `vendor/bin/pint --dirty --format agent` — passed
- `vendor/bin/phpstan analyse <edited paths>` — no errors
- `php artisan routes:validate` — 0 critical / 0 errors

### Impact Analysis
- `TransactionCreationService::create` — GitNexus `impact` upstream:
  **CRITICAL** (core booking path), 4 execution flows affected
  (`Api/V1/TransactionController@store`, `TransactionWizardController@step3`,
  `TransactionController@store`, `TransactionImportService@processRow`).
  The change is scoped to the replay branch only; the fresh-creation path
  is byte-for-byte unchanged. The pre-existing test
  `create_returns_existing_transaction_when_idempotency_key_matches`
  asserted the buggy invariant (side effects expected `->once()` on replay);
  it now asserts `->never()` and a second test covers the lost-insert-race
  replay path.
- `StrReportService` is resolved via `app()` at every call site, so the new
  constructor dependency is container-provided; no manual instantiation.

---


Foundation for the BI initiative (`BI-PLAN.md`): pre-aggregated snapshot
tables so dashboards/reports read rollups instead of scanning
transactions.

- Four rollup tables added to `SchemaSeeder` (drop list + `createSchema`):
  `analytics_daily_branch`, `analytics_daily_currency`,
  `analytics_daily_teller`, `analytics_monthly_customer`. `branch_key`
  generated columns reuse the `currency_positions` idiom so
  (date, branch) uniqueness survives NULL `branch_id`s on MySQL.
- `App\Services\Bi\AnalyticsSnapshotBuilder` — delete-then-insert per
  day/month inside a transaction; idempotent and self-healing on late
  cancellations/reversals. Margin uses the transaction's own
  `prev_average_cost` snapshot, never current position state.
- `DbDate::secondsBetween()` — driver-aware whole-second diff
  (TIMESTAMPDIFF vs strftime) for approval-SLA aggregates.
- `BuildAnalyticsSnapshots` job (`ShouldBeUnique`, 7-day rolling window
  default) scheduled nightly at 02:20; `analytics:rebuild` artisan
  command for backfills (`--from/--to/--days/--queue`).
- `App\Models\Bi\*` rollup models with `MoneyCast` decimal strings.
- `tests/Unit/AnalyticsSnapshotBuilderTest.php` — 7 tests: per-table
  aggregates, margin math, idempotent refresh, rolling-window job, and
  the command.

---

## [2026-11-10] - UI/UX overhaul: responsive shell, user menu, a11y, confirm dialog

Implements every item from the UI/UX audit. 96 Blade views touched, 2 new
components, app.js + components.js extended.

### Responsive shell
- `appShell` Alpine state gains `sidebarOpen` (mobile drawer) and
  `sidebarCollapsed` persistence via `localStorage`; body scroll locks
  while the drawer is open.
- `x-navigation` is now `fixed`/`translate-x-full` off-canvas below `md`
  and `sticky` in-flow on `md+`; collapse toggle is desktop-only.
- `x-app-layout` adds a `md:hidden` hamburger, a scrim (click + Escape
  close), `min-w-0` on `main`, and renders the new components.

### User menu (`x-user-menu`, new)
- Header dropdown: avatar + username/role, links to `password.change`,
  `mfa.trusted-devices`, `notifications.preferences`, the previously
  orphaned dark-mode toggle (`data-toggle="dark-mode"` was already wired
  in app.js but never rendered), and a POST logout. Sidebar logout kept
  as the always-visible fallback.

### Accessibility
- `aria-current="page"` on the active nav link; `title` tooltips on nav
  links (labels go `md:sr-only` when the rail is collapsed).
- Notification bell gains `aria-haspopup` + reactive `aria-expanded`.
- `scope="col"` added to 578 `<th>` cells across 92 views.
- `x-select` and `x-textarea` brought to parity with `x-input`:
  `aria-invalid`, `aria-describedby="{name}-error"`, error `id` +
  `role="alert"`.

### Confirmation dialog (`x-confirm-dialog`, new)
- `data-confirm` forms now open a styled `role="dialog"` modal instead of
  `window.confirm`: message injection, Cancel/backdrop/Escape close,
  focus moves to Confirm and returns to the trigger on close.
- Confirmed submits pass through via a one-shot `data-confirmed` flag +
  `requestSubmit()`; layouts without the shared dialog keep the native
  `window.confirm` fallback.
- New `data-confirm` coverage on genuine gaps: trusted-device removal,
  PEP rejection, branch-pool debit, remittance cancellation, allocation
  rejection. Modal/reason-gated flows intentionally untouched.

### Consistency & hygiene
- Double-submit guard: the delegated submit handler disables a form's
  submit controls once the submission survives all listeners
  (`defaultPrevented` checked in `setTimeout(0)`, so `@submit.prevent`
  AJAX flows like the transaction wizard are untouched).
- Hand-rolled status pills in `transactions/index` converted to
  `x-badge` variants.
- E2E specs updated for the new markup: `md:` width tokens, logout
  scoped to the sidebar form, `#confirm-dialog` assertions replacing the
  native-dialog listener; new specs cover the mobile drawer, user menu +
  dark toggle, and Escape-cancel. (Note: `resources/views/orphaned/` is
  an intentional retention folder asserted by `OrphanedViewsRetentionTest`
  — kept.)

Verified: full suite 2750 green locally, GitNexus `detect-changes` risk
low (2 JS symbols), Pint clean, production build clean, CI run
42878634951 green on all four jobs (security, lint, tests, E2E).

---

## [2026-11-11] - Concurrency sweep round 3: residual idiom audit (9 fixes)

Fresh whole-tree sweep for race idioms beyond the previous index. Nine
findings fixed; ~15 sites verified already-safe (see below).

### Fixed
- `FiscalYearService::createFiscalYear` — non-transactional 3-phase write
  (year create + unlinked-period attach `UPDATE` + `firstOrCreate` loop on
  `period_code` unique). Concurrent overlapping creates could steal
  attached periods (last-writer-wins UPDATE) and lose mid-loop on the
  unique index, leaving a partial year. Wrapped in `DB::transaction` —
  the loser now rolls back atomically.
- `PermissionService::updateRolePermissions` — per-row `updateOrCreate`
  loop on `unique_role_permission` with no transaction: a concurrent
  matrix submit losing mid-loop left a **partially applied privilege
  matrix** (earlier rows committed). Replaced with a single atomic
  `RolePermission::upsert` (BudgetService precedent).
- `PermissionService::updatePermission` — same `updateOrCreate` hole on
  the single-toggle path → single-row `upsert`.
- `AccountMappingService::update` — `updateOrCreate` on
  `account_mappings_key_unique` inside its transaction 500'd the loser;
  added the codebase's catch + `lockForUpdate` re-read idiom (adopt the
  winner's row, apply the update).
- `RiskScoringEngine` event-debounce — `Cache::has` + `Cache::put`
  check-then-act let every concurrent trigger pass; now atomic
  `Cache::add` (SET-NX-with-TTL).
- `BankReconciliationService::importStatement` — `exists()`→`create`
  dedup loop inside a transaction but with no mutex: two concurrent
  uploads of the same statement duplicate reconciliation rows. Added
  `Cache::lock("bank_recon_import:{account}")` (mirrors
  `withImportLock`/monitor idiom).
- `BranchPoolController::store`, `SanctionSourceController::store`,
  `CurrencyController::store` — `exists()` pre-check → `create()` on
  unique-constrained data produced a raw duplicate-key 500 for the race
  loser instead of the friendly error the pre-check intended. Each now
  catches `UniqueConstraintViolationException` → same friendly outcome.

### Verified safe (no change)
- `MfaService::rememberDevice` (`updateOrCreate` keyed on
  `sha256(random_token)` — collision impossible), `RateApiService`
  failure marker (idempotent put), `TransactionValidationService`
  sanctions memo (idempotent), `SetupService` (marker inside setup tx +
  unique backstops), `PepApprovalService` (customer-row mutex),
  `StrReportService` (case-row lock), `BranchClosingService`
  (generated-column unique index), all 43 scheduled commands
  (`withoutOverlapping` + `onOneServer`), all `increment()`/`decrement()`
  (atomic SQL), `seedDefaults` (seeder context).
- Verified: PHPStan clean · Pint clean · 2750 tests pass.

---

## [2026-11-11] - Coverage tail: remaining API v1 / jobs / web / ops surfaces + slow-group repair

### Coverage Added (41 tests across 4 files)
- `tests/Feature/Api/V1/SanctionsScreeningCountersApiTest.php` — 13 tests:
  sanctions search + envelope, lists/entries/import-logs (admin vs teller
  403), screening batch + status/history, branch counters/users own-branch
  scoping, allocation request/my-active/pending/active, customer search,
  KYC document upload.
- `tests/Feature/Jobs/ReconcileAndImportJobsTest.php` — 7 tests:
  `ReconcileDeferredAccountingJob` quiet path + Enhanced-only targeting
  (constructor-injected service spy), `ImportSanctionsJob` slug/fallback/
  no-list resolution, `TransactionCancelledListener` audit write,
  `ReportGeneratedListener` log.
- `tests/Feature/WebTailEndpointsTest.php` — 11 tests: transaction print
  receipt, findings show/dismiss (reason required), web screening trigger,
  expense create form + admin fund, STR export behind `password.confirm`,
  audit-log detail, branch edit, branch-pool fund (balance mutation).
- `tests/Feature/Commands/OpsCommandsTest.php` — 10 tests:
  `compliance:rescreen` stamp vs `--dry-run`, `accounting:month-end`
  preflight-failure/force/attribution paths, `sanctions:update` source
  validation + job dispatch, `sanctions:import` list resolution,
  display/housekeeping commands exit codes, `monitor:status` health-code
  contract.

### Slow-group repair (17 failures on main, pre-existing drift)
- **Real bug found**: `TransactionCreationService::prepareAndCreate()`
  captured `$ipAddress` but invoked `buildCreationContext($user, $data)`
  without it → `validateIpAddress` received `null` → IP validation was
  silently bypassed on this creation path. Now passes `ipAddress:`.
- `tests/Feature/TransactionCancellationFlowTest.php` — fixture used
  `Completed` status, but `canCancel()` now subsumes the window check for
  Completed txns (returns generic "cannot be cancelled"). Status changed
  to `Approved` so the explicit `isWithinCancellationWindow` gate fires
  the expected "outside the cancellation window" message.
- `tests/Feature/RouteVerificationTest.php` — deleted: it tested
  `scripts/check-routes.php`, which was removed in `31da2b93`. A test for
  a deleted script can only ever fail.

### Verified
- The 3 standard suite skips are intentional environment guards
  (production-only DebugMode, mysql-only credentials, Redis-store rate
  limit), not masked failures.
- Slow group returned to green: 17 failed → 0 failed (126 tests).

---

## [2026-11-10] - CI honesty: pipefail masking + encryption-salt determinism + MSB2 unit fix

### Root Cause
CI's PHPUnit steps ran `php artisan test | tee output.txt` — without
`pipefail` the step exits with `tee`'s status (always 0), so the "green"
PHPUnit job was masking **347 real test failures** (Unit: 185, Feature: 162)
on run `42838657891`.

The mass failures traced to one env gap: CI copies `.env.example`, whose
`APP_ENCRYPTION_SALT` is empty, while local `.env` has a real salt.
`EncryptionService` fail-closes on a missing salt → every decrypted-model
read threw `EncryptionConfigurationException` → 500s/redirects everywhere.

### Files Changed
- `.github/workflows/ci.yml` - `defaults.run.shell: bash -eo pipefail {0}`:
  every `run` step now propagates pipeline exit codes. **Expect CI to go
  red if any real failure survives — that is the point.**
- `phpunit.xml` - deterministic `APP_ENCRYPTION_SALT` (64-hex test value,
  `force="true"`, same pattern as the committed test `APP_KEY`) — test env
  no longer inherits salt from the developer's `.env`.
- `app/Services/Reporting/Generators/Msb2ReportGenerator.php` - **real
  bug the masked failure was reporting**: `computeData()` derived
  `Opening_Position` as `closing(units) - net_flow(MYR)` — dimensional
  mix of currency units and MYR amounts. Now requests `quantity` as the
  amount column and derives `netFlow` from `buy_amount`/`sell_amount`
  (units), while `*_Volume_MYR` outputs keep MYR sums. Fixes
  `ReportingServiceTest` (4850 vs 4500).
- `tests/Feature/PerformanceTrackingMiddlewareTest.php` - strict
  `shouldReceive('info')->once()` died on any unrelated log call in the
  request pipeline before the middleware's own `Log::info` fired (0
  invocations). Now `Log::spy()` + `shouldHaveReceived` assertions.
- `tests/Http/Simulation/WaveA/Steps/CounterLifecycleSteps.php` - A13
  travelled +31min, which near midnight rolls `session_date` into the next
  day → `whereDate` finds no session → 409. Now backdates `opened_at`
  deterministically instead of moving the clock.

### Notes
- `tests/Unit/CompiledAssetsTest` passes in CI (assets built in-job);
  locally it only passes after a fresh `npm run build`.

---

## [2026-11-10] - Coverage wave: reporting, API v1, jobs, web flows, ops commands

### Files Added
- `tests/Feature/Reporting/ReportGeneratorsTest.php` - golden-output tests
  for MSB2/LMCA/QLVR/Position/Trial-Balance generators: MYR aggregation,
  threshold filtering, monthly breakdowns, utilization/exposure math, CSV
  headers, and the license-number guard. Numeric assertions use float
  comparison where `SUM(DECIMAL)` scale differs between sqlite and MySQL.
- `tests/Feature/Reporting/ReportCommandsTest.php` - `report:msb2|lmca|qlvr|
  position-limit|trial-balance|eod` smoke tests: exit code, output, CSV/JSON
  artifact, `reports_generated` row. `seedSystemUser()` points
  `cems.system_user_id` at a real user for the `generated_by` FK.
- `tests/Feature/Api/V1/RatesRiskComplianceApiTest.php` - 17 tests across
  `/api/v1/rates` (index, summary, dates, show, history, check, validate +
  teller 403), `/api/v1/risk` (history, recalculate, unlock + teller 403),
  `/api/v1/compliance` (alert show, dashboard, EDD show/reject, reason
  validation).
- `tests/Feature/Jobs/ComplianceJobsTest.php` - `LowStockAlertJob` alert/no-
  alert paths, `ComputeBehavioralBaselineJob` baseline math + version-bump
  upsert, `RunComplianceMonitorJob` engine delegation.
- `tests/Feature/Customers/CustomerFreezeAndKycTest.php` - customer
  freeze/unfreeze (incl. reason validation + teller 403), KYC document
  verify/reject incl. the 409 already-dispositioned path.
- `tests/Feature/Commands/AuditAndRevaluationCommandsTest.php` -
  `audit:verify` empty/valid/broken chain + `--limit` forwarding;
  `revaluation:run` month-end gate, `--force` happy path, failure exit 1.

### Notes
- Report command tests call `Artisan::call` + `Artisan::output()` instead of
  `PendingCommand` - the mocked OutputStyle path silently skipped
  `createReportRecord`'s row write under test (exit 0, no row); direct calls
  are also type-clean for phpstan's `PendingCommand|int` union.
- Command tests mock `RevaluationService`/`ExportService`/
  `RevaluationNotificationService` - the contract under test is the
  command's gating/exit-code/reporting behavior, not the engine.

---

## [2026-11-10] - Concurrency sweep part 2: unique-race catch-and-re-read + import status claim

### Files Changed
- `app/Services/Branch/BranchPoolService.php` - `getOrCreateForBranch()`
  wraps `firstOrCreate` in `UniqueConstraintViolationException` catch +
  `lockForUpdate` re-read (CurrencyPositionLockService idiom).
- `app/Services/Accounting/CurrencyAccountProvisioner.php` - both
  `firstOrCreate` sites (`AccountMapping` by key, `ChartOfAccount` by
  account_code) catch the lost unique race and re-read under lock; the
  existing enum-usability check then adopts or retries the next code.
- `app/Services/Transaction/TransactionImportService.php` - `process()`
  now claims the import via conditional `whereIn(status, [pending,
  processing])` update; terminal-status calls are a no-op.
- `tests/Feature/Audit/TransactionImportProcessTest.php` -
  `test_process_skips_import_already_in_terminal_status`.

### Vulnerabilities Fixed
- `BranchPoolService::getOrCreateForBranch` (Low) - `firstOrCreate`'s
  select-then-insert loses the `(branch_id, currency_code)` unique race:
  two concurrent opening-request allocations on a new currency → loser
  500s on duplicate key. Now re-reads the winner's committed row.
- `CurrencyAccountProvisioner::provisionLeg` / `resolveAccountCode` (Low) -
  same hole on `account_mappings.key` / `chart_of_accounts.account_code`:
  concurrent currency provisioning → duplicate-key exception escaping the
  allocation retry loop. Caught + re-read; existing adoption logic decides.
- `TransactionImportService::process` (Low) - unconditional
  `Processing` transition allowed a direct call/stale instance to re-run a
  Completed/Failed file (dedupe saves rows but corrupts counters). The
  conditional claim mirrors `ProcessTransactionImportJob`'s status gate.

### Verification
- `TransactionImportProcessTest` (3), `ConcurrencyFixesTest` pool tests,
  `CurrencyManagementTest` (23): green. PHPStan clean.

---

## [2026-11-10] - Concurrency sweep: MFA counter TTL race + screening-job dedup serialization

### Files Changed
- `app/Services/System/MfaService.php` - `recordFailedAttempt()` replaces
  `has()` + `put()` with atomic `add()` seeding plus the Redis TTL-repair
  guard (same pattern `RateLimitService::recordFailedAttempt` already uses).
- `app/Jobs/ComplianceScreeningJob.php` - the blocked-screening enforcement
  block (holds + alert + flag + audit) now runs inside a `DB::transaction`
  anchored by `Customer::lockForUpdate()`, serializing concurrent jobs for
  the same customer.
- `tests/Feature/Audit/ConcurrencyFixesTest.php` - two new tests:
  `test_mfa_failed_attempt_counter_accumulates_and_keeps_expiry`,
  `test_repeated_compliance_screening_runs_do_not_duplicate_enforcement_rows`.

### Vulnerabilities Fixed
- `MfaService::recordFailedAttempt` (Medium) - `has()`/`put()`/`increment()`
  TOCTOU: if the counter key expired between the check and `increment()`, a
  bare Redis `INCR` recreated it **without TTL**, leaving a permanent
  failed-attempt counter feeding the verification lockout. `add()` seeds
  atomically (SET-NX-with-TTL); the TTL-repair block re-applies the window
  whenever a live key has none.
- `ComplianceScreeningJob::screenAndEnforce` (Medium) - the job is
  intentionally not `ShouldBeUnique`; parallel workers for the same customer
  both passed the open-alert/open-flag `first()` checks and inserted
  duplicate CRITICAL alerts + flags. The customer-row lock serializes the
  check-then-create block so the loser refreshes instead of duplicating.

### Scan Result (already hardened — no changes needed)
- Approval (status-scoped lock + optimistic version), till/counter sessions,
  stock transfers, branch-pool allocations, remittances, EDD transitions,
  sanctions import (`withImportLock`), audit-chain sealing (ordered
  predecessor locks + `GAP:` quarantine), journal reject/reverse, last-admin
  delete, transaction imports (`uniqueId` + status guard + per-row
  idempotency), setup marker (`updateOrInsert` in-tx).

### Verification
- `ConcurrencyFixesTest` (13 tests) + `ComplianceScreeningJobTest` +
  `ComplianceScreeningJobPerformanceTest` + `MfaControllerTest` +
  `MfaApiTest`: 29 tests green. PHPStan clean on both changed files.

---

## [2026-11-10] - CI E2E environment brought fully green (114/114 across four jobs)

### Files Changed
- `.github/workflows/ci.yml` - `CACHE_DRIVER=redis` (tag support),
  `CACHE_LIMITER_STORE=array` (limiter isolation), `QUEUE_CONNECTION=redis`
  plus a `queue:work audit,compliance,notifications,default` process, and
  `e2e-worker.log` + `laravel.log` added to the e2e artifact upload.
- `config/cache.php` - new `limiter` key (`CACHE_LIMITER_STORE`); null
  falls back to the default store, preserving production behavior.
- `config/database.php` - sqlite connection gains `busy_timeout=5000`
  (`DB_BUSY_TIMEOUT`) and `journal_mode=wal` (`DB_JOURNAL_MODE`).
- `tests/e2e/journey-str.spec.ts` - case-store POST reads the raw 302 via
  `maxRedirects:0` with JSON error-bag + alert-column diagnostics;
  `/str?status=draft` uses the exact stored enum value.

### Root Causes Fixed (CI environment only — all green locally on MySQL)
- `CACHE_DRIVER=file` 500'd accounting/report pages: `Cache::tags()` is
  unsupported by the file store. Redis (predis, no extension) supports tags.
- `CACHE_DRIVER=redis` silently re-armed every `throttle:*` route — Laravel's
  `RateLimiter` uses `cache.default`. Login/TOTP endpoints (5/min) and the
  API bucket began 429ing mid-suite. `cache.limiter=array` restores the
  per-request inert behavior in CI only.
- `QUEUE_CONNECTION=sync` executed `SealAuditHashJob` inline inside the
  request transaction's `afterCommit` and rethrew its *retryable*
  `AuditIntegrityException` as a web 500 on `POST /compliance/cases`.
  Production absorbs this via worker backoff; a real CI worker now does too.
- `QUEUE_CONNECTION=database` on sqlite then produced `SQLITE_BUSY` 500s —
  the worker's `jobs`-table polling collided with web writes. Redis queue
  removes queue traffic from sqlite entirely; `busy_timeout`+`WAL` cover the
  residual job-execution overlap.
- `/str?status=Draft` returned zero rows on sqlite (case-sensitive `=`)
  where local MySQL's `_ci` collation matched — a spec bug masked locally.
- `page.evaluate(fetch)` for the case-store POST died with `Failed to
  fetch` despite reaching PHP; `ctx.request` + `maxRedirects:0` reads the
  redirect deterministically.

### Verification
- GitHub Actions run `42830141948` on `f61c924a`: **success** — security
  audit, lint/static, PHPUnit (unit+feature+simulation), Browser E2E all green.

---

## [2026-11-10] - Deep E2E coverage: wizard completion, STR, onboarding, compliance + admin details

### Files Changed
- `tests/e2e/wizard-step3.spec.ts` (new) - full wizard journey: step 1
  eligibility -> step 2 customer details -> review -> confirm -> books a real
  transaction and asserts the `Transaction Created` result.
- `tests/e2e/journey-str.spec.ts` (new) - high-value booking trips velocity ->
  alert -> case -> dismiss linked alerts -> close -> STR auto-draft -> submit
  -> acknowledge.
- `tests/e2e/journey-onboarding.spec.ts` (new) - walk-in registration via the
  form -> profile -> first transaction.
- `tests/e2e/compliance-details.spec.ts` (new) - compliance surface sweep plus
  sanctions entry create -> search -> show -> edit and screening match ->
  detail -> customer history/status.
- `tests/e2e/admin-internals.spec.ts` (new) - admin surface sweep plus
  audit-log detail, user provisioning and branch edit.
- `tests/e2e/helpers/auth.ts` - `resolveMfa` re-authenticates through the
  login form when a stored session died server-side, instead of silently
  returning on `/login`.
- `tests/e2e/helpers/seat.ts` - `createCustomer` accepts an optional `E2eUser`
  for pre-post session healing; `tillState` probes via API request instead of
  page navigation.
- `tests/e2e/reports-views.spec.ts` - `/reports/monthly-trends` and
  `/reports/profitability` added to the render sweep.
- Call sites in `admin-surfaces`, `journey-refund`, `journey-transaction` and
  `wizard-step2` updated for the new `createCustomer` signature.

### Root Causes Fixed
- `tillState` navigated to `till-report`, whose empty-result `back()` targets
  the session `_previous.url` — consecutive empty probes ping-ponged between
  till-report URLs (`ERR_TOO_MANY_REDIRECTS`). The probe now uses
  `ctx.request.get` with `maxRedirects: 0` (302 = no rows) and sends
  `x-requested-with` so it never writes `_previous.url` either.
- `createCustomer`'s generated phone mapped every non-digit suffix char to
  `'2'` — two suffixes sharing digit positions produced identical phones and
  tripped the `phone_hash` unique blind index (`ValidationException` ->
  `back()` -> referer `/` -> misleading `/dashboard` landing). Digits now
  derive from charcodes, and the referer points at the real form.
- Stored sessions die between specs (idle timeout); a remember-recaller then
  masked the guest bounce as a `/dashboard` landing. `resolveMfa` heals
  `/login` by resubmitting credentials; `createCustomer` warms via
  `gotoVerified` before posting.
- STR browser path uses `dismiss`, not `resolve`: `resolveAlert` clears
  `case_id`, which would orphan the case-alert linkage the auto-draft
  requires.

### Design Notes
- Counter-handover browser coverage intentionally skipped: `initiateHandover`
  is service-only with zero HTTP callers; `CounterHandoverServiceTest` and the
  concurrency suites remain authoritative.
- `resolveAlert` clearing `case_id` is a deliberate domain semantic — the
  intended case-linked terminal path is dismissal. Worth reviewing whether
  resolve should keep the link when a case is open; flagged for a separate
  decision, not changed here.

### Testing
- `npx playwright test` locally — **114 green** (112 in the full pass; the 2
  phone-collision failures re-verified green after the generator fix).

---

## [2026-11-10] - CI Browser E2E job brought green; wizard form dead-submit bug fixed

### Files Changed
- `resources/views/transaction-wizard/index.blade.php` - added `novalidate` to the
  multi-step form.
- `.github/workflows/ci.yml` - E2E env prep generates `APP_ENCRYPTION_SALT`,
  creates `storage/framework/{sessions,cache}` + `storage/logs`, probes `/up`
  for readiness, and runs the same `Start Redis` step the PHPUnit job uses.
- `tests/e2e/helpers/seat.ts` - `api()` reads `XSRF-TOKEN` from context cookies
  (page-state independent); shared `csrf()` falls back to `/dashboard` on
  meta-less pages; `createCustomer()` posts the full CDD store form; `FLOATS.MYR`
  raised to 30000 with the till-drawer semantics documented.
- `tests/e2e/helpers/auth.ts` - MFA self-enrollment + next-window TOTP retry.
- `tests/e2e/journey-transaction.spec.ts`, `journey-refund.spec.ts`,
  `admin-surfaces.spec.ts`, `wizard-step2.spec.ts`, `journey-eod.spec.ts`,
  `transaction-wizard.spec.ts`, `mfa-verify.spec.ts` - dynamic branch/customer
  resolution, full-CDD customers, corrected assertions.

### Root Causes Fixed
- `APP_ENCRYPTION_SALT` was empty in `.env.example` — any page hydrating a
  customer (PII decryption via `EncryptionService`) threw a `DomainException`
  rendered as `back()` → `/` → `/dashboard`, so pages appeared to bounce.
- The wizard's `<form>` wrapped all three steps; hidden `required` controls in
  `x-show` step 2/3 sections made native constraint validation cancel every
  submit ("invalid form control is not focusable"), so `submitStep()` never ran
  and Continue was dead for real users too.
- Counter opening-request floats become the day's `TillBalance` rows —
  `stock-cash/open` then 422s as a duplicate. A ~RM13k Buy exceeded the 5000
  MYR drawer at approve time on a fresh seed ("Insufficient stock for MYR");
  bookings now sit just over the RM10k approval threshold with the float
  raised to 30k.
- Fresh CI seeds ship zero customers: hardcoded staging names timed out, so
  journeys register their own via the full CDD form (a bare quick-create 302s
  Standard-tier bookings back — `assertCustomerCddRequirements` reads the
  persisted record).
- `meta[name="csrf-token"]` reads hung on meta-less pages (`back()` landing on
  a recorded `/api/v1` URL); Sanctum stateful calls need matching
  Referer/Origin plus the `XSRF-TOKEN` cookie — never the page meta.

### Testing
- `npx playwright test` locally — **84/84 passed**.
- GitHub Actions run 42802347388 — **all jobs green**: lint 1m57s, security 18s,
  PHPUnit 3m18s, Browser E2E 4m15s.

---

## [2026-10-04] - Playwright suites: kill the duplicate-ID collision and the timezone-blind counter scan

### Files Changed
- `tests/200-transaction-lifecycle.spec.ts` - customer identity is derived from a
  namespaced index so this file can no longer collide with
  `100-transactions.spec.ts` on the unique id_number hash.
- `tests/support/helpers.ts` - `createCustomer` reports the real rejection reason
  instead of an empty string; `closeOpenCounterSessions` scans a timezone-padded day
  window instead of UTC calendar days.

### Purpose
Both files book customers and transactions against a live branch, and two latent
faults made a run's outcome depend on luck rather than on the code under test.

Customer identity is a pure function of (nationality, index, shared RUN_SEED). The
booking file consumes indices 1..500; the lifecycle file used its own bare counter
1..N. Because RUN_SEED is shared, the two files minted the same id numbers, and the
moment an index overlapped the lifecycle file's `createCustomer` hit the unique
id_number hash and the whole run failed. The failure was invisible to the helper: it
read `p.text-danger`, but the form renders `text-danger-text`, so every rejection
logged an empty reason and the collision cost an hour to trace.

`closeOpenCounterSessions` walked back from today with `toISOString()`, which yields
UTC calendar days, while `session_date` is stored in the application timezone. Between
local 00:00 and the UTC offset the two disagree by a day, so the scan never saw the
current day's open session and settlement stayed blocked. That in turn froze the
branch's business date at initiation, which blocked every later posting on the same
day - one failure silently becoming two.

### Changes Made
- Added `INDEX_NAMESPACE = 1000` to the lifecycle file; every derived identity field
  (name, id type/number, address, DOB, phone) now reads the offset index while the
  email keeps the file's own counter, so its customers sit outside the booking file's
  1..500 range regardless of seed.
- `createCustomer` now reads the first `[role="alert"]` or `.text-danger-text` element,
  trims it, and falls back to a URL-bearing message when nothing renders, so a rejection
  is never logged empty again.
- `closeOpenCounterSessions` builds the scan window once, padding each UTC day by one
  day either side (deduped, newest first) so the application's local today is always
  covered no matter what the timezone offset is.

### Testing
- `npx playwright test` (full suite, 16 tests) - **16 passed, 0 failed (29.2m)**.
  The two tests that failed in the prior run (300 day-close and 400 pool
  remittance) both pass, confirming the date-scan fix closed the settlement
  path and the branch's business date was not left frozen.
- `npx playwright test tests/200-transaction-lifecycle.spec.ts` - 4 passed (37.0s),
  with no `⚠ Customer … rejected:` line anywhere in the log.
- Node probe of the padded day window - the first entry is the application's
  local today, which the previous UTC scan began one day earlier and omitted.

### Impact Analysis
- `gitnexus detect_changes({scope: "all"})` - risk LOW, affected_count 0,
  affected_processes empty. All three files are local-only Playwright probes,
  not CI-gated, and no application code was touched.

---

## [2026-10-03] - Transaction wizard: harden against server errors, bad uploads and number overflow

### Files Changed
- `resources/js/components.js` - 422 errors are kept per field instead of being
  collapsed to the first message; uploads are validated against the server's own
  shape and size before transfer; the MYR figure guards against number overflow;
  every successful response body is checked for presence.
- `resources/views/transaction-wizard/index.blade.php` - each field renders its
  own error line; picked documents are previewed and re-validated on change; a
  retryable failure offers "Try again"; review rows and input ceilings no longer
  overflow.

### Purpose
The server speaks the form's language in three ways the client did not listen
to. A 422 can carry up to ten field errors at once and only the first was shown,
so fixing one mistake still sent the teller back through the step to meet the
next. A document upload could be a 100 MB .exe and would only be rejected after
the whole transfer. A quantity of 1e308 produced an `Infinity` in the Local
Amount box. Each produced a misleading message or a silently wrong figure rather
than a fixable problem.

### Changes Made
- `applyErrors(data, status)` splits a 422 by leaf key name: keys the teller can
  edit land in `fieldErrors` and render under their own field, while state the
  form cannot fix - no open counter session, an expired wizard session, an
  unknown CDD level - goes to the banner. Validation and state errors can arrive
  together and both now survive.
- `fieldError(name)` resolves the flat, `customer.`-prefixed and
  `transaction.`-prefixed spellings, because step 1 submits flat keys while
  step 2 submits `customer[...]` and `transaction[...]`.
- Editing a field clears only that field's error, so fixing one of six mistakes
  does not erase the other five.
- A `networkError` flag distinguishes a retryable failure (5xx or a thrown
  request) from a 4xx the form cannot fix. Only the former shows "Try again".
- `pickFile()` checks the file against the server's own rules - PDF, JPEG or PNG
  under 10 MB, matching `mimes:pdf,jpg,jpeg,png` and `max:10240` - and rejects it
  at the picker instead of after a full upload. The chosen name is shown under
  the field so a teller can confirm which file is going out.
- Text and number fields carry the server's own ceilings (`maxlength` 255 / 500 /
  1000, `max` 9999999999.9999 on quantity, `max` 999999 on rate).
- `amountMyr` returns `—` instead of `Infinity` when the arithmetic leaves
  Number's range.
- Every successful response body is read through `?? {}` and each field is
  defaulted, so a 200 with a missing or partial body cannot throw inside the
  success path and be reported as a network error.
- Review rows use `shrink-0` labels and `min-w-0 break-words text-right` values,
  so a long source of funds wraps inside the column instead of pushing the row
  out of the card.
- A 200 with no status says the transaction was recorded rather than rendering an
  empty success screen, and "View Transaction" is hidden without an id.

### Testing
- `node /tmp/verify-wizard-harden.mjs` - 60 passed, 0 failed. Covers error
  splitting including prefixed keys, retry classification across 5xx and 4xx,
  file shape and the 10 MB boundary, overflow, and a 2400-random-state agreement
  check between the validators and the "still needed" hint.
- `php artisan test --compact tests/Feature/TransactionWizardTest.php` -
  14 passed (54 assertions)
- `npm run build` - clean; every new class confirmed present in the built CSS
- `vendor/bin/pint --dirty --format agent` - passed

### Impact Analysis
- `node .gitnexus/run.cjs detect-changes --scope all --repo .` - 0 affected
  processes, risk low
- `firstError()` was replaced by `applyErrors()` with no remaining call sites; no
  other existing Alpine public member was renamed or removed.
- The server-facing contract is unchanged: no property name, payload key or
  endpoint shape was altered.

---

## [2026-10-03] - Transaction wizard: clarify step intent and name the next actor

### Files Changed
- `resources/views/transaction-wizard/index.blade.php` - step 1 gained a tier
  explanation and a `customer_id` disambiguation; the advance button now names
  the fields still missing; the success screen states what happens next and who
  acts on it.
- `resources/js/components.js` - `missingStep1()` / `missingStep2()` added to
  the `transactionWizard` Alpine data; `validStep1()` now returns a strict
  boolean.

### Purpose
The critique scored Recognition Rather Than Recall 1/5: `*` marked 3 of ~10
required fields, validation was JavaScript-only so a disabled submit button was
the only signal that anything was wrong, and a compliance-hold booking landed on
the same success screen as a completed one with nothing to say about what comes
next.

### Changes Made
- The step 1 heading gained one line explaining that the local amount sets the
  customer's due-diligence tier - the reason the next step may ask for
  documents.
- The Customer ID field gained helper text: the customer record ID, not the
  national ID number.
- Whenever the advance button is disabled, a right-aligned line beside it names
  the fields still needed ("To continue: Customer ID, Currency, Exchange Rate").
  It disappears the moment the step is valid, so a disabled button is no longer
  the only signal.
- `missingStep1()` / `missingStep2()` compute that list from the same CDD-tier
  gates as `validStep1()` / `validStep2()`, and their labels mirror the visible
  field labels.
- The success screen now states the next consequence: a pending booking says a
  compliance officer must review it before it can complete; a completed one says
  the transaction is complete.
- `validStep1()` returned the last operand of its `&&` chain (`null`, or a
  string) rather than a boolean, unlike `validStep2()` / `validStep3()`. A
  truthiness-preserving `!!` was added so the hint and the button cannot
  disagree.

### Testing
- Node check of the `transactionWizard` Alpine data - 19 assertions passed,
  including an invariant over 2400 random states x 4 CDD tiers that
  `missingStepN()` is empty if and only if `validStepN()` is true
- `php artisan test --compact tests/Feature/TransactionWizardTest.php` -
  14 passed (54 assertions)
- `npm run build` - clean; every new utility confirmed present in the built CSS
- `vendor/bin/pint --dirty --format agent` - passed

### Impact Analysis
View + Alpine-data change only; no controller, service, model or route symbol
touched. `validStep1()` truthiness is unchanged at every existing call site
(`:disabled`, `x-show`), so the boolean fix is behaviour-preserving.

---

## [2026-10-03] - Transaction wizard rebuilt on the shared component system

### Files Changed
- `resources/views/transaction-wizard/index.blade.php` - every form field and
  alert now renders through the shared `x-input` / `x-select` / `x-textarea` /
  `x-alert` components on the `app.css` `@theme` token palette; the hand-rolled
  markup and hardcoded hex colours are gone.

### Purpose
A critique of the teller's highest-volume surface found the wizard was the one
view in the app that ignored the app's own component library: 0 shared form
components against 16 in the sibling `transactions/create.blade.php`,
hardcoded `bg-[#0a0a0a]` literals, and default Tailwind grays mixed with the
project's `text-ink-muted` / `border-border` tokens in the same file. The
result was a clean but category-interchangeable page with a single visual
emphasis across 245 lines, and not one `for`/`id` association to tell a screen
reader a field apart from any other.

### Changes Made
- Step 1-2 inputs, selects and the source-of-wealth textarea now render
  through `x-input` / `x-select` / `x-textarea` with `name` + `label`, which
  gives every control a real `id`, a `for`-associated `<label>`, and correct
  `required` / `aria-required` semantics (previously zero pairs).
- Number fields gained `min` guards: `customer_id` min 1, `quantity` min 0.01,
  `rate` min 0.0001, `annual_volume_myr` min 0.
- The request error, the blocked-transaction banner and the risk-flags block
  now use `<x-alert>` on the project's `danger-*` / `warning-*` tokens with
  `role="alert"`, replacing hand-rolled `bg-red-50 border-red-200 text-red-700`
  and `bg-amber-100`.
- Banners switched from `x-show` to `x-if` so they enter the accessibility
  tree when they appear; `x-cloak` now covers all 22 `x-show` elements
  (previously 2), removing the pre-hydration flash of unstyled content.
- The step indicator gained step names plus `aria-current="step"`, and the
  redundant "Step N:" prefix dropped from the headings.
- Navigation buttons rebuilt on the `bg-primary` / `text-on-primary` token
  classes with `disabled:cursor-not-allowed`, and the placeholder spacer
  `<div>` replaced with a conditional `justify-end`.
- The PendingApproval status and the Local Amount row (the figure that sets
  the CDD tier) now carry visual weight.

### Testing
- `php artisan test --compact tests/Feature/TransactionWizardTest.php` -
  14 passed (54 assertions)
- `npm run build` - vite build clean
- `vendor/bin/pint --dirty --format agent` - passed
- Source sweep: `bg-[#0a0a0a]`, `hover:bg-[#262626]`, `text-gray-500`,
  `bg-red-50`, `border-gray-300`, `bg-gray-100`, `text-red-600`, `bg-amber-100`
  all confirmed absent
- `node .gitnexus/run.cjs detect-changes --scope all` - 0 affected processes,
  risk low, no changed symbols attributed to this view

### Impact Analysis
View-only change: no controller, service, model or route symbol touched.
The `transactionWizard` Alpine contract is preserved exactly (`x-data`, all
`data-*` attributes, every `formData` key, `validStep1`/`validStep2`,
`submitStep`, `reset`), so no JavaScript change was required.

---

## [2026-09-24] - Architecture review remediation: cross-cluster fixes + regression suite

### Files Changed
- `app/Services/System/SystemHealthService.php` - `getStatusSummary()` uses
  `$check->status->value` — a backed enum cannot be an array offset on PHP 8.3
  (TypeError crashed `monitor:status` and `alert:daily-summary`).
- `app/Http/Controllers/ReportScheduleController.php` - every action now
  enforces `requirePermission(Permission::ManageReportSchedules)` (was zero
  controller-level authorization).
- `app/Http/Middleware/QueryLogging.php` - the error path rethrows instead of
  re-dispatching `$next($request)`, re-running the pipeline's side effects.
- `app/Http/Middleware/ThrottleRequests.php`,
  `app/Services/System/RateLimitService.php` - the `SECURITY_*_ENABLED`
  kill-switches are honoured only in local; otherwise ignored with a
  `Log::alert` (fail closed — a stale env value can no longer silently
  disable login rate limiting or IP blocking).
- `app/Http/Middleware/EnsureBranchScope.php` - dropped the client-supplied
  `branch_id` input fallback (trust-by-comparison only).
- `app/Models/User.php` - deleted the dead, semantically wrong `isMfaVerified()`
  (enrollment timestamp is not session verification).
- `app/Http/Resources/Api/V1/UserResource.php` - dropped `mfa_verified_at` (it
  leaks the enrollment timestamp, not proof of verification).
- `app/Http/Requests/LmcaReportRequest.php`,
  `app/Http/Requests/StoreMsb2ReportRequest.php` - `authorize()` now checks the
  `ViewReports` matrix permission instead of `return true`.
- `config/security.php` - added the missing `session.mfa_session_max_age` key
  (EnsureMfaVerified referenced it; env-driven timeouts silently defaulted).
- `app/Http/Controllers/Customer/CustomerSearchController.php`,
  `app/Services/Customer/CustomerService.php` - the duplicate-identity race
  (unique blind-index violation on concurrent quick-create) is caught as
  `QueryException` too and resolves to the returning customer instead of a 500.
- `app/Repositories/CustomerRepository.php` - `searchActive()` ID branch now
  matches the HMAC blind index exactly; the plaintext `LIKE` against
  `id_number_hash` could never hit.

- `app/Services/Compliance/EddService.php` - `createEddRecord()` retries with
  a regenerated reference on an `edd_reference` unique violation (bounded, 3
  attempts) instead of surfacing a 500.
- `app/Services/Compliance/AlertTriageService.php` - `calculateRiskScore()`
  is anchored on the canonical `RiskScoringEngine` customer score with
  amount/attribute/flag/repeat deltas (previously two unrelated scoring
  implementations); `bulkAssign()` re-verifies the assignee holds
  `access_compliance` (a demoted user could still be assigned cases).
- `app/Enums/AlertPriority.php`,
  `app/Models/Compliance/ComplianceCase.php` - added
  `AlertPriority::toCasePriority()` and simplified `derivePriorityFromAlerts()`
  onto it (the positional lookup table silently mis-sorted if an enum value
  changed).
- `app/Models/Compliance/StrReport.php` - deleted the stale second
  `@property` block describing a pre-refactor schema.
- `app/Services/Compliance/Parsing/OpenSanctionsJsonParser.php` - a malformed
  JSONL line is skipped with a `malformedLines` counter instead of aborting
  the whole feed parse.
- `app/Services/Risk/PatternRiskService.php` - reversal thresholds extracted
  to named `REVERSAL_MIN_BUYS`/`REVERSAL_MIN_SELLS` constants.
- `app/Services/Compliance/KycDocumentExpiryService.php` - documented the
  intentional divergence between the booking gate
  (`hasAllIdentityDocumentsExpired`) and the stricter enforcement superset
  (`mustBlockDueToExpiredDocuments`).
- `app/Services/Compliance/Monitors/SanctionsRescreeningMonitor.php` -
  rescreen set is fetched with `lazyById(500)` instead of loading the whole
  active customer base into memory.
- `app/Http/Controllers/Compliance/PepApprovalController.php` - all actions
  enforce `requirePermission(Permission::AccessCompliance)`.
- `app/Services/Accounting/BudgetService.php` - `variance_pct` stays a
  decimal string end-to-end (no `(float)` cast on money).
- `app/Services/Traits/AccountingEntriesTrait.php` +
  `app/Services/Transaction/TransactionCreationService.php` +
  `app/Services/Transaction/TransactionApprovalService.php` +
  `tests/Unit/Services/Traits/AccountingEntriesTraitTest.php` - the trait's
  implicit property contract is now explicit abstract accessors
  (`auditTrailHelper()`, `transactionAccountingService()`); a consumer that
  forgets the wiring fails at class load instead of first use.
- `app/Http/Controllers/Report/AnalyticsController.php` - `monthlyTrends`,
  `profitability` and `complianceSummary` scope Transaction/Position/
  Flag queries to the viewer's branch via `viewerBranchId()`; date ranges are
  normalised through `resolveDateRange()` (reversed ranges are swapped, not
  silently empty).

- `app/Services/Transaction/TransactionConfirmationService.php` - rejecting a
  confirmation now releases the pending stock reservation (the compensating
  leg cancellation runs) and the audit action is correctly named
  `transaction_cancelled_via_confirmation`.
- `app/Services/Transaction/TransactionReversalService.php` - a reversal now
  writes a sealed `transaction_reversed` audit record and invalidates the
  dashboard/ledger/report caches after commit (it previously had none).
- `app/Services/Transaction/TransactionApprovalService.php` - generic
  catch-alls return operator-safe messages and log internals (raw exception
  messages no longer reach web/API consumers).
- `app/Actions/Transaction/RequestCancellationAction.php` - `DomainException`
  messages (SoD, cancellation window) are preserved instead of being
  flattened into a generic failure.
- `app/Http/Controllers/Transaction/TransactionApprovalController.php` - web
  `approve` enforces `requirePermission(Permission::ApproveTransactions)` in
  addition to route middleware and policy.
- `app/Http/Controllers/MyStockController.php` - all quantity/cash arithmetic
  routes through `MathService` (BCMath); float drift on the teller's position
  of record is gone.
- `app/Services/Branch/CounterService.php` - closing a counter session returns
  only that counter's allocations to the pool (scoped by `counter_id`);
  drawer-less custody is no longer destroyed by a counter close.
- `app/Services/Branch/PoolRemittanceService.php` - remittance-number retry
  exhaustion throws a clean `TransactionCreationException` instead of a raw
  `QueryException` (HTTP 500).
- `app/Services/Reporting/ReportSchedulingService.php` - `executeReport()`
  covers all eight `ReportType`s (the match previously threw `UnmatchError`
  on the four ledger-backed reports).
- `app/Services/System/LogRotationService.php` +
  `app/Services/Audit/AuditChainService.php` - rotation now reseals surviving
  boundary entries with the existing `GAP:<id>` marker
  (`resealWithGapBoundary()`), so `audit:verify` no longer reports a false
  permanent tamper failure after a backdated entry is archived.
- `app/Providers/QueryLogServiceProvider.php` - query summaries persist via
  `AuditService` into `new_values` (the non-existent `details` column silently
  dropped the payload and bypassed hash sealing).
- `app/Listeners/ComplianceEventListener.php` - null alert risk_score
  no longer propagates into the EDD template amount.
- `app/Services/System/SystemAlertService.php` - `sendEmail()` catches
  `\Throwable` (a `TypeError` while building the mail rolled back the alert
  row).
- `tests/Feature/ReviewRegressionTest.php` - NEW: 18 regression tests, one
  per reviewed defect.

### Purpose
Full remediation of the functional-cluster architecture review: cross-layer
contract fixes, boundary-leak closures (branch isolation, authorization),
SRP clarifications, and feature-specific vulnerability fixes (stock stranding
on confirmation-reject, reversal audit gap, float-on-money, chain-verify
break after rotation).

### Changes Made
- 40+ fixes across 8 functional clusters (see per-file notes above).
- PEP gate policy deliberately unchanged: the tested behaviour encodes that
  domestic low-risk PEPs are exempt from head-office approval
  (`PepApprovalServiceTest`, `web_store_creates_pep_transaction_with_source_of_wealth`).
- `forceStatus` kept — it is exercised by `TransactionStateMachineTest` and
  is a legitimate admin recovery tool with audit logging.

### Testing
- `tests/Feature/ReviewRegressionTest.php`: 18/18 passing.
- Targeted suites all green: Transaction (16), Compliance (124), Accounting
  (68), Reports (34), Audit (124), Branch/Counter/Budget/Monitors (56),
  Auth (1 pre-existing `SessionConfigTest` failure also present on the clean
  tree), MyStock/QueryLogging/RateLimit/SystemHealth (21).
- `vendor/bin/pint` applied to all changed files; `vendor/bin/phpstan` clean
  on every edited path.

### Impact Analysis
- No HIGH/CRITICAL GitNexus risk symbols were modified; changes are confined
  to the reviewed services/controllers plus the new test file. The
  `TransactionConfirmationService`/`TransactionReversalService` constructor
  changes are internally consistent (DI container resolves the new deps).

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

---

## [2026-10-03] - Continuous audit: 18-module deep-scan complete (1C/9H/17M/19L)

### Files Changed
- `COMPREHENSIVE-AUDIT.md` - created; 625-line audit document covering entire codebase
- `app/Services/Transaction/` — idempotency replay re-applies booking side effects (critical)
- `app/Models/Customer.php` — `id_number` re-derives 100k PBKDF2 per access, no singleton binding (high)
- `app/Services/Compliance/` — STR pipeline silent drops; `last_transaction_at` written backwards by queued listener (high/medium)
- `app/Http/Middleware/` — MFA trusted-device `expires_at=NULL` grants permanent trust, no test coverage (medium/low)
- `app/Console/Commands/` — 17 installer DDL commands mirror SchemaSeeder with zero test enforcement (high)
- `bootstrap/app.php` — `RuntimeException` → 409 leaks raw messages to API consumers (medium)
- `tests/Http/Simulation/` — idempotency test asserts only row count, not derived state; branch-closure freeze race untested (high/medium)

### Purpose
Complete the continuous audit loop mandated by AGENTS.md. Deep-scanned all 18 modules (app/, config/, routes/, bootstrap/, database/, resources/views/, tests/) against four axes: correctness/logic gaps, technical debt, architecture flaws, security. Each finding cites file:line evidence and is checkable by inspection.

### Changes Made
- M1 critical: Idempotency replay double-applies position, journal, allocation, till side effects on Completed transactions
- M18 high: Wave B step B5 asserts only `COUNT(*)` on transactions table; does not snapshot journal_entries, currency_positions, teller_allocations
- M10 high: 17 hand-written installer commands with no test verifying parity with SchemaSeeder; already caused production outages
- M11 medium: TransactionCreatedListener writes `last_transaction_at` without GREATEST() guard; queued out-of-order execution can regress timestamp
- M8 high: Customer::id_number decryptor runs 100k-iteration PBKDF2 on every access; EncryptionService not singleton-bound
- M7/M18: MFA trusted_device whereNull('expires_at') silently grants permanent bypass; no unit test covers expiry semantics
- M12 medium: catch-all renderer maps RuntimeException (incl. LogicException, InvalidArgumentException) → 409 with raw message

### Testing
- `vendor/bin/pint --format agent` — passes
- `php artisan test --compact --filter="Transaction"` — 471 tests, 1485 assertions pass
- CI run #37154538320: Lint (1m9s) ✅, Security (26s) ✅, Tests (3m30s) ✅

### Impact Analysis
- Blast radius: idempotency fix touches TransactionCreationService, TransactionIdempotencyService, TransactionApprovalService, Wave B simulation steps
- SchemaSeeder/installer parity: requires either test enforcement or consolidation to single source of truth
- PBKDF2 per-access: affects every customer listing screen; singleton EncryptionService binding is the remediation
