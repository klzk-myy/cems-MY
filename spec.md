# CEMS-MY Implementation Specification

## Document status

- **Repository:** `cems-MY` / `cems-my`
- **Application:** Laravel 12 currency-exchange and compliance management system.
- **Last verified against code:** 2026-09-11.
- **Purpose:** This specification describes the implemented system, not only the original product brief. It is a living reference: each exploration pass updates this file before the next pass begins.
- **Evidence standard:** Statements are based on repository files, routes, tests, configuration, and GitNexus analysis. **Confirmed** means implementation evidence is present; **to verify** is an explicit exploration target, not an assumption.

## 1. Scope and system boundary

CEMS-MY is a single-schema Laravel monolith for currency-exchange transactions, customer due diligence, compliance screening and investigations, branch/counter/teller operations, double-entry accounting, foreign-exchange positions, reporting, and operational administration.

The implemented application exposes:

- A browser-facing web application using Blade, Tailwind CSS v4, Alpine.js, and Livewire assets.
- A Sanctum-protected API v1 surface under `api/v1`.
- Authenticated web routes, API routes, signed EDD customer routes, health routes, webhooks, channels, and console commands.
- Redis-backed cache, queues, Horizon monitoring, rate limiting, and operational recovery tooling.
- A strict HTTP simulation harness that exercises web and API v1 surfaces and compares derived database state.

The application is not sharded. All persistent state is held in one database schema. The retired migration directory is not the schema source of truth; `database/seeders/SchemaSeeder.php` is the destructive, guarded schema seeder used by setup, test reset, and fresh-install paths.

## 2. Verified technical baseline

### Runtime and framework

- PHP 8.3.30 in the project environment.
- Laravel framework v12.
- Laravel Horizon v5, Sanctum v4, Larastan v3, Dusk v8, Pint v1, Sail v1, and PHPUnit v11.
- Alpine.js v3 and Tailwind CSS v4 for the browser application.
- Composer dependencies are declared in `/www/wwwroot/local.host/composer.json`; frontend dependencies are declared in `/www/wwwroot/local.host/package.json`.

### Application structure

The implementation is organized under the standard Laravel application directories:

- `app/Actions/`, `app/Console/Commands/`, `app/DTO/`, `app/Enums/`, `app/Events/`, `app/Exceptions/`, and `app/Helpers/`.
- `app/Http/Controllers/`, `app/Http/Requests/`, `app/Http/Resources/`, `app/Http/Middleware/`, and related HTTP concerns/traits.
- `app/Jobs/`, `app/Listeners/`, `app/Notifications/`, and `app/Policies/`.
- `app/Models/`, repositories, services, rules, value objects, casts, providers, and view components.
- `routes/`, `config/`, `database/`, `resources/`, `tests/`, and `bootstrap/`.

Laravel 12 bootstrap configuration is in `/www/wwwroot/local.host/bootstrap/app.php`. It configures middleware aliases and stacks, routing files, event discovery, exceptions, and the scheduler.

### Database and schema

- The schema is represented by `/www/wwwroot/local.host/database/seeders/SchemaSeeder.php`.
- The schema seeder is destructive and guarded; it must only be used through approved setup/reset/test paths.
- The repository contains model relationships, factories, seeders, and feature/unit tests that exercise the schema and business workflows.
- The exact table, column, relationship, enum, and reference-data inventory is documented in the database exploration pass.
- The transaction snapshot fields `prev_quantity` and `prev_average_cost`, plus the alert uniqueness constraint, are part of the implemented schema and should be preserved in documentation and future edits.

### Cache, queues, and Redis

- Laravel cache, queue, Horizon, and rate-limit configuration is under `/www/wwwroot/local.host/config/`.
- Redis is used for cache/queues/Horizon and operational health/monitoring.
- Prefix, TTL, atomicity, fail-closed behavior, lock namespace, and DB/Redis write-order rules from the project guidelines apply to future Redis changes.
- Existing Redis consumers and their exact guarantees are documented in the infrastructure exploration pass.

## 3. HTTP surfaces and routing

### Web application

`routes/web.php` defines the browser application. Confirmed route groups and capabilities already visible in the implementation include:

- Home, health, setup, authentication, MFA, users, customers, transactions, transaction batches, and transaction exports.
- Branches, counters, allocations, till balances, stock/cash, and stock transfers.
- Accounting journals, chart of accounts, periods, fiscal years, budgets, reconciliation, revaluation, and reports.
- Compliance dashboards, unified alerts, screening, matches, findings, cases, EDD, PEP approvals, sanction lists, and STR/regulatory reporting.
- Audit logs, notifications, preferences, performance monitoring, test results, DLQ administration, and system alerts.
- Signed EDD customer document upload/download flows.

The exact route names, methods, middleware, authorization roles, and controller actions are documented in the core and domain passes below.

### API v1

`routes/api_v1.php` defines a Sanctum-authenticated API v1 surface. Confirmed API areas include:

- Current user and MFA lifecycle.
- Transactions, approvals, cancellations, confirmation, and transaction wizard operations.
- Customers, branches, counters, teller allocations, emergency counter operations, and handovers.
- Branch closing, month-end close, accounting, rates, reports, compliance dashboards, alerts, cases, findings, EDD, risk, sanctions, and screening.

The API uses branch scope and role/MFA middleware where applicable. Exact endpoint contracts and response behavior are documented during the domain passes.

### Other HTTP surfaces

- `routes/auth.php` supplies authentication-related routes.
- `routes/webhooks.php` supplies webhook endpoints.
- `routes/channels.php` supplies broadcast channel authorization.
- `routes/console.php` supplies console command definitions.
- Health and diagnostic routes are present in the web route file.

## 4. Implementation addendum

This section documents implemented behavior that is already present in the codebase but was not fully reflected in the earlier specification. It is written as a bounded specification of current behavior, not as new requirements.

### 4.1 Transaction creation, approval, and accounting timing

Transaction booking is coordinated by `TransactionCreationService::prepareAndCreate()` and related services in `app/Services/Transaction/`. Creation performs preflight validation before booking, including currency validation, IP checks, till-balance validation, customer branch-scope checks, KYC expiry checks, PEP checks, compliance screening, and AML/structuring rules. Bookings are routed through `TransactionAccountingService` so that journal creation is aligned with transaction state transitions rather than being an unrelated side effect.

Approval behavior is centralized in `TransactionApprovalService` and dependent transaction services. Approval can update status, set approval metadata, release or reserve accounting effects, and trigger downstream event listeners. The intended order of operations is to validate first, mutate business state second, and emit derived events last. Where implementation code shows accounting or position changes being applied during the transition, that behavior is part of the implemented lifecycle rather than a detached accounting step.

The implemented API surface includes the confirmation endpoint for large transactions, and the test-only `test.query-log` route is not part of the runtime route surface.

`TransactionAccountingService` builds journal entries for transactions and records the resulting `journal_entry_id` and timestamps back on the transaction row. The implementation is intended to keep the transaction record, journal entry, and derived compliance/alert side effects consistent within the same workflow path.

### 4.2 Positions, teller allocations, and branch funds

`CurrencyPositionService` manages positions and uses locking behavior through `CurrencyPositionLockService`. Position updates should be treated as part of the transaction workflow and should occur under the same concurrency discipline as the transaction state transition.

`TellerAllocationService` maintains teller allocation balances and exposes transaction allocation helpers such as `applyTransactionAllocation()` and reversal paths. The service is expected to keep per-teller balances synchronized with branch pool activity and to avoid allowing allocations against balances that are not yet available.

Branch and pool services keep the allocation hierarchy coherent: pool balances feed branch allocations, branch allocations feed tellers, and tellers feed tills. This hierarchy is part of the supported operating model, so branch/till operations must preserve balance consistency across those layers.

### 4.3 Till balances, counter sessions, and branch closing

`TillBalanceManager` opens, closes, adjusts, and reconciles till balances. In the implemented codebase, it can reject negative closings and enforce balance sanity when applying transaction effects to tills. Till balance behavior is coupled to counter sessions and branch closing flows.

`CounterService` manages counter session open/close lifecycle, including variance checks and close-session updates. Counter operations are guarded by session and balance consistency checks, so the close path should not assume that a till can be closed with unreconciled physical counts.

`BranchClosingService` coordinates end-of-business close workflows. It checks that branches are ready to close, finalizes workflows when required conditions are met, and settles branch-related balances. The service is intentionally conservative: it should only finalize when outstanding allocations, transfers, or document workflows no longer block the close.


### 4.4 Compliance, alert triage, and case management

`ComplianceService` performs screening and risk-oriented checks used by the transaction workflow, including sanction checks and hold decisions. In the implemented code, it reuses recent screening results when appropriate and raises holds when compliance rules require review.

`AlertTriageService` turns flagged transactions into alerts, computes risk scores, and assigns or auto-assigns alerts to available officers. Alert state is expected to flow from detection through prioritization and assignment.

`CaseManagementService` creates cases from findings or manually, manages assignment, notes, and status transitions, and handles closure with linked-alert validation. The implemented behavior includes enforcement that a case cannot be closed while unresolved alerts remain linked. When a case is closed, the service may draft downstream regulatory reporting artifacts such as STR-related outputs where the implementation supports them.

### 4.5 Audit logging and tamper-evident chain verification

`AuditService` is the canonical audit entry point for most domain actions. It provides severity-aware logging, hash generation, chain sealing, and integrity verification. The implementation distinguishes sealed entries from non-sealed entries and exposes verification that traverses the hash chain.

`AuditTrailHelper` provides higher-level convenience helpers for common transaction/domain audit actions. It should be used where the calling code needs concise audit recording, but it should not duplicate the hash and sealing logic that belongs in `AuditService`.

Chain verification is expected to fail closed on hash mismatch or missing links. In the current implementation, verification scans stored logs in id order, computes expected hashes, and reports mismatches rather than assuming integrity by default.

### 4.6 Notifications and notification jobs

`NotificationDispatcher` is the safe wrapper for enqueuing notification jobs. It accepts users, iterable recipients, anonymous notifiables, or strings, applies user notification preferences where relevant, and fails closed for malformed recipients rather than escaping an exception into the caller’s workflow.

`SendNotificationJob` is the queued job that actually sends notifications. It is marked unique and queued, supports custom channel overrides, and logs failures on permanent job failure. Notification delivery is asynchronous from the business workflow; business operations should not rely on synchronous notification completion for correctness.

`NotificationBadgeService` summarizes unread notifications and DLQ counts for the UI. It maps notification data into display-ready structures and exposes counts that the frontend can poll.

### 4.7 Setup flow and setup guards

`SetupService` manages setup state, seeding, fiscal-period preparation, and setup completion markers. The important implemented behavior is that setup completion is tracked through a persisted marker rather than only inferred from data presence.

`SetupController` drives the interactive setup flow and marks setup complete inside a guarded path. The controller is expected to avoid reopening setup for already-initialized installs and to preserve the intended setup lifecycle.

`EnsureSetupAccessible` is the middleware guard that blocks setup routes once setup is complete, with a deliberate carve-out for admin-controlled reset paths. This guard should be treated as part of the setup security boundary, not as a convenience layer.

### 4.8 MFA, security headers, IP blocking, and rate limiting

`MfaService` handles TOTP secret generation, recovery codes, trusted-device state, global MFA enablement, role-based MFA requirements, and failed-attempt counters. The implemented service uses encrypted TOTP secrets, hashed device tokens, and a bounded recovery-code model.

`EnsureMfaVerified` enforces MFA verification for protected routes, including role-based enforcement, session checks, trusted-device bypass, and JSON-oriented error responses for API clients. This middleware is part of the authentication boundary for protected actions.

`SecurityHeaders` applies response hardening headers, including CSP and HSTS behavior. It is intended to be present on web responses and should not be bypassed by individual route handlers.

`IpBlocker` blocks requests from blocked IPs early in the request lifecycle. `StrictRateLimit` enforces limiter-based throttling, burst handling, and retry headers. The rate-limit behavior is intended to fail closed for the primary limiter and only fail open for the burst-smoothing sub-check, consistent with the project’s Redis fail-closed guidance.

## 5. Verification expectations for future edits

When editing the implemented behavior covered above, future changes should preserve the following verification expectations:

- Transaction and accounting changes should be validated with targeted tests for booking, approval, reversal, and journal creation.
- Branch, till, allocation, and closing changes should be validated with branch-scoped workflow tests and balance-reconciliation tests.
- Compliance, audit, setup, and security changes should be validated with role-based and middleware-level tests, not only with service-only unit checks.
- Documentation-only updates should keep `spec.md` as the only modified file.
