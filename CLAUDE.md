# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

CEMS-MY is a Laravel 12.x Currency Exchange Management System for Malaysian Money Services Businesses (MSB), compliant with Bank Negara Malaysia (BNM) AML/CFT requirements. It handles foreign currency trading, till management, compliance reporting, and double-entry accounting.

## Local Development Notes

### Redis
Local Redis runs **with authentication**: `REDIS_PASSWORD=redpass` in `.env`, mirrored by `phpunit.xml` (`<env name="REDIS_PASSWORD" value="redpass" force="true"/>`). Keep the two in sync — `phpunit.xml`'s comment states the local Redis requires the auth password from `.env`.

Do not change the local password without updating both files. Staging and production must configure Redis `requirepass` with a strong `REDIS_PASSWORD`.

Note: `.env.example` still documents the older no-auth setup (`REDIS_PASSWORD=` empty) — that file is stale relative to the current local config.

### Laravel Boost MCP
The project includes Laravel Boost as an MCP server. Start it with:

```bash
php artisan boost:mcp
```

If the MCP client reports error **32603** (Internal Error), check `storage/logs/laravel.log`. The most common cause is a `REDIS_PASSWORD` mismatch: Laravel's AUTH must match the password local Redis is configured with (`redpass` in `.env`).

## Common Commands

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --filter=TransactionWorkflowTest

# Run a single test class
php artisan test --filter=MathServiceTest

# Lint (PSR-12 via Laravel Pint)
./vendor/bin/pint

# Clear caches
php artisan config:clear && php artisan route:clear && php artisan view:clear

# List routes
php artisan route:list

# Run a specific Artisan command
php artisan report:msb2 --date=2026-04-06
```

## Architecture

### Layer Structure

```
app/
├── Console/Commands/  # 56 Artisan commands
├── Enums/  # 64 PHP 8.3 enums
├── Events/  # 13 Event classes (TransactionCreated, CounterSessionOpened, etc.)
├── Exceptions/Domain/  # 71 typed domain exceptions (InsufficientStockException, etc.)
├── Http/
│   ├── Controllers/  # 88 controllers (59 web + 29 API)
│   ├── Middleware/  # 19 middleware classes
│   ├── Requests/  # Form request validation classes
│   └── Resources/  # 12 API resource transformers
├── Jobs/  # 15 background jobs (7 root + 6 Compliance + 1 Accounting + 1 Audit)
├── Models/  # 92 Eloquent models
├── Policies/  # 15 authorization policies
└── Services/  # 154 services
```

There is no `app/Observers/` directory — model event hooks are handled via listeners and service calls, not observers.

### Key Architectural Patterns

**1. Enum-Based RBAC**
All role checks use PHP enums in `App\Enums\`:
- `UserRole::Teller`, `UserRole::Manager`, `UserRole::ComplianceOfficer`, `UserRole::Admin`
- Permission methods on enums: `$role->canApproveLargeTransactions()`, `$role->canViewReports()`
- All status/type enums organized by domain (Transaction, Customer, Session, Compliance, Accounting, Alert)
- Models return enum instances, not strings

**2. Service Layer**
Controllers inject services via constructor dependency injection (no `app()` service locator):
```php
public function __construct(
    protected CurrencyPositionService $positionService,
    protected ComplianceService $complianceService,
) {}
```

**3. Double-Entry Accounting**
- `AccountingService` creates journal entries for every transaction
- `LedgerService` maintains running balances
- `RevaluationService` handles monthly currency revaluation
- Account codes use `AccountCode` enum (e.g., `AccountCode::CASH_MYR->value`)

**4. BCMath Precision**
All monetary calculations use `App\Services\MathService` (BCMath), not floats. Never cast money values to `float`.

**5. Compliance Workflow**

- Transactions ≥ RM 10,000 (auto_approve threshold) require manager approval via `PendingApproval` status
- Transactions ≥ RM 50,000 OR high-risk customers go to `Pending` status (compliance hold)
- `ComplianceService` runs CDD determination
- `TransactionMonitoringService` runs automated compliance monitors via background jobs:
  - `VelocityMonitor` - Detects velocity/structuring patterns (7-day lookback)
  - `StructuringMonitor` - Transaction aggregation detection
  - `SanctionsRescreeningMonitor` - Monthly rescreening of all customers
  - `CustomerLocationAnomalyMonitor` - Geographic anomaly detection
  - `CurrencyFlowMonitor` - Currency flow pattern analysis
  - `CounterfeitAlertMonitor` - Counterfeit currency detection
- `AlertTriageService` triages and assigns compliance alerts
- `CustomerRiskScoringService` calculates customer risk scores with lock/unlock capability
- All cancellations require manager approval via `PendingCancellation` status (segregation of duties)

**6. Stock Reservation (Concurrency Control)**
- `StockReservation` model reserves stock when `PendingApproval` transaction is created
- `CurrencyPositionService::getAvailableBalance()` returns balance minus pending reservations
- `consumeStockReservation()` called at approval time — fails if stock no longer available
- `releaseStockReservation()` releases reservation on cancel/expire
- `reservation:expire` command releases stale reservations (24-hour expiry)

**7. Domain Exceptions**
- Business rule violations use typed exceptions in `app/Exceptions/Domain/`:
  - `InsufficientStockException` - Sell with insufficient foreign currency
  - `StockReservationExpiredException` - Reservation not found at approval
  - `TillAlreadyOpenException` / `UserAlreadyAtCounterException` - Counter state errors
  - `TillBalanceMissingException` - Required MYR till balance missing
- Generic `throw new Exception` should not be used for business rules

**8. Event-Driven Architecture**
Events fire for critical operations (`TransactionCreated`, `CounterSessionOpened`, etc.) with listeners for audit logging, notifications, and compliance triggers.

**9. Background Processing**
Laravel queues handle async compliance screening, STR report submission, and sanctions rescreening via `App\Jobs\` (15 jobs: 7 root-level + 6 Compliance + 1 Accounting + 1 Audit). Laravel Horizon provides a dashboard to monitor queue jobs, failures, and throughput (`php artisan horizon`).

**10. Role Hierarchy**
Permissions inherit upward: `Admin` > `ComplianceOfficer` > `Manager` > `Teller`.
- Managers can approve large transactions but not configure system settings
- Compliance Officers handle AML workflows, not daily operations

**11. Security Features**
- MFA required for ALL roles including Tellers (BNM compliance)
  - `EnsureMfaEnabled` forces MFA setup on first login for all roles
  - `EnsureMfaVerified` requires re-verification for sensitive operations (15-min session expiry, trusted device bypass)
  - Scope: MFA is enforced on write/approval operations (transaction creation, approvals, admin functions). Non-sensitive read operations (viewing transactions, customers, counters) do not require MFA.
- IP-based blocking after 10 failed login attempts (5-minute window, 1-hour block duration)
- Strict rate limiting on sensitive endpoints (login: disabled for dev convenience, API: 30/min, transactions: 10/min, STR: 3/min, bulk: 1/5min, export: 5/min, sensitive: 3/min)
- Session timeout (configurable, default 8 hours)
- Audit log with cryptographic hash chaining (tampering protection)
- Password complexity requirements (min 12 chars, mixed case, number, special char, max 5 attempts, 15-min lockout)
- HSTS support (configurable max-age, includeSubDomains, preload)
- IP whitelist support (exact IPs and CIDR notation)
- **Route Security**: `/api/rates/history/{currency}` requires `auth` middleware; `/health` has `throttle:60,1`

### Middleware Stack

Routes use these middleware:
- `auth` - All authenticated users
- `role:manager` / `role:compliance` / `CheckRoleAny` - Role-based access control
- `EnsureMfaEnabled` / `EnsureMfaVerified` - MFA enforcement
- `DataBreachDetection` - Data breach monitoring and alerting
- `StrictRateLimit` - BNM-compliant rate limiting with burst protection
- `IpBlocker` - IP-based blocking after repeated failed attempts
- `SecurityHeaders` - HSTS, CSP, X-Frame-Options, X-Content-Type-Options, etc.
- `session.timeout` - Idle session timeout
- `CheckBranchAccess` - Branch-based access control
- `LogRequests` / `QueryPerformanceMonitor` - Request logging and monitoring

**12. Centralized Threshold Configuration**

All threshold amounts are centralized in `config/thresholds.php` and accessed **only** via `ThresholdService`. Direct calls to `config('thresholds.*')` in services are prohibited — this prevents hardcoded constants from diverging from the centralized source:

```php
// ✅ Correct: use ThresholdService
$threshold = thresholdService()->getAutoApprove();

// ❌ Wrong: hardcoded constant
$threshold = 10000;
```

```php
// config/thresholds.php structure
return [
    'approval' => ['auto_approve' => '10000', 'manager' => '50000'],
    'cdd' => ['specific' => '3000', 'standard' => '10000', 'large_transaction' => '50000'],
    'risk_scoring' => ['high' => '50000', 'medium' => '30000', 'low' => '10000'],
    'alert_triage' => ['critical' => '50000', 'high' => '30000', 'medium' => '10000'],
    'reporting' => ['str' => '50000', 'edd' => '50000'],
    'structuring' => ['sub_threshold' => '3000', 'min_transactions' => 3, 'hourly_window' => 1, 'lookup_days' => 7],
    'duration' => ['warning_hours' => 24, 'critical_hours' => 48],
    'variance' => ['yellow' => '100.00', 'red' => '500.00'],
    'velocity' => ['alert_threshold' => '50000', 'warning_threshold' => '45000', 'window_days' => 90],
    'aml' => ['amount_threshold' => '50000', 'aggregate_threshold' => '50000'],
    'currency_flow' => ['round_trip_threshold' => '5000', 'lookback_days' => 7],
];
```

- All thresholds can be overridden via environment variables
- `ThresholdService` provides type-safe getters with backward-compatible fallbacks
- `ThresholdAudit` model tracks all threshold changes (category, key, old/new value, changed_by, reason)
- Enums use `config()` helper directly since they cannot use dependency injection

**Key Services**

| Service | Purpose |
|---------|---------|
| `AccountingService` | Journal entry creation and reversal |
| `LedgerService` | Trial balance, P&L, balance sheet |
| `RevaluationService` | Monthly currency revaluation |
| `CounterService` | Till/counter lifecycle (open, close, handover) |
| `TransactionService` | Core transaction operations |
| `CurrencyPositionService` | Stock/position management with reservation system |
| `ComplianceService` | CDD determination |
| `TransactionMonitoringService` | Automated compliance monitoring |
| `CustomerRiskScoringService` | Customer risk scoring with lock/unlock |
| `EddService` | Enhanced Due Diligence workflow |
| `CaseManagementService` | Compliance case management |
| `MathService` | BCMath precision calculations |
| `AuditService` | Audit log with async hash sealing |
| `ThresholdService` | Centralized threshold access with audit logging |
| `RateApiService` | External rate API fetching with spread calculation |
| `RateManagementService` | Daily rate workflow (fetch, copy, override, validate) |

**13. Rate Management**

Daily rate workflow before counter opening:
- `RateApiService::fetchLatestRates()` - Fetches from external API, stores in `exchange_rates` table
- `RateManagementService::fetchAndStoreRates()` - Fetch and persist rates
- `RateManagementService::overrideRate()` - Manager manual override with audit logging
- `RateManagementService::validateTransactionRate()` - Validate teller-submitted rate vs market
- `RateManagementService::areAllRatesSet()` - Check if rates are ready for opening

Rate configuration via `config/thresholds.php`:
```php
'rates' => [
    'spread' => env('RATE_SPREAD', '0.02'),           // 2% spread
    'max_deviation_percent' => env('RATE_MAX_DEVIATION', '0.05'), // 5% max deviation
    'precision' => env('RATE_PRECISION', 4),          // 4 decimal places
    'cache_duration' => env('RATE_CACHE_DURATION', 60),
],
```

**Rate API Endpoints** (`routes/api_v1.php`):
- `GET /api/v1/rates` - List current rates
- `GET /api/v1/rates/summary` - Rates with spread calculation
- `POST /api/v1/rates/fetch` - Fetch from external API (Manager+)
- `POST /api/v1/rates/copy-previous` - Copy previous day's rates (Manager+)
- `PUT /api/v1/rates/{currency}` - Manual override (Manager+)
- `GET /api/v1/rates/check` - Check if all required rates are set

### Counter Management

Counters (tills) with full lifecycle:
- `/counters/{counter}/open` - Open counter with opening floats
- `/counters/{counter}/close` - Close counter with closing floats
- `/counters/{counter}/handover` - Transfer custody between users
- `/counters/{counter}/status` - Real-time counter status

**EOD Reconciliation** (`EodReconciliationService`):
- `GET /api/v1/eod/reconciliation/{date}` - Daily reconciliation summary
- `GET /api/v1/eod/reconciliation/{date}/counters/{counterId}` - Counter-specific reconciliation
- Artisan command: `php artisan report:eod --date=YYYY-MM-DD`

### Compliance & AML

**CDD Levels** (`CddLevel` enum, per pd-00.md 14C.12):

- `Simplified` - Transaction < RM 3,000
- `Specific` - RM 3,000 to RM 9,999
- `Standard` - ≥ RM 10,000 (full documentation per 14C.12.2)
- `Enhanced` - Risk-based: PEP, Sanction match, High risk customer, or large transaction ≥ RM 50,000 (per 14C.13)

`CddLevel::determine()` accepts `RiskRating|string` union type internally. Use `RiskRating::High` or the string `'High'` interchangeably.

**Structuring Detection**: 7-day lookback for aggregate transactions (configurable)

### Report Generation

BNM compliance reports via Artisan commands:
- `report:msb2` - Daily transaction summary
- `report:lmca` - Monthly LMCA
- `report:qlvr` - Quarterly Large Value
- `compliance:rescreen` - Monthly sanctions rescreening

### Test Organization

Tests use `RefreshDatabase` trait and are in `tests/Feature/` and `tests/Unit/`. Key test files:
- `tests/Feature/TransactionAccountingVerificationTest.php` - Verifies 60 transactions (20/branch × 3 branches) with accounting and ledger balance validation
- `tests/Feature/TransactionWorkflowTest.php` - Transaction creation, approval, cancellation
- `tests/Feature/TransactionTest.php` - Transaction web controller tests
- `tests/Feature/RealWorldTransactionWorkflowTest.php` - End-to-end transaction scenarios
- `tests/Feature/RouteConsistencyTest.php` - Route/role access verification
- `tests/Feature/AccountingWorkflowTest.php` - Journal entries, periods, closing
- `tests/Feature/StrWorkflowTest.php` - STR creation and workflow
- `tests/Feature/CounterHandoverTest.php` - Till custody transfer
- `tests/Feature/EddWorkflowTest.php` - EDD workflow tests
- `tests/Feature/FiscalYearControllerTest.php` - Fiscal year creation, closing, opening
- `tests/Feature/FinancialStatementControllerTest.php` - Trial balance, P&L, balance sheet, cash flow, ratios
- `tests/Feature/JournalEntryWorkflowTest.php` - Journal draft → pending → posted workflow
- `tests/Unit/AmlRuleTest.php` - AML rule engine
- `tests/Unit/MathServiceTest.php` - BCMath precision
- `tests/Unit/CurrencyPositionServiceTest.php` - Stock/position calculations, reservations
- `tests/Unit/AuditServiceTest.php` - Hash chaining verification (`verifyChainIntegrity`)
- `tests/Unit/FinancialRatioServiceTest.php` - Liquidity, profitability, leverage, efficiency ratios
- `tests/Unit/TransactionServiceTest.php` - Transaction service unit tests
- `tests/Unit/CustomerBlindIndexTest.php` - Customer blind indexing tests
- `tests/Unit/CashFlowServiceTest.php` - Cash flow statement generation
- `tests/Unit/RiskRatingServiceTest.php` - Risk scoring (uses real DB, not facade mocks)
- `tests/Unit/ComplianceServiceTest.php` - CDD levels, sanctions, velocity, structuring (uses real DB)

## Important Conventions

- **Money**: Always use `MathService` or BCMath functions. Never use PHP `float` for currency.
- **Enums**: All magic strings (statuses, types, roles) should be converted to PHP enums.
- **Audit**: Critical operations must create `SystemLog` entries with hash chaining.
- **Hash Verification**: `AuditService::verifyChainIntegrity()` verifies the tamper-evident chain by recomputing each entry's SHA-256 hash and checking the `previous_hash` chain link. Returns `{valid: bool, broken_at: int|null, message: string}`. Call this method to detect any tampering with audit log entries.
- **Async Hash Sealing**: `AuditService::logWithSeverity()` creates entries with `hash` and `previous_hash` set to null; `SealAuditHashJob` seals the chain asynchronously via Laravel queue. This avoids global DB lock contention when high-throughput operations (e.g., bulk transactions) all try to update the chain simultaneously. Unsealed entries can be monitored via `getUnsealedCount()`.
- **RBAC**: Check permissions via enum methods, not string comparison.
- **Services over Controllers**: Business logic belongs in services, not controllers.
- **Encryption**: Use `EncryptionService` with random IV per encryption (IV prepended to ciphertext).
- **Blind Indexing**: Customer `id_number` uses HMAC-SHA256 blind index (`Customer::findByIdNumber()`) for exact-match KYC search without decrypting PII.
- **Async Audit Hashing**: `AuditService::logWithSeverity()` creates entries with null hash; `SealAuditHashJob` seals the chain asynchronously via queue to avoid global DB lock contention.
- **File Uploads**: Sanitize filenames with `basename()` or use `Str::uuid()` for naming.
- **Query Parameters**: Use parameterized queries for user-supplied values in LIKE clauses.
- **Cancellation**: ALL transaction cancellations require manager approval via `PendingCancellation` workflow.
- **Concurrency**: Use `lockForUpdate()` for position updates to prevent race conditions.
- **Database Transactions**: Wrap multi-step financial operations in `DB::transaction()` for atomicity (especially transactions ≥ RM 25,000).
- **Thresholds**: All threshold values must be accessed via `ThresholdService` (read from `config/thresholds.php`). No hardcoded threshold constants in services.
- **Schema**: `database/migrations/` is retired. `database/seeders/SchemaSeeder.php` is the schema source of truth — edit it for schema changes, never create migrations.

### Frontend Styling

Tailwind v4 with CSS-based `@theme` configuration — single source of truth for design tokens.

- Design tokens defined in `resources/css/app.css` `@theme` block (colors, typography, radius, spacing)
- `tailwind.config.js` deleted — no dual color sources
- Views use pure Tailwind utilities (no `.card`, `.btn`, `.form-*` component classes)
- Nav classes in `app.css` use CSS variables (`var(--sidebar-*)`)
- Livewire is NOT installed — sidebar and other UI components use Blade templates

**Class mapping reference:**

| Old Class      | Tailwind Equivalent                                                    |
|----------------|-----------------------------------------------------------------------|
| `.card`        | `bg-white border border-[#e5e5e5] rounded-xl`                       |
| `.btn-primary` | `px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]` |
| `.btn-secondary` | `px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5]` |
| `.form-input`  | `w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg` |
| `.badge-success` | `inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700` |

## Task Completion

When a task is implemented, always finish with these steps in order:

1. **Code review** — re-read every file you changed; check for correctness, edge cases, consistency with existing conventions, and leftover debug/scratch artifacts.
2. **Partial tests** — run the tests that cover the code you edited (e.g. `php artisan test --filter="<related>"` or the specific test files), not the whole suite.
3. **Commit and push** — stage the changed files, commit with a message explaining *why* (matching existing commit style), then `git push` to the remote.

<!-- gitnexus:start -->
# GitNexus — Code Intelligence

This project is indexed by GitNexus as **cems-MY**.

> Index stale? Run `node .gitnexus/run.cjs analyze --index-only` from the project root — it auto-selects an available runner. No `.gitnexus/run.cjs` yet? Bootstrap with `npx`, `bunx`, or `pnpm dlx` — e.g. `bunx gitnexus@latest analyze` (npm 11 npx crash; #1939).

## Always Do

- **MUST run impact before editing.** Use `impact({target: "symbolName", direction: "upstream"})` or `node .gitnexus/run.cjs impact "symbolName" --direction upstream --repo .`; report callers, processes, and risk. Never substitute grep for graph analysis.
- **MUST analyze graph changes before committing.** Use `detect_changes({scope: "all"})` (MCP) or `node .gitnexus/run.cjs detect-changes --scope all --repo .` (CLI fallback). `partial: true` or `truncated: true` is not a clean check — a zero means unseen, not unaffected; re-run it. For regression review: `detect_changes({scope: "compare", base_ref: "main"})` or `node .gitnexus/run.cjs detect-changes --scope compare --base-ref "main" --repo .`.
- MUST warn on HIGH/CRITICAL `risk` pre-edit; never use `riskSharedAxes` to waive a HIGH/CRITICAL `risk` warning. Compare File/symbol: MCP File omits axes; Graph-RAG expands File.
- **MUST treat `risk: UNKNOWN` as unresolved, not as low.** An empty caller set is not evidence the symbol is unused — it can also mean the callers are not resolvable by the index (plain-object property access, dynamic dispatch, cross-language calls). `impact` pairs `UNKNOWN` with a `riskNote` saying so. Confirm with a text search before treating the symbol as safe to change or delete; do not proceed on the strength of a zero.
- **MUST use `query({search_query: "concept"})` for concepts/flows, `context({name: "symbolName"})` for a named symbol, or `impact` for blast radius, on read-only callers, dependencies, imports, or execution flow.** Graph first; text search only for empty/`UNKNOWN`/literals.
- For security review, `explain({target: "fileOrSymbol"})` lists taint findings (source→sink flows; needs `analyze --pdg`).

## Never Do

- NEVER edit a function, class, or method before MCP/CLI impact analysis.
- NEVER ignore HIGH or CRITICAL risk warnings from impact analysis, and never read `UNKNOWN` as an all-clear — it means the walk could not answer, which is the one verdict that requires confirming by other means.
- NEVER rename symbols with find-and-replace — use `rename` which understands the call graph.
- NEVER commit before MCP/CLI graph change analysis.

## Resources

| Resource | Use for |
| --- | --- |
| `gitnexus://repo/cems-MY/context` | Codebase overview, check index freshness |
| `gitnexus://repo/cems-MY/clusters` | All functional areas |
| `gitnexus://repo/cems-MY/processes` | All execution flows |
| `gitnexus://repo/cems-MY/process/{name}` | Step-by-step execution trace |

<!-- gitnexus:end -->

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context
This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.3.30
- laravel/framework (LARAVEL) - v12
- laravel/horizon (HORIZON) - v5
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- larastan/larastan (LARASTAN) - v3
- laravel/dusk (DUSK) - v8
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v11
- alpinejs (ALPINEJS) - v3
- tailwindcss (TAILWINDCSS) - v4

## Conventions
- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts
- Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

## Application Structure & Architecture
- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling
- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Replies
- Be concise in your explanations - focus on what's important rather than explaining obvious details.

## Documentation Files
- You must only create documentation files if explicitly requested by the user.

## Database Schema (migration-free)
- The `database/migrations/` directory has been retired. `database/seeders/SchemaSeeder.php` is the single source of truth for the schema (85 tables + reference data).
- Do not create new migrations. Schema changes are made by editing `SchemaSeeder.php` directly.
- `SchemaSeeder` is destructive: it drops every table and recreates them. It must only run against empty databases through the guarded call sites (`business:setup --fresh` with confirmation, `SetupController` pre-setup schema creation, `ResetTestDatabase --fresh`, and the test bootstrap).
- Fresh install: `php artisan db:seed --class=SchemaSeeder`. The seeder also drops the legacy `migrations` bookkeeping table when present.

=== boost rules ===

## Laravel Boost
- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan
- Use the `list-artisan-commands` tool when you need to call an Artisan command to double-check the available parameters.

## URLs
- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging
- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool
- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)
- Boost comes with a powerful `search-docs` tool you should use before any other approaches when dealing with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- The `search-docs` tool is perfect for all Laravel-related packages, including Laravel, Inertia, Livewire, Filament, Tailwind, Pest, Nova, Nightwatch, etc.
- You must use this tool to search for Laravel ecosystem documentation before falling back to other approaches.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries to start. For example: `['rate limiting', 'routing rate limiting', 'routing']`.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax
- You can and should pass multiple queries at once. The most relevant results will be returned first.

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

## PHP

- Always use curly braces for control structures, even if it has one line.

### Constructors
- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

### Type Declarations
- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Comments
- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless there is something very complex going on.

## PHPDoc Blocks
- Add useful array shape type definitions for arrays when appropriate.

## Enums
- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

=== tests rules ===

## Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

## Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Database
- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation
- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources
- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

### Controllers & Validation
- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

### Queues
- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

### Authentication & Authorization
- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

### URL Generation
- When generating links to other pages, prefer named routes and the `route()` function.

### Configuration
- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

### Testing
- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

### Vite Error
- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

## Laravel 12

- Use the `search-docs` tool to get version-specific documentation.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

### Laravel 12 Structure
- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console\Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

### Database
- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models
- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

## Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

## PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should test all of the happy paths, failure paths, and weird paths.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

### Running Tests
- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

=== tailwindcss/core rules ===

## Tailwind CSS

- Use Tailwind CSS classes to style HTML; check and use existing Tailwind conventions within the project before writing your own.
- Offer to extract repeated patterns into components that match the project's conventions (i.e. Blade, JSX, Vue, etc.).
- Think through class placement, order, priority, and defaults. Remove redundant classes, add classes to parent or child carefully to limit repetition, and group elements logically.
- You can use the `search-docs` tool to get exact examples from the official documentation when needed.

### Spacing
- When listing items, use gap utilities for spacing; don't use margins.

<code-snippet name="Valid Flex Gap Spacing Example" lang="html">
    <div class="flex gap-8">
        <div>Superior</div>
        <div>Michigan</div>
        <div>Erie</div>
    </div>
</code-snippet>

### Dark Mode
- If existing pages and components support dark mode, new pages and components must support dark mode in a similar way, typically using `dark:`.

=== tailwindcss/v4 rules ===

## Tailwind CSS 4

- Always use Tailwind CSS v4; do not use the deprecated utilities.
- `corePlugins` is not supported in Tailwind v4.
- In Tailwind v4, configuration is CSS-first using the `@theme` directive — no separate `tailwind.config.js` file is needed.

<code-snippet name="Extending Theme in CSS" lang="css">
@theme {
  --color-brand: oklch(0.72 0.11 178);
}
</code-snippet>

- In Tailwind v4, you import Tailwind using a regular CSS `@import` statement, not using the `@tailwind` directives used in v3:

<code-snippet name="Tailwind v4 Import Tailwind Diff" lang="diff">
   - @tailwind base;
   - @tailwind components;
   - @tailwind utilities;
   + @import "tailwindcss";
</code-snippet>

### Replaced Utilities
- Tailwind v4 removed deprecated utilities. Do not use the deprecated option; use the replacement.
- Opacity values are still numeric.

| Deprecated |	Replacement |
|------------+--------------|
| bg-opacity-* | bg-black/* |
| text-opacity-* | text-black/* |
| border-opacity-* | border-black/* |
| divide-opacity-* | divide-black/* |
| ring-opacity-* | ring-black/* |
| placeholder-opacity-* | placeholder-black/* |
| flex-shrink-* | shrink-* |
| flex-grow-* | grow-* |
| overflow-ellipsis | text-ellipsis |
| decoration-slice | box-decoration-slice |
| decoration-clone | box-decoration-clone |
</laravel-boost-guidelines>
