# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

CEMS-MY is a Laravel 10.x Currency Exchange Management System for Malaysian Money Services Businesses (MSB), compliant with Bank Negara Malaysia (BNM) AML/CFT requirements. It handles foreign currency trading, till management, compliance reporting, and double-entry accounting.

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
├── Console/Commands/  # 35 Artisan commands (29 main + 6 Backup)
├── Enums/  # 34 PHP 8.1 enums replacing magic strings
├── Events/  # 14 Event classes (TransactionCreated, CounterSessionOpened, etc.)
├── Exceptions/Domain/  # 35 typed domain exceptions (InsufficientStockException, etc.)
├── Http/
│   ├── Controllers/  # 28 controllers (16 main + 12 in sub-dirs)
│   ├── Middleware/  # 21 middleware classes
│   ├── Requests/  # Form request validation classes
│   └── Resources/  # API resource transformers
├── Jobs/  # 19 background jobs (7 main + 7 Compliance + 5 Sanctions)
├── Jobs/Audit/  # Async jobs (SealAuditHashJob)
├── Models/  # 64 Eloquent models
├── Observers/  # Model observers for event-driven hooks
└── Services/  # 82 services (70 top-level + 4 Compliance/ + 8 Monitors/)
```

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
- `ComplianceService` runs CDD determination and CTOS reporting (both Buy and Sell)
- `TransactionMonitoringService` runs automated compliance monitors via background jobs:
  - `VelocityMonitor` - Detects velocity/structuring patterns (7-day lookback)
  - `StructuringMonitor` - Transaction aggregation detection
  - `SanctionsRescreeningMonitor` - Monthly rescreening of all customers
  - `StrDeadlineMonitor` - STR submission deadline tracking
  - `CustomerLocationAnomalyMonitor` - Geographic anomaly detection
  - `CurrencyFlowMonitor` - Currency flow pattern analysis
  - `CounterfeitAlertMonitor` - Counterfeit currency detection
- `AlertTriageService` triages and assigns compliance alerts
- `CustomerRiskScoringService` calculates customer risk scores with lock/unlock capability
- **CTOS Submission**: `POST /api/v1/compliance/ctos/{id}/submit` - Submit CTOS reports to BNM with compliance officer sign-off
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
Laravel queues handle async compliance screening, STR report submission, and sanctions rescreening via `App\Jobs\`.

**10. Role Hierarchy**
Permissions inherit upward: `Admin` > `ComplianceOfficer` > `Manager` > `Teller`.
- Managers can approve large transactions but not configure system settings
- Compliance Officers handle AML workflows, not daily operations

**11. Security Features**
- MFA required for ALL roles including Tellers (BNM compliance)
- IP-based blocking after 10 failed login attempts (5-minute window, 1-hour block duration)
- Strict rate limiting on sensitive endpoints (login: 5/min, API: 30/min, transactions: 10/min, STR: 3/min, bulk: 1/5min, export: 5/min, sensitive: 3/min)
- Session timeout (configurable, default 8 hours)
- Audit log with cryptographic hash chaining (tampering protection)
- Password complexity requirements (min 12 chars, mixed case, number, special char, max 5 attempts, 15-min lockout)
- HSTS support (configurable max-age, includeSubDomains, preload)
- IP whitelist support (exact IPs and CIDR notation)

**Note on MFA scope**: MFA is enforced on sensitive operations (transaction creation, approvals, admin functions). Non-sensitive read operations (viewing transactions, customers, counters) do not require MFA.

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

All threshold amounts are centralized in `config/thresholds.php` and accessed via `ThresholdService`:

```php
// config/thresholds.php structure
return [
    'approval' => ['auto_approve' => '10000', 'manager' => '50000'],
    'cdd' => ['specific' => '3000', 'standard' => '10000', 'large_transaction' => '50000'],
    'risk_scoring' => ['high' => '50000', 'medium' => '30000', 'low' => '10000'],
    'alert_triage' => ['critical' => '50000', 'high' => '30000', 'medium' => '10000'],
    'reporting' => ['ctos' => '25000', 'ctr' => '25000', 'str' => '50000', 'edd' => '50000', 'lctr' => '25000'],
    'structuring' => ['sub_threshold' => '3000', 'min_transactions' => 3, 'hourly_window' => 1, 'lookup_days' => 7],
    'duration' => ['warning_hours' => 24, 'critical_hours' => 48],
    'variance' => ['yellow' => '100.00', 'red' => '500.00'],
    'velocity' => ['alert_threshold' => '50000', 'warning_threshold' => '45000', 'window_days' => 90],
    'aml' => ['amount_threshold' => '50000', 'aggregate_threshold' => '50000'],
    'currency_flow' => ['round_trip_threshold' => '5000', 'lookback_days' => 7],
];
```

- All thresholds can be overridden via environment variables (e.g., `THRESHOLD_CTOS=15000`)
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
| `ComplianceService` | CDD determination and CTOS reporting |
| `TransactionMonitoringService` | Automated compliance monitoring |
| `CustomerRiskScoringService` | Customer risk scoring with lock/unlock |
| `StrReportService` | Suspicious Transaction Report generation |
| `EddService` | Enhanced Due Diligence workflow |
| `CaseManagementService` | Compliance case management |
| `MathService` | BCMath precision calculations |
| `AuditService` | Audit log with async hash sealing |
| `ThresholdService` | Centralized threshold access with audit logging |

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
- `Specific` - RM 3,000 to RM 10,000 (reduced documentation per 14C.12.1)
- `Standard` - ≥ RM 10,000 (full documentation per 14C.12.2)
- `Enhanced` - Risk-based: PEP, Sanction match, or High risk (per 14C.13)

**CTOS Reporting**: Applies to ALL cash transactions (Buy and Sell) ≥ RM 25,000

**Structuring Detection**: 7-day lookback for aggregate transactions (configurable)

### Report Generation

BNM compliance reports via Artisan commands:
- `report:msb2` - Daily transaction summary
- `report:lctr` - Large Cash Transaction Report (≥ RM 25,000) - **Monthly**
- `report:lmca` - Monthly LMCA
- `report:qlvr` - Quarterly Large Value
- `compliance:rescreen` - Monthly sanctions rescreening

### Test Organization

Tests use `RefreshDatabase` trait and are in `tests/Feature/` and `tests/Unit/`. Key test files:
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
