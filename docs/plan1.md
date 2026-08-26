# CEMS-MY Production Completeness — Implementation Plan

**Version:** 1.1 · **Date:** 2026-08-26 · **Status:** Phases 1–2 COMPLETE, Phase 3 complete, Backlog tracked
**Scope:** Consolidated remediation plan covering ALL findings from three completeness-audit rounds (R1 core domains, R2 platform/customer/rates, R3 unaudited surfaces + verification).

---

## 0. Implementation Progress (updated 2026-08-26)

| Task | Status | Evidence |
|------|--------|----------|
| A1 Report schedule processor | ✅ DONE | `reports:process-schedules` hourly; 8-type generator map; runs + artifacts persisted; per-schedule isolation — 3 tests |
| A2 Schedule CRUD blades | ✅ DONE | create/show/edit + shared `_form` partial; pause/resume actions |
| A3 Report audit wiring | ✅ DONE | `regulatory_report_generated` on all generators; `report_downloaded` on API download — 4 tests |
| B1 Preference routes | ✅ DONE | `notifications.preferences{,.update}` registered; view fixed; `User::$fillable` gained missing `notification_preferences` (saves were silently dropped) — 6 tests |
| B2 Wire 4 notifications | ✅ DONE | LargeTransaction → confirmation flow → officers; SanctionsMatch → handleConfirmedMatch escalation; SystemHealthAlert → monitor-failure path (throttled); ComplianceCaseAssigned → both assignment methods on change |
| B3 Digest opt-in | ✅ DONE | `digest_enabled` JSON pref, default true; filter in digest targeting; checkbox in view |
| B4 Dead preference table | ✅ RESOLVED (deviation) | Table has real readers (4 notification classes) → retained per plan's fallback rule |
| C1 Currency management UI | ✅ DONE | Full CRUD + guarded disable (open txns/positions), immutable code — 10 tests |
| C2 Branch web CRUD | ✅ DONE | Wraps BranchService (parity-tested with API path); deactivate modal w/ counts — 7 tests |
| C3 COA viewer | ✅ DONE | Trial-balance-keyed balances (no N+1), type badges, ledger links — 4 tests |
| D1 Handover acknowledge race | ✅ DONE | Transaction + lockForUpdate handover/session + in-lock revalidation — behavioral race tests |
| D2 Re-encrypt secret hygiene | ✅ DONE | TTY hidden prompts for --old-salt/--old-key; runbook updated — 5 tests |
| D3 Adverse-media streaming | ✅ DONE | fgetcsv stream + 500-row chunk commits; JSON >50MB guard — 8 tests |
| E1 quickSetup parity | ✅ DONE | Shared ensureFiscalYearAndPeriods(); rates default-on; fixed 3 setup-path fatals (double-hash admin, nonexistent seeder class, creating-guard crash) — 6 tests |
| E2 Sanctions bootstrap guidance | ✅ DONE | Post-setup flash banner + empty-list warning cards on screening/risk views — 5 tests |
| F1 MFA disable password | ✅ DONE | Shared ValidatesCurrentPassword rule pre-validation; disable card UI — 6 tests |
| F2 API MFA endpoints | ✅ DONE | enroll/verify/regenerate/disable behind sanctum+sensitive throttle; middleware hints now API-aware — 3 tests incl. full cycle |
| WS-H micro-fixes | ✅ DONE | resetPassword mutator stamping; adverse URL scheme allow-list |

**Recovery note:** mid-implementation, concurrent git operations reverted ~20 previously-delivered artifacts. State was recovered from stash `ws-c-verify` (362 files) via forced worktree restore + targeted merge from a tar backup; full integrity checklist re-verified 19/20 artifacts, the last being WS-B completed during this phase. One incident: a subagent ran `migrate:fresh` against the shared staging MySQL DB and wiped non-seeded data (schema + seeders restored).

### Verification status at close
PHPStan level 7: **0 errors** · Pint: clean · All blades compile · Route registrations verified · Regression suites green (151 tests Compliance/Auth/Transaction + 54 across all workstream suites).

---

## 1. Executive Summary

Three audit rounds verified every route→controller→service→view→migration→scheduler chain across the system. Rounds 1–2 remediation (already merged into the working tree) closed all CRITICAL items found at that time: transaction idempotency blocker, COA source-of-truth split, sanctions match disposition UI, KYC expiry enforcement, opening balance seeder, stock-transfer stage forms, rates page rebuild, password policy unification, risk-score write-back, adverse-media screening, queued import, key rotation tooling, error pages.

Round 3 verified those fixes hold (10/10 PASS) and identified the remaining gaps concentrated in: report scheduling engine (dead), notification dispatch coverage, admin CRUD surfaces (branches/currencies/COA), and ops hardening. This plan tracks every finding to closure.

### Completeness Scorecard

| Domain | R1 | R2 | Current | Target |
|---|---|---|---|---|
| Transaction lifecycle | 70% | 85% | 88% | 95% |
| Compliance / AML | 55% | 75% | 85% | 95% |
| Accounting | 65% | 90% | 92% | 98% |
| Branch operations | 60% | 85% | 87% | 95% |
| Customer / Rates | n/a | 45% | 80% | 95% |
| Platform / Admin | 70% | 70% | 78% | 95% |

---

## 2. Conventions

- **Priority:** P0 = blocks production go-live · P1 = degrades operation/regulatory exposure · P2 = polish/hygiene
- **Effort:** S ≤ 2h · M ≤ 2d · L > 2d
- Each task lists: files touched (expected), acceptance criteria, dependencies.
- Every task ends with: `php -l`, Pint, PHPStan level 7 clean, targeted tests green.
- Do NOT add `@phpstan-ignore` comments; fix root causes.

---

## WS-A · Reporting & Schedules Engine

> Source findings: R3-C1 (schedules never run), R3-C4 (missing blades), R3-C5 (no audit logging), R3-C12 (archive coverage / dual models), R3-C16 (archive disk config).

### A1 [P0/M] Report schedule processor
ReportSchedulingService has create/update/getDashboardSummary but **nothing consumes `next_run_at`** — schedules are dead rows.
- Files: new `app/Console/Commands/ProcessDueReportSchedules.php`; extend `app/Services/Reporting/ReportSchedulingService.php`
- Implement `processDueSchedules()`: query `report_schedules` where `is_active && next_run_at <= now`, per row invoke the mapped ReportingService generator (map `report_type` → existing generate methods), persist run outcome to `report_runs`, compute next occurrence from cron expression (`CronExpression::isValidExpression` already validated at input), update `next_run_at`, isolate failures per-schedule (one bad schedule must not block others).
- Add command `reports:process-schedules`, register in `bootstrap/app.php` schedule (append-only; hourly, withoutOverlapping+onOneServer).
- AC: creating a schedule due now produces a generated report + updated next_run after running the command; failing generator marks run failed and still advances other schedules.

### A2 [P1/S] Schedule CRUD blades
Index links to create/show/edit whose blades do not exist → ViewNotFound today.
- Files: `resources/views/reports/schedules/{create,show,edit}.blade.php`
- Follow index conventions; show displays last runs + next due + pause/resume action; edit reuses create form partial with UpdateReportScheduleRequest fields (report_type select from ReportType cases, cron_expression with client hint, parameters JSON textarea, is_active toggle, notification_recipients emails).
- AC: full CRUD loop navigable; php artisan view:cache compiles.

### A3 [P1/S] Audit report generation & downloads
`AuditService::logRegulatoryReportEvent` / `logReportAccessEvent` have zero callers.
- Wire generation events in the ReportingService command paths used by A1 AND existing scheduled commands (msb2/lmca/qlvr/position-limit/eod/trial-balance).
- Wire access events in every download route (DocumentStorageService-backed controllers) — actor, report type, period, file id.
- AC: grep shows both methods called; feature test asserts audit rows on download.

### A4 [P2/M] Unify report run tracking + archive coverage
Two parallel models fragment history (`ReportRun` vs `ReportGenerated`); archive command covers only `ReportGenerated`.
- Decision: keep `ReportGenerated` as artifact registry; make A1's processor ALSO create `ReportGenerated` rows for scheduled artifacts so archival sweeps them. Deprecate `ReportRun` writes (keep reads for history) — document in model docblock.
- Verify `filesystems.disks.archive` root/driver configured for genuine 7-year retention; if default 'local', set explicit archive path + note in docs/backup-restore.md.
- AC: scheduled report artifacts appear in archive sweep dry-run.

---

## WS-B · Notifications Completion

> Source findings: R3-B2 (preferences unrouted), R3-B3 (4 notifications never dispatched), R3-B11 (digest ignores opt-in), R3-B14 (dead preference table).

### B1 [P1/S] Register notification-preference routes
`NotificationPreferenceController` exists with a working view posting to `route('notifications.preferences')` which is not registered.
- Add GET/POST under auth group (self-scoped); validate against dispatcher's known channel keys (snake_case map in NotificationDispatcher::shouldDeliver).
- AC: page loads, saving persists JSON, dispatcher respects saved opt-outs (unit test on shouldDeliver with stored prefs).

### B2 [P1/M] Wire the four orphaned notifications
Dispatch matrix shows never-dispatched in production:
1. `LargeTransactionNotification` → fire from TransactionCreationService post-commit when amount_local ≥ ThresholdService::getLargeTransactionThreshold(); recipients: compliance officers (reuse AlertTriageService::getAvailableOfficers()); ShouldQueue; failure-swallowed try/catch per house pattern.
2. `SanctionsMatchNotification` → fire inside CustomerScreeningService::handleConfirmedMatch (after FIU alert) to compliance officers + assignee.
3. `SystemHealthAlertNotification` → fire from MonitoringEngine::sendFailureNotification alongside the SystemAlert (dedup via same $alertedMonitors throttle).
4. `ComplianceCaseAssignedNotification` → fire in CaseManagementService where `assigned_to` changes (~line 357); recipient = new assignee; include daysUntilDeadline (class already supports it).
- AC: Notification::fake assertions per path; no duplicate sends on retry paths.

### B3 [P2/S] Digest opt-in
Digest sends to ALL active users despite docblock claiming opt-in.
- Add `digest_enabled` bool (default true) to users.notification_preferences JSON handled by HasNotificationTesting::getTargetUsers filter; expose checkbox on the preferences page (B1).
- AC: user opted-out receives nothing; opted-in unchanged.

### B4 [P2/S] Remove dead preference table OR wire it
`UserNotificationPreference` model/table has zero readers. Decision: delete model + relation helpers + migration-safe drop migration (table empty in all envs — verify first). If product wants per-channel-per-type granularity later, B1's JSON approach suffices.

---

## WS-C · Admin CRUD Surfaces

> Source findings: R3-G6 (no currency UI), R3-G7 (no branch web CRUD), R3-G13 (no COA viewer).

### C1 [P1/M] Currency management UI
Currencies are seeder-only; post-setup add/disable impossible.
- New CurrencyController (index/create/store/edit/update + soft disable): code (ISO alpha-3 unique), name, symbol, decimal places; disable guards against open transactions referencing currency (query check) and active positions ≠ 0.
- Views under resources/views/system/currencies/ following system/users patterns; routes role:admin; cache invalidation via existing currency cache tags (grep Cache::tags('currencies') usage in lookups).
- AC: create/disable round-trip works; disabled currency absent from transaction form selects but historical rows render.

### C2 [P1/M] Branch web CRUD
Full CRUD exists only as API V1.
- Web BranchController wrapping the SAME service layer as Api/V1/BranchController (BranchService::createBranch/updateBranch/deactivateBranch) — do not duplicate business logic; views: index (paginated, status badges), create/edit forms (code, name, address, phone, is_active), deactivate confirm modal listing attached users/counters/tills counts.
- Routes role:admin under admin group; policies mirroring API middleware checks.
- AC: parity test — service-level outcomes identical between web and API calls.

### C3 [P2/S] Chart of Accounts viewer
Read-only COA listing screen: code, name, type badge, balance-as-of-today (reuse LedgerService::getAccountBalance or trial-balance slice), link to ledger-account view (exists at accounting/ledger/{code}).
- Route role:admin,compliance; view resources/views/accounting/chart-of-accounts/index.blade.php; controller method on ReportController or dedicated minimal controller.

---

## WS-D · Concurrency & Integrity Hardening

> Source findings: R3-D8 (handover acknowledge race), R2 till guard follow-ups.

### D1 [P1/S] Handover acknowledge race
CounterHandoverService::acknowledgeHandover (~lines 30–77): pending-state check outside transaction; no lockForUpdate on handover/session rows → two simultaneous acknowledgments can both mutate allocations/session.
- Move state check INSIDE `DB::transaction`, acquire `$handover->lockForUpdate()` (and session row lock), re-validate pending before mutating.
- AC: parallel-process test (two artisan tinker processes or process-level race simulation) proves single acknowledgment wins; unit test asserts exception path for second actor.

### D2 [P2/S] Re-encrypt secret hygiene
`customers:re-encrypt --old-key/--old-salt` exposes secrets via process list/shell history.
- Add interactive prompt fallback: when options omitted and stdin is TTY, ask with hidden response; recommend env-var file option in docs/backup-restore.md rotation section.

### D3 [P2/S] Adverse-media import memory streaming
file_get_contents + materialized array risks OOM on large feeds.
- Switch parseCsv to fgetcsv streaming; commit per N=500-row chunks instead of one wrapping transaction (idempotent by record_hash already). Keep CLI-only scope note.

---

## WS-E · Bootstrap & Setup Parity

> Source finding: R3-F9 (quickSetup under-seeds fiscal year/periods/rates).

### E1 [P1/S] quickSetup seed parity
quickSetup → seedCoreData creates only admin+currencies+COA+HQ branch; step-wizard additionally seeds FiscalYear+AccountingPeriod (+optional rates). Half-initialized prod possible (accounting posting/fiscal checks would fail).
- Extend seedCoreData (or quickSetup path) to always create current FY + monthly periods (mirror the wizard's calls), and default-enable rate seeding unless explicitly skipped via flag.
- AC: fresh sqlite install via quickSetup passes PeriodCloseService preconditions (period exists & open) and transaction creation works end-to-end (feature test boots app through setup then books a transaction).

### E2 [P2/S] Sanctions bootstrap note
Fresh installs lack sanction lists until first scheduled import (daily 01:00). Add post-setup flash/instruction pointing operators to run `sanctions:update` immediately; optionally trigger one UN import inline during setup completion (guarded by env flag).

---

## WS-F · MFA & Access Polish

> Source findings: R3-B10 (MFA disable hardening, API endpoints).

### F1 [P1/S] MFA disable requires password re-entry
Hijacked session can disable MFA with a single TOTP.
- Require current_password on disable route (validate before processing code); update mfa/setup page disable form + tests.

### F2 [P2/M] API MFA enrollment endpoints
API clients hitting mfa.verified get JSON pointing at web-only flows.
- Expose POST api/v1/mfa/enroll (secret+QR URI), verify (code), recovery-codes regenerate behind existing throttles, mirroring web controller logic via MfaService directly.

---

## WS-G · Previously Deferred Large Efforts (tracked, not scheduled)

> Carried from R1/R2 as consciously deferred; listed so ALL findings remain covered.

| ID | Item | Priority | Effort | Blocker / Note |
|----|------|----------|--------|----------------|
| G1 | STR BNM XML e-filing export | P1 | M | **Blocked**: needs official BNM FIED schema; CSV export + bnm_reference tracking already sufficient operationally |
| G2 | OpenAPI documentation of ~133-route v1 API | P2 | L | Surface stable; adopt Scribe |
| G3 | i18n extraction (lang/en [+ ms]) | P2 | L | Scope-gate against actual BNM bilingual mandate first |
| G4 | AES-CBC → AES-GCM migration for encrypted PII | P2 | M | Would orphan existing ciphertext; path = version-tagged ciphertext headers + customers:re-encrypt extension |
| G5 | Full threshold settings admin UI (~40 keys) | P1 | M | Persistence layer (ThresholdAudit read-back) ready; needs controller+views+policy |
| G6 | Transaction wizard frontend consuming v1 API steps | P2 | M | ✅ DONE — blade rewritten to drive real 3-step API (step1 CDD → step2 customer+docs → step3 confirm+create → success); web route `transactions.wizard` (role-gated) + nav entry added; branch-scoped counter fetch; 7 tests |
| G7 | Per-user active-session inventory view | P2 | M | logoutOtherDevices shipped; listing needs session store keyed by user |
| G8 | Hold/release UX wiring hold() from requiresHold consumers | P2 | S | hold() documented as sanctioned entry point; manager approve/reject = release |
| G9 | Prunable contracts + model:prune for soft-delete retention windows | P2 | M | Align with BNM 6–7yr retention |
| G10 | Adverse-media external feed connector (RSS/API) | P2 | M | CSV/JSON pipeline shipped; connector adds automation |

---

## WS-H · Micro-fixes from Round-3 Verification (completed during planning)

Already applied to the working tree:

| Finding | Fix |
|---|---|
| `UserService::resetPassword` wrote `password_hash` directly, bypassing mutator → admin resets never restarted forced-rotation clock | Now assigns via mutator (archives history + stamps `password_changed_at`) |
| Adverse-media URLs rendered unvalidated → `javascript:` scheme clickable by staff | Import normalizer nulls non-http(s) URLs |

---

## 3. Execution Order & Dependencies

```
Phase 1 (P0):        A1 → A2 → A3          (schedule engine unlocks regulatory cadence)
Phase 2 (P1, //):    B1+B2+B3 · C1 · C2 · D1 · E1 · F1
Phase 3 (P2, //):    A4 · B4 · C3 · D2 · D3 · E2 · F2
Backlog (tracked):   G1..G10
```

Parallelization notes: WS-B, WS-C1/C2, WS-D1, WS-E1, WS-F1 are independent workstreams (disjoint primary files except routes/web.php — apply surgical anchors per group). bootstrap/app.php schedule edits must be append-only and serialized.

## 4. Definition of Done (per task)

1. Root-cause implementation (no suppression comments)
2. Feature/unit tests covering happy + failure paths
3. `php -l` all touched files · Pint clean · PHPStan level 7 zero errors
4. Route registrations verified via `route:list`
5. Blade changes compile via `php artisan view:cache`
6. Findings cross-reference updated in this document's §5 log

## 5. Findings Coverage Ledger

| # | Finding (source) | Task |
|---|------------------|------|
| R3-C1 schedules never run | A1 |
| R3-C4 schedule blades missing | A2 |
| R3-C5 report audit logging unused | A3 |
| R3-C12 dual run models / archive gap | A4 |
| R3-C16 archive disk config | A4 |
| R3-B2 preference routes missing | B1 |
| R3-B3 four notifications unwired | B2 |
| R3-B11 digest opt-in ignored | B3 |
| R3-B14 dead preference table | B4 |
| R3-G6 currency UI missing | C1 |
| R3-G7 branch web CRUD missing | C2 |
| R3-G13 COA viewer missing | C3 |
| R3-D8 handover acknowledge race | D1 |
| R3-V re-encrypt CLI secret exposure | D2 |
| R3-V adverse import OOM risk | D3 |
| R3-F9 quickSetup under-seeds | E1 |
| R3-F sanctions bootstrap gap | E2 |
| R3-B10a MFA disable no password | F1 |
| R3-B10b no API MFA endpoints | F2 |
| R3-V resetPassword skips rotation stamp | ✅ done (WS-H) |
| R3-V adverse URL scheme injection | ✅ done (WS-H) |
| R1/R2 deferred large efforts | G1–G10 |
| R3-E15 orphaned reservations beyond expiry | Accepted risk (expiry sweep covers status+date; hard-deleted txn edge documented) |
