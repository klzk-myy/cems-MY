# CEMS-MY

Currency Exchange Management System for Malaysian Money Services Businesses (MSB), compliant with Bank Negara Malaysia (BNM) AML/CFT requirements. Handles foreign currency trading, till management, compliance reporting, and double-entry accounting.

## Table of Contents

- [Features](#features)
- [Tech Stack](#tech-stack)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Commands](#commands)
- [Architecture](#architecture)
- [User Roles](#user-roles)
- [Security](#security)
- [Compliance](#compliance)
- [API Endpoints](#api-endpoints)
- [Development](#development)

## Features

### Core Functionality

- **Foreign Currency Trading**
  - Buy/sell transactions with real-time position tracking
  - Multi-currency support with instant rate calculation
  - Stock reservation system for pending approvals

- **Till/Counter Management**
  - Full lifecycle: open, close, handover
  - Float management and reconciliation
  - Real-time till status monitoring
  - End-of-day (EOD) reconciliation

- **Double-Entry Accounting**
  - Complete ledger system with trial balance
  - Profit & Loss statements
  - Balance sheet generation
  - Monthly revaluation
  - Fiscal year management

- **Customer Management**
  - Customer registration with KYC
  - ID number blind indexing for PII protection
  - Risk scoring and monitoring
  - Customer lock/unlock capability

### AML/CFT Compliance

- **Customer Due Diligence (CDD)**
  - Simplified: Transaction < RM 3,000
  - Standard: RM 3,000 to RM 49,999
  - Enhanced: ≥ RM 50,000 OR PEP OR Sanction match

- **CTOS Reporting**
  - Cash transactions (Buy/Sell) ≥ RM 10,000
  - Compliance officer sign-off workflow

- **STR Generation**
  - Suspicious Transaction Report creation
  - Submission tracking and deadline monitoring

- **Automated Monitoring**
  - Velocity monitoring (structuring detection)
  - Sanctions rescreening (monthly)
  - Currency flow analysis
  - Customer location anomaly detection
  - Counterfeit currency alerts

### BNM Reporting

| Report | Frequency | Description |
|--------|-----------|-------------|
| MSB2 | Daily | Transaction summary |
| LCTR | Monthly | Large Cash Transaction Report (≥ RM 50,000) |
| LMCA | Monthly | Monthly Large Cash Aggregate |
| LVR | Quarterly | Large Value Transactions |

## Tech Stack

| Component | Technology |
|-----------|------------|
| Framework | Laravel 10.x |
| Language | PHP 8.1+ |
| Database | MySQL 8.0 |
| Cache/Queue | Redis |
| Queue UI | Laravel Horizon |
| Auth | Laravel Sanctum |
| PDF Generation | DomPDF |
| Excel Export | Maatwebsite Excel |
| QR/Barcode | simple-qrcode, php-barcode-generator |

## Requirements

- PHP 8.1+
- MySQL 8.0+
- Redis 6+
- Composer 2.x
- Node.js 18+ (for frontend assets)
- NPM 9+

## Installation

```bash
# Clone the repository
git clone https://github.com/klzk-myy/cems-my.git
cd cems-my

# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Generate JWT secret (if using Sanctum)
php artisan sanctum:secret

# Run database migrations
php artisan migrate

# Seed initial data (optional)
php artisan db:seed

# Start the development server
php artisan serve
```

**Note:** Ensure MySQL and Redis services are running before migrating.

## Configuration

Copy `.env.example` to `.env` and configure:

```env
# Application
APP_NAME=CEMS-MY
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cems_my
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Queue (Redis)
QUEUE_CONNECTION=redis

# Session
SESSION_DRIVER=file
SESSION_LIFETIME=480

# Mail (configure for your provider)
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
```

### Environment Variables

| Variable | Description | Required |
|----------|-------------|----------|
| `APP_KEY` | Laravel application key | Yes |
| `DB_*` | Database connection | Yes |
| `REDIS_*` | Redis connection | Yes |
| `ENCRYPTION_KEY` | 32-char encryption key | Yes |
| `EXCHANGE_RATE_API_KEY` | Exchange rate API | Optional |

## Commands

### Testing

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --filter=TransactionWorkflowTest

# Run specific test class
php artisan test --filter=MathServiceTest

# Run with coverage
php artisan test --coverage

# Run via test runner (with category filtering)
php test-runner.php
```

### BNM Reports

```bash
# Generate MSB2 daily report
php artisan report:msb2 --date=2026-04-18

# Generate LCTR monthly report
php artisan report:lctr --month=2026-03

# Generate LMCA monthly report
php artisan report:lmca --month=2026-03

# Generate LVR quarterly report
php artisan report:qlvr --quarter=2026-Q1

# Generate EOD reconciliation
php artisan report:eod --date=2026-04-18

# Generate trial balance
php artisan report:trial-balance --date=2026-03-31
```

### Compliance

```bash
# Rescreen customers against sanctions (monthly)
php artisan compliance:rescreen

# Generate position limit report
php artisan report:position-limits

# Monitor check
php artisan monitor:check

# Monitor status
php artisan monitor:status
```

### Cache & Maintenance

```bash
# Clear all caches
php artisan config:clear && php artisan route:clear && php artisan view:clear

# Clear cache only
php artisan cache:clear

# Clear expired stock reservations
php artisan reservation:expire

# IP blocker
php artisan ip:blocker

# Queue health check
php artisan queue:health

# Clear stuck queues
php artisan queue:clear-stuck
```

### Other

```bash
# List all routes
php artisan route:list

# Create user
php artisan create:user --role=teller --name="John Doe" --email=john@example.com

# Backup verify
php artisan backup:verify

# Archive reports
php artisan reports:archive --days=90
```

## Architecture

```
app/
├── Console/Commands/     # Artisan CLI commands (scheduled reports, compliance)
├── Enums/                # PHP 8.1 enums (29 enums, status/type organization)
├── Events/               # Event classes (TransactionCreated, CounterSessionOpened)
├── Exceptions/Domain/     # Typed domain exceptions
│   ├── InsufficientStockException.php
│   ├── StockReservationExpiredException.php
│   ├── TillAlreadyOpenException.php
│   └── UserAlreadyAtCounterException.php
├── Http/
│   ├── Controllers/      # Thin controllers, delegate to services
│   ├── Middleware/       # Auth, RBAC, MFA, rate limiting, security headers
│   ├── Requests/         # Form request validation classes
│   └── Resources/        # API resource transformers
├── Jobs/                 # Background jobs (async audit hashing)
├── Models/               # Eloquent models (57 models)
├── Observers/            # Model observers for event-driven hooks
└── Services/             # Business logic (55 services)
    ├── AccountingService.php      # Journal entries, ledger
    ├── ComplianceService.php      # CDD, CTOS, STR
    ├── CounterService.php         # Till lifecycle
    ├── CurrencyPositionService.php # Stock/position management
    ├── CustomerRiskScoringService.php # Risk scoring
    ├── LedgerService.php         # Trial balance, P&L, BS
    ├── MathService.php           # BCMath precision calculations
    ├── RevaluationService.php    # Monthly currency revaluation
    ├── StrReportService.php      # STR generation
    ├── TransactionMonitoringService.php # Automated monitors
    └── TransactionService.php    # Core transaction operations
```

### Key Design Patterns

**1. Enum-Based RBAC**
```php
UserRole::Teller->canApproveLargeTransactions(); // false
UserRole::Manager->canApproveLargeTransactions(); // true
UserRole::ComplianceOfficer->canViewReports(); // true
```

**2. BCMath Precision**
```php
MathService::add('100.50', '50.25'); // '150.75'
MathService::multiply('100.00', '0.15', 4); // '15.0000'
// Never use float for currency
```

**3. Stock Reservations (Concurrency Control)**
```php
// When transaction goes PendingApproval
$reservation = CurrencyPositionService::reserveStock($currency, $amount);

// At approval - consumes reservation
CurrencyPositionService::consumeStockReservation($reservationId);

// On cancel/expire - releases reservation
CurrencyPositionService::releaseStockReservation($reservationId);
```

**4. Domain Exceptions**
```php
throw new InsufficientStockException($currency, $requested, $available);
throw new StockReservationExpiredException($reservationId);
throw new TillAlreadyOpenException($counterId, $currentUserId);
```

**5. Event-Driven Architecture**
```php
// Events fire for critical operations
event(new TransactionCreated($transaction));
event(new CounterSessionOpened($session));

// Listeners handle audit logging, notifications, compliance
```

## User Roles

| Role | Permissions |
|------|-------------|
| **Teller** | Create transactions, view customers, operate assigned counter |
| **Manager** | Approve large transactions, manage counters, view reports, handle cancellations |
| **Compliance Officer** | CDD review, CTOS/STR submission, sanctions management, risk monitoring |
| **Admin** | System configuration, user management, branch settings |

**Permission Hierarchy:** Admin > ComplianceOfficer > Manager > Teller

## Security

### Authentication & Authorization

- MFA required for all roles (BNM mandatory)
- Role-based access control via `CheckRole` middleware
- Session timeout (configurable, default 8 hours)

### Rate Limiting

| Endpoint | Limit |
|----------|-------|
| Login | 5/min |
| API | 30/min |
| Transactions | 10/min |
| STR Submit | 3/min |
| Bulk Export | 1/5min |
| Sensitive Ops | 3/min |

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
- Content Security Policy
- X-Frame-Options, X-Content-Type-Options

## Compliance

### Customer Due Diligence (CDD) Levels

| Level | Trigger | Action |
|-------|---------|--------|
| **Simplified** | < RM 3,000 | Auto-approve |
| **Standard** | RM 3,000 - 49,999 | Manager approval if hold |
| **Enhanced** | ≥ RM 50,000 OR PEP OR Sanction | Compliance review |

### Transaction Holds

| Condition | Status |
|-----------|--------|
| Amount ≥ RM 3,000, no compliance flag | `PendingApproval` (Manager approval) |
| Amount ≥ RM 50,000 | `PendingApproval` (Manager) |
| High-risk customer | `Pending` (Compliance hold) |
| CDD required | `PendingCdd` |

### CTOS Reporting

- **Threshold:** Cash transactions (Buy/Sell) ≥ RM 10,000
- **Submission:** `POST /api/v1/compliance/ctos/{id}/submit`
- **Sign-off:** Requires compliance officer approval

### Structuring Detection

- 7-day lookback period for aggregation
- Configurable threshold and pattern matching
- Automatic flagging and alert generation

### STR Workflow

1. Alert generated by monitoring service
2. Compliance officer triages via `AlertTriageService`
3. Investigation and documentation
4. STR generation via `StrReportService`
5. Submission to BNM with approval chain

## API Endpoints

### Authentication

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/auth/login` | User login |
| POST | `/api/v1/auth/logout` | User logout |
| POST | `/api/v1/auth/mfa/verify` | Verify MFA code |

### Transactions

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/transactions` | List transactions |
| POST | `/api/v1/transactions` | Create transaction |
| GET | `/api/v1/transactions/{id}` | Get transaction |
| POST | `/api/v1/transactions/{id}/approve` | Approve transaction |
| POST | `/api/v1/transactions/{id}/cancel` | Cancel transaction |

### Counter/Till

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/counters/{counter}/open` | Open counter |
| POST | `/api/v1/counters/{counter}/close` | Close counter |
| POST | `/api/v1/counters/{counter}/handover` | Handover custody |
| GET | `/api/v1/counters/{counter}/status` | Get counter status |

### Compliance

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/compliance/ctos/{id}/submit` | Submit CTOS |
| POST | `/api/v1/compliance/str` | Create STR |
| GET | `/api/v1/compliance/alerts` | List alerts |
| POST | `/api/v1/compliance/alerts/{id}/triage` | Triage alert |

### Reports

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/reports/msb2` | MSB2 daily report |
| GET | `/api/v1/reports/trial-balance` | Trial balance |
| GET | `/api/v1/eod/reconciliation/{date}` | EOD reconciliation |

### Accounting

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/accounting/journal-entries` | List journal entries |
| POST | `/api/v1/accounting/journal-entries` | Create journal entry |
| GET | `/api/v1/accounting/ledger/{accountCode}` | Get ledger |
| GET | `/api/v1/accounting/trial-balance` | Trial balance |
| GET | `/api/v1/accounting/balance-sheet` | Balance sheet |
| GET | `/api/v1/accounting/profit-loss` | P&L statement |

## Development

### Running Tests

```bash
# All tests
php artisan test

# With coverage report
php artisan test --coverage=coverage

# Watch mode (requires tailwindcss)
npm run test:watch

# Dusk browser tests
php artisan dusk
```

### Code Style

```bash
# Lint with Laravel Pint (PSR-12)
./vendor/bin/pint

# Check for issues
./vendor/bin/pint --test
```

### Database

```bash
# Rollback and re-migrate
php artisan migrate:fresh

# Seed with test data
php artisan db:seed

# Fresh migrate + seed
php artisan migrate:fresh --seed
```

### Queue Workers

```bash
# Start Horizon (recommended)
php artisan horizon

# Start traditional queue worker
php artisan queue:work redis --sleep=3 --tries=3

# Monitor queue health
php artisan queue:health
```

## License

MIT License

## Support

For issues and feature requests, please create an issue on GitHub.