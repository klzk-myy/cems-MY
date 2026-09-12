# CEMS-MY — Workflow Inventory

All user-facing and automated workflows, grouped by business function.
Sources: `routes/web.php`, `routes/api_v1.php`, `routes/auth.php`, `routes/webhooks.php`,
`bootstrap/app.php` (scheduler), `app/Console/Commands`, `app/Jobs`,
`app/Providers/EventServiceProvider.php`, `app/Enums/TransactionStatus.php`.

Roles: **Teller** < **Manager** < **ComplianceOfficer** < **Admin** (permissions inherit upward).
MFA is required for all roles and re-verified (`mfa.verified`) on sensitive writes.

---

## 1. Onboarding & Setup

| Workflow | Entry points | Notes |
|---|---|---|
| First-run setup wizard (6 steps) | `GET/POST /setup/*` (web) | Company info → admin user → currencies → exchange rates → initial stock → opening balances → complete. `setup.accessible` middleware gates it; `POST /setup/reset` is admin-only |
| Quick setup | `POST /setup/quick` | One-shot seed for dev/testing |
| CLI setup | `business:setup`, `comprehensive:setup` | Seeded demo data (branches, transactions, admin) |
| New currency onboarding | `system/currencies` (admin, web) | Auto-provisions GL accounts + currency positions; disable guarded against open transactions and non-zero positions |

## 2. Authentication, MFA & Session Security

| Workflow | Entry points | Notes |
|---|---|---|
| Login / logout | `routes/auth.php` | `throttle:login`; `IpBlocker` blocks IP after 10 failed attempts (5-min window, 1-hour block) |
| Password reset (forgot) | `forgot-password`, `reset-password/{token}` | `throttle:password-reset` |
| Forced password rotation | `GET/POST /password/change` | BNM policy; `throttle:5,1` |
| MFA enrollment (TOTP) | `GET/POST /mfa/setup` | `EnsureMfaEnabled` forces setup on first login for **all** roles |
| MFA verify (step-up) | `GET/POST /mfa/verify` | 15-min verification window; `throttle:5,1` + per-user counter |
| Recovery codes | `mfa/recovery*`, `recovery-codes/regenerate` | Brute-force throttled |
| Trusted devices | `mfa/trusted-devices`, DELETE device | Bypass for step-up on known devices |
| Logout other devices | `POST /profile/devices/logout-others` | |
| API MFA | `POST /api/v1/mfa/{enroll,verify,disable,recovery-codes/regenerate}` | `throttle:sensitive`; password re-entry |
| Session timeout | `session.timeout` middleware on all authed web routes | Default 8 h |
| IP block admin | `security:ip stats` (daily 04:45 schedule) | Snapshot + index refresh; Redis TTL self-expiry |

## 3. Daily Branch Opening (Manager-driven)

Sequenced morning workflow, per `buz.opn.brc.md`:

1. **Rates ready** — `rates:staleness-check` (hourly) alerts on stale market rates; optional auto-fetch every 2 h (`RATE_AUTO_FETCH_ENABLED`); manual: web `POST /rates/override`, `POST /rates/copy-previous` or API `rates/fetch`, `rates/copy-previous`, `PUT rates/{ccy}`, `GET rates/check`.
2. **Teller allocations** (API) — manager approves/rejects/modifies pending allocations: `allocations/pending`, `…/approve`, `…/reject`, `…/modify`; teller polls `allocations/my-active`.
3. **Counter opening** — teller requests `POST /api/v1/counters/{id}/opening-request` → manager `approve-and-open` (`mfa.verified`); or web `GET/POST /counters/{c}/open` with opening floats.
4. **Branch pool funding** — `POST /branch-pools/{pool}/fund` / `/debit` (manager/admin).

## 4. Counter / Till Lifecycle

| Workflow | Entry points | Actor |
|---|---|---|
| Open counter | `counters/{c}/open` (web) or API opening-request → approve-and-open | Teller requests, Manager approves |
| Live status / history | `counters/{c}/status`, `/history` | Teller+ |
| Handover | `counters/{c}/handover` → `handover/acknowledge` (web); API `handover/{id}/acknowledge` | Custody transfer between users |
| Normal close | `counters/{c}/close` (web), `POST /api/v1/counters/{id}/close` | Manager/admin (web); teller+ via API w/ MFA |
| Emergency close | `counters/{c}/emergency*`, API `emergency-close` → `emergency/{id}/variance` → `acknowledge` | Initiate (teller+) → manager acknowledges variance |
| Till open/close (stock-cash) | `POST /stock-cash/open`, `/close` | Manager |
| Till report / reconciliation | `stock-cash/till-report`, `stock-cash/reconciliation` | Manager |
| Branch closing | `branches/{b}/closing` initiate → checklist → settle → finalize (web + API) | Manager/admin |
| EOD reconciliation | `GET /api/v1/eod/reconciliation/{date}[…]`; `report:eod` (daily 20:00); `/eod` dashboard | Manager/compliance |

## 5. Customer Management & KYC

| Workflow | Entry points | Notes |
|---|---|---|
| Customer CRUD | `customers/*` (web), `api/v1/customers/*` | Search + quick-create for teller flow |
| Notes | `POST /customers/{c}/notes` | |
| Freeze / unfreeze | `POST /customers/{c}/freeze|unfreeze` | Compliance/admin — blocks transacting |
| Close account | `POST /customers/{c}/close` | Manager/admin |
| KYC document upload | `POST /api/v1/customers/{c}/kyc` | |
| KYC verify / reject / download | `kyc-documents/{doc}/*` | Compliance/admin |
| KYC expiry sweep | `KycDocumentExpiryService::expireDocuments` (daily 01:45) | Verified docs past expiry + grace → `Expired` |
| Dormancy sweep | `customers:mark-dormant` (monthly, 2nd 02:30) | No transactions within `cems.dormancy_months` |
| Behavioral baseline backfill | `customer:baseline-backfill` (monthly 03:30) | |
| Customer risk review sweep | `customer:risk-review` (daily 02:30) | |
| PEP cessation review | `customers:pep-cessation-review` (monthly 04:00) | |
| Record-change rescreening | `CustomerRecordUpdated` → `TriggerSanctionsRescreening` | Event-driven |

## 6. Transaction Lifecycle (core)

**State machine** (`TransactionStatus`): `PendingApproval → Approved → Processing → Completed → Finalized`,
plus `Rejected`, `PendingCancellation → Cancelled`, `Failed`, `Reversed`.
(`Draft`, `Pending`, `OnHold` are legacy read-only states.)

### Creation paths (all converge on the same service pipeline)

- `POST /transactions` (web, `mfa.verified`)
- `POST /api/v1/transactions` (`mfa.verified`, `throttle:10,1`)
- Teller wizard: `POST /api/v1/wizard/transactions/step1→step2→step3` (step3 = creation, `mfa.verified`); session status/cancel
- Batch upload (manager): `transactions/batch-upload` → `ProcessTransactionImportJob`

### Creation pipeline

1. Validation (form request; field-mapped errors via `MapsTransactionExceptionsToFields`)
2. Teller allocation check (`DeterminesTransactionStatus::determineTellerAllocation`)
3. **Risk evaluation at creation** — `CustomerRiskScoringService` + sanctions screening
4. Initial status via `determineInitialStatus`: **< RM 10,000 auto-approve threshold AND risk = Low AND no compliance hold → `Completed`**; otherwise `PendingApproval` (stock reservation created)
5. `TransactionCreated` event → listener (accounting, monitoring triggers)

### Post-creation flows

| Workflow | Entry points | Actor |
|---|---|---|
| Approve / reject | `POST /transactions/{t}/approve|reject` (web + API) | Manager, MFA |
| Confirm | `GET/POST /transactions/{t}/confirm` | Manager; stale confirmations expired every 15 min (`TransactionConfirmationService::expireStale`) |
| Cancellation | `cancel` (request) → `approve-cancellation` / `reject-cancellation` | Manager requests; manager/compliance approves — segregation of duties |
| Receipt / print / verify | `receipt`, `print`; public `GET /verify/transaction/{ref}` (throttled) | QR-verifiable |
| Export | `transactions/export` | Manager/admin |
| Failed recovery | `transactions:recover` (every 5 min) → retry; `transactions:dlq-alert` (every 5 min) | Automatic |
| DLQ admin | `transactions/dlq`, `…/retry`, `…/purge` | Admin |
| Reservation expiry | `reservation:expire` (every 15 min) | Releases stale stock reservations |
| Reversal | `journal/{entry}/reverse`, transaction reversal service | Manager |

## 7. Stock, Positions & Transfers

| Workflow | Entry points | Notes |
|---|---|---|
| Position views | `stock-cash`, `stock-cash/position/{p}` | Manager |
| Branch pools | `branch-pools/*` fund/debit | Manager/admin |
| Stock transfer pipeline | `stock-transfers/*` | **create (manager) → approve-bm (branch manager) → approve-hq (admin) → dispatch → receive → complete (admin)**; cancel (manager) / reject (admin) |
| Low stock alert | `LowStockAlertJob` (daily 06:00) | |
| Reservations | `StockReservation` + `reservation:expire` | Concurrency control on pending approvals |

## 8. Accounting (double-entry, BCMath)

| Workflow | Entry points | Notes |
|---|---|---|
| Journal entries | `accounting/journal*` | Create → pending → posted; reverse |
| Ledger / statements | `accounting/ledger`, `trial-balance`, `profit-loss`, `balance-sheet`, `cash-flow`, `ratios` | Manager; `whereDate` boundaries (SQLite/MariaDB portable) |
| Chart of accounts | `accounting/chart-of-accounts` | Admin/compliance read-only |
| Period close | `POST /accounting/periods/{p}/close` | |
| Fiscal year | `fiscal-years` list/store/close | Closing entries via `AccountingService::postToLedger` |
| Month-end close | `accounting:month-end` (monthly 01:00); API `accounting/month-end/close|status` | |
| Revaluation | `revaluation:run` (month-end 23:59); `accounting/revaluation*` | Monthly currency revaluation |
| Bank reconciliation | `accounting/reconciliation*` | Import statement → auto/manual match → exceptions → export |
| Budgets | `accounting/budget*` | |
| Deferred accounting | `ReconcileDeferredAccountingJob` (daily 21:00) | Enhanced-CDD transactions' journal entries |
| Reports | `report:trial-balance` (Sun 01:00) | |

## 9. Compliance & AML

### Sanctions data pipeline

| Workflow | Entry points | Notes |
|---|---|---|
| Scheduled imports | `ImportSanctionsJob(eu_consolidated)`, `ImportSanctionsJob(ofac_sdn)` — weekly Sun 02:00 | Single reusable job per list slug |
| Manual/CLI import | `sanctions:update`, `sanctions:import`, `sanctions:status` | Delta/incremental sync |
| Webhook trigger | `POST /sanctions/update` (throttled), `GET /sanctions/health` | Token-authenticated |
| On-demand import | `POST compliance/sanctions/{list}/import`, API `sanctions/import/trigger/{list}` | `throttle:5,10` |
| Entry management | `compliance/sanctions/entries*` (web), `api/v1/sanctions/*` (admin) | Canonical `NameNormalizer` + `LikeEscaper` |
| Storage hygiene | `sanctions:prune` (daily 04:30) | Archive retention + temp cleanup |
| Adverse media | `adverse-media:import` | |

### Screening & matching

- **At creation**: risk + sanctions evaluated inside transaction creation.
- **Manual**: `compliance/screening/{customer}` (web), `api/v1/screening/*` (screen, batch, history, status).
- **Match disposition**: `compliance/screening-matches/{id}/confirm|dismiss`.
- **Scheduled rescreening**: `SanctionsRescreeningMonitor` weekly Sun 02:00 (staleness-aware); `compliance:rescreen` manual only; `SanctionsListUpdated` event → `TriggerSanctionsRescreening`.
- `ComplianceScreeningJob`, `RescreenHighRiskCustomersJob`, `SanctionsRescreeningJob` for async work.

### CDD / EDD / PEP

| Workflow | Entry points |
|---|---|
| CDD level determination | `CddLevelDeterminationService` — Simplified <3k, Specific 3–10k, Standard ≥10k, Enhanced (PEP/sanction/high-risk/≥50k) |
| EDD staff review | `compliance/edd-review/*` — approve/reject records + questionnaire |
| EDD customer portal | `compliance/edd/*` — **signed URLs**, upload/download requested docs |
| EDD expiry | `EddService::expireRecords` (daily 01:30) |
| PEP approvals | `compliance/pep-approvals/*` — senior management approve/reject |

### Alerts, flags, findings, cases

| Workflow | Entry points |
|---|---|
| Flagged transactions | `compliance/flagged`, `flags/{f}/assign|resolve` |
| Alert triage | `compliance/alerts/*` assign/resolve/dismiss/escalate; API bulk-assign/bulk-resolve/auto-assign, summary, overdue |
| Unified alerts | `compliance/unified` |
| Findings | `compliance/findings/*`; `…/create-case`; API stats/dismiss |
| Cases | `compliance/cases/*` — create, update, merge, link-alert, escalate, documents+verify, links; API + timeline, close |
| Case SLA alerts | `CaseManagementService::alertBreachedCases` (daily 06:00) |
| Compliance dashboard | `compliance` + API KPIs, calendar, case-aging, audit-trail(+export), auto-reports |

### Risk scoring

- `compliance/risk-dashboard` (manager/admin): portfolio, per-customer, trends, admin rescreen.
- API `risk/*`: show, history, recalculate, **lock/unlock**.
- Component services: `RiskScoringEngine`, `RiskCalculationService`, `RiskScoreWriteBackService`, `HistoricalRiskAnalysisService`, amount/geographic/pattern/structuring/velocity risk services.

### Monitoring engine

`RunComplianceMonitorJob` wraps monitors: **VelocityMonitor, StructuringMonitor, SanctionsRescreeningMonitor, CustomerLocationAnomalyMonitor, CurrencyFlowMonitor, CounterfeitAlertMonitor** (`monitor:check`, `monitor:status`).

### STR filings

`compliance/str/*` — list, show, `create-from-case`, submit, acknowledge, CSV export (`StrReportService`, `NarrativeGenerator`).

## 10. Regulatory & Operational Reporting

| Report | Command / route | Schedule |
|---|---|---|
| MSB(2) daily | `report:msb2`, `reports/msb2` + export | Daily 00:05 (prev day) |
| Position limit | `report:position-limit`, `reports/position-limit` | Daily 06:00 |
| EOD reconciliation | `report:eod`, API eod endpoints | Daily 20:00 |
| Trial balance | `report:trial-balance` | Sun 01:00 |
| LMCA monthly | `report:lmca`, `reports/lmca` | 1st 00:30 |
| QLVR quarterly | `report:qlvr`, `reports/quarterly-lvr` | 1st of Jan/Apr/Jul/Oct 01:00 |
| Analytics | `reports/monthly-trends|profitability|customer-analysis|compliance-summary` | On demand |
| User report schedules | `reports/schedules*` CRUD (admin) → `reports:process-schedules` (hourly) | User-defined |
| Retention | `reports:cleanup --days=90` (monthly 02:00), `reports:archive --months=12` (Jan 1 04:00, 7-yr BNM retention) | |
| Downloads | `api/v1/reports/download/{filename}` | |

## 11. Notifications

- **In-app bell**: `notifications/unread-count` (polled), `…/{n}/read` (marks read + optional relative redirect), `…/read-all`, `…/preferences`.
- **Digest**: `notifications:send-digest` daily at configured time (feature-flagged).
- **Async delivery**: `SendNotificationJob`; `notifications:test` for verification.
- Notification classes cover: transaction flagged/approved/outcome/cancellation-pending, large transaction, sanctions match, compliance finding/case assigned/SLA breach, emergency closure, deferred-accounting failure, DLQ alert, reservation expired, revaluation complete, system alert/health, report email, confirmation required, digest mail.
- Broadcast channels in `routes/channels.php`.

## 12. Administration & Platform Ops

| Workflow | Entry points |
|---|---|
| User management | `users/*` CRUD + `reset-password` (admin, MFA); `user:create` CLI |
| Branch management | `branches/*` CRUD + deactivate (admin); API branches endpoints |
| Audit logs | `admin/audit-logs` viewer (admin/compliance); `audit:verify` (daily 03:00 hash-chain check); `audit:rotate --cleanup` (Sun 03:30, 5-yr retention); `SealAuditHashJob` async sealing |
| System alerts | `system/alerts` list + acknowledge; `alert:cleanup`, `alert:send`, `alert:daily-summary` |
| Health & perf | `/health` (admin), `/up`, `/performance` (manager), `queue:health-check` (daily 05:30), `queue:clear-stuck`, `queue:retry-failed`, `queue:prune-failed` (Sun 04:00), `horizon:snapshot` (5 min) |
| Backups | `backup:run` (daily DB 02:00; weekly full Sun 03:05; monthly labelled), `backup:clean` (Sun 03:00), `backup:monitor` (daily 07:00), `backup:list|restore|verify` |
| Test tooling | `test-results/*` dashboard (admin, feature-flagged), `test:run`, `test:scenarios`, `simulation:*`, `db:reset-test`, `routes:validate`, `customers:re-encrypt` |

## 13. Scheduler Map (`bootstrap/app.php`)

| Frequency | Jobs |
|---|---|
| Every 5 min | `transactions:recover`, `transactions:dlq-alert`, `horizon:snapshot` |
| Every 15 min | `reservation:expire`, `confirmation-expire-stale` |
| Hourly | `rates:staleness-check`, `reports:process-schedules` |
| Daily | `report:msb2` 00:05 · `edd-expire` 01:30 · `kyc-expire` 01:45 · `backup:run --type=database` 02:00 · `customer:risk-review` 02:30 · `audit:verify` 03:00 · `sanctions:prune` 04:30 · `security:ip stats` 04:45 · `queue:health-check` 05:30 · `report:position-limit` + `LowStockAlertJob` + case-SLA alerts 06:00 · `backup:monitor` 07:00 · digest 09:00 · `report:eod` 20:00 · `ReconcileDeferredAccountingJob` 21:00 |
| Weekly (Sun) | `report:trial-balance` 01:00 · `SanctionsRescreeningMonitor` + EU + OFAC imports 02:00 · `backup:clean` 03:00 · `backup:run` 03:05 · `audit:rotate` 03:30 · `queue:prune-failed` 04:00 |
| Monthly | LMCA 1st 00:30 · `accounting:month-end` 1st 01:00 · `reports:cleanup` 1st 02:00 · `customer:baseline-backfill` 1st 03:30 · `customers:pep-cessation-review` + `backup:run --filename=monthly-full` 1st 04:00 · `customers:mark-dormant` 2nd 02:30 · `revaluation:run` last day 23:59 |
| Quarterly | `report:qlvr` 1st of Jan/Apr/Jul/Oct 01:00 |
| Yearly | `reports:archive` Jan 1 04:00 |
| Conditional | `rates-auto-fetch` every 2 h (`RATE_AUTO_FETCH_ENABLED`); `notifications:send-digest` (`notifications.digest.enabled`) |

All scheduled entries use `withoutOverlapping()` + `onOneServer()` + per-task log files.

## 14. Event → Listener Map (`EventServiceProvider`)

| Event | Listener(s) | Workflow triggered |
|---|---|---|
| `TransactionCreated` | `TransactionCreatedListener` | Post-creation side effects (accounting, monitoring) |
| `TransactionApproved` | `TransactionApprovedListener` | Stock reservation consumption, completion pipeline |
| `TransactionCancelled` | `TransactionCancelledListener` | Reservation release, reversal |
| `CustomerRecordUpdated` | `TriggerSanctionsRescreening::handleCustomerUpdate` | Re-screen on KYC data change |
| `SanctionsListUpdated` | `TriggerSanctionsRescreening::handleSanctionsUpdate` | Re-screen after list refresh |
| `CustomerRelationAdded/Removed` | `CustomerRelationListener` | Related-party risk propagation |
| `RelatedPartyOwnershipConcern` | `RelatedPartyOwnershipConcernListener` | Ownership-structure alert |
| `ReportGenerated` | `ReportGeneratedListener` | Report lifecycle/notification |
| `AlertCreated`, `CaseOpened`, `RiskScoreCalculated/Updated` | `ComplianceEventListener` (subscriber) | Compliance notifications/escalation |
| Queue `JobProcessing/JobProcessed/JobFailed` | inline `Queue::*` hooks | Job lifecycle logging |

Event auto-discovery is **disabled** (`withEvents(discover: false)`); the `$listen`/`$subscribe` map above is authoritative.

---

# Audit Findings

**All 14 groups audited. Tally: 9 bugs fixed, 1 risk resolved, 14 caveats noted, 35 areas verified OK.**
Committed as `a389bf32` (plus `92427b91` sanctions + `c27b2825` notifications). CI green.

## Consolidated findings — bugs & fixes

| # | Severity | Area | Finding → Fix |
|---|---|---|---|
| 1.1 | **bug** | Setup | Empty-session `completeSetup` POST seeded data + set the immutable marker with no admin → bricked install → **422 guard on `business`+`admin` session keys** |
| 2.1 | **bug** | Auth/MFA | `_session_created_at` never written → absolute session-lifetime check dead → **stamped at login + lazily in `SessionTimeout`** |
| 2.2 | **bug** | Auth/MFA | `EnsureMfaEnabled` documented but never existed — users could dodge MFA enrollment forever → **new middleware on the whole authed web group** |
| 2.3 | risk | Auth/MFA | Enrollment-grace basis diverged (`created_at` vs `last_login_at`, never-logged-in exempt forever) → **canonical `MfaService::isEnrollmentOverdue()` everywhere** |
| 3.1 | **bug** | Rates | `copyPreviousRates` wrote mid→both sides = zero-margin trading → **re-derives buy/sell via shared `applySpread()`** |
| 3.2 | **bug** | Rates | Rate creation via override path skipped the audit log → **logs `rate_overridden` on create too** |
| 4.1 | **bug** | Counter | Red-variance close demanded a supervisor no caller could supply → unclosable sessions → **`supervisor_id` + `ResolvesCloseSupervisor` concern on web+API** |
| 4.2 | **bug** | Counter API | `closing_floats` validated as `currency=>amount` map but service indexes item arrays → 500 → **normalized before dispatch** |
| 5.1 | **bug** | Customer | `is_active=false` (closed/sanctioned/rejected) customers could still book → **creation guard covers `!is_active`** |
| 5.2 | **bug** | Risk scoring | Sanction-hit `High` silently downgraded to Medium by score write-back (50 pts < 60) → **`apply()` pins High on `sanction_hit`** |
| 7.1 | **bug** | Transfers | Cancel/reject after dispatch leaked in-flight stock → **`returnInFlightStockToSource()` restores unreceived qty under lock** |
| 10.1 | **bug** | Reporting | 90-day cleanup deleted exports before 12-month archival → BNM retention gap → **cleanup skips unarchived report files** |

## Consolidated findings — caveats (accepted, no change)

| # | Area | Caveat |
|---|---|---|
| 1.2 | Setup | DDL inside `DB::transaction` auto-commits on MariaDB — seeders idempotent, marker written only on success |
| 1.3 | Setup | `createInitialStock` uses `rate_buy` as cost basis; zero-cost stock possible if rate missing (step-4 rules prevent) |
| 1.4 | Setup | Progress computed data-derived vs marker-based lock — display only |
| 2.4 | Auth | `Auth::login` inside DB txn masks infra errors as "Invalid credentials" + burns a failed-attempt |
| 2.5 | Auth/MFA | 30-day enrollment grace weakens "MFA for all" — deliberate policy knob |
| 3.3 | Counter open | No teller-role check on opening request — stays PENDING until manager approves |
| 4.3 | Handover | `from_user_id`/`supervisor_id` are name-records without second auth — role/branch still enforced |
| 4.4 | Counter | `history()` filters unvalidated — safe (bound params), garbage input just returns nothing |
| 5.5 | Customer | `notes.store` unrestricted to any authed user — audit-logged |
| 6.6 | Transactions | `PendingApproval→Completed` skips `Approved` — documented intent for manager approval |
| 7.4 | Transfers | `createRequest` needs `auth()->user()` — would fatal in console/queue context |
| 7.5 | Transfers | BM approval doesn't check manager's branch in-service — route/policy layer covers it |
| 8.4 | Accounting | Entries outside all periods post with `period_id=null` — documented behavior |
| 9.5 | STR | One-STR-per-case guard is unlocked `exists()` — theoretical race on a deliberate action |
| 11.3 | Notifications | Digest config-gated; `markAllRead` has no confirmation step |

Details per group below.

## Group 1 — Onboarding & Setup ✅

| # | Severity | Finding | Status |
|---|---|---|---|
| 1.1 | **bug** | `completeSetup` executed with whatever the session held — including nothing. An empty-session POST seeded reference data, set the immutable `setup_state` marker, then `EnsureSetupAccessible` locked all `/setup` routes while `setup.reset` needed an admin that was never created → **bricked install** | **Fixed** — requires `business` + `admin` session keys, else 422. Regression test in `SetupControllerTest` |
| 1.2 | caveat | `runMigrations()` can invoke `SchemaSeeder` (DDL) inside `DB::beginTransaction`; MariaDB DDL auto-commits so a later failure can't roll back the created schema | Noted — acceptable: seeders are idempotent and the marker is only written after success |
| 1.3 | caveat | `createInitialStock` uses `rate_buy` as cost basis; a currency with stock but no rate gets `average_cost = 0` (zero-cost sellable stock) | Noted — step-4 rules force a rate for custom currencies; seeded currencies always have seeded rates |
| 1.4 | caveat | Controller `isSetupComplete`/`getCurrentStep`/`calculateProgress` are data-derived while `EnsureSetupAccessible` is marker-based; `chart_of_accounts` is collected but excluded from `every()` | Noted — display-only |
| 1.5 | ok | Custom "other" currency: step-3 folds code into active set, step-4 mandates its rates, `executeSetup` creates the `Currency` row + pool + position for HQ — consistent with `CurrencyController::store` provisioning (only HQ exists at setup) | Verified |
| 1.6 | ok | Marker-based lock + admin-only reset + production fallback in `EnsureSetupAccessible` closes the re-open/takeover path | Verified |

## Group 2 — Auth, MFA & Session Security ✅

| # | Severity | Finding | Status |
|---|---|---|---|
| 2.1 | **bug** | `EnsureMfaVerified` reads `_session_created_at` for the absolute session-lifetime check, but **nothing ever wrote that key** — the check always defaulted to `now()` (elapsed 0). Dead code; absolute session expiry never enforced | **Fixed** — stamped at login; `SessionTimeout` back-fills it on first authed request (remember-me restores, legacy sessions) |
| 2.2 | **bug** | `EnsureMfaEnabled` was documented (CLAUDE.md: "forces MFA setup on first login for all roles") but **the class didn't exist** — enrollment was only forced when a user happened to hit an `mfa.verified` route after grace. A user avoiding sensitive routes never enrolled | **Fixed** — new `EnsureMfaEnabled` middleware (`mfa.enabled` alias) on the whole authed web group; redirects overdue unenrolled users to `mfa.setup`, exempting `mfa.*`, `logout`, `password.*`, `notifications.*` |
| 2.3 | risk | Grace basis diverged: `EnsureMfaVerified` measured from `created_at`, dead `User::needsMfaSetup` from `last_login_at` (and never-logged-in users → `false` forever) | **Fixed** — canonical `MfaService::isEnrollmentOverdue()` (created_at, fail-closed on null) used by all three |
| 2.4 | caveat | `login()` wraps `Auth::login` in `DB::transaction`; a `\Throwable` there falls through to "Invalid credentials", masking infra errors as bad logins | Noted — also increments the failed-attempt counter on infra failure |
| 2.5 | caveat | 30-day enrollment grace (`cems.mfa.grace_days`) lets required-role users defer MFA — deliberate design, but technically weaker than "MFA for all roles" | Noted — policy knob, configurable to 0 |
| 2.6 | ok | Login: uniform "Invalid credentials" (no user enumeration), IP failed-attempt tracking, session regeneration, forced rotation on expired password, `logoutOtherDevices` rehash trick correct | Verified |
| 2.7 | ok | `SessionTimeout` exempts `notifications/unread-count` so the bell poll can't defeat idle timeout; MFA code entry throttled + per-user lockout counter | Verified |

## Group 3 — Daily Branch Opening ✅

| # | Severity | Finding | Status |
|---|---|---|---|
| 3.1 | **bug** | `copyPreviousRates` wrote the historical **mid** rate to both `rate_buy` and `rate_sell` — flattening spread to zero, violating the sell>buy invariant, and trading at zero margin | **Fixed** — `RateApiService::applySpread()` (extracted, shared with `processRates`) re-derives buy/sell from mid with the configured spread; test updated |
| 3.2 | **bug** | `overrideRate` skipped the audit log when **creating** a rate (no existing row) — a manager could set a rate for a new currency untracked | **Fixed** — creation path now logs `rate_overridden` with null old values |
| 3.3 | caveat | `initiateOpeningRequest` has no teller-role check — any authenticated user can request a float for a counter (branch-scope still applies via `authorizeCounter`) | Noted — allocation stays PENDING until manager approves; low impact |
| 3.4 | ok | Allocation service is race-safe: `lockForUpdate` on approve/modify/returnToPool, decrease capped at unspent balance, PENDING rejection doesn't credit phantom pool funds — documented | Verified |
| 3.5 | ok | `areAllRatesSet` single-query check, per-currency cache invalidation on every writer, spread band [min,max] enforced on overrides | Verified |

## Group 4 — Counter / Till Lifecycle ✅

| # | Severity | Finding | Status |
|---|---|---|---|
| 4.1 | **bug** | `closeSession` demands a manager supervisor when variance exceeds the red threshold — but **no caller could ever supply one** (no `supervisor_id` field on either request, no view field). Any session with red variance was unclosable → EOD deadlock | **Fixed** — `supervisor_id` on both requests; `ResolvesCloseSupervisor` concern lets an acting manager self-satisfy, others must name a manager/admin |
| 4.2 | **bug** | API `close` validated `closing_floats` as a `currency => amount` map, but `closeSession` indexes `$float['currency_id']`/`$float['amount']` — a scalar map value → TypeError → 500 on every API close | **Fixed** — controller normalizes map → `[{currency_id, amount}]` items |
| 4.3 | caveat | Handover `from_user_id`/`supervisor_id` are caller-supplied ids (name-record, no second auth) — consistent with existing design; service still enforces `isManager()` on supervisor and same-branch membership | Noted |
| 4.4 | caveat | `history()` takes `from_date`/`to_date`/`user_id` unvalidated (bound params — safe, but garbage input silently filters to nothing) | Noted |
| 4.5 | ok | `closeSession` is atomic (single txn, locked till balances, two-phase validate→update); variance thresholds enforced; emergency close has cooldown + min-session-age guards; `BranchClosingService` uses lockForUpdate + status guards | Verified |

## Group 5 — Customer Management & KYC ✅

| # | Severity | Finding | Status |
|---|---|---|---|
| 5.1 | **bug** | Deactivated customers could still book transactions — `closeCustomer`/`reject`/sanction-hit set `is_active=false`, but `TransactionCreationService` only checked `transactions_blocked \|\| is_frozen`. A closed or sanctioned customer was not blocked at booking | **Fixed** — creation guard now also rejects `! is_active` (message falls back to `closure_reason`) |
| 5.2 | **bug** | Sanction-hit rating could be silently downgraded — `screenCustomer` sets `risk_rating=High`, then `calculateRiskScore`→`RiskScoreWriteBackService::apply` re-derives rating from the numeric score (sanction=50 → Medium for a Malaysian with no other factors) | **Fixed** — `apply()` pins `High` whenever `sanction_hit` is set (single shared hook covers all scoring paths) |
| 5.3 | ok | Creation is atomic: blind-index duplicate pre-check inside txn (incl. soft-deleted), encryption of id/address/phone/employer_address, explicit hash assignment (not mass-assignable), sanction screen → risk score → audit. Update re-screens only on name change | Verified |
| 5.4 | ok | KYC docs: SHA-256 file hash stored, verify/reject gated by `role:compliance,admin`; freeze/unfreeze compliance+admin; close manager+admin with pending-transaction guard; `hasAllIdentityDocumentsExpired` enforced at booking | Verified |
| 5.5 | caveat | `notes.store` has no role restriction (any authenticated user can add notes) — consistent with open-note design; notes are audit-logged | Noted |

## Group 6 — Transaction Lifecycle ✅

| # | Severity | Finding | Status |
|---|---|---|---|
| 6.1 | ok | State machine enforces a strict transition map with history on every hop; terminal states (Finalized/Cancelled/Reversed/Rejected) have no outgoing edges; `Failed`/`Completed`→`Failed` documented for the recovery path | Verified |
| 6.2 | ok | Initial status: `Completed` only when no hold AND `risk_rating=Low` AND amount < auto-approve threshold — else `PendingApproval` (DeterminesTransactionStatus, all 4 creation paths share it) | Verified |
| 6.3 | ok | Segregation of duties enforced: self-approval blocked (`user_id === approverId`), self-cancellation-approval blocked + logged, self-reversal blocked; approvals gated by `role:manager` (hierarchical — includes Admin) + `mfa.verified` | Verified |
| 6.4 | ok | Stock reservation lifecycle is closed-loop: created on `PendingApproval`, consumed atomically at approval (fails if stock gone), released via `StockReleaseService` on cancel and by `reservation:expire` for stale holds | Verified |
| 6.5 | ok | Approval runs AML check first (`handleAmlBlocks` can divert), validates status under lock, customer-exists + till-open guards before side effects | Verified |
| 6.6 | caveat | `PendingApproval → Completed` direct edge skips the `Approved` intermediate state — intentional for manager approval, documented in the transitions map | Noted |

## Group 7 — Stock, Positions & Transfers ✅

| # | Severity | Finding | Status |
|---|---|---|---|
| 7.1 | **bug** | Cancelling or rejecting a transfer after dispatch leaked stock — `dispatch()` removed the full quantity from the source branch, but `cancel()`/`reject()` (both allowed on `InTransit`/`PartiallyReceived`) only flipped status; the unreceived remainder vanished from every position | **Fixed** — `returnInFlightStockToSource()` credits unreceived quantity back to source under position lock, inside the cancel/reject transaction |
| 7.2 | ok | Transfer pipeline enforces dual approval (BM → HQ), status guards at every hop, locked source decrement with insufficient-stock guard, negative/over-receipt rejected, >5% variance audit-logged | Verified |
| 7.3 | ok | Position sign conventions consistent (Sell subtracts source, Buy adds destination); `complete()` lands outstanding on destination so dispatch = receive + complete exactly; branch-key resolution maps name/code → `branches.id` | Verified |
| 7.4 | caveat | `createRequest` relies on `$this->requester` defaulting to `auth()->user()` — null in console/queue context → fatal; only invoked from web/API today | Noted |
| 7.5 | caveat | `approveByBranchManager` checks role but not that the manager belongs to the source branch — branch isolation is enforced at route/policy level (verified in `StockTransferBranchIsolationTest`) | Noted |

## Group 8 — Accounting ✅

| # | Severity | Finding | Status |
|---|---|---|---|
| 8.1 | ok | `createJournalEntry` enforces ≥2 lines, no negative amounts, debit=credit balance, closed-period rejection — all inside a txn with audit log | Verified |
| 8.2 | ok | Ledger running-balance chain is concurrency-safe: `lockForUpdate` on the chart-of-accounts anchor before the balance read, branch-scoped so multi-branch postings can't contaminate each other | Verified |
| 8.3 | ok | Reversals re-lock the original (double-reverse safe), require Posted status, and post through `createJournalEntry` — so period/ balance validation applies to the reversal too | Verified |
| 8.4 | caveat | An entry dated outside every defined `AccountingPeriod` posts with `period_id = null` (closed-period guard only applies when a period row exists) — backdating into an undefined period is possible but documented behavior | Noted |
| 8.5 | ok | Earlier session fixes verified in tests: EOD Buy/Sell direction, MYR float summation via MathService (no float money math), `TransactionAccountingVerificationTest` covers 60 txns across 3 branches | Verified |

## Group 9 — Compliance & AML ✅

| # | Severity | Finding | Status |
|---|---|---|---|
| 9.1 | ok | STR lifecycle is guarded end-to-end: draft only from Closed cases, one-per-case, threshold check (`meetsThreshold`), BNM-reference uniqueness, `canTransitionTo` state guard on acknowledge, WARNING-level audit at each hop | Verified |
| 9.2 | ok | Sanctions pipeline consolidated earlier (92427b91): canonical `NameNormalizer` shared by import/manual-entry/screening, `LikeEscaper` on all LIKE prefilters incl. bound `ESCAPE` clause, single `ImportSanctionsJob` for all lists, weekly rescreening monitor only | Verified |
| 9.3 | ok | Flag queue: assign-to-self, resolve with audit; EDD: create→submit→approve/reject with reviewer + completeness check + 365-day expiry sweep; PEP approval blocks self-approval (`isSelfApproval`) | Verified |
| 9.4 | ok | Six monitors (velocity, structuring, sanctions rescreening, location anomaly, currency flow, counterfeit) all scheduled; risk scoring writes ComplianceFinding on ≥10-point swings | Verified |
| 9.5 | caveat | `createFromCase`'s one-STR-per-case guard is a non-locked `exists()` check — two simultaneous submissions could race; low risk since drafting is a deliberate compliance action on a closed case | Noted |

## Group 10 — Regulatory & Operational Reporting ✅

| # | Severity | Finding | Status |
|---|---|---|---|
| 10.1 | **bug** | Retention gap: `reports:cleanup --days=90` deleted every export file older than 90 days, but `reports:archive --months=12` only copies at 12 months — files aged 90d–12mo were destroyed before archival could ever see them, breaking the BNM 7-year chain | **Fixed** — `cleanupOldReports` now skips any file whose `ReportGenerated` record isn't `Archived` (basename match covers absolute + storage-relative `file_path` forms) |
| 10.2 | ok | Full BNM report set scheduled: MSB2 daily, LMCA monthly, QLVR quarterly, EOD + position-limit + trial-balance; `ProcessDueReportSchedules` handles user-defined schedules with per-schedule failure isolation + audit rows | Verified |
| 10.3 | ok | Archive copies the actual artifact before flipping status to `Archived` (no status-only archival); missing files are warned + logged, never silently marked | Verified |

## Group 11 — Notifications ✅

| # | Severity | Finding | Status |
|---|---|---|---|
| 11.1 | ok | Click-to-act flow fixed earlier (c27b2825): item body submits mark-read + relative redirect; `normalizeUrl` reduces absolute stored URLs to path+query (same-site safe); `TransactionFlaggedNotification` targets the GET flags queue not the PATCH endpoint | Verified live in browser |
| 11.2 | ok | `markRead` enforces ownership (`assertOwnedByCurrentUser` — notifiable id+type), redirect is relative-only (`//` rejected → no open redirect); `dlq_count` only populated for admins | Verified |
| 11.3 | caveat | Digest is conditionally scheduled (config-gated); `markAllRead` has no per-user confirmation — bulk action, all rows of current user only | Noted |

## Group 12 — Administration & Platform Ops ✅

| # | Severity | Finding | Status |
|---|---|---|---|
| 12.1 | ok | Audit chain: `audit:verify` scheduled + standalone command with `--limit`; `SealAuditHashJob` async sealing with sync-retry fallback (3 attempts → defer to queue, never drops the seal) | Verified |
| 12.2 | ok | Backups scheduled daily (DB 02:00), weekly full (Sun 03:00) with `backup:clean` retention, monthly labelled — all append logs; `backup:clean` asks confirmation interactively (scheduled run is non-interactive `cleanOldBackups` path) | Verified |
| 12.3 | ok | `ReEncryptCustomers` handles key rotation best-effort per relation, reports undecryptable rows as failures without touching them | Verified |

## Group 13 — Scheduler Map ✅

| # | Severity | Finding | Status |
|---|---|---|---|
| 13.1 | ok | Every scheduled command in `bootstrap/app.php` resolves to a registered artisan command (verified via `artisan list`); all scheduled jobs/closures exist (`ImportSanctionsJob`, `RunComplianceMonitorJob`, `ReconcileDeferredAccountingJob`, `LowStockAlertJob`, EDD/KYC/confirmation expiry calls) | Verified |
| 13.2 | ok | Prior dedup holds: one rescreening path (weekly monitor), one job class for all sanction-list imports | Verified |

## Group 14 — Event → Listener Map ✅

| # | Severity | Finding | Status |
|---|---|---|---|
| 14.1 | ok | All 13 event classes are wired: 8 via `$listen` (incl. `[Class, method]` tuples for shared listeners), 4 via `ComplianceEventListener::subscribe()`, email-verification listener delegated to framework once | Verified |
| 14.2 | ok | `shouldDiscoverEvents()` returns false — the `$listen`/`$subscribe` maps are authoritative; subscriber correctly registered via `$subscribe` (documented why: no `handle()`/`__invoke()`) | Verified |
| 14.3 | ok | Queue lifecycle logging (before/after/failing) wired in `boot()` — every queued job's start/complete/fail is logged | Verified |

---

# Browser E2E Results (Playwright, live `local.host`)

## Run 1 — Auth → Customer → Transaction → Approval

| Step | Result | Data integrity verified |
|---|---|---|
| Admin login (`admin`) | ✅ → `/dashboard`, MFA grace respected | — |
| Customer list | ✅ renders all customers | — |
| Create customer via form | ✅ → customer #20, detail page | Encrypted id/phone/address ciphertext ✅, blind indexes ✅, sanctions screening timestamp ✅, risk score+rating ✅, active ✅ |
| Transaction create (Sell USD 400 @4.81) | ✅ → txn #24 `PendingApproval`, RM1,924 | CDD=Simplified ✅; Medium-risk customer → approval required ✅; pending USD `StockReservation` created ✅; inactive/sanctioned customers **excluded from typeahead search** ✅ |
| Create manager via `/users/create` | ✅ → user #12, role=manager, branch=4 | — |
| Manager login (`manager1`) | ✅ → `/dashboard`; `EnsureMfaEnabled` allowed entry within enrollment grace (correct — user just created) | — |
| Approve txn #24 as manager | ✅ → "approved and completed", status `Completed` | `approved_by=12` ≠ creator `u4` (segregation ✅); reservation `consumed` ✅; journal #12 `Posted` — D1000 1,924 / C2000 1,888 / C5000 36 (cost+profit split ✅); USD position updated qty=9,200 avg_cost=4.72 ✅ |
| `TransactionApprovedNotification` | ❌→✅ | Job failed: SMTP 530 — `.env` missing `MAIL_MAILER`, fell back to smtp. **Fix**: `MAIL_MAILER=log` in `.env` (matches `.env.example`). Re-sent → DONE; db notification + broadcast ✅ |

### Config issues found (env-level, not code bugs)
- `.env` missing `MAIL_MAILER` → every email-bearing queued notification fails to DLQ. Fixed.
- `till_balances` has no `created_at` column (schema quirk — `latest()` ordering fails; use `id`).
- Recurring PHP warning `mbstring already loaded` (php.ini dupe — infra, not app).

## Run 2 — Counter Close (G4 in browser)

| Step | Result | Data integrity |
|---|---|---|
| `/counters/C01/close` (route key = code, not id) | ✅ renders | — |
| **BUG**: close form fields were `currencies[{"code":"USD",...}][count]` — view stringified the Currency model as array key | ❌→✅ fixed | Form could never POST valid `closing_floats`; web close was unsubmitable since the request contract changed |
| **BUG**: `myr_cash`/`summary`/`currencies[*][count]` fields vs required `closing_floats[].currency_id/amount` | ❌→✅ | Rewrote form to `closing_floats[CODE]` map + `prepareForValidation` transform (mirrors `OpenCounterRequest` convention); dropped orphan fields, added supervisor select + `username` fixes |
| Close C01 as manager1 with counted balances | ✅ → "Counter C01 closed successfully" | session4 `closed`, `closed_by=12`; per-currency closing/variance recorded; MYR −1924 & USD +1200 variances = **correct** (expected = open + buy − sell; two completed Sells of 400 USD today → expected USD 1,200); red-variance self-satisfied by acting manager ✅ |

### Verified semantics
- Branch **Sell** = foreign stock out, MYR in: USD position 10,000→9,200; `sell_total_foreign`=800 (txn8+txn24), `transaction_total`(MYR)=3,848 — no double-counting.
- Variance thresholds: |var| > red (500) requires manager supervisor — acting manager self-satisfies (ResolvesCloseSupervisor); yellow (>100) requires notes.
- `till_balances` has no `created_at`/`counter_session_id` — order by `id`, link via `till_id`+`date`.

## Run 3 — Counter Open + Handover (G4, browser)

| Step | Result | Data integrity |
|---|---|---|
| Open C01 via form (`opening_floats[CODE]`) | ✅ → session #8, owner manager1 | Field contract correct (unlike close/handover) |
| Handover page GET | ❌→✅ | 500: `$user->role` interpolated as string in blade → `->label()` fix |
| Handover form contract | ❌→✅ | Request requires `from_user_id`, `supervisor_id`, `physical_counts[]` — view had none. Added hidden from_user (session owner), supervisor select, `physical_counts[CODE]` + `prepareForValidation` transform; removed dead `pin`; scoped operator+supervisor dropdowns to counter's branch |
| Initiate handover manager1→teller1, supervisor=admin | ✅ → "handed over to teller1" | handover #4 row created; session custody moved to to_user |
| Acknowledge page (as teller1) | ❌→✅ | Form submitted dead `pin` field, no `verified` → silent validation fail. Replaced pin with `verified` checkbox + notes |
| **BUG**: `acknowledgeHandover` required `isManager()` while `findPendingHandover` scoped to `to_user_id` → teller recipients deadlocked | ❌→✅ | Rule is now **recipient OR recorded supervisor** may acknowledge (service check `to_user_id`/`supervisor_id`; lookup widened to match); API route `role:manager` relaxed to `teller,manager,admin` — service enforces authorization. +2 regression tests |
| teller1 confirms receipt | ✅ → acknowledged | `acknowledged_at` set, `physical_count_verified=1`, session #8 → `handed_over`, new session #12 opened for teller1 |
| teller1 password unknown → reset via tinker for E2E | note | staging DB only |

### Code changed this run
- `resources/views/counters/close.blade.php` — closing_floats[CODE] fields, supervisor select, username fixes
- `resources/views/counters/handover.blade.php` — full field set + role->label()
- `resources/views/counters/acknowledge-handover.blade.php` — verified checkbox + notes
- `app/Http/Requests/CloseCounterRequest.php` + `HandoverCounterRequest.php` — map→items `prepareForValidation`
- `app/Http/Controllers/CounterController.php` — supervisor/user queries (branch scope, username, enum ->value)
- `app/Services/Branch/CounterHandoverService.php` — recipient-can-acknowledge fix

## Run 4 — Teller Transaction + Approval (G5/G6, browser)

| Step | Result | Data integrity |
|---|---|---|
| teller1 `/rates` | 403 ✅ | manager-only route, correctly denied; nav link hidden for teller |
| teller1 Buy txn without allocation | ✅ rejected | Field-level error on Counter: "No active allocation for this currency" — allocation gate works |
| Allocation via service (request→approve→activate) | ✅ | teller1 USD 2,000 / 20,000 MYR daily limit |
| teller1 creates Buy USD 300 @4.85 | ✅ → txn #28 `PendingApproval` | RM1,455; Medium-risk customer → not auto-completed ✅; **no stock reservation for Buy** (stock increases, nothing to reserve) ✅; daily_used_myr=0 until approval |
| teller1 force-POST `/transactions/28/approve` | ✅ 403 | Server-side segregation confirmed (UI also hides the button) |
| manager1 approves txn #28 | ✅ → Completed | `approved_by=12` ≠ creator `u8` ✅; journal #16 Posted D2000 1,455 / C1000 1,455 ✅; USD position 9,200→9,500, weighted avg cost 4.72→4.7241 ✅ |
| `TransactionApprovedNotification` | ✅ | mailer fixed — job completes, DB notification + broadcast |

### Type semantics verified (branch perspective)
- **Buy** = branch buys foreign from customer → foreign position +, MYR −; journal D-foreign-stock / C-MYR-cash
- **Sell** = branch sells foreign to customer → foreign position −, MYR +; journal D-MYR / C-cost+profit
- Auto-complete rule: only amount<threshold **and** risk=Low — Medium-risk always requires approval

## Run 5 — Stock Transfer Pipeline (G7, browser)

| Step | Result | Data integrity |
|---|---|---|
| `/stock-transfers/create` form | ❌→✅ | Contract mismatch: form submitted `branch_id`/`currency_id`/`amount`; request requires `source_branch_name`/`destination_branch_name`/`type`/`items[]` — **form could never succeed**. Rewrote view to the real contract; `create()` now plucks branches by name (schema stores names) |
| `createRequest()` 500 `property "id" on null` | ❌→✅ | `$this->requester` captured `auth()->user()` at construction — null when resolved before auth guard ready. Lazy `requester()` resolver + `UnauthorizedException` fallback |
| BM approve (manager1) | ✅ | Requested → BranchManagerApproved |
| HQ approve attempted as manager | ✅ 403 | admin-only enforced (caveat: button visible to manager — server-side enforced) |
| HQ approve as admin | ✅ | → HqApproved |
| Dispatch | ✅ | HQ USD 9,500 → 9,000 (locked decrement) |
| Receive 500 USD | ✅ | KL02 position created +500; status Received |
| **BUG**: `Received` was a dead-end — `complete()` rejected it, no Complete button | ❌→✅ | `canComplete()` + `complete()` now accept `Received` (outstanding=0, only finalizes) |
| Complete as admin | ✅ → Completed | HQ 9,000 / KL02 500 — stock conserved end-to-end |

### Keyboard navigation
- Tab order: skip-link → sidebar toggle → nav links; Enter navigates (verified Dashboard → Transactions)
- Notification bell & logout are real buttons, focusable

## Run 6 — Page Sweep + Notifications + Keyboard (as admin)

| Step | Result |
|---|---|
| `/accounting`, `/reports`, `/users`, `/customers/20`, `/stock-cash`, `/stock-transfers` | ✅ all render 200, no 500s |
| Customer PII | ✅ MyKad masked `8503****5522`; name/phone/email visible to authorized staff |
| `/notifications` index | 404 by design — bell dropdown is the surface |
| Bell dropdown | ✅ items render with safe relative links; "Mark all as read" present |
| Notification item click | ✅ mark-read + redirect (unread 4→3, → /transactions/24) |
| Notification "View" link | navigates WITHOUT marking read — minor inconsistency (caveat) |
| Compliance monitors live | ✅ structuring flags firing: "Potential structuring: 3+ transactions under RM 10,000 within 1 hour" |
| Console errors | only favicon 404 + report-only CSP + `speaker`/`vibrate` Permissions-Policy noise |

### Browser E2E bugs fixed this session
1. `close.blade.php` — currency model stringified into field names; rewrote to `closing_floats[CODE]` + request transform + supervisor select
2. `handover.blade.php` — `role` enum interpolation 500; missing from_user/supervisor/physical_counts; dead `pin`
3. `acknowledge-handover.blade.php` — dead `pin`, no `verified` field → silent validation failure
4. `CounterHandoverService` — manager-only ack deadlocked teller recipients → recipient-or-supervisor
5. `CounterController` — `users.name` column doesn't exist (→username); enum in whereIn; branch-scoped dropdowns
6. `stock-transfers/create.blade.php` — entirely wrong field contract vs request
7. `StockTransferService` — null requester crash; `Received` dead-end status
8. `StockTransfer::canComplete()` — now includes `Received`
9. `.env` — missing `MAIL_MAILER` → all email notifications failed to DLQ
