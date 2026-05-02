# CEMS-MY

Currency Exchange Management System for Malaysian Money Services Businesses (MSB), compliant with Bank Negara Malaysia (BNM) AML/CFT requirements. Handles foreign currency trading, till management, compliance reporting, and double-entry accounting.

## Table of Contents

- [Features](#features)
- [Tech Stack](#tech-stack)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Commands](#commands)
- [Architecture](docs/ARCHITECTURE.md)
- [User Roles](#user-roles)
- [Security](#security)
- [Compliance](#compliance)
- [API Documentation](docs/API.md)
- [Development](#development)

## Features

### Core Functionality

- **Foreign Currency Trading**
  - Buy/sell transactions with real-time position tracking
  - Multi-currency support with instant rate calculation
  - Stock reservation system for concurrency control (24h expiry)
  - PendingApproval workflow for transactions ≥ RM 3,000
  - **Rate Management**: Daily rate workflow with configurable spread, deviation validation, copy previous rates, and manual override capability

- **Till/Counter Management**
  - Full lifecycle: open, close, handover
  - Float management and reconciliation
  - Real-time till status monitoring
  - End-of-day (EOD) reconciliation per counter and per date

- **Double-Entry Accounting**
  - Complete ledger system with trial balance, P&L, balance sheet
  - Monthly currency revaluation (RevaluationService)
  - Fiscal year management with period closing
  - Journal entry workflow (Draft → Pending → Posted)
  - Cash flow statements and financial ratio analysis

- **Customer Management**
  - Customer registration with KYC document upload
  - ID number HMAC-SHA256 blind indexing for PII protection
  - Risk scoring with lock/unlock capability
  - Customer location anomaly detection

### AML/CFT Compliance

- **Customer Due Diligence (CDD)**
  - Simplified: Transaction < RM 3,000
  - Specific: RM 3,000 to RM 9,999
  - Standard: ≥ RM 10,000 OR Enhanced: ≥ RM 50,000 OR PEP OR Sanction match OR High risk

- **CTOS Reporting**
  - All cash transactions (Buy and Sell) ≥ RM 25,000
  - Compliance officer sign-off workflow via `POST /api/v1/compliance/ctos/{id}/submit`

- **Rate Management**
  - Daily rate workflow: fetch from API, copy previous, or manual override
  - Configurable spread (default 2%) and max deviation (5%)
  - Rate validation against market before transaction execution
  - Full audit trail for all rate changes
  - See `buz.opn.brc.md` for business opening workflow documentation

- **STR Generation & Automation**
  - Suspicious Transaction Report creation and submission
  - STR deadline tracking via `StrDeadlineMonitor`
  - Narrative generation and GoAML XML output

- **Automated Monitoring (background jobs)**
  | Monitor | Purpose |
  |---------|---------|
  | `VelocityMonitor` | Detects velocity/structuring patterns (7-day lookback) |
  | `StructuringMonitor` | Transaction aggregation detection |
  | `SanctionsRescreeningMonitor` | Monthly rescreening of all customers |
  | `StrDeadlineMonitor` | STR submission deadline tracking |
  | `CustomerLocationAnomalyMonitor` | Geographic anomaly detection |
  | `CurrencyFlowMonitor` | Currency flow pattern analysis |
  | `CounterfeitAlertMonitor` | Counterfeit currency detection |

### BNM Reporting

| Report | Frequency | Command | Description |
|--------|-----------|---------|-------------|
| MSB2 | Daily | `report:msb2` | Transaction summary |
| LCTR | Monthly | `report:lctr` | Large Cash Transaction Report (≥ RM 50,000) |
| LMCA | Monthly | `report:lmca` | Monthly Large Cash Aggregate |
| LVR | Quarterly | `report:qlvr` | Large Value Transactions |
| EOD | Daily | `report:eod` | End-of-Day reconciliation |

## Tech Stack

| Component | Technology |
|-----------|------------|
| Framework | Laravel 10.x |
| Language | PHP 8.1+ |
| Database | MySQL 8.0 |
| Cache/Queue | Redis |
| Queue UI | Laravel Horizon |
| Auth | Laravel Sanctum (token) / Session (web) |
| PDF Generation | DomPDF |
| Excel Export | Maatwebsite Excel |
| QR/Barcode | simple-qrcode, php-barcode-generator |

## Requirements

- PHP 8.1+
- MySQL 8.0+
- Redis 6+
- Composer 2.x
- Node.js 18+
- NPM 9+

## Installation

```bash
git clone https://github.com/klzk-myy/cems-my.git
cd cems-my
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan sanctum:secret  # for API auth
php artisan migrate
php artisan db:seed  # optional
php artisan serve
```

**Note:** Ensure MySQL and Redis services are running before migrating.

## Configuration

Copy `.env.example` to `.env` and configure:

```env
APP_NAME=CEMS-MY
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cems_my
DB_USERNAME=your_username
DB_PASSWORD=your_password

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

QUEUE_CONNECTION=redis
SESSION_DRIVER=file
SESSION_LIFETIME=480
```

### Threshold Overrides

All thresholds support environment variable overrides via `ThresholdService`:

```env
THRESHOLD_AUTO_APPROVE=10000
THRESHOLD_MANAGER=50000
THRESHOLD_CDD_SPECIFIC=3000
THRESHOLD_CDD_STANDARD=10000
THRESHOLD_CDD_LARGE=50000
THRESHOLD_CTOS=25000
```

## Commands

### Testing

```bash
php artisan test                          # Run all tests
php artisan test --filter=TransactionWorkflowTest  # Run specific suite
php artisan test --filter=MathServiceTest  # Run specific test class
php test-runner.php                       # Run with category filtering
```

### BNM Reports

```bash
php artisan report:msb2 --date=2026-04-18      # Daily transaction summary
php artisan report:lctr --month=2026-03        # Monthly large cash transactions
php artisan report:lmca --month=2026-03       # Monthly LMCA
php artisan report:qlvr --quarter=2026-Q1     # Quarterly large value
php artisan report:eod --date=2026-04-18     # End-of-day reconciliation
php artisan report:trial-balance --date=2026-03-31  # Accounting trial balance
php artisan report:position-limit             # Daily position limits
```

### Compliance

```bash
php artisan compliance:rescreen              # Monthly sanctions rescreening
php artisan reservation:expire               # Release stale stock reservations (24h)
php artisan monitor:check                    # Run compliance monitors
php artisan monitor:status                   # Show monitor status
```

### Cache & Maintenance

```bash
php artisan config:clear && php artisan route:clear && php artisan view:clear  # Clear all caches
php artisan cache:clear                     # Clear cache only
php artisan ip:blocker                     # IP blocker management
php artisan queue:health                    # Queue health check
php artisan queue:clear-stuck               # Clear stuck queues
php artisan backup:verify                   # Verify backups
php artisan reports:archive --days=90        # Archive old reports
php artisan audit:rotate                    # Rotate audit logs
```

### User Management

```bash
php artisan user:create --role=teller --name="John Doe" --email=john@example.com
```

### All Artisan Commands

| Command | Description |
|---------|-------------|
| `compliance:rescreen` | Rescreen all customers against sanctions lists |
| `report:msb2` | Generate daily MSB(2) report |
| `report:lctr` | Generate monthly Cash Transaction Report (≥ RM50,000) |
| `report:lmca` | Generate monthly BNM Form LMCA report |
| `report:qlvr` | Generate quarterly large value transaction report |
| `report:eod` | Generate End-of-Day reconciliation report |
| `report:trial-balance` | Generate trial balance for accounting period |
| `report:position-limit` | Generate daily position limit utilization report |
| `reservation:expire` | Release expired stock reservations |
| `user:create` | Create a new user with specified role |
| `alert:daily-summary` | Send daily alert summary |
| `alert:send` | Send pending alerts |
| `archive:reports` | Archive old reports |
| `cleanup:old-reports` | Clean up old report files |
| `queue:clear-stuck` | Clear stuck queue jobs |
| `queue:health` | Check queue health |
| `sanctions:import` | Import sanctions list updates |
| `sanctions:status` | Show sanctions list status |
| `sanctions:update` | Update sanctions lists |
| `rotate:audit-logs` | Rotate audit logs |
| `revaluation:run` | Run monthly currency revaluation |
| `retry:failed-jobs` | Retry failed queue jobs |
| `test:notification` | Send test notification |
| `tests:run` | Run test suite |
| `ip:blocker` | Manage IP blocks |
| `monitor:check` | Run compliance monitors |
| `monitor:status` | Show monitor status |

## Architecture

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for full system architecture documentation.

```
app/
├── Console/Commands/        # 35 Artisan CLI commands
├── Enums/                  # 34 PHP 8.1 enums (UserRole, TransactionStatus, CddLevel, etc.)
├── Events/                 # 13 Event classes (TransactionCreated, CounterSessionOpened, etc.)
├── Exceptions/Domain/       # 43 typed domain exceptions
├── Http/
│   ├── Controllers/        # 61 controllers (42 web + 19 API)
│   ├── Middleware/          # 21 middleware classes
│   ├── Requests/            # Form request validation classes
│   └── Resources/           # API resource transformers
├── Jobs/                   # 9 background jobs
├── Models/                 # 60 Eloquent models
├── Observers/              # Model observers for event-driven hooks
└── Services/               # 78 services
```
app/
├── Console/Commands/        # 35 Artisan CLI commands
├── Enums/                  # 34 PHP 8.1 enums (UserRole, TransactionStatus, CddLevel, etc.)
├── Events/                 # 13 Event classes (TransactionCreated, CounterSessionOpened, etc.)
├── Exceptions/Domain/       # 43 typed domain exceptions
├── Http/
│   ├── Controllers/        # 50 controllers
│   ├── Middleware/          # 21 middleware classes
│   ├── Requests/            # Form request validation classes
│   └── Resources/           # API resource transformers
├── Jobs/                   # 23 background jobs
├── Models/                 # 62 Eloquent models
├── Observers/              # Model observers for event-driven hooks
└── Services/               # 83 services

## User Roles

| Role | Permissions |
|------|-------------|
| **Teller** | Create transactions, view customers, operate assigned counter |
| **Manager** | Approve large transactions, manage counters, view reports, handle cancellations |
| **Compliance Officer** | CDD review, CTOS/STR submission, sanctions management, risk monitoring, alerts triage |
| **Admin** | System configuration, user management, branch settings |

**Permission Hierarchy:** Admin > ComplianceOfficer > Manager > Teller

## Security

### Authentication & Authorization

- MFA required for all roles (BNM mandatory)
- Role-based access control via `CheckRole` middleware and enum permission methods
- Session timeout (configurable, default 8 hours)

### Rate Limiting

| Endpoint | Limit |
|----------|-------|
| Login | 5/min |
| API (general) | 30/min |
| Transactions | 10/min |
| STR Submit | 3/min |
| Bulk Export | 1/5min |
| Sensitive Ops | 3/min |

### Rate Management

Rates are managed via `RateManagementService` and `RateApiService`:

```bash
# Fetch latest rates from external API
POST /api/v1/rates/fetch

# Copy previous day's rates
POST /api/v1/rates/copy-previous

# Manual override (Manager/Admin)
PUT /api/v1/rates/{currencyCode}

# Check if rates are set
GET /api/v1/rates/check
```

All rate changes are logged to audit trail. Spread and deviation thresholds configured in `config/thresholds.php`.

### IP Protection

- IP-based blocking after 10 failed login attempts
- 5-minute detection window, 1-hour block duration
- IP whitelist support (exact IPs and CIDR notation)

### Password Policy

- Minimum 12 characters
- Mixed case, number, special character required
- Maximum 5 failed attempts
- 15-minute lockout on failure

### Audit & Integrity

- Audit log with cryptographic hash chaining (tamper-evident)
- SHA-256 chain verification via `AuditService::verifyChainIntegrity()`
- Async hash sealing via `SealAuditHashJob`

### Security Headers

- HSTS (configurable max-age, includeSubDomains, preload)
- Content Security Policy, X-Frame-Options, X-Content-Type-Options

## Compliance

### Customer Due Diligence (CDD) Levels

| Level | Trigger | Action |
|-------|---------|--------|
| **Simplified** | < RM 3,000 | Auto-approve |
| **Standard** | RM 3,000 - 49,999 | Manager approval if flagged |
| **Enhanced** | ≥ RM 50,000 OR PEP OR Sanction | Compliance review |

### Transaction Status Workflow

```
PendingApproval ──(manager approve)──> Completed
     │
     └──(request cancel)──> PendingCancellation ──(approve)──> Cancelled
```

| Condition | Status |
|-----------|--------|
| Amount < RM 10,000, no compliance flag | Auto-approve |
| Amount ≥ RM 10,000, no compliance flag | `PendingApproval` (Manager approval) |
| Amount ≥ RM 50,000 | `PendingApproval` (Manager) |
| High-risk customer or compliance flag | `Pending` (Compliance hold) |
| CDD required | `PendingCdd` |
| Cancellation requested | `PendingCancellation` (segregation of duties) |

### Structuring Detection

- 7-day lookback period for aggregate transactions
- Configurable threshold and pattern matching
- Automatic flagging via `StructuringMonitor`

### Centralized Thresholds

All thresholds are centralized in `config/thresholds.php` and accessed via `ThresholdService`:

```php
// config/thresholds.php
return [
    'approval' => ['auto_approve' => '10000', 'manager' => '50000'],
    'cdd' => ['specific' => '3000', 'standard' => '10000', 'large_transaction' => '50000'],
    'reporting' => ['ctos' => '25000', 'ctr' => '25000', 'str' => '50000', 'edd' => '50000', 'lctr' => '25000'],
    'structuring' => ['sub_threshold' => '3000', 'min_transactions' => 3, 'hourly_window' => 1, 'lookup_days' => 7],
    // ...
];
```

All values overridable via environment variables. `ThresholdAudit` model tracks all changes.

## Development

### Running Tests

```bash
php artisan test                  # All tests
php artisan test --coverage=coverage  # With coverage
npm run test:watch               # Watch mode
php artisan dusk                 # Browser tests
```

### Code Style

```bash
./vendor/bin/pint        # Lint with Laravel Pint (PSR-12)
./vendor/bin/pint --test  # Check without modifying
```

### Database

```bash
php artisan migrate:fresh          # Rollback and re-migrate
php artisan db:seed                # Seed with test data
php artisan migrate:fresh --seed   # Fresh migrate + seed
```

### Queue Workers

```bash
php artisan horizon                 # Start Horizon (recommended)
php artisan queue:work redis --sleep=3 --tries=3  # Traditional worker
php artisan queue:health            # Monitor queue health
```

## License

MIT License
