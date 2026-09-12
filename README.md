# CEMS-MY

Currency Exchange Management System for Malaysian Money Services Businesses (MSB), compliant with Bank Negara Malaysia (BNM) AML/CFT requirements. Handles foreign currency trading, till management, compliance reporting, and double-entry accounting.

## Table of Contents

- [Features](#features)
- [Tech Stack](#tech-stack)
- [Requirements](#requirements)
- [Installation](#installation)
- [Deployment (Production)](#deployment-production)
- [Configuration](#configuration)
- [Commands](#commands)
- [Architecture](#architecture)
- [User Roles](#user-roles)
- [Security](#security)
- [Compliance](#compliance)
- [Development](#development)

## Features

### Core Functionality

- **Foreign Currency Trading**
  - Buy/sell transactions with real-time position tracking
  - Multi-currency support with instant rate calculation
  - Stock reservation system for concurrency control (24h expiry)
  - PendingApproval workflow for transactions ≥ RM 10,000
  - **Rate Management**: Per-branch rate cards set by the branch manager; configurable spread, teller override limits, copy previous rates

- **Till/Counter Management**
  - Full lifecycle: open, close, handover
  - Float management and reconciliation
  - Real-time till status monitoring
  - End-of-day (EOD) reconciliation per counter and per date

- **Double-Entry Accounting**
  - Complete ledger system with trial balance, P&L, balance sheet
  - Monthly currency revaluation (RevaluationService)
  - Fiscal year management with period closing
  - Journal entries post directly (no approval step); branch-scoped for managers, company-wide for admin/accountant
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
  - Standard: ≥ RM 10,000 OR Enhanced: ≥ RM 50,000 OR PEP OR Sanction match OR High risk OR Large transaction

- **Rate Management**
  - Daily rate workflow: fetch from API, copy previous, or manual override
  - Configurable spread (default 2%) and max deviation (5%)
  - Rate validation against market before transaction execution
  - Full audit trail for all rate changes
  - See `buz.opn.brc.md` for business opening workflow documentation

- **Automated Monitoring (background jobs)**
  | Monitor | Purpose |
  |---------|---------|
  | `VelocityMonitor` | Detects velocity/structuring patterns (7-day lookback) |
  | `StructuringMonitor` | Transaction aggregation detection |
  | `SanctionsRescreeningMonitor` | Monthly rescreening of all customers |
  | `CustomerLocationAnomalyMonitor` | Geographic anomaly detection |
  | `CurrencyFlowMonitor` | Currency flow pattern analysis |
  | `CounterfeitAlertMonitor` | Counterfeit currency detection |

### BNM Reporting

| Report | Frequency | Command | Description |
|--------|-----------|---------|-------------|
| MSB2 | Daily | `report:msb2` | Transaction summary |
| LMCA | Monthly | `report:lmca` | Monthly Large Cash Aggregate |
| LVR | Quarterly | `report:qlvr` | Large Value Transactions |
| EOD | Daily | `report:eod` | End-of-Day reconciliation |

## Tech Stack

| Component | Technology |
|-----------|------------|
| Framework | Laravel 12.x |
| Language | PHP 8.3 |
| Database | MariaDB 10.11 LTS |
| Cache/Queue | Redis |
| Queue UI | Laravel Horizon |
| Auth | Laravel Sanctum (token) / Session (web) |
| PDF Generation | DomPDF |
| Excel Export | Maatwebsite Excel |
| QR/Barcode | simple-qrcode, php-barcode-generator |

## Requirements

- PHP 8.3+
- MariaDB 10.6+ (10.11 LTS recommended)
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
php artisan db:seed --class=SchemaSeeder  # migration-free schema setup
php artisan serve
```

**Note:** Ensure MariaDB and Redis services are running before seeding.

The project is migration-free: `database/seeders/SchemaSeeder.php` is the single source of truth for schema creation. It drops and recreates every table, so run it only on an empty database or through the guarded setup/reset flows.

## Deployment (Production)

Step-by-step for a bare server. Supported targets: **Ubuntu** (20.04+), **AlmaLinux** and **Oracle Linux** (8/9), on **x86_64 or aarch64/ARM** — detected automatically.

### 1. Install system dependencies

```bash
sudo bash scripts/install-deps.sh
```

Installs nginx, MariaDB 10.11 LTS, Redis, PHP 8.3 (with all required extensions), Composer and Node.js 22, then enables the services.

### 2. Deploy the application

```bash
git clone https://github.com/klzk-myy/cems-my.git /var/www/cems-my
cd /var/www/cems-my

composer install --no-dev --optimize-autoloader
npm ci && npm run build

cp .env.example .env
php artisan key:generate
```

Create the database and user:

```sql
CREATE DATABASE cems_my CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'cems'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD';
GRANT ALL ON cems_my.* TO 'cems'@'localhost';
```

Edit `.env` (`APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, `DB_*`, `REDIS_*`, `SESSION_DOMAIN`, `SANCTUM_STATEFUL_DOMAINS`), then seed the schema:

```bash
php artisan db:seed --class=SchemaSeeder
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

### 3. nginx

Point the vhost docroot at `public/` and add the Laravel rewrite:

```nginx
root /var/www/cems-my/public;
location / { try_files $uri $uri/ /index.php?$query_string; }
```

PHP requests should go to PHP-FPM (`php8.3-fpm` socket on Ubuntu, `php-fpm` on RHEL-family). Reload nginx.

### 4. Scheduler cron (required)

The scheduler drives all automation — EOD reconciliation, deferred accounting, revaluation, month-end close, reports, rescreening. Install as the PHP-FPM user (usually `www`, `www-data` or `nginx`):

```bash
sudo crontab -u www -e
```

```cron
* * * * * cd /var/www/cems-my && php artisan schedule:run >> /dev/null 2>&1
```

Verify with `php artisan schedule:list`.

### 5. Queue worker (required)

`$schedule->job(...)` entries, audit sealing, sanctions screening and risk rescoring all run through Redis queues. Create a systemd unit `/etc/systemd/system/cems-queue.service`:

```ini
[Unit]
Description=CEMS-MY Laravel queue worker
After=network.target

[Service]
Type=simple
User=www
Group=www
WorkingDirectory=/var/www/cems-my
ExecStart=/usr/bin/php artisan queue:work --queue=default,audit --sleep=3 --tries=3 --timeout=120 --backoff=5
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now cems-queue
```

### 6. Permissions

```bash
sudo chown -R www:www storage bootstrap/cache
```

(Use the PHP-FPM user for your distro: `www-data` on Ubuntu, `nginx` on RHEL-family.)

### 7. Verify

- `GET /` redirects to `/setup` — complete the 6-step business setup wizard
- `systemctl status cems-queue` — active
- `storage/logs/queue-worker.log` shows jobs being processed

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

### Simulation Harness

The project includes a black-box HTTP simulation harness for verifying both web and API behavior.

```bash
php artisan simulation:issue-token          # Issue a Sanctum token for a simulation role
php artisan simulation:run --wave=A --surface=both
php artisan simulation:run --wave=B --surface=web
php artisan simulation:run --wave=C --surface=api
```

### All Artisan Commands

| Command | Description |
|---------|-------------|
| `compliance:rescreen` | Rescreen all customers against sanctions lists |
| `report:msb2` | Generate daily MSB(2) report |
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

**Codebase Statistics (as of latest commit):**

| Component | Count | Key Examples |
|-----------|-------|--------------|
| Eloquent Models | 62 | `Customer`, `Transaction`, `SanctionEntry`, `JournalEntry`, `Counter` |
| Controllers | 34 | `CustomerController`, `TransactionController`, `SanctionListController`, `RateController` |
| Services | 79 | `ThresholdService`, `RateManagementService`, `CustomerRiskScoringService`, `RevaluationService` |
| Middleware | 21 | `CheckRole`, `SessionTimeout`, `PerformanceTrackingMiddleware`, `SecurityHeaders` |
| Artisan Commands | 34 | `report:msb2`, `compliance:rescreen`, `revaluation:run`, `user:create` |
| Enums | 64 | `UserRole`, `TransactionStatus`, `CddLevel`, `EntityType`, `CounterStatus` |

**Directory Structure:**

```
app/
├── Console/Commands/        # 34 Artisan CLI commands (reports, compliance, maintenance)
├── DTO/                     # Data transfer objects for type-safe data handling
├── Enums/                   # 64 PHP 8.3 enums (strict typing for roles, statuses, types)
├── Events/                  # Domain events for event-driven architecture
├── Exceptions/Domain/       # Typed domain exceptions (business logic errors)
├── Http/
│   ├── Controllers/         # 34 controllers (Web + API v1)
│   ├── Middleware/          # 21 middleware (auth, roles, security, performance)
│   ├── Requests/            # Form request validation classes
│   └── Resources/           # API resource transformers
├── Jobs/                    # Queued background jobs (compliance monitors, reports)
├── Models/                  # 62 Eloquent models with relationships
├── Observers/               # Model observers for event-driven hooks
└── Services/                # 79 business logic services
```

**Key Recent Implementations:**

- **View Consistency Cleanup** (2026-06): Standardized Blade components, fixed broken forms, replaced dummy data with real model bindings across compliance views
- **Sanctions Entry Management**: Full CRUD for OFAC/UN/EU sanctions lists with address fields and list source tracking
- **Customer Notes**: Note-taking system for compliance observations with dedicated model and API
- **Rate Override API**: Secure rate override endpoint with Form Request validation
- **Shared UI Components**: Reusable `<x-input>`, `<x-select>`, `<x-button>`, `<x-card>`, `<x-page-header>` components with accessibility improvements

**Knowledge Graph Index:**

Codebase is indexed by GitNexus for semantic search, impact analysis, and execution flow tracing:

```bash
npx gitnexus analyze    # Build/refresh index
npx gitnexus status     # Check index freshness
```

See `.gitnexus/` for index data. GitNexus enables:
- Blast radius analysis before code changes
- Cross-file symbol reference tracking
- Execution flow debugging
- Automated rename refactoring

## Organizational Model & User Roles

```
                    Company
              (Admin, Accountant — consolidated)
              HQ: non-trading, MYR expense float only
              ┌─────────────┴─────────────┐
           Branch A                    Branch B
     Manager, Compliance Officer   Manager, Compliance Officer
        ┌─────┴─────┐                 ┌─────┴─────┐
     Counter A1  Counter A2        Counter B1  Counter B2
      Teller 1    Teller 2          Teller 3    Teller 4
```

| Role | Scope | Can | Cannot |
|------|-------|-----|--------|
| **Teller** | Own counter | Create transactions, view own balancing/stock, request stock, request cancellation | Profit, reports, approvals |
| **Manager** | Own branch | Approve transactions RM10k–50k, approve cancellations, set branch rates, post branch expenses/petty cash, approve/assign/return teller stock, create/accept stock transfers, manage in-transit stock, EOD sign-off | Other branches, create transactions, approve ≥RM50k, reversals |
| **Compliance Officer** | Own branch | Clear high-risk holds, approve cancellations, approve ≥RM50k transactions, reverse completed transactions, PEP sign-off, STR filing, KYC verify/reject | Create transactions |
| **Accountant** | Company-wide | GL, journals, period/fiscal close, consolidation, bank reconciliation, budgets, all financial reports | Create transactions |
| **Admin** | Company-wide | Everything except creating transactions: users, branches, company-wide journals, configuration, audit | Create transactions |

**Key rules:**

- **Customers** are company-wide — any teller at any branch can serve any customer
- **Stock sourcing**: branches hold their own foreign stock, sourced from customer buys, setup seed, and branch↔branch transfers. HQ holds no stock
- **Stock transfers** are maker/taker: source branch manager creates, destination branch manager approves — no HQ approval step
- **Petty cash**: per-branch MYR float for branch expenses, posted by the branch manager
- **Rates**: each branch has its own rate card, set by its manager with no approval requirement
- **Fiscal year-end**: 31 December (configurable via `FISCAL_YEAR_END_MONTH`/`FISCAL_YEAR_END_DAY`)

## Security

### Authentication & Authorization

- MFA required for all roles (BNM mandatory)
- Role-based access control via `CheckRole` middleware and enum permission methods
- Session timeout (configurable, default 8 hours)

### Rate Limiting

| Endpoint | Limit |
|----------|-------|
| Login | disabled (re-enable via RouteServiceProvider) |
| `/health` | 60/min |
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
| **Specific** | RM 3,000 - 9,999 | Auto-approve |
| **Standard** | ≥ RM 10,000 | Auto-approve if no flags |
| **Enhanced** | ≥ RM 50,000 OR PEP OR Sanction OR High risk | Compliance review |

### Transaction Status Workflow

```
Created ──> Completed                                    (auto: < RM10k, no flag, not High risk)
Created ──> PendingApproval ──(Manager approves)──> Completed      (RM10k–50k)
Created ──> PendingApproval ──(Compliance approves)──> Completed   (≥ RM50k)
Created ──> PendingApproval [hold] ──(Compliance clears)──> tiered approval   (PEP/sanction/High/flag)
PendingApproval ──(request cancel)──> PendingCancellation ──(Manager|Compliance)──> Cancelled
Completed ──(Compliance reverses)──> Reversed
```

| Condition | Status | Approver |
|-----------|--------|----------|
| Amount < RM 10,000, no compliance flag | Auto-approve | — |
| Amount RM 10,000–49,999, no flag | `PendingApproval` | Manager |
| Amount ≥ RM 50,000, no flag | `PendingApproval` | Compliance Officer |
| High-risk customer or compliance flag | `PendingApproval` + hold | Compliance must clear first, then tiered approval |
| Cancellation requested | `PendingCancellation` | Manager or Compliance (≠ requester) |
| Completed transaction | `Reversed` | Compliance only |

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
    'reporting' => ['str' => '50000', 'edd' => '50000'],
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

The schema is migration-free: `database/seeders/SchemaSeeder.php` is the single source of truth.

```bash
php artisan db:seed --class=SchemaSeeder   # Recreate schema from the seeder
php artisan db:seed                        # Seed with test data
```

### Queue Workers

```bash
php artisan horizon                 # Start Horizon (recommended)
php artisan queue:work redis --sleep=3 --tries=3  # Traditional worker
php artisan queue:health            # Monitor queue health
```

## License

MIT License
