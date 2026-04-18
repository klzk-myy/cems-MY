# CEMS-MY

Currency Exchange Management System for Malaysian Money Services Businesses (MSB), compliant with Bank Negara Malaysia (BNM) AML/CFT requirements.

## Features

- **Foreign Currency Trading** - Buy/sell transactions with real-time position tracking
- **Till/Counter Management** - Full lifecycle: open, close, handover with float management
- **Double-Entry Accounting** - Complete ledger with trial balance, P&L, balance sheet
- **AML/CFT Compliance** - CDD levels, CTOS reporting, STR generation, sanctions screening
- **Risk Scoring** - Customer risk assessment with automated monitoring
- **BNM Reporting** - MSB2 daily, LCTR monthly, LMCA, LVR quarterly reports

## Tech Stack

- Laravel 10.x (PHP 8.1+)
- MySQL 8.0
- Redis (queues, caching)
- Laravel Horizon (queue management)
- Laravel Sanctum (API authentication)

## Requirements

- PHP 8.1+
- MySQL 8.0+
- Redis 6+
- Composer 2.x

## Installation

```bash
# Clone the repository
git clone https://github.com/klzk-myy/cems-my.git
cd cems-my

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Start the development server
php artisan serve
```

## Configuration

Copy `.env.example` to `.env` and configure:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cems_my
DB_USERNAME=root
DB_PASSWORD=

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

## Commands

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --filter=TransactionWorkflowTest

# Generate BNM reports
php artisan report:msb2 --date=2026-04-18

# Clear caches
php artisan config:clear && php artisan route:clear && php artisan view:clear

# List routes
php artisan route:list
```

## Architecture

```
app/
├── Console/Commands/  # Artisan CLI commands
├── Enums/             # PHP 8.1 enums (29 enums)
├── Events/            # Event-driven architecture
├── Exceptions/Domain/ # Typed domain exceptions
├── Http/
│   ├── Controllers/   # Thin controllers
│   ├── Middleware/    # Auth, RBAC, rate limiting
│   ├── Requests/      # Form validation
│   └── Resources/     # API transformers
├── Models/             # 57 Eloquent models
├── Services/          # 55 business logic services
└── Observers/         # Model event hooks
```

### Key Patterns

- **Enum-Based RBAC** - Role permissions via `UserRole` enum
- **BCMath Precision** - All monetary calculations via `MathService`
- **Stock Reservations** - Concurrency control for pending transactions
- **Event-Driven** - Audit logging, notifications, compliance triggers
- **Domain Exceptions** - Typed exceptions for business rule violations

## Security

- MFA required for all roles (BNM compliance)
- IP-based blocking after failed login attempts
- Strict rate limiting on sensitive endpoints
- Audit log with cryptographic hash chaining
- Password complexity enforcement

## Compliance

- **CDD Levels**: Simplified (< RM 3,000), Standard (RM 3,000-49,999), Enhanced (≥ RM 50,000)
- **CTOS Reporting**: Cash transactions ≥ RM 10,000
- **Structuring Detection**: 7-day lookback for aggregation patterns
- **STR Generation**: Suspicious transaction report workflow

## License

MIT