# CEMS-MY (Currency Exchange Management System) - Complete Design Specification

**Version**: 1.0  
**Generated**: 2026-04-13  
**Framework**: Laravel 10.x  
**Purpose**: Bank Negara Malaysia (BNM) MSB AML/CFT Compliance Platform  
**Database**: MySQL 8.0+ (utf8mb4_unicode_ci, InnoDB)

---

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [System Architecture](#2-system-architecture)
3. [Technology Stack](#3-technology-stack)
4. [Database Schema (Complete)](#4-database-schema-complete)
5. [Enums Reference](#5-enums-reference)
6. [Models Reference](#6-models-reference)
7. [Services Reference](#7-services-reference)
8. [Controllers Reference](#8-controllers-reference)
9. [Middleware Reference](#9-middleware-reference)
10. [Routes Reference](#10-routes-reference)
11. [Artisan Commands](#11-artisan-commands)
12. [Background Jobs](#12-background-jobs)
13. [Events & Listeners](#13-events--listeners)
14. [Configuration Files](#14-configuration-files)
15. [Navigation Structure](#15-navigation-structure)
16. [Business Rules](#16-business-rules)
17. [Workflows](#17-workflows)
18. [Security Implementation](#18-security-implementation)
19. [API Reference](#19-api-reference)
20. [Frontend/Views](#20-frontendviews)
21. [Testing Structure](#21-testing-structure)

---

## 1. Project Overview

### Project Name
**CEMS-MY** - Currency Exchange Management System for Malaysian Money Services Businesses

### Core Purpose
A Laravel-based web application for Malaysian MSB (Money Services Business) operators to handle:
- Foreign currency trading (buy/sell)
- Till/counter management with custody chains
- Double-entry accounting
- AML/CFT compliance (Bank Negara Malaysia requirements)
- Multi-branch support
- Regulatory reporting (LCTR, MSB2, LMCA, QLVR)

### Key Compliance Features
- **CDD (Customer Due Diligence)**: Three-tier system (Simplified/Standard/Enhanced)
- **CTOS Reporting**: All cash transactions >= RM 10,000 (Buy AND Sell)
- **STR (Suspicious Transaction Reports)**: >= RM 50,000 threshold
- **Sanctions Screening**: OFAC, UN, EU, MOFA lists
- **Transaction Monitoring**: Velocity, structuring, pattern detection
- **MFA Enforcement**: All users require TOTP MFA
- **Audit Logging**: Tamper-evident hash chaining (SHA-256)
- **7-Year Retention**: BNM requirement for audit logs

### Role Hierarchy
```
Admin > ComplianceOfficer > Manager > Teller
```
- **Admin**: Full system access, user management, branch setup
- **ComplianceOfficer**: AML workflows, EDD, STR, case management, risk dashboard
- **Manager**: Approve large transactions, counter management, stock transfers
- **Teller**: Create transactions, open/close counters

---

## 2. System Architecture

### Layer Structure
```
app/
├── Config/              # Navigation and application configuration
├── Console/Commands/    # 31 Artisan CLI commands
├── Enums/               # 26 PHP 8.1 enums
├── Events/              # 6 event classes
├── Http/
│   ├── Controllers/     # 45+ controllers
│   ├── Middleware/      # 20 middleware classes
│   └── Requests/        # Form request validation classes
├── Jobs/                # Background job classes
├── Listeners/           # Event listeners
├── Models/              # 57+ Eloquent models
├── Notifications/        # Notification classes
├── Providers/            # Service providers
└── Services/            # 56+ business logic services
```

### Architectural Patterns

**1. Service Layer Pattern**
- Controllers inject services via constructor DI (no `app()` service locator)
- Example:
```php
public function __construct(
    protected CurrencyPositionService $positionService,
    protected ComplianceService $complianceService,
) {}
```

**2. Enum-Based RBAC**
- All role checks use PHP enums in `App\Enums\UserRole`
- Permission methods: `$role->canApproveLargeTransactions()`, `$role->canViewReports()`
- Status/type enums organized by domain

**3. Double-Entry Accounting**
- `AccountingService` creates journal entries for every transaction
- `LedgerService` maintains running balances
- `RevaluationService` handles monthly currency revaluation
- Account codes use `AccountCode` enum

**4. BCMath Precision**
- All monetary calculations use `App\Services\MathService`
- Never cast money values to PHP `float`

**5. Event-Driven Architecture**
- Events fire for critical operations
- Listeners handle audit logging, notifications, compliance triggers

**6. Optimistic Locking**
- Transactions use `version` column for concurrency control
- `lockForUpdate()` for position updates

---

## 3. Technology Stack

### Core Framework
- **PHP**: 8.1+
- **Laravel**: 10.x
- **Database**: MySQL 8.0+ (InnoDB, utf8mb4_unicode_ci)

### Key Packages/Dependencies
| Package | Purpose |
|---------|---------|
| Laravel Sanctum | API token authentication |
| Laravel Horizon | Queue job management |
| Laravel Pint | PSR-12 code formatting |
| PHPUnit | Testing framework |
| DomPDF | PDF report generation |

### Configuration Files (22 total)
```
config/
├── accounting.php      # Accounting module settings
├── app.php              # Application core
├── auth.php              # Authentication config
├── backup.php            # Backup system settings
├── broadcasting.php      # Event broadcasting
├── cache.php             # Cache configuration
├── cems.php              # CEMS-specific settings (thresholds, MFA, BNM)
├── cors.php              # CORS settings
├── database.php           # Database connection
├── dompdf.php             # PDF generation
├── filesystems.php         # File storage
├── hashing.php             # Password hashing
├── horizon.php             # Queue management
├── logging.php             # Logging configuration
├── mail.php                # Email settings
├── notifications.php        # Notification channels
├── queue.php               # Queue configuration
├── sanctum.php              # API authentication
├── security.php            # Security settings (rate limits, HSTS, passwords)
├── services.php             # Third-party services
├── session.php              # Session configuration
├── str.php                  # STR reporting config
├── sanctions.php             # Sanctions list settings
└── view.php                 # View configuration
```

---

## 4. Database Schema (Complete)

### Database Information
| Property | Value |
|----------|-------|
| **Database Name** | `cems_my` |
| **Character Set** | utf8mb4 |
| **Collation** | utf8mb4_unicode_ci |
| **Engine** | InnoDB |
| **Total Tables** | 57+ migrations |

### Naming Conventions
- **Tables**: snake_case, plural (e.g., `users`, `transactions`)
- **Columns**: snake_case (e.g., `created_at`, `customer_id`)
- **Foreign Keys**: `{table}_id` (e.g., `customer_id`, `user_id`)
- **Indexes**: `idx_{column}` or `idx_{table}_{column}`
- **Timestamps**: All tables include `created_at` and `updated_at`

---

### Table: users

Stores system user accounts with role-based access control.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Unique identifier |
| `username` | varchar(50) | unique, not null | Login username |
| `email` | varchar(255) | unique, not null | Email address |
| `password_hash` | varchar(255) | not null | Bcrypt hashed password |
| `role` | enum('teller','manager','compliance_officer','admin') | not null, default: 'teller' | User role |
| `mfa_enabled` | tinyint(1) | default: 0 | MFA enabled flag |
| `mfa_secret` | varchar(32) | nullable | TOTP secret key |
| `mfa_verified_at` | timestamp | nullable | Last MFA verification |
| `is_active` | tinyint(1) | default: 1 | Account active status |
| `last_login_at` | timestamp | nullable | Last login timestamp |
| `deleted_at` | timestamp | nullable | Soft delete timestamp |
| `branch_id` | bigint unsigned | FK, nullable | Assigned branch |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

**Indexes:**
- `idx_role` (role)
- `idx_is_active` (is_active)
- `idx_branch_id` (branch_id)

**Roles:**
- `teller`: Front-line staff, create transactions
- `manager`: Approve large transactions, manage tills
- `compliance_officer`: Review flagged transactions, AML workflows
- `admin`: Full system access

---

### Table: branches

Multi-branch support (HQ, branches).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Branch ID |
| `code` | varchar(20) | unique, not null | Branch code |
| `name` | varchar(255) | not null | Branch name |
| `address` | text | nullable | Branch address |
| `phone` | varchar(20) | nullable | Contact phone |
| `email` | varchar(255) | nullable | Contact email |
| `is_active` | tinyint(1) | default: 1 | Active status |
| `is_hq` | tinyint(1) | default: 0 | HQ flag |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: customers

Customer KYC data with risk ratings and CDD levels.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Customer ID |
| `full_name` | varchar(255) | not null | Customer full name |
| `id_type` | enum('mykad','passport','police','army','other') | not null | ID document type |
| `id_number_encrypted` | text | not null | Encrypted ID number |
| `nationality` | varchar(100) | not null | Country of citizenship |
| `date_of_birth` | date | not null | Birth date |
| `address` | text | nullable | Residential address |
| `phone` | varchar(20) | not null | Contact number |
| `email` | varchar(255) | nullable | Email address |
| `occupation` | varchar(100) | nullable | Job title |
| `employer` | varchar(255) | nullable | Employer name |
| `pep_status` | tinyint(1) | default: 0 | Politically Exposed Person |
| `risk_score` | int | default: 0 | Risk score (0-100) |
| `risk_rating` | enum('Low','Medium','High') | default: 'Low' | Risk level |
| `risk_trend` | enum('improving','stable','deteriorating') | default: 'stable' | Risk trend |
| `risk_assessed_at` | timestamp | nullable | Last risk assessment |
| `last_transaction_at` | timestamp | nullable | Last transaction date |
| `sanctions_screened_at` | timestamp | nullable | Last sanctions screening |
| `deleted_at` | timestamp | nullable | Soft delete |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

**Indexes:**
- `idx_risk_rating` (risk_rating)
- `idx_pep_status` (pep_status)
- `idx_created_at` (created_at)

---

### Table: customer_documents

KYC document storage.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Document ID |
| `customer_id` | bigint unsigned | FK, not null | Reference to customer |
| `document_type` | enum('id_front','id_back','proof_of_address','employer_letter','other') | not null | Document type |
| `file_path` | varchar(500) | not null | Storage path |
| `file_name` | varchar(255) | not null | Original filename |
| `file_size` | int | not null | File size in bytes |
| `mime_type` | varchar(100) | not null | File MIME type |
| `verified` | tinyint(1) | default: 0 | Verification status |
| `verified_at` | timestamp | nullable | Verification timestamp |
| `verified_by` | bigint unsigned | FK, nullable | Verifier user ID |
| `rejection_reason` | text | nullable | Rejection reason |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: customer_risk_history

Tracks changes to customer risk ratings.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | History ID |
| `customer_id` | bigint unsigned | FK, not null | Reference to customer |
| `old_risk_rating` | enum('Low','Medium','High') | nullable | Previous rating |
| `new_risk_rating` | enum('Low','Medium','High') | not null | New rating |
| `risk_score` | int | not null | Risk score |
| `reason` | text | nullable | Reason for change |
| `assessed_by` | bigint unsigned | FK, not null | Assessor user ID |
| `created_at` | timestamp | default: current | Record creation |

---

### Table: currencies

Master table for supported currencies.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `code` | varchar(3) | PK | ISO 4217 currency code |
| `name` | varchar(100) | not null | Currency name |
| `symbol` | varchar(10) | not null | Currency symbol |
| `decimal_places` | tinyint | default: 2 | Decimal precision |
| `is_active` | tinyint(1) | default: 1 | Trading enabled |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

**Supported Currencies:**
- `USD`: US Dollar
- `EUR`: Euro
- `GBP`: British Pound
- `SGD`: Singapore Dollar
- `JPY`: Japanese Yen
- `AUD`: Australian Dollar
- `CNY`: Chinese Yuan
- `MYR`: Malaysian Ringgit (base)

---

### Table: exchange_rates

Current exchange rates for all currencies.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Rate ID |
| `currency_code` | varchar(3) | FK, not null | Currency code |
| `rate` | decimal(18,6) | not null | Exchange rate vs MYR |
| `buy_rate` | decimal(18,6) | not null | Buy rate |
| `sell_rate` | decimal(18,6) | not null | Sell rate |
| `effective_date` | date | not null | Rate effective date |
| `source` | varchar(100) | nullable | Rate source |
| `is_active` | tinyint(1) | default: 1 | Active status |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: exchange_rate_histories

Historical record of rate changes.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | History ID |
| `currency_code` | varchar(3) | FK, not null | Currency code |
| `old_rate` | decimal(18,6) | not null | Previous rate |
| `new_rate` | decimal(18,6) | not null | New rate |
| `old_buy_rate` | decimal(18,6) | not null | Previous buy rate |
| `new_buy_rate` | decimal(18,6) | not null | New buy rate |
| `old_sell_rate` | decimal(18,6) | not null | Previous sell rate |
| `new_sell_rate` | decimal(18,6) | not null | New sell rate |
| `changed_by` | bigint unsigned | FK, nullable | User who changed |
| `reason` | text | nullable | Change reason |
| `created_at` | timestamp | default: current | Record creation |

---

### Table: transactions

Core transaction table for all buy/sell operations.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Transaction ID |
| `customer_id` | bigint unsigned | FK, nullable | Customer ID |
| `user_id` | bigint unsigned | FK, not null | Created by user |
| `counter_session_id` | bigint unsigned | FK, nullable | Counter session |
| `branch_id` | bigint unsigned | FK, nullable | Branch ID |
| `till_id` | varchar(50) | not null | Till identifier |
| `type` | enum('Buy','Sell') | not null | Transaction type |
| `currency_code` | varchar(3) | FK, not null | Currency code |
| `amount_local` | decimal(18,4) | not null | Amount in MYR |
| `amount_foreign` | decimal(18,4) | not null | Foreign amount |
| `rate` | decimal(18,6) | not null | Exchange rate applied |
| `rate_override` | decimal(18,6) | nullable | Override rate reason |
| `spread_amount` | decimal(18,4) | nullable | Spread earned |
| `purpose` | varchar(255) | nullable | Transaction purpose |
| `source_of_funds` | varchar(255) | nullable | Source of funds |
| `status` | enum | not null, default: 'Pending' | Transaction status |
| `hold_reason` | varchar(500) | nullable | Reason for hold |
| `cdd_level` | enum('Simplified','Standard','Enhanced') | default: 'Standard' | CDD level applied |
| `approved_by` | bigint unsigned | FK, nullable | Approver user ID |
| `approved_at` | timestamp | nullable | Approval timestamp |
| `cancelled_at` | timestamp | nullable | Cancellation timestamp |
| `cancelled_by` | bigint unsigned | FK, nullable | Canceller user ID |
| `cancellation_reason` | varchar(500) | nullable | Cancellation reason |
| `original_transaction_id` | bigint unsigned | FK, nullable | For refunds |
| `is_refund` | tinyint(1) | default: 0 | Is this a refund |
| `idempotency_key` | varchar(100) | unique, nullable | Duplicate prevention |
| `version` | int | default: 0 | Optimistic locking |
| `ctos_reported` | tinyint(1) | default: 0 | CTOS report filed |
| `ctos_reported_at` | timestamp | nullable | CTOS report timestamp |
| `deleted_at` | timestamp | nullable | Soft delete |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

**Indexes:**
- `idx_customer_id` (customer_id)
- `idx_user_id` (user_id)
- `idx_currency_code` (currency_code)
- `idx_status` (status)
- `idx_type` (type)
- `idx_created_at` (created_at)
- `idx_idempotency_key` (idempotency_key) - UNIQUE
- `idx_status_created` (status, created_at) - Composite
- `idx_customer_date` (customer_id, created_at) - Composite

**Transaction Status Values:**
- `Draft` - Initial state, not yet submitted
- `PendingApproval` - Awaiting approval (>= RM 50,000)
- `Approved` - Approved and ready for processing
- `Processing` - Stock movements, accounting running
- `Completed` - All side effects completed
- `Finalized` - Day-end processed, cannot be modified
- `Cancelled` - Cancelled before completion (requires manager approval)
- `Reversed` - Reversed after completion with compensating entries
- `Failed` - Processing failed, awaiting recovery
- `Rejected` - Rejected during approval
- `Pending` - Legacy (returns false in isPending())
- `OnHold` - Legacy (returns false in isOnHold())

---

### Table: transaction_confirmations

Large transaction manager confirmation records.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Confirmation ID |
| `transaction_id` | bigint unsigned | FK, unique, not null | Transaction ID |
| `confirmed_by` | bigint unsigned | FK, not null | Confirming manager |
| `confirmation_method` | enum('in_person','phone','system') | not null | Method |
| `verified_mfa` | tinyint(1) | default: 1 | MFA verified |
| `notes` | text | nullable | Confirmation notes |
| `confirmed_at` | timestamp | not null | Confirmation time |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: transaction_errors

Error logging for failed transactions.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Error ID |
| `transaction_id` | bigint unsigned | FK, not null | Transaction ID |
| `error_type` | varchar(50) | not null | Error classification |
| `error_message` | text | not null | Error message |
| `error_context` | json | nullable | Stack trace/context |
| `resolved` | tinyint(1) | default: 0 | Resolution status |
| `resolved_by` | bigint unsigned | FK, nullable | Resolver user ID |
| `resolved_at` | timestamp | nullable | Resolution time |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: transaction_state_history

Audit trail for transaction state changes.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | History ID |
| `transaction_id` | bigint unsigned | FK, not null | Transaction ID |
| `from_status` | varchar(30) | nullable | Previous status |
| `to_status` | varchar(30) | not null | New status |
| `changed_by` | bigint unsigned | FK, nullable | User who changed |
| `change_reason` | varchar(255) | nullable | Reason for change |
| `metadata` | json | nullable | Additional context |
| `created_at` | timestamp | default: current | Record creation |

---

### Table: transaction_imports

Bulk transaction import tracking.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Import ID |
| `user_id` | bigint unsigned | FK, not null | Importing user |
| `file_name` | varchar(255) | not null | Original filename |
| `file_path` | varchar(500) | not null | Storage path |
| `status` | enum('pending','processing','completed','completed_with_errors','failed') | not null | Import status |
| `total_rows` | int | default: 0 | Total rows |
| `processed_rows` | int | default: 0 | Processed rows |
| `success_rows` | int | default: 0 | Successful rows |
| `error_rows` | int | default: 0 | Failed rows |
| `error_log` | text | nullable | Error details |
| `completed_at` | timestamp | nullable | Completion time |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: counters

Physical counter/till definitions.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Counter ID |
| `counter_code` | varchar(50) | unique, not null | Counter code |
| `counter_name` | varchar(255) | not null | Counter name |
| `branch_id` | bigint unsigned | FK, not null | Branch ID |
| `location` | varchar(255) | nullable | Physical location |
| `is_active` | tinyint(1) | default: 1 | Active status |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: counter_sessions

User sessions at counters with opening/closing floats.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Session ID |
| `counter_id` | bigint unsigned | FK, not null | Counter ID |
| `user_id` | bigint unsigned | FK, not null | User ID |
| `session_start` | timestamp | not null | Start time |
| `session_end` | timestamp | nullable | End time |
| `opening_balance` | decimal(18,4) | not null | Opening amount |
| `closing_balance` | decimal(18,4) | nullable | Closing amount |
| `variance` | decimal(18,4) | default: 0.0000 | Cash variance |
| `variance_approved_by` | bigint unsigned | FK, nullable | Variance approver |
| `status` | enum('open','closed','handed_over') | default: 'open' | Session status |
| `closed_at` | timestamp | nullable | Actual close time |
| `closed_by` | bigint unsigned | FK, nullable | Closer user ID |
| `notes` | text | nullable | Session notes |
| `deleted_at` | timestamp | nullable | Soft delete |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

**Indexes:**
- `idx_counter_id` (counter_id)
- `idx_user_id` (user_id)
- `idx_status` (status)
- `idx_session_start` (session_start)

---

### Table: counter_handovers

Shift handover records between users.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Handover ID |
| `counter_session_id` | bigint unsigned | FK, not null | Counter session |
| `counter_id` | bigint unsigned | FK, not null | Counter ID |
| `from_user_id` | bigint unsigned | FK, not null | Handing over user |
| `to_user_id` | bigint unsigned | FK, not null | Receiving user |
| `currency_code` | varchar(3) | FK, not null | Currency |
| `amount` | decimal(18,4) | not null | Amount transferred |
| `handover_time` | timestamp | not null | Handover timestamp |
| `confirmed` | tinyint(1) | default: 0 | Confirmation status |
| `confirmed_at` | timestamp | nullable | Confirmation timestamp |
| `approved_by` | bigint unsigned | FK, nullable | Manager approver |
| `notes` | text | nullable | Handover notes |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: till_balances

Daily till opening and closing records.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Balance ID |
| `till_id` | varchar(50) | not null | Till identifier |
| `counter_session_id` | bigint unsigned | FK, nullable | Counter session |
| `currency_code` | varchar(3) | FK, not null | Currency code |
| `date` | date | not null | Transaction date |
| `opening_balance` | decimal(18,4) | not null | Opening amount |
| `closing_balance` | decimal(18,4) | nullable | Closing amount |
| `variance` | decimal(18,4) | default: 0.0000 | Cash variance |
| `variance_reason` | text | nullable | Variance explanation |
| `opened_by` | bigint unsigned | FK, not null | User who opened |
| `closed_by` | bigint unsigned | FK, nullable | User who closed |
| `closed_at` | timestamp | nullable | Closing timestamp |
| `transaction_total` | decimal(18,4) | default: 0.0000 | Total transactions |
| `foreign_total` | decimal(18,4) | default: 0.0000 | Foreign total |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: currency_positions

Real-time foreign currency inventory tracking with weighted average cost.

| Column | | Type | Constraints | Description |
|----------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Position ID |
| `currency_code` | varchar(3) | FK, not null | Currency code |
| `till_id` | varchar(50) | nullable | Till identifier (null = branch HQ pool) |
| `branch_id` | bigint unsigned | FK, nullable | Branch ID |
| `balance` | decimal(18,4) | default: 0.0000 | Current balance |
| `avg_cost_rate` | decimal(18,6) | default: 0.000000 | Weighted avg cost |
| `last_valuation_rate` | decimal(18,6) | nullable | Last valuation rate |
| `unrealized_pnl` | decimal(18,4) | default: 0.0000 | Unrealized P&L |
| `last_valuation_at` | timestamp | nullable | Last valuation date |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

**Indexes:**
- `idx_currency_till` (currency_code, till_id) - UNIQUE
- `idx_branch_id` (branch_id)

---

### Table: stock_transfers

Inter-branch stock transfers with multi-stage approval workflow.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Transfer ID |
| `reference` | varchar(50) | unique, not null | Transfer reference number |
| `transfer_type` | enum('Standard','Emergency','Scheduled','Return') | not null | Transfer type |
| `from_branch_id` | bigint unsigned | FK, not null | Source branch |
| `to_branch_id` | bigint unsigned | FK, not null | Destination branch |
| `status` | enum | not null | Transfer status |
| `requested_by` | bigint unsigned | FK, not null | Requesting user |
| `approved_by_bm` | bigint unsigned | FK, nullable | Branch Manager approver |
| `approved_at_bm` | timestamp | nullable | BM approval timestamp |
| `approved_by_hq` | bigint unsigned | FK, nullable | HQ/Admin approver |
| `approved_at_hq` | timestamp | nullable | HQ approval timestamp |
| `dispatched_at` | timestamp | nullable | Dispatch timestamp |
| `received_at` | timestamp | nullable | Receive timestamp |
| `completed_at` | timestamp | nullable | Completion timestamp |
| `notes` | text | nullable | Transfer notes |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

**Transfer Status Values:**
- `Requested`
- `BranchManagerApproved`
- `HQApproved`
- `InTransit`
- `PartiallyReceived`
- `Completed`
- `Cancelled`
- `Rejected`

---

### Table: stock_transfer_items

Line items for stock transfers.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Item ID |
| `stock_transfer_id` | bigint unsigned | FK, not null | Parent transfer |
| `currency_code` | varchar(3) | FK, not null | Currency code |
| `denomination` | varchar(50) | nullable | Banknote denomination |
| `quantity` | int | not null | Number of units |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: chart_of_accounts

Chart of accounts structure (MIA compliant).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Account ID |
| `account_code` | varchar(20) | unique, not null | Account code |
| `account_name` | varchar(255) | not null | Account name |
| `account_type` | enum('Asset','Liability','Equity','Revenue','Expense') | not null | Account type |
| `parent_id` | bigint unsigned | FK, nullable | Parent account |
| `is_active` | tinyint(1) | default: 1 | Active status |
| `is_cash` | tinyint(1) | default: 0 | Cash account flag |
| `is_off_balance` | tinyint(1) | default: 0 | Off-balance sheet |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

**Default Account Codes (AccountCode enum):**
```
ASSETS
  1000 - CASH_MYR (Cash in Hand - MYR)
  2000 - FOREIGN_CURRENCY_INVENTORY (Foreign Currency Inventory)
  2100 - RECEIVABLES (Accounts Receivable)
  2200 - OTHER_CURRENT_ASSETS (Other Current Assets)

LIABILITIES
  3000 - PAYABLES (Accounts Payable)
  3100 - ACCRUALS (Accruals)

EQUITY
  4000 - CAPITAL (Capital)
  4100 - RETAINED_EARNINGS (Retained Earnings)
  4200 - CURRENT_YEAR_EARNINGS (Current Year Earnings)

REVENUE
  5000 - FOREX_TRADING_REVENUE (Forex Trading Revenue)
  5100 - REVALUATION_GAINS (Revaluation Gains)

EXPENSE
  6000 - FOREX_LOSS (Forex Loss)
  6100 - REVALUATION_LOSS (Revaluation Loss)
  6200 - OPERATING_EXPENSES (Operating Expenses)
```

---

### Table: departments

Organizational departments.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Department ID |
| `code` | varchar(20) | unique, not null | Department code |
| `name` | varchar(255) | not null | Department name |
| `is_active` | tinyint(1) | default: 1 | Active status |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: cost_centers

Cost center tracking.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Cost Center ID |
| `code` | varchar(20) | unique, not null | Cost center code |
| `name` | varchar(255) | not null | Cost center name |
| `department_id` | bigint unsigned | FK, nullable | Department ID |
| `is_active` | tinyint(1) | default: 1 | Active status |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: accounting_periods

Fiscal periods for accounting closure.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Period ID |
| `period_code` | varchar(20) | unique, not null | Period code (e.g., "2026-04") |
| `start_date` | date | not null | Period start |
| `end_date` | date | not null | Period end |
| `status` | enum('Open','Closing','Closed') | default: 'Open' | Period status |
| `closed_by` | bigint unsigned | FK, nullable | Closer user ID |
| `closed_at` | timestamp | nullable | Closure timestamp |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: fiscal_years

Annual fiscal year management.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Fiscal Year ID |
| `year_code` | varchar(10) | unique, not null | Year code (e.g., "2026") |
| `start_date` | date | not null | FY start date |
| `end_date` | date | not null | FY end date |
| `status` | enum('Open','Closed') | default: 'Open' | FY status |
| `closed_by` | bigint unsigned | FK, nullable | Closer user ID |
| `closed_at` | timestamp | nullable | Closure timestamp |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: journal_entries

Double-entry bookkeeping journal entries.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Entry ID |
| `entry_number` | varchar(50) | unique, not null | Auto-generated entry number |
| `entry_date` | date | not null | Transaction date |
| `period_id` | bigint unsigned | FK, nullable | Accounting period |
| `fiscal_year_id` | bigint unsigned | FK, nullable | Fiscal year |
| `reference_type` | varchar(50) | nullable | Source (Transaction, Manual, etc.) |
| `reference_id` | bigint unsigned | nullable | Source ID |
| `description` | varchar(500) | not null | Entry description |
| `total_amount` | decimal(18,4) | not null | Total entry amount |
| `workflow_status` | enum('draft','pending','posted') | default: 'draft' | Workflow status |
| `source_module` | varchar(50) | nullable | Source module |
| `reversal_of_id` | bigint unsigned | FK, nullable | Reversal of entry |
| `reversed_at` | timestamp | nullable | Reversal timestamp |
| `posted_by` | bigint unsigned | FK, nullable | Poster user ID |
| `posted_at` | timestamp | nullable | Posting timestamp |
| `created_by` | bigint unsigned | FK, not null | Creator user ID |
| `approved_by` | bigint unsigned | FK, nullable | Approver user ID |
| `approved_at` | timestamp | nullable | Approval timestamp |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

**Indexes:**
- `idx_entry_date` (entry_date)
- `idx_period_id` (period_id)
- `idx_reference` (reference_type, reference_id) - Composite

---

### Table: journal_lines

Individual debit/credit lines for journal entries.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Line ID |
| `journal_entry_id` | bigint unsigned | FK, not null | Parent entry |
| `account_id` | bigint unsigned | FK, not null | Account ID |
| `department_id` | bigint unsigned | FK, nullable | Department ID |
| `cost_center_id` | bigint unsigned | FK, nullable | Cost center ID |
| `debit_amount` | decimal(18,4) | default: 0.0000 | Debit amount |
| `credit_amount` | decimal(18,4) | default: 0.0000 | Credit amount |
| `description` | varchar(500) | nullable | Line description |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: account_ledger

Running balance ledger entries.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Ledger ID |
| `account_id` | bigint unsigned | FK, not null | Account ID |
| `journal_line_id` | bigint unsigned | FK, not null | Journal line |
| `branch_id` | bigint unsigned | FK, nullable | Branch ID |
| `transaction_date` | date | not null | Transaction date |
| `entry_type` | enum('debit','credit') | not null | Entry type |
| `amount` | decimal(18,4) | not null | Amount |
| `running_balance` | decimal(18,4) | not null | Running balance after entry |
| `reference_type` | varchar(50) | nullable | Source type |
| `reference_id` | bigint unsigned | nullable | Source ID |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: revaluation_entries

Month-end currency revaluation entries.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Entry ID |
| `currency_code` | varchar(3) | FK, not null | Currency code |
| `old_rate` | decimal(18,6) | not null | Previous rate |
| `new_rate` | decimal(18,6) | not null | Current rate |
| `gain_loss_amount` | decimal(18,4) | not null | Revaluation P&L |
| `journal_entry_id` | bigint unsigned | FK, nullable | Related journal entry |
| `revaluation_date` | date | not null | Revaluation date |
| `created_by` | bigint unsigned | FK, not null | Creator user ID |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: budgets

Budget vs actual tracking.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Budget ID |
| `account_id` | bigint unsigned | FK, not null | Account ID |
| `period_code` | varchar(20) | not null | Period code (YYYY-MM) |
| `amount` | decimal(18,4) | not null | Budget amount |
| `created_by` | bigint unsigned | FK, not null | Creator user ID |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

**Indexes:**
- `idx_account_period` (account_id, period_code) - UNIQUE

---

### Table: bank_reconciliations

Bank reconciliation with outstanding checks.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Reconciliation ID |
| `period_code` | varchar(20) | not null | Period code |
| `bank_account_id` | bigint unsigned | FK, not null | Bank account |
| `statement_balance` | decimal(18,4) | not null | Bank statement balance |
| `book_balance` | decimal(18,4) | not null | Book balance |
| `difference` | decimal(18,4) | default: 0.0000 | Difference |
| `imported_at` | timestamp | nullable | Import timestamp |
| `reconciled_by` | bigint unsigned | FK, nullable | Reconciler user ID |
| `reconciled_at` | timestamp | nullable | Reconciliation time |
| `status` | enum('draft','reconciled') | default: 'draft' | Status |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: bank_reconciliation_items

Line items for bank reconciliation.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Item ID |
| `reconciliation_id` | bigint unsigned | FK, not null | Parent reconciliation |
| `type` | enum('deposit','check','other') | not null | Item type |
| `reference` | varchar(100) | nullable | Check/deposit reference |
| `date` | date | not null | Transaction date |
| `description` | varchar(255) | nullable | Description |
| `amount` | decimal(18,4) | not null | Amount |
| `matched` | tinyint(1) | default: 0 | Match status |
| `matched_entry_id` | bigint unsigned | FK, nullable | Matched journal entry |
| `exception_reason` | text | nullable | Exception reason |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: sanction_lists

International sanction lists.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | List ID |
| `list_name` | varchar(255) | not null | List name |
| `list_type` | varchar(50) | not null | Type (UN, OFAC, EU, MOFA) |
| `country` | varchar(100) | nullable | Issuing country |
| `last_updated` | date | not null | Last update date |
| `total_records` | int | default: 0 | Total entries |
| `is_active` | tinyint(1) | default: 1 | Active status |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: sanction_entries

Individual entries on sanction lists.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Entry ID |
| `list_id` | bigint unsigned | FK, not null | Sanction list |
| `name` | varchar(255) | not null | Sanctioned name |
| `aliases` | text | nullable | Alternative names |
| `nationality` | varchar(100) | nullable | Nationality |
| `date_of_birth` | date | nullable | Birth date |
| `place_of_birth` | varchar(255) | nullable | Birth place |
| `id_number` | varchar(100) | nullable | ID number |
| `address` | text | nullable | Known addresses |
| `reason` | text | nullable | Sanction reason |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

**Indexes:**
- `idx_name` (name)
- `idx_dob` (date_of_birth)

---

### Table: flagged_transactions

Transactions flagged for compliance review.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Flag ID |
| `transaction_id` | bigint unsigned | FK, not null | Transaction ID |
| `flag_type` | enum | not null | Flag reason |
| `severity` | enum('Low','Medium','High','Critical') | default: 'Medium' | Flag severity |
| `description` | text | nullable | Flag details |
| `status` | enum('Open','UnderReview','Resolved','Escalated','Rejected') | default: 'Open' | Review status |
| `assigned_to` | bigint unsigned | FK, nullable | Assigned officer |
| `reviewed_by` | bigint unsigned | FK, nullable | Reviewer ID |
| `reviewed_at` | timestamp | nullable | Review timestamp |
| `resolution_notes` | text | nullable | Resolution notes |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: compliance_findings

Automated compliance findings from the monitoring engine.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Finding ID |
| `finding_type` | varchar(50) | not null | Type of finding |
| `severity` | varchar(20) | not null | Severity level |
| `subject_type` | varchar(50) | not null | Subject type (Customer, Transaction) |
| `subject_id` | bigint unsigned | not null | Subject ID |
| `details` | json | nullable | Finding details |
| `status` | varchar(20) | default: 'New' | Status |
| `assigned_to` | bigint unsigned | FK, nullable | Assigned officer |
| `reviewed_by` | bigint unsigned | FK, nullable | Reviewer ID |
| `reviewed_at` | timestamp | nullable | Review timestamp |
| `resolution_notes` | text | nullable | Resolution notes |
| `generated_at` | timestamp | not null | When finding was generated |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

**Finding Types:**
- `VelocityExceeded` - 24-hour threshold exceeded
- `StructuringPattern` - Multiple small transactions
- `AggregateTransaction` - Aggregate threshold concern
- `StrDeadline` - STR filing deadline approaching
- `SanctionMatch` - Sanctions list match
- `LocationAnomaly` - Geographic anomaly
- `CurrencyFlowAnomaly` - Round-tripping pattern
- `CounterfeitAlert` - Counterfeit currency detected
- `RiskScoreChange` - Significant risk score change

---

### Table: compliance_cases

Compliance investigation and case management.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Case ID |
| `case_number` | varchar(20) | unique, not null | Auto-generated (CASE-YYYY-NNNNN) |
| `case_type` | enum('Investigation','Edd','Str','SanctionReview','Counterfeit') | not null | Case type |
| `status` | enum('Open','UnderReview','PendingApproval','Closed','Escalated') | not null | Case status |
| `severity` | enum('Low','Medium','High','Critical') | not null | Severity level |
| `priority` | enum('Low','Medium','High','Critical') | not null | Priority level |
| `customer_id` | bigint unsigned | FK, nullable | Customer ID |
| `primary_flag_id` | bigint unsigned | FK, nullable | Primary flagged transaction |
| `primary_finding_id` | bigint unsigned | FK, nullable | Primary compliance finding |
| `assigned_to` | bigint unsigned | FK, not null | Assigned officer |
| `case_summary` | text | nullable | Initial assessment |
| `sla_deadline` | timestamp | nullable | SLA deadline |
| `escalated_at` | timestamp | nullable | When case was escalated |
| `resolved_at` | timestamp | nullable | When case was resolved |
| `resolution` | enum | nullable | Resolution outcome |
| `resolution_notes` | text | nullable | Resolution details |
| `metadata` | json | nullable | Additional metadata |
| `created_via` | enum('Automated','Manual') | not null | Creation source |
| `created_by` | bigint unsigned | FK, nullable | Creator user ID |
| `closed_by` | bigint unsigned | FK, nullable | Closer user ID |
| `closed_at` | timestamp | nullable | Closure time |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

**Case Resolutions:**
- `NoConcern` - No concern identified
- `WarningIssued` - Warning issued to customer
- `EddRequired` - Enhanced Due Diligence required
- `StrFiled` - STR filed with authorities
- `ClosedNoAction` - Closed without action

**SLA Deadlines:**
- Critical: 24 hours
- High: 48 hours
- Medium: 5 days
- Low: 10 days

---

### Table: compliance_case_notes

Notes added to compliance cases.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Note ID |
| `case_id` | bigint unsigned | FK, not null | Case ID |
| `author_id` | bigint unsigned | FK, not null | Author user ID |
| `note_type` | enum('Investigation','Update','Decision','Escalation') | not null | Note type |
| `content` | text | not null | Note content |
| `is_internal` | tinyint(1) | default: 1 | Internal note flag |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: compliance_case_documents

Documents uploaded to compliance cases.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Document ID |
| `case_id` | bigint unsigned | FK, not null | Case ID |
| `file_name` | varchar(255) | not null | Original filename |
| `file_path` | varchar(500) | not null | Storage path |
| `file_type` | varchar(100) | nullable | MIME type |
| `file_size` | int | nullable | File size |
| `uploaded_by` | bigint unsigned | FK, not null | Uploader user ID |
| `uploaded_at` | timestamp | nullable | Upload timestamp |
| `verified_at` | timestamp | nullable | Verification timestamp |
| `verified_by` | bigint unsigned | FK, nullable | Verifier user ID |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: compliance_case_links

Links between compliance cases and other entities.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Link ID |
| `case_id` | bigint unsigned | FK, not null | Case ID |
| `linked_type` | varchar(50) | not null | Linked entity type |
| `linked_id` | bigint unsigned | not null | Linked entity ID |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: alerts

System alerts requiring attention.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Alert ID |
| `alert_type` | varchar(50) | not null | Alert type |
| `severity` | enum('critical','high','medium','low') | not null | Severity |
| `title` | varchar(255) | not null | Alert title |
| `message` | text | not null | Alert message |
| `status` | enum('open','assigned','resolved','closed') | default: 'open' | Alert status |
| `assigned_to` | bigint unsigned | FK, nullable | Assigned officer |
| `resolved_by` | bigint unsigned | FK, nullable | Resolver user ID |
| `resolved_at` | timestamp | nullable | Resolution time |
| `context` | json | nullable | Additional context |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: enhanced_diligence_records

Enhanced Due Diligence records.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | EDD ID |
| `customer_id` | bigint unsigned | FK, not null | Customer ID |
| `edd_type` | enum('pep','high_risk_country','unusual_pattern','sanction_match','large_transaction','high_risk_industry') | not null | EDD type |
| `status` | enum('Incomplete','Pending_Questionnaire','Questionnaire_Submitted','Pending_Review','Approved','Rejected','Expired') | not null | Status |
| `risk_level` | enum('Low','Medium','High','Critical') | not null | Risk level |
| `template_id` | bigint unsigned | FK, nullable | Questionnaire template |
| `questionnaire_answers` | json | nullable | Submitted answers |
| `submitted_at` | timestamp | nullable | Submission timestamp |
| `reviewed_by` | bigint unsigned | FK, nullable | Reviewer user ID |
| `reviewed_at` | timestamp | nullable | Review timestamp |
| `approval_notes` | text | nullable | Approval/rejection notes |
| `expires_at` | timestamp | nullable | Expiration date |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: edd_questionnaire_templates

EDD questionnaire template definitions.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Template ID |
| `name` | varchar(255) | not null | Template name |
| `version` | varchar(20) | not null | Template version |
| `template_type` | enum('pep','high_risk_country','unusual_pattern','sanction_match','large_transaction','high_risk_industry') | not null | Template type |
| `is_active` | tinyint(1) | default: 1 | Active status |
| `questions` | json | not null | Questionnaire questions |
| `created_by` | bigint unsigned | FK, not null | Creator user ID |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: edd_document_requests

EDD document request tracking.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Request ID |
| `edd_record_id` | bigint unsigned | FK, not null | EDD record ID |
| `document_type` | varchar(100) | not null | Document type requested |
| `status` | enum('Pending','Received','Verified','Rejected') | not null | Request status |
| `file_path` | varchar(500) | nullable | Uploaded file path |
| `rejection_reason` | text | nullable | Rejection reason |
| `uploaded_at` | timestamp | nullable | Upload timestamp |
| `verified_at` | timestamp | nullable | Verification timestamp |
| `verified_by` | bigint unsigned | FK, nullable | Verifier user ID |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: customer_risk_profiles

Dynamic customer risk scoring profiles (0-100 score).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Profile ID |
| `customer_id` | bigint unsigned | FK, unique, not null | Customer ID |
| `risk_score` | int | not null | Risk score (0-100) |
| `risk_tier` | varchar(20) | not null | Risk tier |
| `risk_factors` | json | nullable | Contributing factors with weights |
| `previous_score` | int | nullable | Previous risk score |
| `score_changed_at` | timestamp | nullable | Last score change |
| `next_scheduled_recalculation` | timestamp | nullable | Next recalculation |
| `recalculation_trigger` | enum('Manual','Scheduled','EventDriven') | nullable | Trigger type |
| `locked_until` | date | nullable | Lock expiry date |
| `locked_by` | bigint unsigned | FK, nullable | Locker user ID |
| `lock_reason` | varchar(255) | nullable | Lock reason |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

**Risk Tiers:**
- Low: 0-25
- Medium: 26-50
- High: 51-75
- Critical: 76-100

---

### Table: customer_behavioral_baselines

Customer behavioral baseline for deviation detection.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Baseline ID |
| `customer_id` | bigint unsigned | FK, unique, not null | Customer ID |
| `currency_codes` | json | nullable | Commonly traded currencies |
| `avg_transaction_size_myr` | decimal(18,4) | nullable | Average transaction size |
| `avg_transaction_frequency` | decimal(10,2) | nullable | Transactions per month |
| `preferred_counter_ids` | json | nullable | Common counter locations |
| `registered_location` | varchar(255) | nullable | Registered address zone |
| `last_calculated_at` | timestamp | nullable | Last calculation |
| `baseline_version` | int | default: 1 | Baseline version |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: str_reports

Suspicious Transaction Reports.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | STR ID |
| `str_number` | varchar(50) | unique, not null | Auto-generated STR number |
| `case_id` | bigint unsigned | FK, nullable | Related compliance case |
| `customer_id` | bigint unsigned | FK, not null | Customer ID |
| `status` | enum('draft','pending_review','pending_approval','submitted','acknowledged','failed') | not null | STR status |
| `transaction_ids` | json | nullable | Related transactions |
| `narrative` | text | nullable | STR narrative |
| `prepared_by` | bigint unsigned | FK, not null | Preparer user ID |
| `reviewed_by` | bigint unsigned | FK, nullable | Reviewer user ID |
| `approved_by` | bigint unsigned | FK, nullable | Approver user ID |
| `submitted_at` | timestamp | nullable | Submission timestamp |
| `goaml_submission_id` | varchar(100) | nullable | goAML submission ID |
| `retry_count` | int | default: 0 | Submission retry count |
| `last_retry_at` | timestamp | nullable | Last retry time |
| `acknowledged_at` | timestamp | nullable | BNM acknowledgment |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: str_drafts

STR drafts before submission.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Draft ID |
| `draft_number` | varchar(50) | unique, not null | Draft number |
| `case_id` | bigint unsigned | FK, nullable | Related compliance case |
| `customer_id` | bigint unsigned | FK, not null | Customer ID |
| `transaction_ids` | json | nullable | Related transactions |
| `narrative` | text | nullable | STR narrative |
| `prepared_by` | bigint unsigned | FK, not null | Preparer user ID |
| `status` | enum('draft','generated','submitted') | default: 'draft' | Draft status |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: aml_rules

AML rule engine configuration.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Rule ID |
| `rule_name` | varchar(100) | not null | Rule name |
| `rule_type` | enum('velocity','structuring','amount_threshold','frequency','geographic') | not null | Rule type |
| `description` | text | nullable | Rule description |
| `parameters` | json | not null | Rule parameters |
| `severity` | enum('low','medium','high','critical') | not null | Alert severity |
| `is_active` | tinyint(1) | default: 1 | Active status |
| `created_by` | bigint unsigned | FK, not null | Creator user ID |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: system_logs

Comprehensive audit trail with hash chaining.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Log ID |
| `user_id` | bigint unsigned | FK, nullable | User who acted |
| `action` | varchar(100) | not null | Action type |
| `entity_type` | varchar(100) | not null | Affected entity type |
| `entity_id` | bigint unsigned | nullable | Affected entity ID |
| `old_values` | json | nullable | Previous values |
| `new_values` | json | nullable | New values |
| `description` | text | nullable | Action description |
| `ip_address` | varchar(45) | nullable | IP address |
| `user_agent` | text | nullable | User agent |
| `previous_hash` | varchar(64) | nullable | Previous log hash (chain) |
| `hash` | varchar(64) | not null | SHA-256 hash of this entry |
| `created_at` | timestamp | default: current | Record creation |

**Action Types:**
- `login`, `login_failed`, `logout`
- `user_created`, `user_updated`, `user_deleted`
- `transaction_created`, `transaction_approved`, `transaction_cancelled`
- `till_opened`, `till_closed`, `till_handed_over`
- `journal_entry_created`, `journal_entry_posted`, `journal_entry_reversed`
- `compliance_case_created`, `compliance_case_updated`, `compliance_case_closed`
- `str_created`, `str_submitted`, `str_acknowledged`

---

### Table: tasks

Task management.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Task ID |
| `task_number` | varchar(50) | unique, not null | Task number |
| `title` | varchar(255) | not null | Task title |
| `description` | text | nullable | Task description |
| `task_type` | varchar(50) | not null | Task type |
| `priority` | enum('low','medium','high','critical') | default: 'medium' | Priority |
| `status` | enum('pending','in_progress','completed','cancelled') | default: 'pending' | Status |
| `due_date` | timestamp | nullable | Due date |
| `assigned_to` | bigint unsigned | FK, nullable | Assigned user |
| `assigned_by` | bigint unsigned | FK, nullable | Assigner user ID |
| `completed_at` | timestamp | nullable | Completion time |
| `completed_by` | bigint unsigned | FK, nullable | Completer user ID |
| `cancelled_at` | timestamp | nullable | Cancellation time |
| `cancelled_by` | bigint unsigned | FK, nullable | Canceller user ID |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: approval_tasks

Multi-stage approval workflow tasks.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Approval Task ID |
| `approvable_type` | varchar(100) | not null | Related entity type |
| `approvable_id` | bigint unsigned | not null | Related entity ID |
| `approval_level` | int | not null | Approval stage level |
| `approver_role` | varchar(50) | not null | Required approver role |
| `status` | enum('pending','approved','rejected') | default: 'pending' | Status |
| `approved_by` | bigint unsigned | FK, nullable | Approver user ID |
| `approved_at` | timestamp | nullable | Approval time |
| `rejected_reason` | text | nullable | Rejection reason |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: report_templates

Custom report template definitions.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Template ID |
| `name` | varchar(255) | not null | Template name |
| `description` | text | nullable | Template description |
| `report_type` | varchar(50) | not null | Report type |
| `query_sql` | text | not null | SQL query |
| `parameters` | json | nullable | Report parameters |
| `created_by` | bigint unsigned | FK, not null | Creator ID |
| `is_active` | tinyint(1) | default: 1 | Active status |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: reports_generated

Generated reports with download tracking.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Report ID |
| `template_id` | bigint unsigned | FK, nullable | Template used |
| `report_name` | varchar(255) | not null | Report name |
| `report_type` | varchar(50) | not null | Report type |
| `parameters` | json | nullable | Report parameters |
| `file_path` | varchar(500) | not null | Storage path |
| `file_format` | enum('pdf','csv','excel','html') | not null | Format |
| `generated_by` | bigint unsigned | FK, not null | Generator ID |
| `status` | enum('pending','running','completed','failed') | default: 'pending' | Generation status |
| `download_count` | int | default: 0 | Download count |
| `expires_at` | timestamp | nullable | Expiration date |
| `error_message` | text | nullable | Error if failed |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: report_runs

Report generation execution logs.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Run ID |
| `report_id` | bigint unsigned | FK, not null | Report generated |
| `started_at` | timestamp | not null | Start time |
| `completed_at` | timestamp | nullable | Completion time |
| `status` | enum('running','completed','failed') | not null | Run status |
| `records_processed` | int | default: 0 | Records processed |
| `error_message` | text | nullable | Error message |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: report_schedules

Scheduled report configurations.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Schedule ID |
| `name` | varchar(255) | not null | Schedule name |
| `report_type` | varchar(50) | not null | Report type |
| `parameters` | json | nullable | Schedule parameters |
| `frequency` | enum('daily','weekly','monthly') | not null | Frequency |
| `next_run_at` | timestamp | not null | Next run time |
| `last_run_at` | timestamp | nullable | Last run time |
| `is_active` | tinyint(1) | default: 1 | Active status |
| `recipients` | json | nullable | Email recipients |
| `created_by` | bigint unsigned | FK, not null | Creator user ID |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: high_risk_countries

High-risk countries for AML.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Country ID |
| `country_code` | varchar(3) | unique, not null | ISO country code |
| `country_name` | varchar(100) | not null | Country name |
| `risk_category` | enum('high','medium','low') | not null | Risk category |
| `fatf_listing` | tinyint(1) | default: 0 | On FATF list |
| `eu_listing` | tinyint(1) | default: 0 | On EU list |
| `notes` | text | nullable | Additional notes |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: mfa_recovery_codes

MFA recovery codes for account recovery.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Code ID |
| `user_id` | bigint unsigned | FK, not null | User ID |
| `code_hash` | varchar(64) | not null | SHA-256 hash of code |
| `used_at` | timestamp | nullable | When code was used |
| `expires_at` | timestamp | not null | Expiration time |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: device_computations

Device trust score computations for MFA.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Device ID |
| `user_id` | bigint unsigned | FK, not null | User ID |
| `device_fingerprint` | varchar(255) | not null | Device fingerprint hash |
| `device_name` | varchar(100) | nullable | Device name |
| `trust_score` | int | default: 0 | Trust score (0-100) |
| `is_trusted` | tinyint(1) | default: 0 | Trusted device flag |
| `last_used_at` | timestamp | nullable | Last use time |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: data_breach_alerts

Data breach alerts and notifications.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Alert ID |
| `alert_type` | varchar(50) | not null | Alert type |
| `severity` | enum('critical','high','medium','low') | not null | Severity |
| `description` | text | not null | Alert description |
| `affected_user_ids` | json | nullable | Affected user IDs |
| `affected_tables` | json | nullable | Affected database tables |
| `source_ip` | varchar(45) | nullable | Source IP address |
| `status` | enum('open','investigating','resolved','closed') | default: 'open' | Status |
| `resolved_by` | bigint unsigned | FK, nullable | Resolver user ID |
| `resolved_at` | timestamp | nullable | Resolution time |
| `resolution_notes` | text | nullable | Resolution notes |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: system_health_checks

System health check monitoring.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Check ID |
| `check_name` | varchar(100) | not null | Check name |
| `status` | enum('healthy','warning','critical') | not null | Status |
| `message` | varchar(255) | nullable | Status message |
| `details` | json | nullable | Check details |
| `checked_at` | timestamp | not null | Check time |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: system_alerts

System alerts for monitoring.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Alert ID |
| `alert_type` | varchar(50) | not null | Alert type |
| `severity` | enum('critical','high','medium','low') | not null | Severity |
| `title` | varchar(255) | not null | Alert title |
| `message` | text | not null | Alert message |
| `context` | json | nullable | Additional context |
| `acknowledged_at` | timestamp | nullable | Acknowledge time |
| `acknowledged_by` | bigint unsigned | FK, nullable | Acknowledger user ID |
| `resolved_at` | timestamp | nullable | Resolution time |
| `resolved_by` | bigint unsigned | FK, nullable | Resolver user ID |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: test_results

Test execution results.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Result ID |
| `test_name` | varchar(255) | not null | Test name |
| `test_suite` | varchar(100) | nullable | Test suite name |
| `status` | enum('passed','failed','skipped') | not null | Test status |
| `duration_ms` | int | nullable | Execution duration |
| `error_message` | text | nullable | Error message if failed |
| `stack_trace` | text | nullable | Stack trace if failed |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: backup_logs

Backup operation logs.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Log ID |
| `backup_type` | enum('full','partial') | not null | Backup type |
| `file_path` | varchar(500) | not null | Backup file path |
| `file_size` | bigint | nullable | File size in bytes |
| `status` | enum('in_progress','completed','failed','verified') | not null | Status |
| `started_at` | timestamp | not null | Start time |
| `completed_at` | timestamp | nullable | Completion time |
| `verified_at` | timestamp | nullable | Verification time |
| `verified_by` | bigint unsigned | FK, nullable | Verifier user ID |
| `error_message` | text | nullable | Error message if failed |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: ctos_reports

CTOS credit bureau reports.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Report ID |
| `report_reference` | varchar(50) | unique, not null | Report reference |
| `customer_id` | bigint unsigned | FK, not null | Customer ID |
| `transaction_id` | bigint unsigned | FK, not null | Transaction ID |
| `status` | enum('draft','submitted','acknowledged','rejected') | not null | Status |
| `submitted_at` | timestamp | nullable | Submission time |
| `acknowledged_at` | timestamp | nullable | Acknowledgment time |
| `response_data` | json | nullable | CTOS response |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

### Table: risk_score_snapshots

Periodic risk score snapshots.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint unsigned | PK, auto-increment | Snapshot ID |
| `customer_id` | bigint unsigned | FK, not null | Customer ID |
| `snapshot_date` | date | not null | Snapshot date |
| `risk_score` | int | not null | Risk score |
| `risk_tier` | varchar(20) | not null | Risk tier |
| `risk_factors` | json | nullable | Risk factors at snapshot |
| `calculated_at` | timestamp | not null | Calculation time |
| `created_at` | timestamp | default: current | Record creation |
| `updated_at` | timestamp | default: current | Last update |

---

## 5. Enums Reference

### AccountCode.php
```php
enum AccountCode: string
{
    case CASH_MYR = '1000';
    case FOREIGN_CURRENCY_INVENTORY = '2000';
    case RECEIVABLES = '2100';
    case OTHER_CURRENT_ASSETS = '2200';
    case PAYABLES = '3000';
    case ACCRUALS = '3100';
    case CAPITAL = '4000';
    case RETAINED_EARNINGS = '4100';
    case CURRENT_YEAR_EARNINGS = '4200';
    case FOREX_TRADING_REVENUE = '5000';
    case REVALUATION_GAINS = '5100';
    case FOREX_LOSS = '6000';
    case REVALUATION_LOSS = '6100';
    case OPERATING_EXPENSES = '6200';
}
```

### AlertPriority.php
```php
enum AlertPriority: string
{
    case Critical = 'critical';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
}
```

### AmlRuleType.php
```php
enum AmlRuleType: string
{
    case Velocity = 'velocity';
    case Structuring = 'structuring';
    case AmountThreshold = 'amount_threshold';
    case Frequency = 'frequency';
    case Geographic = 'geographic';
}
```

### CaseNoteType.php
```php
enum CaseNoteType: string
{
    case Investigation = 'Investigation';
    case Update = 'Update';
    case Decision = 'Decision';
    case Escalation = 'Escalation';
}
```

### CaseResolution.php
```php
enum CaseResolution: string
{
    case NoConcern = 'NoConcern';
    case WarningIssued = 'WarningIssued';
    case EddRequired = 'EddRequired';
    case StrFiled = 'StrFiled';
    case ClosedNoAction = 'ClosedNoAction';
}
```

### CaseStatus.php
```php
enum CaseStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case PendingReview = 'pending_review';
    case Resolved = 'resolved';
    case Closed = 'closed';
}
```

### CddLevel.php
```php
enum CddLevel: string
{
    case Simplified = 'Simplified';
    case Standard = 'Standard';
    case Enhanced = 'Enhanced';
}
```

### ComplianceCasePriority.php
```php
enum ComplianceCasePriority: string
{
    case Low = 'Low';
    case Medium = 'Medium';
    case High = 'High';
    case Critical = 'Critical';
}
```

### ComplianceCaseStatus.php
```php
enum ComplianceCaseStatus: string
{
    case Open = 'Open';
    case UnderReview = 'UnderReview';
    case PendingApproval = 'PendingApproval';
    case Closed = 'Closed';
    case Escalated = 'Escalated';
}
```

### ComplianceCaseType.php
```php
enum ComplianceCaseType: string
{
    case Investigation = 'Investigation';
    case Edd = 'Edd';
    case Str = 'Str';
    case SanctionReview = 'SanctionReview';
    case Counterfeit = 'Counterfeit';
}
```

### ComplianceFlagType.php
```php
enum ComplianceFlagType: string
{
    case LargeAmount = 'Large_Amount';
    case SanctionsHit = 'Sanctions_Hit';
    case Velocity = 'Velocity';
    case Structuring = 'Structuring';
    case EddRequired = 'EDD_Required';
    case PepStatus = 'PEP_Status';
    case SanctionMatch = 'Sanction_Match';
    case HighRiskCustomer = 'High_Risk_Customer';
    case UnusualPattern = 'Unusual_Pattern';
    case ManualReview = 'Manual_Review';
    case HighRiskCountry = 'High_Risk_Country';
    case RoundAmount = 'Round_Amount';
    case ProfileDeviation = 'Profile_Deviation';
    case AmlRuleTriggered = 'Aml_Rule_Triggered';
    case CounterfeitCurrency = 'Counterfeit_Currency';
    case RiskScoreEscalation = 'Risk_Score_Escalation';
}
```

### CounterSessionStatus.php
```php
enum CounterSessionStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case HandedOver = 'handed_over';
}
```

### CtosStatus.php
```php
enum CtosStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Acknowledged = 'acknowledged';
    case Rejected = 'rejected';
}
```

### EddDocumentStatus.php
```php
enum EddDocumentStatus: string
{
    case Pending = 'Pending';
    case Received = 'Received';
    case Verified = 'Verified';
    case Rejected = 'Rejected';
}
```

### EddRiskLevel.php
```php
enum EddRiskLevel: string
{
    case Low = 'Low';
    case Medium = 'Medium';
    case High = 'High';
    case Critical = 'Critical';
}
```

### EddStatus.php
```php
enum EddStatus: string
{
    case Incomplete = 'Incomplete';
    case PendingQuestionnaire = 'Pending_Questionnaire';
    case QuestionnaireSubmitted = 'Questionnaire_Submitted';
    case PendingReview = 'Pending_Review';
    case Approved = 'Approved';
    case Rejected = 'Rejected';
    case Expired = 'Expired';
}
```

### EddTemplateType.php
```php
enum EddTemplateType: string
{
    case Pep = 'pep';
    case HighRiskCountry = 'high_risk_country';
    case UnusualPattern = 'unusual_pattern';
    case SanctionMatch = 'sanction_match';
    case LargeTransaction = 'large_transaction';
    case HighRiskIndustry = 'high_risk_industry';
}
```

### FindingSeverity.php
```php
enum FindingSeverity: string
{
    case Low = 'Low';
    case Medium = 'Medium';
    case High = 'High';
    case Critical = 'Critical';
}
```

### FindingStatus.php
```php
enum FindingStatus: string
{
    case New = 'New';
    case Reviewed = 'Reviewed';
    case Dismissed = 'Dismissed';
    case CaseCreated = 'Case_Created';
}
```

### FindingType.php
```php
enum FindingType: string
{
    case VelocityExceeded = 'Velocity_Exceeded';
    case StructuringPattern = 'Structuring_Pattern';
    case AggregateTransaction = 'Aggregate_Transaction';
    case StrDeadline = 'STR_Deadline';
    case SanctionMatch = 'Sanction_Match';
    case LocationAnomaly = 'Location_Anomaly';
    case CurrencyFlowAnomaly = 'Currency_Flow_Anomaly';
    case CounterfeitAlert = 'Counterfeit_Alert';
    case RiskScoreChange = 'Risk_Score_Change';
}
```

### FlagStatus.php
```php
enum FlagStatus: string
{
    case Open = 'Open';
    case UnderReview = 'Under_Review';
    case Resolved = 'Resolved';
    case Escalated = 'Escalated';
    case Rejected = 'Rejected';
}
```

### RecalculationTrigger.php
```php
enum RecalculationTrigger: string
{
    case Manual = 'Manual';
    case Scheduled = 'Scheduled';
    case EventDriven = 'Event_Driven';
}
```

### ReportStatus.php
```php
enum ReportStatus: string
{
    case Scheduled = 'scheduled';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}
```

### RiskTrend.php
```php
enum RiskTrend: string
{
    case Improving = 'improving';
    case Stable = 'stable';
    case Deteriorating = 'deteriorating';
}
```

### StrStatus.php
```php
enum StrStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case PendingApproval = 'pending_approval';
    case Submitted = 'submitted';
    case Acknowledged = 'acknowledged';
    case Failed = 'failed';
}
```

### TransactionImportStatus.php
```php
enum TransactionImportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case CompletedWithErrors = 'completed_with_errors';
    case Failed = 'failed';
}
```

### TransactionStatus.php
```php
enum TransactionStatus: string
{
    case Draft = 'Draft';
    case PendingApproval = 'PendingApproval';
    case Approved = 'Approved';
    case Processing = 'Processing';
    case Completed = 'Completed';
    case Finalized = 'Finalized';
    case Cancelled = 'Cancelled';
    case Reversed = 'Reversed';
    case Failed = 'Failed';
    case Rejected = 'Rejected';
    case Pending = 'Pending';
    case OnHold = 'OnHold';
}
```

### TransactionType.php
```php
enum TransactionType: string
{
    case Buy = 'Buy';
    case Sell = 'Sell';
}
```

### UserRole.php
```php
enum UserRole: string
{
    case Teller = 'teller';
    case Manager = 'manager';
    case ComplianceOfficer = 'compliance_officer';
    case Admin = 'admin';
}
```

---

## 6. Models Reference

### Core Models
- `AccountLedger` - Running balance ledger entries
- `AccountingPeriod` - Fiscal periods
- `Alert` - System alerts
- `AmlRule` - AML rule configuration
- `ApprovalTask` - Multi-stage approval tasks
- `BackupLog` - Backup operation logs
- `BankReconciliation` - Bank reconciliation records
- `Branch` - Multi-branch support
- `Budget` - Budget vs actual
- `ChartOfAccount` - COA structure
- `Counter` - Till/counter master
- `CounterHandover` - Till custody transfer
- `CounterSession` - Till session lifecycle
- `CtosReport` - CTOS bureau reports
- `Currency` - Supported currencies
- `CurrencyPosition` - Stock tracking with WAC
- `Customer` - Customer KYC data
- `CustomerBehavioralBaseline` - Behavioral deviation
- `CustomerDocument` - KYC documents
- `CustomerRiskHistory` - Risk rating changes
- `CustomerRiskProfile` - Dynamic risk scoring
- `DataBreachAlert` - Data breach notifications
- `Department` - Organizational departments
- `DeviceComputations` - MFA trusted devices
- `EddDocumentRequest` - EDD document tracking
- `EddQuestionnaireTemplate` - EDD questionnaire
- `EnhancedDiligenceRecord` - EDD records
- `ExchangeRate` - Current rates
- `ExchangeRateHistory` - Rate change history
- `FiscalYear` - Annual FY management
- `FlaggedTransaction` - AML alerts
- `HighRiskCountry` - High-risk country lists
- `JournalEntry` - Double-entry journal
- `JournalLine` - Journal debit/credit lines
- `MfaRecoveryCode` - MFA recovery codes
- `ReportGenerated` - Generated reports
- `ReportRun` - Report execution logs
- `ReportSchedule` - Scheduled reports
- `ReportTemplate` - Report templates
- `RevaluationEntry` - Currency revaluation
- `RiskScoreSnapshot` - Risk score history
- `SanctionEntry` - Sanctions list entries
- `SanctionList` - Sanctions list master
- `StockTransfer` - Inter-branch transfers
- `StockTransferItem` - Transfer line items
- `StrDraft` - STR drafts
- `StrReport` - Suspicious transaction reports
- `SystemAlert` - System monitoring alerts
- `SystemHealthCheck` - Health check records
- `SystemLog` - Tamper-evident audit trail
- `Task` - Task management
- `TestResult` - Test execution results
- `TillBalance` - Daily till balances
- `Transaction` - Core buy/sell transactions
- `TransactionConfirmation` - Large transaction confirmations
- `TransactionError` - Error logging
- `TransactionImport` - Bulk import tracking
- `TransactionStateHistory` - State change audit
- `User` - User accounts

### Compliance Sub-models
- `ComplianceCase` - Case management
- `ComplianceCaseDocument` - Case documents
- `ComplianceCaseLink` - Case entity links
- `ComplianceCaseNote` - Case notes
- `ComplianceFinding` - Automated findings

---

## 7. Services Reference

### Core Services

| Service | Purpose |
|---------|---------|
| `AccountingService` | Journal entry creation/reversal, double-entry bookkeeping |
| `AlertService` | System alert management |
| `AlertTriageService` | Alert triage and assignment |
| `ApprovalWorkflowService` | Multi-stage approval workflows |
| `AuditService` | Hash chaining verification, chain integrity |
| `BackupService` | Backup operations |
| `BranchScopeService` | Branch-based data scoping |
| `BranchService` | Branch CRUD operations |
| `BudgetService` | Budget vs actual reporting |
| `CashFlowService` | Cash flow statement generation |
| `CaseManagementService` | Compliance case management |
| `ComplianceService` | CDD determination, CTF decisions, sanctions |
| `CounterService` | Till/counter lifecycle (open/close/handover) |
| `CurrencyPositionService` | Stock/position tracking with WAC |
| `CustomerRiskScoringService` | Customer risk scoring with lock/unlock |
| `CtosReportService` | CTOS bureau integration |
| `EddService` | Enhanced Due Diligence workflow |
| `EddTemplateService` | EDD questionnaire management |
| `EncryptionService` | Data encryption with random IV |
| `ExportService` | Data export utilities |
| `FinancialRatioService` | Liquidity, profitability, leverage, efficiency ratios |
| `FinancialStatementService` | Trial balance, P&L, balance sheet |
| `FiscalYearService` | FY creation, closing, opening |
| `GoAmlMockServer` | Mock goAML server for testing |
| `GoAmlXmlGenerator` | goAML XML generation |
| `JournalEntryWorkflowService` | Draft → Pending → Posted workflow |
| `LedgerService` | Account ledger, running balances |
| `LogRotationService` | Audit log rotation |
| `MathService` | BCMath precision calculations |
| `MfaService` | TOTP MFA, recovery codes, trusted devices |
| `MonitorService` | System monitoring |
| `PeriodCloseService` | Period closing with validation |
| `QueryOptimizerService` | Query optimization |
| `RateApiService` | Exchange rate API integration |
| `RateLimitService` | Rate limiting enforcement |
| `ReconciliationService` | Bank reconciliation |
| `RevaluationService` | Monthly currency revaluation |
| `RiskRatingService` | Customer risk rating |
| `SanctionsDownloadService` | Sanctions list downloads |
| `SanctionsImportService` | Sanctions list import |
| `SanctionScreeningService` | Customer/transaction screening |
| `StockTransferService` | Inter-branch transfers |
| `StrAutomationService` | STR automated preparation |
| `StrReportService` | STR management and submission |
| `TaskService` | Task management |
| `TestRunnerService` | Test execution |
| `TransactionCancellationService` | Cancellation workflows |
| `TransactionErrorHandler` | Error logging |
| `TransactionImportService` | Bulk transaction import |
| `TransactionMonitoringService` | AML monitoring engine |
| `TransactionRecoveryService` | Recovery workflows |
| `TransactionService` | Core transaction operations |
| `TransactionStateMachine` | Transaction state management |

### Compliance Services (`app/Services/Compliance/`)

| Service | Purpose |
|---------|---------|
| `CaseManagementService` | Case management with notes/documents |
| `ComplianceReportingService` | Dashboard KPIs, calendar, aging |
| `MonitoringEngine` | Compliance monitoring orchestration |
| `RiskScoringEngine` | Risk score calculation |

### Compliance Monitors (`app/Services/Compliance/Monitors/`)

| Monitor | Purpose | Detection |
|---------|---------|-----------|
| `BaseMonitor` | Abstract base class | - |
| `CounterfeitAlertMonitor` | Counterfeit detection | 30-day lookback |
| `CurrencyFlowMonitor` | Round-tripping patterns | Same currency sold then bought within 72h (>= RM 5,000) |
| `CustomerLocationAnomalyMonitor` | Geographic anomalies | Foreign nationals with multiple currencies |
| `SanctionsRescreeningMonitor` | Weekly rescreening | Not screened since latest update |
| `StrDeadlineMonitor` | STR filing deadline | 3 working day deadline |
| `StructuringMonitor` | Aggregation detection | 3+ transactions under RM 3,000 within 1 hour |
| `VelocityMonitor` | Velocity threshold | RM 50,000+ in 24h (High), RM 45,000+ (Warning) |

---

## 8. Controllers Reference

### Main Controllers
- `AccountingController` - Accounting module (journal, periods, budget, reconciliation)
- `AlertTriageController` - Alert triage workflow
- `AuditController` - Audit log viewer
- `BranchController` - Branch management
- `CaseManagementController` - Compliance case management
- `ComplianceReportingController` - Compliance reporting dashboard
- `ComplianceWorkspaceController` - Compliance workspace
- `CounterController` - Counter/till management
- `DashboardController` - Main dashboard
- `DataBreachAlertController` - Data breach alerts
- `EddTemplateController` - EDD template management
- `EnhancedDiligenceController` - EDD records
- `FinancialStatementController` - Financial statements
- `FiscalYearController` - Fiscal year management
- `HealthCheckController` - System health checks
- `JournalEntryWorkflowController` - JE workflow
- `LedgerController` - Account ledger
- `MfaController` - MFA setup/verify
- `ReportController` - BNM reports
- `ReportingService` - Report generation
- `RevaluationController` - Currency revaluation
- `RiskDashboardController` - Risk portfolio
- `SanctionController` - Sanctions management
- `StockCashController` - Stock & cash view
- `StockTransferController` - Stock transfers
- `StrController` - STR management
- `StrStudioController` - STR drafting
- `TaskController` - Task management
- `TestResultsController` - Test results
- `TransactionBatchController` - Batch import
- `TransactionController` - Transaction CRUD
- `TransactionReportController` - Transaction reports
- `UserController` - User management

### Auth Controllers
- `LoginController` - Login/logout

### Customer Controllers
- `CustomerKycController` - Customer KYC documents

### API Controllers (`app/Http/Controllers/Api/`)

| Controller | Purpose |
|------------|---------|
| `Compliance/AlertController` | Alert listing/summary |
| `Compliance/CaseController` | Case CRUD |
| `Compliance/DashboardController` | Dashboard KPIs |
| `Compliance/EddController` | EDD records |
| `Compliance/FindingController` | Compliance findings |
| `Compliance/RiskController` | Risk portfolio |
| `SanctionsWebhookController` | Sanctions webhook |
| `V1/BranchController` | Branch API |
| `V1/Compliance/AlertController` | v1 Alert API |
| `V1/Compliance/CaseController` | v1 Case API |
| `V1/Compliance/DashboardController` | v1 Dashboard API |
| `V1/Compliance/EddController` | v1 EDD API |
| `V1/Compliance/FindingController` | v1 Finding API |
| `V1/Compliance/RiskController` | v1 Risk API |
| `V1/CustomerController` | Customer API |
| `V1/ReportController` | Report API |
| `V1/SanctionController` | Sanction API |
| `V1/StrController` | STR API |
| `V1/TransactionApprovalController` | Transaction approval API |
| `V1/TransactionCancellationController` | Transaction cancellation API |
| `V1/TransactionController` | Transaction API |

### Transaction Controllers
- `Transaction/TransactionApprovalController` - Large transaction approval
- `Transaction/TransactionCancellationController` - Cancellation workflow
- `Transaction/TransactionReportController` - Transaction reporting

---

## 9. Middleware Reference

| Middleware | Purpose |
|------------|---------|
| `Authenticate` | Session-based authentication |
| `CheckBranchAccess` | Branch-based data access control |
| `CheckRole` | Single role requirement check |
| `CheckRoleAny` | Multiple role support |
| `DataBreachDetection` | Data breach monitoring/alerting |
| `EncryptCookies` | Cookie encryption |
| `EnsureMfaEnabled` | Redirect to MFA setup if not enabled |
| `EnsureMfaVerified` | Require MFA verification |
| `IpBlocker` | IP-based blocking after failed attempts |
| `LogRequests` | Request logging |
| `PreventRequestsDuringMaintenance` | Maintenance mode check |
| `QueryPerformanceMonitor` | Slow query monitoring |
| `RedirectIfAuthenticated` | Redirect authenticated users |
| `SecurityHeaders` | Security header injection |
| `SessionTimeout` | Idle session timeout (configurable, default 15 min) |
| `StrictRateLimit` | Hardened rate limiting |
| `TrimStrings` | Input string trimming |
| `TrustHosts` | Trusted host validation |
| `TrustProxies` | Proxy trust configuration |
| `ValidateSignature` | Signed URL validation |
| `VerifyCsrfToken` | CSRF token verification |

---

## 10. Routes Reference

### Route File Structure
- `routes/web.php` - Main web routes (session auth)
- `routes/auth.php` - Authentication routes
- `routes/api.php` - Legacy REST API (Sanctum token auth)
- `routes/api_v1.php` - Current REST API version
- `routes/channels.php` - Broadcast channels
- `routes/console.php` - Console routes

### Key Route Groups

**Accounting Routes** (`/accounting/*`)
| Route | Method | Description |
|-------|--------|-------------|
| `/accounting` | GET | Accounting dashboard |
| `/accounting/journal` | GET/POST | Journal entries |
| `/accounting/journal/create` | GET | Create journal entry |
| `/accounting/journal/{entry}` | GET | View journal entry |
| `/accounting/journal/{entry}/reverse` | POST | Reverse entry |
| `/accounting/journal/workflow` | GET | Workflow status |
| `/accounting/journal/{entry}/submit` | POST | Submit for approval |
| `/accounting/journal/{entry}/approve` | POST | Approve entry |
| `/accounting/ledger` | GET | Chart of accounts |
| `/accounting/ledger/{code}` | GET | Account ledger |
| `/accounting/trial-balance` | GET | Trial balance |
| `/accounting/profit-loss` | GET | P&L statement |
| `/accounting/balance-sheet` | GET | Balance sheet |
| `/accounting/cash-flow` | GET | Cash flow |
| `/accounting/ratios` | GET | Financial ratios |
| `/accounting/revaluation` | GET | Revaluation |
| `/accounting/revaluation/run` | POST | Run revaluation |
| `/accounting/revaluation/history` | GET | Revaluation history |
| `/accounting/periods` | GET | Accounting periods |
| `/accounting/periods/{period}/close` | POST | Close period |
| `/accounting/fiscal-years` | GET | Fiscal years |
| `/accounting/fiscal-years` | POST | Create fiscal year |
| `/accounting/fiscal-years/{year}/close` | POST | Close fiscal year |
| `/accounting/reconciliation` | GET | Bank reconciliation |
| `/accounting/reconciliation/import` | POST | Import statement |
| `/accounting/budget` | GET/POST | Budget |
| `/accounting/budget/{id}` | PUT/PATCH | Update budget |

**Compliance Routes** (`/compliance/*`)
| Route | Method | Description |
|-------|--------|-------------|
| `/compliance` | GET | Compliance dashboard |
| `/compliance/workspace` | GET | Compliance workspace |
| `/compliance/alerts` | GET | Alert triage |
| `/compliance/alerts/{alert}` | GET | Alert details |
| `/compliance/alerts/{alert}/assign` | PATCH | Assign alert |
| `/compliance/alerts/{alert}/resolve` | PATCH | Resolve alert |
| `/compliance/cases` | GET/POST | Cases |
| `/compliance/cases/{case}` | GET/PATCH | Case details |
| `/compliance/cases/{case}/escalate` | POST | Escalate case |
| `/compliance/cases/{case}/documents` | POST | Upload document |
| `/compliance/flagged` | GET | Flagged transactions |
| `/compliance/flags/{flag}/assign` | PATCH | Assign flag |
| `/compliance/flags/{flag}/resolve` | PATCH | Resolve flag |
| `/compliance/flags/{flag}/generate-str` | POST | Generate STR |
| `/compliance/edd` | GET | EDD records |
| `/compliance/edd/create` | GET | Create EDD |
| `/compliance/edd/{record}` | GET | EDD details |
| `/compliance/edd/{record}/submit` | POST | Submit EDD |
| `/compliance/edd/{record}/approve` | POST | Approve EDD |
| `/compliance/edd/{record}/reject` | POST | Reject EDD |
| `/compliance/edd-templates` | GET | EDD templates |
| `/compliance/rules` | GET/POST | AML rules |
| `/compliance/risk-dashboard` | GET | Risk dashboard |
| `/compliance/risk-dashboard/customer/{customer}` | GET | Customer risk |
| `/compliance/risk-dashboard/trends` | GET | Risk trends |
| `/compliance/str-studio` | GET | STR studio |
| `/compliance/str-studio/create/{caseId}` | GET | Create from case |
| `/compliance/str-studio/draft` | POST | Save draft |
| `/compliance/str-studio/{draft}/submit` | POST | Submit draft |
| `/compliance/reporting` | GET | Compliance reporting |
| `/compliance/reporting/generate` | GET | Generate report |
| `/compliance/reporting/history` | GET | Report history |
| `/compliance/reporting/schedule` | GET/POST | Report schedule |

**Transaction Routes** (`/transactions/*`)
| Route | Method | Description |
|-------|--------|-------------|
| `/transactions` | GET | Transaction list |
| `/transactions/create` | GET | Create form |
| `/transactions` | POST | Store transaction |
| `/transactions/{transaction}` | GET | Transaction details |
| `/transactions/{transaction}/approve` | POST | Approve |
| `/transactions/{transaction}/cancel` | GET/POST | Cancel |
| `/transactions/{transaction}/confirm` | GET/POST | Confirm large |
| `/transactions/{transaction}/receipt` | GET | Receipt |
| `/transactions/batch-upload` | GET/POST | Batch import |

**STR Routes** (`/str/*`)
| Route | Method | Description |
|-------|--------|-------------|
| `/str` | GET | STR list |
| `/str/create` | GET | Create STR |
| `/str` | POST | Store STR |
| `/str/{str}` | GET | STR details |
| `/str/{str}/submit-review` | POST | Submit for review |
| `/str/{str}/submit-approval` | POST | Submit for approval |
| `/str/{str}/approve` | POST | Approve |
| `/str/{str}/submit` | POST | Submit to goAML |
| `/str/{str}/track-acknowledgment` | POST | Track acknowledgment |

**Counter Routes** (`/counters/*`)
| Route | Method | Description |
|-------|--------|-------------|
| `/counters` | GET | Counter list |
| `/counters/{counter}/open` | GET/POST | Open counter |
| `/counters/{counter}/close` | GET/POST | Close counter |
| `/counters/{counter}/handover` | GET/POST | Handover |
| `/counters/{counter}/status` | GET | Counter status |
| `/counters/{counter}/history` | GET | Session history |

**Stock Transfer Routes** (`/stock-transfers/*`)
| Route | Method | Description |
|-------|--------|-------------|
| `/stock-transfers` | GET | Transfer list |
| `/stock-transfers/create` | GET | Create form |
| `/stock-transfers` | POST | Store transfer |
| `/stock-transfers/{transfer}` | GET | Transfer details |
| `/stock-transfers/{transfer}/approve-bm` | POST | BM approval |
| `/stock-transfers/{transfer}/approve-hq` | POST | HQ approval |
| `/stock-transfers/{transfer}/dispatch` | POST | Dispatch |
| `/stock-transfers/{transfer}/receive` | POST | Receive |
| `/stock-transfers/{transfer}/complete` | POST | Complete |
| `/stock-transfers/{transfer}/cancel` | POST | Cancel |

**Report Routes** (`/reports/*`)
| Route | Method | Description |
|-------|--------|-------------|
| `/reports` | GET | Reports dashboard |
| `/reports/msb2` | GET | MSB2 report |
| `/reports/msb2/generate` | GET | Generate MSB2 |
| `/reports/lctr` | GET | LCTR report |
| `/reports/lctr/generate` | GET | Generate LCTR |
| `/reports/lmca` | GET | LMCA report |
| `/reports/lmca/generate` | GET | Generate LMCA |
| `/reports/quarterly-lvr` | GET | QLVR report |
| `/reports/quarterly-lvr/generate` | GET | Generate QLVR |
| `/reports/position-limit` | GET | Position limits |
| `/reports/position-limit/generate` | GET | Generate PLR |
| `/reports/history` | GET | Report history |
| `/reports/download/{filename}` | GET | Download |

**System Routes**
| Route | Method | Description |
|-------|--------|-------------|
| `/dashboard` | GET | Main dashboard |
| `/branches` | GET/POST | Branches (Admin) |
| `/branches/{branch}` | GET/PUT/DELETE | Branch details |
| `/users` | GET/POST | Users (Admin) |
| `/users/{user}` | GET/PUT/DELETE | User details |
| `/users/{user}/reset-password` | POST | Reset password |
| `/tasks` | GET | Task list |
| `/tasks/my` | GET | My tasks |
| `/audit` | GET | Audit log |
| `/test-results` | GET | Test results |
| `/data-breach-alerts` | GET | Data breach alerts |

**API Routes** (`/api/*`)
| Route | Method | Description |
|-------|--------|-------------|
| `/api/compliance/dashboard` | GET | Dashboard KPIs |
| `/api/compliance/calendar` | GET | Filing calendar |
| `/api/compliance/case-aging` | GET | Case aging |
| `/api/compliance/audit-trail` | GET | Audit trail |
| `/api/compliance/alerts` | GET | Alert list |
| `/api/compliance/alerts/summary` | GET | Alert summary |
| `/api/compliance/alerts/overdue` | GET | Overdue alerts |
| `/api/compliance/alerts/bulk-assign` | POST | Bulk assign |
| `/api/compliance/alerts/bulk-resolve` | POST | Bulk resolve |
| `/api/compliance/findings` | GET | Findings list |
| `/api/compliance/findings/stats` | GET | Finding stats |
| `/api/compliance/cases` | GET/POST | Cases CRUD |
| `/api/compliance/cases/{id}/notes` | POST | Add note |
| `/api/compliance/cases/{id}/close` | POST | Close case |
| `/api/compliance/cases/{id}/timeline` | GET | Case timeline |
| `/api/compliance/edd` | GET | EDD records |
| `/api/compliance/edd/templates` | GET | EDD templates |
| `/api/compliance/edd/{id}/questionnaire` | POST | Submit questionnaire |
| `/api/risk/portfolio` | GET | Risk portfolio |
| `/api/risk/{customerId}` | GET | Customer risk |
| `/api/risk/{customerId}/history` | GET | Risk history |
| `/api/risk/{customerId}/recalculate` | POST | Recalculate |
| `/api/risk/{customerId}/lock` | POST | Lock score |
| `/api/risk/{customerId}/unlock` | POST | Unlock score |
| `/api/rates/history/{currency}` | GET | Rate history |

---

## 11. Artisan Commands

### Backup Commands (`app/Console/Commands/Backup/`)
| Command | Purpose |
|---------|---------|
| `backup:clean` | Clean old backups |
| `backup:list` | List backups |
| `backup:monitor` | Monitor backup health |
| `backup:restore` | Restore from backup |
| `backup:run` | Run backup |
| `backup:verify` | Verify backup integrity |

### Report Generation Commands
| Command | Purpose |
|---------|---------|
| `report:msb2` | Generate daily MSB2 report |
| `report:lctr` | Generate LCTR report |
| `report:lmca` | Generate monthly LMCA |
| `report:qlvr` | Generate quarterly LVR |
| `report:position-limit` | Generate position limit report |
| `report:trial-balance` | Generate trial balance |

### Compliance Commands
| Command | Purpose |
|---------|---------|
| `compliance:rescreen` | Monthly sanctions rescreening |
| `sanctions:update` | Update sanctions lists |
| `sanctions:status` | Show sanctions status |

### System Commands
| Command | Purpose |
|---------|---------|
| `alert:daily-summary` | Send daily alert summary |
| `alert:send` | Send notification |
| `queue:health-check` | Check queue health |
| `queue:clear-stuck` | Clear stuck jobs |
| `queue:retry-failed` | Retry failed jobs |
| `audit:rotate` | Rotate audit logs |
| `revaluation:run-monthly` | Run monthly revaluation |

### User Management
| Command | Purpose |
|---------|---------|
| `user:create` | Create new user |

### Utility Commands
| Command | Purpose |
|---------|---------|
| `test:run` | Run test suite |
| `test:reset-database` | Reset test database |
| `ip-blocker:run` | Run IP blocker |
| `monitor:check` | System health check |
| `monitor:status` | Monitor status |

---

## 12. Background Jobs

### Compliance Jobs (`app/Jobs/Compliance/`)
| Job | Purpose |
|-----|---------|
| `CounterfeitAlertJob` | Counterfeit currency detection |
| `CurrencyFlowJob` | Currency flow monitoring |
| `CustomerLocationAnomalyJob` | Geographic anomaly detection |
| `SanctionsRescreeningJob` | Monthly sanctions rescreening |
| `StrDeadlineMonitorJob` | STR deadline tracking |
| `StructuringMonitorJob` | Structuring pattern detection |
| `VelocityMonitorJob` | Velocity threshold monitoring |

### Sanctions Jobs (`app/Jobs/Sanctions/`)
| Job | Purpose |
|-----|---------|
| `BaseSanctionsDownloadJob` | Base download class |
| `DownloadEuSanctionsList` | Download EU sanctions |
| `DownloadMofaSanctionsList` | Download MOFA sanctions |
| `DownloadOfacSanctionsList` | Download OFAC sanctions |
| `DownloadUnSanctionsList` | Download UN sanctions |

### Other Jobs
| Job | Purpose |
|-----|---------|
| `ProcessTransactionRetry` | Retry failed transaction processing |
| `SubmitStrToGoAmlJob` | Submit STR to goAML |

---

## 13. Events & Listeners

### Events
| Event | Purpose |
|-------|---------|
| `AlertCreated` | New system alert created |
| `CaseOpened` | Compliance case opened |
| `ReportGenerated` | Report generation completed |
| `RiskScoreUpdated` | Customer risk score changed |
| `StrDraftGenerated` | STR draft created |
| `TransactionCreated` | New transaction created |

### Listeners
| Listener | Purpose |
|----------|---------|
| `ComplianceEventListener` | Handle compliance events |
| `TransactionCreatedListener` | Handle transaction creation side effects |

---

## 14. Configuration Files

### Key Configuration Values

**config/cems.php**
```php
[
    'transaction_cancellation_window_hours' => 24,
    'aggregate_lookback_days' => 7,
    'position_limits' => [
        'USD' => 1000000,
        'EUR' => 800000,
        'GBP' => 700000,
        'SGD' => 900000,
        'JPY' => 100000000,
        'AUD' => 750000,
        'CHF' => 700000,
        'CAD' => 700000,
        'HKD' => 6000000,
    ],
    'thresholds' => [
        'ctr' => 50000,      // Cash Transaction Report
        'edd' => 50000,      // Enhanced Due Diligence
        'str' => 50000,      // Suspicious Transaction Report
        'lctr' => 50000,     // Large Cash Transaction Report
    ],
    'mfa' => [
        'enabled' => true,
        'issuer' => 'CEMS-MY',
        'period' => 30,      // TOTP period
        'digits' => 6,       // TOTP digits
        'require_for_roles' => ['admin', 'manager', 'compliance', 'teller'],
        'grace_days' => 30,
        'remember_days' => 30,
    ],
    'session_timeout_minutes' => 15,
    'license_number' => 'MSB-XXXXXXX',
    'company_name' => 'CEMS-MY MSB',
    'goaml' => [
        'enabled' => false,
        'endpoint' => '',
        'api_key' => '',
    ],
]
```

**config/security.php**
```php
[
    'hsts_max_age' => 31536000,  // 1 year
    'rate_limits' => [
        'login' => ['attempts' => 5, 'per_minutes' => 1],
        'api' => ['attempts' => 30, 'per_minutes' => 1],
        'transactions' => ['attempts' => 10, 'per_minutes' => 1],
        'str' => ['attempts' => 3, 'per_minutes' => 1],
        'bulk' => ['attempts' => 1, 'per_minutes' => 5],
        'export' => ['attempts' => 5, 'per_minutes' => 1],
        'sensitive' => ['attempts' => 3, 'per_minutes' => 1],
    ],
    'ip_blocking' => [
        'enabled' => true,
        'failed_attempts_threshold' => 10,
        'time_window_minutes' => 5,
        'block_duration_minutes' => 60,
        'max_block_duration_minutes' => 1440,
        'whitelist' => ['192.168.1.0/24', '127.0.0.1'],
    ],
    'password' => [
        'min_length' => 12,
        'require_uppercase' => true,
        'require_lowercase' => true,
        'require_numbers' => true,
        'require_symbols' => true,
    ],
    'audit' => [
        'hash_algorithm' => 'sha256',
        'retention_days' => 2555,  // 7 years
        'encrypt_sensitive' => true,
    ],
]
```

---

## 15. Navigation Structure

### Main
- Dashboard (`/dashboard`)

### Operations
- Transactions (`/transactions`)
- Customers (`/customers`)

### Counter Management
- Counters (`/counters`)
- Branches (`/branches`) - Admin only

### Stock Management
- Stock & Cash (`/stock-cash`)
- Stock Transfers (`/stock-transfers`)

### Compliance & AML (12 items)
- Compliance Dashboard (`/compliance`)
- Compliance Workspace (`/compliance/workspace`)
- Alert Triage (`/compliance/alerts`)
- Cases (`/compliance/cases`)
- Flagged Transactions (`/compliance/flagged`)
- EDD Records (`/compliance/edd`)
- EDD Templates (`/compliance/edd-templates`)
- AML Rules (`/compliance/rules`)
- Risk Dashboard (`/compliance/risk-dashboard`)
- STR Studio (`/compliance/str-studio`)
- Compliance Reporting (`/compliance/reporting`)
- STR Reports (`/str`)

### Accounting (13 items)
- Accounting Dashboard (`/accounting`)
- Journal Entries (`/accounting/journal`)
- Ledger (`/accounting/ledger`)
- Trial Balance (`/accounting/trial-balance`)
- Profit & Loss (`/accounting/profit-loss`)
- Balance Sheet (`/accounting/balance-sheet`)
- Cash Flow (`/accounting/cash-flow`)
- Financial Ratios (`/accounting/ratios`)
- Revaluation (`/accounting/revaluation`)
- Reconciliation (`/accounting/reconciliation`)
- Budget (`/accounting/budget`)
- Periods (`/accounting/periods`)
- Fiscal Years (`/accounting/fiscal-years`)

### Reports (7 items)
- Reports Dashboard (`/reports`)
- MSB2 Report (`/reports/msb2`)
- LCTR (`/reports/lctr`)
- LMCA (`/reports/lmca`)
- Quarterly LVR (`/reports/quarterly-lvr`)
- Position Limits (`/reports/position-limit`)
- Report History (`/reports/history`)

### System (6 items)
- Tasks (`/tasks`)
- Transaction Imports (`/transactions/batch-upload`)
- Audit Log (`/audit`)
- Test Results (`/test-results`)
- Users (`/users`) - Admin only
- Data Breach Alerts (`/data-breach-alerts`)

---

## 16. Business Rules

### CDD Level Determination
```
IF isPep OR hasSanctionMatch → Enhanced CDD
ELSE IF amount >= RM 50,000 OR riskRating === 'High' → Enhanced CDD
ELSE IF amount >= RM 3,000 → Standard CDD
ELSE → Simplified CDD
```

### Hold Decision Logic
```
Enhanced CDD triggers hold:
  - amount >= RM 50,000 → Hold
  - customer is PEP → Hold
  - sanctions match → Hold
  - riskRating === 'High' → Hold
Standard/Simplified CDD → No automatic hold
```

### CTOS Reporting
- ALL cash transactions (Buy AND Sell) >= RM 10,000

### Transaction Approval Requirements
| Amount | Requirement |
|--------|-------------|
| < RM 50,000 | Teller can complete |
| >= RM 50,000 | Manager approval required |

### Cancellation
- ALL cancellations require manager approval (segregation of duties)

### Variance Thresholds
| Variance | Action |
|----------|--------|
| < RM 100 | Normal |
| RM 100 - 500 | Requires notes |
| > RM 500 | Supervisor approval |

### Password Requirements
- Minimum 12 characters
- Must include: lowercase, uppercase, digit, special character

### MFA Requirements
- MFA enabled for all roles (Teller, Manager, ComplianceOfficer, Admin)
- Grace period: 30 days after first login

### Session Timeout
- Default: 15 minutes idle
- Configurable via `cems.session_timeout_minutes`

---

## 17. Workflows

### Transaction Lifecycle
```
[Draft] → [PendingApproval] → [Approved] → [Processing] → [Completed] → [Finalized]
    ↓              ↓
[Cancelled]  [Cancelled]
    ↓              ↓
[Refunded] (creates new transaction)
```

### Counter Session Lifecycle
```
[Closed] → [Open Session] → [Active] → [Close Session] → [Closed]
                    ↓                      ↓
              [Enter Floats]        [Verify Variance]
                                         ↓
                              [Yellow: Notes] [Red: Supervisor]
```

### Stock Transfer Lifecycle
```
[Requested] → [BranchManagerApproved] → [HQApproved] → [InTransit]
      ↓              ↓                      ↓
  [Cancelled]   [Rejected]           [PartiallyReceived] → [Completed]
```

### Journal Entry Workflow
```
[Draft] → [Pending] → [Posted]
   ↓
[Reversed] (creates new entry)
```

### STR Workflow
```
[Draft] → [PendingReview] → [PendingApproval] → [Submitted] → [Acknowledged]
   ↓              ↓                      ↓                  ↓
[Edit/Delete] [Return to Draft]   [Approve/Reject]   [Submit to goAML]
```

### Compliance Case Lifecycle
```
[Open] → [UnderReview] → [PendingApproval] → [Closed/Escalated]
    ↓
[Escalated] → [Closed]
```

---

## 18. Security Implementation

### Rate Limiting
| Endpoint | Limit |
|----------|-------|
| Login | 5/min per IP |
| API | 30/min per IP |
| Transactions | 10/min per user |
| STR | 3/min per user |
| Bulk | 1/5 min per user |
| Export | 5/min per user |

### IP Blocking
- Block after 10 failed login attempts in 5 minutes
- Default block duration: 60 minutes
- Max block duration: 24 hours
- Whitelist support (exact IP and CIDR notation)

### Audit Log Hash Chain
```php
// Each log entry contains SHA-256 hash of current entry
// plus hash of previous entry (previous_hash)
// Verification: recompute hash and check chain integrity
hash('sha256', $id . $action . $user_id . $entity_type . $entity_id . $old_values . $new_values . $previous_hash)
```

### Encryption
- ID numbers encrypted with random IV per encryption
- IV prepended to ciphertext
- Use `EncryptionService` for all sensitive data

### CSRF Protection
- All non-GET form submissions require valid CSRF token
- Same-origin policy enforced

---

## 19. API Reference

### Authentication
**Note**: CEMS-MY primarily uses session-based web authentication. The REST API uses Laravel Sanctum token authentication.

**Session-based auth (web):**
```
POST /login          - Login with email/password
POST /logout         - Logout
GET  /mfa/setup      - MFA setup page
POST /mfa/setup      - Store MFA secret
GET  /mfa/verify     - MFA verification page
POST /mfa/verify     - Verify MFA code
```

**Token-based auth (API):**
```
POST /api/sanctum/token  - Get Sanctum token
```

### API Response Format
```json
{
    "success": true,
    "data": { ... },
    "message": "Operation successful"
}
```

### API Error Format
```json
{
    "success": false,
    "message": "Error description",
    "errors": { ... }
}
```

---

## 20. Frontend/Views

### View Structure
```
resources/views/
├── accounting/           # Accounting views
│   ├── balance-sheet.blade.php
│   ├── budget.blade.php
│   ├── cash-flow.blade.php
│   ├── fiscal-years.blade.php
│   ├── journal/
│   │   ├── create.blade.php
│   │   ├── index.blade.php
│   │   ├── show.blade.php
│   │   └── workflow.blade.php
│   ├── ledger/
│   │   ├── account.blade.php
│   │   └── index.blade.php
│   ├── periods.blade.php
│   ├── profit-loss.blade.php
│   ├── ratios.blade.php
│   ├── reconciliation*.blade.php
│   └── revaluation/
├── audit/
│   ├── dashboard.blade.php
│   └── pdf.blade.php
├── auth/
│   └── login.blade.php
├── branches/
│   ├── create.blade.php
│   ├── edit.blade.php
│   ├── index.blade.php
│   └── show.blade.php
├── compliance/
│   ├── alerts/
│   ├── cases/
│   ├── edd-templates/
│   ├── edd/
│   ├── *.blade.php       # compliance.blade.php
│   └── workspace/
├── customers/            # Customer views
├── layout/               # Layout templates
├── reports/              # Report views
├── stock-transfers/      # Transfer views
├── counters/             # Counter views
└── transactions/          # Transaction views
```

### Frontend Stack
- **CSS**: Tailwind CSS (theme system with consistent design tokens)
- **JavaScript**: Laravel default (Blade templates with minimal JS)
- **No SPA framework** - Traditional server-rendered views

---

## 21. Testing Structure

### Test Organization
```
tests/
├── Feature/              # Integration tests
│   ├── AccountingWorkflowTest.php
│   ├── CounterHandoverTest.php
│   ├── EddWorkflowTest.php
│   ├── FiscalYearControllerTest.php
│   ├── FinancialStatementControllerTest.php
│   ├── JournalEntryWorkflowTest.php
│   ├── RealWorldTransactionWorkflowTest.php
│   ├── RouteConsistencyTest.php
│   ├── StrWorkflowTest.php
│   └── TransactionWorkflowTest.php
├── Unit/                 # Unit tests
│   ├── AmlRuleTest.php
│   ├── AuditServiceTest.php
│   ├── CashFlowServiceTest.php
│   ├── ComplianceServiceTest.php
│   ├── CurrencyPositionServiceTest.php
│   ├── FinancialRatioServiceTest.php
│   ├── MathServiceTest.php
│   └── RiskRatingServiceTest.php
└── TestCase.php          # Base test case
```

### Key Test Classes
| Test | Purpose |
|------|---------|
| `TransactionWorkflowTest` | Transaction creation, approval, cancellation |
| `RealWorldTransactionWorkflowTest` | End-to-end scenarios |
| `AccountingWorkflowTest` | Journal entries, periods, closing |
| `JournalEntryWorkflowTest` | Draft → Pending → Posted workflow |
| `StrWorkflowTest` | STR creation and workflow |
| `CounterHandoverTest` | Till custody transfer |
| `EddWorkflowTest` | EDD workflow tests |
| `FiscalYearControllerTest` | FY creation, closing, opening |
| `FinancialStatementControllerTest` | Trial balance, P&L, balance sheet, ratios |
| `RouteConsistencyTest` | Route/role access verification |
| `MathServiceTest` | BCMath precision |
| `CurrencyPositionServiceTest` | Stock/position calculations |
| `AuditServiceTest` | Hash chain verification |
| `FinancialRatioServiceTest` | Financial ratios |
| `CashFlowServiceTest` | Cash flow statement |
| `RiskRatingServiceTest` | Risk scoring |
| `ComplianceServiceTest` | CDD levels, sanctions, velocity, structuring |
| `AmlRuleTest` | AML rule engine |

### Test Commands
```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --filter=TransactionWorkflowTest

# Run single test class
php artisan test --filter=MathServiceTest

# Run via test runner script
php test-runner.php
```

---

## Appendix A: Default Seed Data

### Default Users
| Email | Password | Role |
|-------|----------|------|
| `admin@cems.my` | `Admin@123456` | admin |
| `teller1@cems.my` | `Teller@1234` | teller |
| `manager1@cems.my` | `Manager@1234` | manager |
| `compliance1@cems.my` | `Compliance@1234` | compliance_officer |

### Seeders
- `AccountingPeriodSeeder` - Creates current + 2 months
- `AmlRuleSeeder` - AML rule configurations
- `BranchSeeder` - HQ and branches
- `BudgetSeeder` - Sample monthly budgets
- `ChartOfAccountsSeeder` - 18 default accounts
- `CostCenterSeeder` - Cost center tracking
- `CounterSeeder` - Till/counter definitions
- `CurrencySeeder` - Supported currencies
- `DatabaseSeeder` - Main seeder
- `DepartmentSeeder` - Departments
- `EnhancedChartOfAccountsSeeder` - 50+ accounts
- `FiscalYearSeeder` - Current fiscal year
- `HighRiskCountrySeeder` - High-risk countries
- `SanctionListSeeder` - Sanctions lists
- `UserSeeder` - Default users

---

## Appendix B: Common Artisan Commands

```bash
# Clear caches
php artisan config:clear
php artisan route:clear
php artisan view:clear

# List routes
php artisan route:list

# Run tests
php artisan test

# Generate report
php artisan report:msb2 --date=2026-04-06

# Update sanctions
php artisan sanctions:update

# Run revaluation
php artisan revaluation:run-monthly

# Backup
php artisan backup:run

# Check queue health
php artisan queue:health-check
```

---

## Appendix C: Environment Variables

```env
# Application
APP_NAME="CEMS-MY"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cems_my
DB_USERNAME=root
DB_PASSWORD=

# Session
SESSION_LIFETIME=480
SESSION_SECURE_COOKIE=true

# MFA
MFA_ENABLED=true

# BNM Settings
CANCELLATION_WINDOW_HOURS=24
AGGREGATE_LOOKBACK_DAYS=7
SESSION_TIMEOUT_MINUTES=15
BNM_LICENSE_NUMBER=MSB-XXXXXXX
COMPANY_NAME="CEMS-MY MSB"

# goAML
GOAML_ENABLED=false
GOAML_ENDPOINT=
GOAML_API_KEY=

# Security
SECURITY_IP_BLOCKING_ENABLED=true
SECURITY_IP_WHITELIST=192.168.1.0/24,127.0.0.1
AUDIT_RETENTION_DAYS=2555

# Backup
BACKUP_DISK=local
```

---

**END OF DESIGN SPECIFICATION**
