# POS Module Design Specification

**Project:** CEMS-MY Currency Exchange Management System
**Module:** Point of Sale (POS) Interface
**Date:** 2026-04-15
**Version:** 1.0
**Status:** Approved

## Executive Summary

This document specifies the design for a Point of Sale (POS) interface module for the CEMS-MY Currency Exchange Management System. The POS module provides tellers at physical branches with a streamlined interface for daily operations including exchange rate management, multi-currency transaction processing, receipt generation, inventory tracking, end-of-day balancing, and customer relationship management.

The POS module is implemented as a distinct module within the existing Laravel application, following a modular architecture approach. It leverages existing services (TransactionService, ComplianceService, CounterService, etc.) while providing POS-specific UI and orchestration logic.

**Target Users:** Tellers at physical branches
**Timeline:** 14 weeks (3-6 months)
**Team Size:** 3-5 developers

## Table of Contents

1. [Architecture Overview](#1-architecture-overview)
2. [Module Structure](#2-module-structure)
3. [Features & User Flows](#3-features--user-flows)
4. [Data Models & Database Schema](#4-data-models--database-schema)
5. [API Design & Routes](#5-api-design--routes)
6. [Testing Strategy](#6-testing-strategy)
7. [Security Considerations](#7-security-considerations)
8. [Deployment & Monitoring](#8-deployment--monitoring)
9. [Implementation Phases](#9-implementation-phases)
10. [Success Criteria](#10-success-criteria)

## 1. Architecture Overview

### 1.1 Architectural Approach

The POS module follows a **modular architecture** approach within the existing Laravel application. This provides clear separation of concerns while sharing core services (authentication, RBAC, compliance, accounting).

**Key Design Principles:**
- Service Layer Pattern: All business logic in services, controllers handle HTTP only
- Repository Pattern: Data access through repositories for testability
- Event-Driven: Use Laravel events for cross-module communication
- API-First: Controllers expose both web and API endpoints
- Shared Services: Leverage existing services (TransactionService, ComplianceService, etc.)

### 1.2 Relationship to Existing Codebase

The POS module is **thin** - it's primarily a UI and orchestration layer on top of existing robust services.

#### Services We REUSE (Already Built)
- **TransactionService** - Core transaction creation, approval workflow, compliance checks
- **CounterService** - Counter session management, opening/closing, handover
- **CurrencyPositionService** - Multi-currency inventory tracking, position updates
- **StockTransferService** - Inter-branch cash transfers
- **ComplianceService** - CDD determination, sanctions screening, CTOS reporting
- **AccountingService** - Double-entry journal entries
- **RateApiService** - Exchange rate fetching (we'll extend for daily manual rates)
- **MathService** - BCMath precision calculations

#### Models We EXTEND
- **Customer** - Add corporate profile fields (company registration, signatories)
- **Transaction** - Already has all core fields, may add POS-specific metadata
- **Counter** - Already exists, POS will use directly
- **TillBalance** - Already exists, POS will query for real-time inventory
- **Currency** - Already exists, POS will use directly

#### New Models We CREATE
- **PosDailyRate** - Daily manually-keyed rates (extends ExchangeRateHistory concept)
- **PosReceipt** - Receipt templates and generation history
- **PosCorporateProfile** - Corporate customer details (company reg, directors, etc.)

#### New Services We CREATE
- **PosRateService** - Daily rate management (manual entry, copy previous day)
- **PosReceiptService** - Receipt generation (thermal + PDF)
- **PosInventoryService** - Real-time inventory aggregation across counters
- **PosCustomerService** - Corporate profile management

## 2. Module Structure

### 2.1 Directory Structure

```
app/Modules/Pos/
├── Controllers/
│   ├── PosDashboardController.php
│   ├── PosTransactionController.php
│   ├── PosRateController.php
│   ├── PosReceiptController.php
│   ├── PosInventoryController.php
│   └── PosCustomerController.php
├── Services/
│   ├── PosRateService.php
│   ├── PosTransactionService.php
│   ├── PosReceiptService.php
│   ├── PosInventoryService.php
│   └── PosCustomerService.php
├── Models/
│   ├── PosDailyRate.php
│   ├── PosReceipt.php
│   └── PosCorporateProfile.php
├── Requests/
│   ├── PosRateRequest.php
│   ├── PosTransactionRequest.php
│   └── PosReceiptRequest.php
└── resources/views/pos/
    ├── dashboard.blade.php
    ├── transaction.blade.php
    ├── rates.blade.php
    ├── receipt.blade.php
    └── inventory.blade.php
```

### 2.2 Module Boundaries

**POS Module Responsibilities:**
- POS-specific UI and user workflows
- Daily rate management (manual entry)
- Receipt generation (thermal + PDF)
- Real-time inventory aggregation
- Corporate profile management
- POS-specific orchestration of existing services

**Shared Services (Existing):**
- Transaction processing (TransactionService)
- Compliance checks (ComplianceService)
- Counter management (CounterService)
- Accounting (AccountingService)
- Authentication & RBAC (existing middleware)

## 3. Features & User Flows

### 3.1 Feature 1: Real-Time Exchange Rate Dashboard

**User Flow:**
1. Teller opens POS dashboard at start of shift
2. Dashboard shows today's rates (Buy/Sell boards) or prompts to enter rates
3. Teller can:
   - Manually key in rates for each currency
   - Copy previous day's rates with one click
   - View rate history (last 7 days)
4. Rates are stored in `PosDailyRate` table for the day
5. All transactions use today's rates

**UI Components:**
- Rate board display (large, readable fonts)
- Currency grid with Buy/Sell columns
- "Copy Yesterday's Rates" button
- Rate history modal
- Last updated timestamp

**Technical Implementation:**
- `PosRateService::getTodayRates()` - Returns today's rates or null
- `PosRateService::setDailyRates(array $rates)` - Stores today's rates
- `PosRateService::copyPreviousDayRates()` - Copies from yesterday
- Cache rates for performance (invalidate on update)

### 3.2 Feature 2: Multi-Currency Transaction Processing

**User Flow:**
1. Teller selects customer (search by name/ID/phone)
2. System displays customer profile (individual or corporate)
3. Teller selects transaction type (Buy/Sell)
4. Teller enters:
   - Currency
   - Amount (foreign)
   - Purpose
   - Source of funds
5. System auto-calculates:
   - Local amount (using today's rates)
   - CDD level
   - Compliance flags
6. System validates:
   - Till is open
   - Sufficient stock (for Sell)
   - Customer compliance status
7. Teller confirms transaction
8. System processes transaction and generates receipt

**UI Components:**
- Customer search with autocomplete
- Customer profile card (risk rating, CDD level, PEP status)
- Transaction form (currency selector, amount input, purpose dropdown)
- Real-time calculation display
- Compliance warnings (if any)
- Confirmation modal

**Technical Implementation:**
- `PosTransactionService::createTransaction(array $data)` - Wraps existing TransactionService
- `PosTransactionService::validateTransaction(array $data)` - Pre-validation checks
- `PosTransactionService::calculateQuote(array $data)` - Real-time quote calculation
- Uses existing `TransactionService`, `ComplianceService`, `CurrencyPositionService`

### 3.3 Feature 3: Automated Receipt Generation

**User Flow:**
1. After transaction completion, system auto-generates receipt
2. Teller can:
   - Print to thermal printer (58mm/80mm)
   - Download PDF (A4)
   - Reprint last receipt
3. Receipt includes:
   - BNM-required disclosures
   - Transaction details
   - Customer info (masked ID)
   - Exchange rate used
   - Compliance warnings (if applicable)

**UI Components:**
- "Print Receipt" button (thermal)
- "Download PDF" button
- "Reprint" button (last transaction)
- Receipt preview modal

**Technical Implementation:**
- `PosReceiptService::generateThermalReceipt(Transaction $transaction)` - HTML for thermal printer
- `PosReceiptService::generatePdfReceipt(Transaction $transaction)` - PDF generation
- `PosReceiptService::getReceiptTemplate(string $type)` - Template retrieval
- Uses existing `Transaction` model data

### 3.4 Feature 4: Real-Time Multi-Currency Inventory Tracking

**User Flow:**
1. Teller views inventory dashboard
2. System shows:
   - Current stock per currency (all counters)
   - Today's transactions per currency
   - Low stock warnings
3. Teller can drill down to specific counter

**UI Components:**
- Inventory grid (currency, balance, today's transactions, status)
- Low stock indicators (color-coded)
- Counter selector (for multi-branch view)
- Refresh button

**Technical Implementation:**
- `PosInventoryService::getInventoryByCounter(string $counterId)` - Per-counter inventory
- `PosInventoryService::getAggregateInventory()` - All counters combined
- `PosInventoryService::getLowStockCurrencies()` - Low stock detection
- Uses existing `CurrencyPositionService`, `TillBalance`

### 3.5 Feature 5: End-of-Day Till Balancing

**User Flow:**
1. Teller initiates EOD process
2. System shows:
   - Expected balance per currency
   - Physical count input fields
   - Variance calculation
3. Teller enters physical counts
4. System calculates variance
5. If variance > threshold:
   - Yellow (RM 100-500): Requires notes
   - Red (> RM 500): Requires manager approval
6. Manager approves (if needed)
7. System closes till and generates EOD report

**UI Components:**
- EOD form (physical count inputs per currency)
- Variance display (color-coded)
- Notes field (for yellow variance)
- Manager approval modal (for red variance)
- EOD report preview

**Technical Implementation:**
- Uses existing `CounterService::closeSession()`
- `PosInventoryService::calculateEodVariance(array $physicalCounts)` - Variance calc
- `PosInventoryService::generateEodReport(CounterSession $session)` - Report generation

### 3.6 Feature 6: Inter-Branch Cash Transfers

**User Flow:**
1. Teller initiates transfer request
2. Selects source/destination branches
3. Adds transfer items (currency, quantity, rate)
4. System validates stock availability
5. Manager approves (branch manager)
6. HQ approves (admin)
7. Transfer dispatched
8. Destination branch receives and confirms

**UI Components:**
- Transfer request form
- Branch selectors
- Item grid (currency, quantity, rate, value)
- Approval workflow display
- Transfer status tracking

**Technical Implementation:**
- Uses existing `StockTransferService`
- `PosInventoryService::validateTransferStock(array $items)` - Stock validation
- UI wraps existing service methods

### 3.7 Feature 7: Customer Profile & CRM

**User Flow:**
1. Teller searches for customer
2. System displays:
   - Individual: Personal info, risk rating, transaction history
   - Corporate: Company info, authorized signatories, transaction history
3. Teller can:
   - View full profile
   - See recent transactions
   - Check compliance status
   - Create new customer

**UI Components:**
- Customer search with autocomplete
- Customer profile card (individual/corporate)
- Transaction history table
- Compliance status display
- "New Customer" button

**Technical Implementation:**
- `PosCustomerService::searchCustomers(string $query)` - Customer search
- `PosCustomerService::getCustomerProfile(int $customerId)` - Profile retrieval
- `PosCustomerService::getTransactionHistory(int $customerId)` - Transaction history
- Extends existing `Customer` model with corporate fields

## 4. Data Models & Database Schema

### 4.1 New Tables

#### 4.1.1 pos_daily_rates

Stores daily manually-keyed exchange rates for POS operations.

```php
Schema::create('pos_daily_rates', function (Blueprint $table) {
    $table->id();
    $table->date('rate_date')->unique();
    $table->string('currency_code', 3);
    $table->decimal('buy_rate', 10, 6);
    $table->decimal('sell_rate', 10, 6);
    $table->decimal('mid_rate', 10, 6);
    $table->boolean('is_active')->default(true);
    $table->foreignId('created_by')->constrained('users');
    $table->timestamp('created_at');
    $table->timestamp('updated_at')->nullable();

    $table->index(['rate_date', 'currency_code']);
    $table->index('is_active');
});
```

**Relationship to Current Schema:**
- Complements `exchange_rate_histories` (API rates)
- `exchange_rate_histories` = automated API rates
- `pos_daily_rates` = manual POS rates
- Both can coexist for different purposes

#### 4.1.2 pos_receipts

Stores receipt generation history and templates.

```php
Schema::create('pos_receipts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('transaction_id')->constrained()->onDelete('cascade');
    $table->string('receipt_number')->unique();
    $table->enum('receipt_type', ['thermal', 'pdf']);
    $table->string('template_type');
    $table->json('receipt_data');
    $table->timestamp('printed_at')->nullable();
    $table->foreignId('printed_by')->nullable()->constrained('users');
    $table->timestamps();

    $table->index('transaction_id');
    $table->index('receipt_number');
});
```

**Relationship to Current Schema:**
- Extends `transactions` table
- Each transaction can have multiple receipts (thermal reprint, PDF download)
- Stores receipt content for audit trail and reprint capability

#### 4.1.3 pos_corporate_profiles

Extends customer model for corporate customers.

```php
Schema::create('pos_corporate_profiles', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_id')->constrained()->onDelete('cascade');
    $table->string('company_name');
    $table->string('registration_number', 50)->unique();
    $table->string('business_type');
    $table->string('industry');
    $table->text('registered_address');
    $table->string('contact_person');
    $table->string('contact_phone');
    $table->string('contact_email');
    $table->date('registration_date');
    $table->string('tax_id')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();

    $table->unique('customer_id');
    $table->index('registration_number');
    $table->index('company_name');
});
```

**Relationship to Current Schema:**
- Extends `customers` table
- One-to-one relationship with `customers`
- Only for customers where `customer_type = 'corporate'`

#### 4.1.4 pos_authorized_signatories

Authorized signatories for corporate accounts.

```php
Schema::create('pos_authorized_signatories', function (Blueprint $table) {
    $table->id();
    $table->foreignId('corporate_profile_id')->constrained('pos_corporate_profiles')->onDelete('cascade');
    $table->string('full_name');
    $table->string('id_type');
    $table->string('id_number_encrypted');
    $table->string('position');
    $table->string('phone');
    $table->string('email');
    $table->boolean('is_primary')->default(false);
    $table->boolean('is_active')->default(true);
    $table->timestamps();

    $table->index('corporate_profile_id');
});
```

**Relationship to Current Schema:**
- Extends `pos_corporate_profiles`
- Many-to-one relationship with corporate profiles
- Independent of `customers` table

### 4.2 Modified Tables

#### 4.2.1 customers

Add customer_type field to distinguish individual vs corporate.

```php
// Add to existing customers table
$table->enum('customer_type', ['individual', 'corporate'])->default('individual')->after('id');
$table->index('customer_type');
```

**Purpose:** Distinguish individual vs corporate customers for POS UI.

### 4.3 Database Relationships

```
Customer (1) ←→ (0..1) PosCorporateProfile
PosCorporateProfile (1) ←→ (0..n) PosAuthorizedSignatory
Transaction (1) ←→ (1) PosReceipt
PosDailyRate (1) ←→ (n) Transaction (via rate_date)
```

### 4.4 Key Design Decisions

1. **Separate pos_daily_rates table**: Keeps POS rates distinct from ExchangeRateHistory (API rates). POS rates are manually keyed, API rates are automated.

2. **Receipt history**: Storing receipt data allows reprinting and audit trail. JSON field stores receipt content for flexibility.

3. **Corporate profile extension**: Separate table keeps individual customers clean while supporting rich corporate data.

4. **Authorized signatories**: Many-to-many relationship allows multiple signatories per corporate account.

5. **Customer type enum**: Simple flag to distinguish customer types without complex inheritance.

## 5. API Design & Routes

### 5.1 Route Structure

All POS routes are organized under `/pos` prefix with appropriate middleware.

```php
Route::middleware(['auth', 'session.timeout'])->group(function () {
    Route::prefix('pos')->name('pos.')->group(function () {
        // Dashboard
        Route::get('/dashboard', [PosDashboardController::class, 'index'])->name('dashboard');

        // Rates
        Route::prefix('rates')->name('rates.')->group(function () {
            Route::get('/', [PosRateController::class, 'index'])->name('index');
            Route::get('/today', [PosRateController::class, 'getTodayRates'])->name('today');
            Route::post('/set', [PosRateController::class, 'setDailyRates'])->name('set');
            Route::post('/copy-yesterday', [PosRateController::class, 'copyYesterdayRates'])->name('copy-yesterday');
            Route::get('/history', [PosRateController::class, 'getRateHistory'])->name('history');
        });

        // Transactions
        Route::prefix('transactions')->name('transactions.')->group(function () {
            Route::get('/', [PosTransactionController::class, 'index'])->name('index');
            Route::get('/create', [PosTransactionController::class, 'create'])->name('create');
            Route::post('/', [PosTransactionController::class, 'store'])->name('store');
            Route::get('/{transaction}', [PosTransactionController::class, 'show'])->name('show');
            Route::get('/{transaction}/quote', [PosTransactionController::class, 'quote'])->name('quote');
            Route::post('/{transaction}/approve', [PosTransactionController::class, 'approve'])->name('approve');
        });

        // Receipts
        Route::prefix('receipts')->name('receipts.')->group(function () {
            Route::get('/{transaction}/thermal', [PosReceiptController::class, 'thermal'])->name('thermal');
            Route::get('/{transaction}/pdf', [PosReceiptController::class, 'pdf'])->name('pdf');
            Route::get('/{transaction}/reprint', [PosReceiptController::class, 'reprint'])->name('reprint');
        });

        // Inventory
        Route::prefix('inventory')->name('inventory.')->group(function () {
            Route::get('/', [PosInventoryController::class, 'index'])->name('index');
            Route::get('/counter/{counterId}', [PosInventoryController::class, 'counter'])->name('counter');
            Route::get('/aggregate', [PosInventoryController::class, 'aggregate'])->name('aggregate');
            Route::get('/low-stock', [PosInventoryController::class, 'lowStock'])->name('low-stock');
            Route::post('/eod', [PosInventoryController::class, 'eod'])->name('eod');
            Route::get('/eod/{sessionId}/report', [PosInventoryController::class, 'eodReport'])->name('eod-report');
        });

        // Customers
        Route::prefix('customers')->name('customers.')->group(function () {
            Route::get('/search', [PosCustomerController::class, 'search'])->name('search');
            Route::get('/{customer}', [PosCustomerController::class, 'show'])->name('show');
            Route::get('/{customer}/transactions', [PosCustomerController::class, 'transactions'])->name('transactions');
            Route::get('/create', [PosCustomerController::class, 'create'])->name('create');
            Route::post('/', [PosCustomerController::class, 'store'])->name('store');
            Route::get('/{customer}/corporate', [PosCustomerController::class, 'corporateProfile'])->name('corporate');
            Route::post('/{customer}/corporate', [PosCustomerController::class, 'storeCorporate'])->name('store-corporate');
        });
    });
});
```

### 5.2 API Endpoints (JSON Responses)

#### GET /api/v1/pos/rates/today
```json
{
    "date": "2026-04-15",
    "rates": {
        "USD": {"buy": 4.6500, "sell": 4.7500, "mid": 4.7000},
        "EUR": {"buy": 5.0500, "sell": 5.1500, "mid": 5.1000}
    },
    "last_updated": "2026-04-15 09:00:00",
    "updated_by": {"id": 1, "name": "John Doe"}
}
```

#### POST /api/v1/pos/transactions/quote
**Request:**
```json
{
    "type": "Buy",
    "currency_code": "USD",
    "amount_foreign": 1000
}
```
**Response:**
```json
{
    "amount_local": 4650.00,
    "rate": 4.6500,
    "cdd_level": "Standard",
    "compliance_flags": [],
    "warnings": []
}
```

#### GET /api/v1/pos/inventory/aggregate
```json
{
    "currencies": [
        {
            "code": "USD",
            "balance": 50000.00,
            "today_buy": 15000.00,
            "today_sell": 10000.00,
            "status": "normal"
        }
    ],
    "low_stock": ["EUR", "GBP"]
}
```

### 5.3 Middleware Stack

POS routes use the following middleware:

```php
Route::middleware([
    'auth',                    // Authentication required
    'session.timeout',         // Session timeout (8 hours default)
    'EnsureMfaVerified',       // MFA verification for sensitive operations
])->group(function () {
    // POS routes here
});
```

**Note:** MFA is enforced on sensitive operations (transaction creation, rate setting, EOD). Read operations (viewing rates, inventory, customer profiles) do not require MFA verification.

## 6. Testing Strategy

### 6.1 Test Organization

POS module tests follow the existing test structure:

```
tests/
├── Feature/
│   └── Pos/
│       ├── PosRateControllerTest.php
│       ├── PosTransactionControllerTest.php
│       ├── PosReceiptControllerTest.php
│       ├── PosInventoryControllerTest.php
│       ├── PosCustomerControllerTest.php
│       └── PosDashboardControllerTest.php
├── Unit/
│   └── Pos/
│       ├── PosRateServiceTest.php
│       ├── PosTransactionServiceTest.php
│       ├── PosReceiptServiceTest.php
│       ├── PosInventoryServiceTest.php
│       └── PosCustomerServiceTest.php
```

### 6.2 Unit Tests (Service Layer)

#### PosRateServiceTest
```php
class PosRateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_today_rates_returns_null_when_no_rates_set()
    {
        $service = new PosRateService();
        $rates = $service->getTodayRates();

        $this->assertNull($rates);
    }

    public function test_set_daily_rates_stores_rates_correctly()
    {
        $user = User::factory()->create();
        $service = new PosRateService();

        $rates = [
            'USD' => ['buy' => 4.6500, 'sell' => 4.7500, 'mid' => 4.7000],
            'EUR' => ['buy' => 5.0500, 'sell' => 5.1500, 'mid' => 5.1000],
        ];

        $result = $service->setDailyRates($rates, $user->id);

        $this->assertDatabaseHas('pos_daily_rates', [
            'rate_date' => today()->toDateString(),
            'currency_code' => 'USD',
            'buy_rate' => 4.6500,
        ]);
    }

    public function test_copy_previous_day_rates_copies_correctly()
    {
        PosDailyRate::factory()->create([
            'rate_date' => yesterday()->toDateString(),
            'currency_code' => 'USD',
            'buy_rate' => 4.6500,
            'sell_rate' => 4.7500,
        ]);

        $service = new PosRateService();
        $rates = $service->copyPreviousDayRates();

        $this->assertNotNull($rates);
        $this->assertEquals(4.6500, $rates['USD']['buy']);
    }
}
```

### 6.3 Feature Tests (Controller Layer)

#### PosRateControllerTest
```php
class PosRateControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_today_rates_returns_json()
    {
        $user = User::factory()->create();
        PosDailyRate::factory()->create([
            'rate_date' => today()->toDateString(),
            'currency_code' => 'USD',
            'buy_rate' => 4.6500,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/pos/rates/today');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'date',
                'rates' => ['USD' => ['buy', 'sell', 'mid']],
                'last_updated',
            ]);
    }

    public function test_set_daily_rates_requires_authentication()
    {
        $response = $this->postJson('/api/v1/pos/rates/set', [
            'rates' => ['USD' => ['buy' => 4.6500, 'sell' => 4.7500]],
        ]);

        $response->assertStatus(401);
    }

    public function test_set_daily_rates_requires_mfa()
    {
        $user = User::factory()->create(['mfa_enabled' => true]);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/pos/rates/set', [
                'rates' => ['USD' => ['buy' => 4.6500, 'sell' => 4.7500]],
            ]);

        $response->assertStatus(403); // MFA not verified
    }
}
```

### 6.4 Integration Tests

#### PosEodWorkflowTest
```php
class PosEodWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_eod_workflow_with_normal_variance()
    {
        $user = User::factory()->create();
        $counter = Counter::factory()->create();

        $session = CounterSession::factory()->create([
            'counter_id' => $counter->id,
            'user_id' => $user->id,
            'status' => 'open',
        ]);

        TillBalance::factory()->create([
            'till_id' => $counter->id,
            'currency_code' => 'USD',
            'opening_balance' => 10000,
            'foreign_total' => 5000,
            'date' => today(),
        ]);

        $response = $this->actingAs($user)
            ->post(route('pos.inventory.eod'), [
                'counter_id' => $counter->id,
                'physical_counts' => [
                    ['currency_code' => 'USD', 'amount' => 15050], // 50 variance
                ],
                'notes' => 'Small counting error',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('counter_sessions', [
            'id' => $session->id,
            'status' => 'closed',
        ]);
    }

    public function test_eod_workflow_with_large_variance_requires_manager_approval()
    {
        $user = User::factory()->create();
        $manager = User::factory()->manager()->create();
        $counter = Counter::factory()->create();

        $session = CounterSession::factory()->create([
            'counter_id' => $counter->id,
            'user_id' => $user->id,
            'status' => 'open',
        ]);

        TillBalance::factory()->create([
            'till_id' => $counter->id,
            'currency_code' => 'USD',
            'opening_balance' => 10000,
            'foreign_total' => 5000,
            'date' => today(),
        ]);

        $response = $this->actingAs($user)
            ->post(route('pos.inventory.eod'), [
                'counter_id' => $counter->id,
                'physical_counts' => [
                    ['currency_code' => 'USD', 'amount' => 16000], // 1000 variance
                ],
            ]);

        $response->assertRedirect(route('pos.inventory.eod.approval'));
    }
}
```

### 6.5 Test Coverage Goals

- **Unit Tests**: 90%+ coverage for service layer
- **Feature Tests**: 80%+ coverage for controller layer
- **Integration Tests**: Critical workflows (EOD, transaction creation, rate setting)
- **Edge Cases**: Race conditions, variance thresholds, compliance flags

### 6.6 Testing Tools

- **PHPUnit**: Existing test framework
- **RefreshDatabase**: Clean database for each test
- **Factory**: Use existing factories for test data
- **Mocking**: Mock external services (RateApiService, printers)
- **Browser Tests**: Optional Playwright tests for critical UI flows

## 7. Security Considerations

### 7.1 Authentication & Authorization

#### Role-Based Access Control (RBAC)
POS operations leverage the existing enum-based RBAC system:

```php
// Existing roles from UserRole enum
UserRole::Teller       // Can create transactions, view rates/inventory
UserRole::Manager     // Can approve large transactions, EOD with variance
UserRole::ComplianceOfficer // Can view compliance data
UserRole::Admin       // Full access including rate management
```

**POS-Specific Permissions:**
- **Teller**: Create transactions, view rates, view inventory, search customers
- **Manager**: Approve transactions (≥ RM 50k), approve EOD variance, view all reports
- **Admin**: Set daily rates, manage corporate profiles, configure POS settings

#### MFA Enforcement
MFA is enforced on sensitive operations (per existing system):

```php
// MFA required for:
- Setting daily rates
- Creating transactions
- Approving transactions
- Processing EOD with variance
- Managing corporate profiles

// MFA NOT required for:
- Viewing rates (read-only)
- Viewing inventory (read-only)
- Searching customers (read-only)
- Viewing transaction history (read-only)
```

### 7.2 Data Protection

#### Customer Data Encryption
- **ID Numbers**: Already encrypted in `customers.id_number_encrypted` (binary field)
- **Corporate Data**: New fields in `pos_corporate_profiles` will use existing `EncryptionService`
- **Signatory Data**: ID numbers in `pos_authorized_signatories` will be encrypted

#### Audit Trail
All POS operations create audit log entries using existing `AuditService`:

```php
// Rate changes
$auditService->logWithSeverity('pos_rate_set', [
    'user_id' => $userId,
    'entity_type' => 'PosDailyRate',
    'new_values' => $rates,
], 'INFO');

// Transaction creation
$auditService->logWithSeverity('pos_transaction_created', [
    'user_id' => $userId,
    'entity_type' => 'Transaction',
    'entity_id' => $transactionId,
    'new_values' => [
        'type' => $transaction->type,
        'amount_local' => $transaction->amount_local,
        'currency' => $transaction->currency_code,
    ],
], 'INFO');

// EOD variance
$auditService->logWithSeverity('pos_eod_variance', [
    'user_id' => $userId,
    'entity_type' => 'CounterSession',
    'entity_id' => $sessionId,
    'new_values' => [
        'variance_myr' => $variance,
        'variance_notes' => $notes,
    ],
], $variance > 500 ? 'WARNING' : 'INFO');
```

### 7.3 Input Validation

#### Request Validation
All POS requests use Form Request validation:

```php
class PosRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('setRates', PosDailyRate::class);
    }

    public function rules(): array
    {
        return [
            'rates' => 'required|array|min:1',
            'rates.*.buy_rate' => 'required|numeric|min:0|max:999999.999999',
            'rates.*.sell_rate' => 'required|numeric|min:0|max:999999.999999',
            'rates.*.mid_rate' => 'required|numeric|min:0|max:999999.999999',
        ];
    }
}

class PosTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('createTransaction', Transaction::class);
    }

    public function rules(): array
    {
        return [
            'type' => 'required|in:Buy,Sell',
            'currency_code' => 'required|string|size:3|exists:currencies,code',
            'amount_foreign' => 'required|numeric|min:0.01|max:999999999.9999',
            'customer_id' => 'required|exists:customers,id',
            'till_id' => 'required|string|max:50',
            'purpose' => 'required|string|max:255',
            'source_of_funds' => 'required|string|max:255',
        ];
    }
}
```

#### SQL Injection Prevention
- Use parameterized queries (Laravel Eloquent/Query Builder)
- Never concatenate user input into SQL queries
- Use `where()` with parameters instead of raw `whereRaw()`

#### XSS Prevention
- Escape all user input in Blade templates (automatic with `{{ }}`)
- Use `{!! !!}` only for trusted content (receipts, PDFs)
- Sanitize customer search input

### 7.4 Rate Limiting

Leverage existing `StrictRateLimit` middleware:

```php
Route::middleware([
    'auth',
    'session.timeout',
    'StrictRateLimit:pos', // Custom POS rate limit
])->prefix('pos')->group(function () {
    // POS routes
});
```

**Rate Limits:**
- **Transaction creation**: 10 per minute per user
- **Rate setting**: 3 per minute per user
- **EOD processing**: 1 per 5 minutes per counter
- **Customer search**: 30 per minute per user

### 7.5 Concurrency Control

#### Optimistic Locking
Transactions already use optimistic locking (`version` field). POS will leverage this:

```php
// Existing transaction version field
$transaction = Transaction::where('id', $id)
    ->where('version', $currentVersion)
    ->lockForUpdate()
    ->first();

if (!$transaction) {
    throw new \RuntimeException('Transaction was modified by another user');
}
```

#### Pessimistic Locking
For inventory updates and EOD processing:

```php
// Lock till balance during EOD
$tillBalance = TillBalance::where('id', $id)
    ->lockForUpdate()
    ->first();

// Lock currency position during transaction
$position = CurrencyPosition::where('currency_code', $currencyCode)
    ->where('till_id', $tillId)
    ->lockForUpdate()
    ->first();
```

### 7.6 Session Security

#### Session Timeout
- Existing `session.timeout` middleware (8 hours default)
- POS sessions will inherit this setting
- Configurable per environment

#### Session Fixation Prevention
- Laravel automatically regenerates session ID on login
- POS will use existing authentication system

#### CSRF Protection
- All POS POST/PUT/DELETE routes require CSRF token
- API endpoints will use token-based authentication

### 7.7 Printer Security

#### Thermal Printer Access
- Printer access restricted to authenticated users
- Receipt generation logged with user ID
- No sensitive data in printer logs

#### PDF Generation
- PDF files stored temporarily (24 hours)
- Access restricted to transaction owner
- Automatic cleanup of old PDFs

### 7.8 Compliance & BNM Requirements

#### CDD Level Enforcement
- Automatic CDD determination based on transaction amount
- Enhanced CDD transactions require manager approval
- CDD level stored in transaction record

#### Sanctions Screening
- All customers screened against sanctions lists
- Sanction matches block transactions
- Screening logged in audit trail

#### CTOS Reporting
- Automatic CTOS report generation for ≥ RM 10k cash transactions
- CTOS submission tracked in audit log
- Failed submissions flagged for manual review

### 7.9 Error Handling

#### Sensitive Data in Errors
- Never expose customer data in error messages
- Never display database errors to users
- Log detailed errors server-side only

#### Rate Limit Errors
```php
if ($rateLimitExceeded) {
    return response()->json([
        'error' => 'Too many requests',
        'message' => 'Please wait before trying again',
    ], 429);
}
```

#### Validation Errors
```php
if ($validationFails) {
    return response()->json([
        'error' => 'Validation failed',
        'errors' => $validator->errors(),
    ], 422);
}
```

## 8. Deployment & Monitoring

### 8.1 Deployment Strategy

#### Environment Configuration
POS module uses existing Laravel environment variables:

```bash
# .env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://cems-msb.example.com

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cems_msb
DB_USERNAME=pos_user
DB_PASSWORD=secure_password

# Cache
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

# POS-specific settings
POS_RATE_CACHE_TTL=3600  # 1 hour
POS_RECEIPT_STORAGE_PATH=storage/app/receipts
POS_THERMAL_PRINTER_DEFAULT=58mm
POS_EOD_VARIANCE_YELLOW=100
POS_EOD_VARIANCE_RED=500
```

#### Database Migrations
Run migrations in order:

```bash
# Create POS tables
php artisan migrate --path=database/migrations/2026_04_15_000001_add_customer_type_to_customers_table.php
php artisan migrate --path=database/migrations/2026_04_15_000002_create_pos_daily_rates_table.php
php artisan migrate --path=database/migrations/2026_04_15_000003_create_pos_receipts_table.php
php artisan migrate --path=database/migrations/2026_04_15_000004_create_pos_corporate_profiles_table.php
php artisan migrate --path=database/migrations/2026_04_15_000005_create_pos_authorized_signatories_table.php

# Seed initial data
php artisan db:seed --class=PosDailyRateSeeder
php artisan db:seed --class=PosReceiptTemplateSeeder
```

#### Asset Compilation
```bash
# Install dependencies
npm install

# Compile assets for production
npm run build

# Optimize for production
php artisan optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

#### Deployment Steps
1. **Staging Deployment**
   - Deploy to staging environment
   - Run full test suite
   - Perform UAT with tellers
   - Verify all POS features work correctly

2. **Production Deployment**
   - Schedule maintenance window (low-traffic hours)
   - Backup database
   - Deploy code changes
   - Run migrations
   - Clear caches
   - Verify POS functionality
   - Monitor for errors

### 8.2 Monitoring & Logging

#### Application Logging
Leverage existing logging infrastructure:

```php
// POS-specific log channels
'channels' => [
    'pos' => [
        'driver' => 'daily',
        'path' => storage_path('logs/pos.log'),
        'level' => 'info',
        'days' => 30,
    ],
    'pos_errors' => [
        'driver' => 'daily',
        'path' => storage_path('logs/pos-errors.log'),
        'level' => 'error',
        'days' => 90,
    ],
],
```

**Log Events:**
- Rate changes (who, when, what)
- Transaction creation (amounts, customer, compliance)
- EOD processing (variance, approval)
- Receipt generation (type, user)
- Inventory alerts (low stock, discrepancies)

#### Performance Monitoring
Use existing query performance monitoring:

```php
// Log slow POS queries
if ($queryTime > 1000) { // 1 second
    Log::channel('pos')->warning('Slow POS query', [
        'query' => $sql,
        'time' => $queryTime,
        'user_id' => auth()->id(),
    ]);
}
```

**Key Metrics:**
- Transaction creation time
- Rate query response time
- Inventory aggregation time
- Receipt generation time
- EOD processing time

#### Error Tracking
Integrate with existing error tracking:

```php
// Track POS-specific errors
try {
    // POS operation
} catch (\Exception $e) {
    Log::channel('pos_errors')->error('POS operation failed', [
        'operation' => 'transaction_create',
        'user_id' => auth()->id(),
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);

    // Notify admin for critical errors
    if ($e->getCode() >= 500) {
        Notification::route('mail', 'admin@example.com')
            ->notify(new PosCriticalError($e));
    }
}
```

#### Health Checks
Add POS-specific health checks:

```php
// app/Http/Controllers/HealthCheckController.php
public function index(): JsonResponse
{
    $health = [
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
        'services' => [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
            'pos_rates' => $this->checkPosRates(),
            'pos_inventory' => $this->checkPosInventory(),
        ],
    ];

    return response()->json($health);
}

protected function checkPosRates(): array
{
    try {
        $todayRates = PosDailyRate::where('rate_date', today())->first();
        return [
            'status' => $todayRates ? 'ok' : 'warning',
            'message' => $todayRates ? 'Rates set for today' : 'No rates set for today',
        ];
    } catch (\Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}
```

### 8.3 Alerting

#### Critical Alerts
- **Database connection failures**: Immediate notification
- **Cache failures**: Immediate notification
- **Queue failures**: Immediate notification
- **Rate not set for today**: Warning at 9 AM
- **EOD variance > RM 500**: Immediate notification to manager
- **Transaction failures**: Immediate notification to admin

#### Warning Alerts
- **Low stock (< RM 10k)**: Daily summary
- **EOD variance RM 100-500**: Daily summary
- **Slow queries (> 1s)**: Weekly summary
- **High error rate**: Daily summary

#### Informational Alerts
- **Daily transaction volume**: Daily summary
- **EOD completion status**: Daily summary
- **Rate changes**: Immediate notification

### 8.4 Backup & Recovery

#### Database Backups
- **Daily backups**: Automated at 2 AM
- **Retention**: 30 days
- **Offsite storage**: Encrypted backups to cloud storage

#### Recovery Procedures
1. **Database Recovery**
   - Restore from latest backup
   - Replay transaction logs
   - Verify data integrity

2. **Application Recovery**
   - Deploy previous stable version
   - Clear caches
   - Verify functionality

3. **POS-Specific Recovery**
   - Verify today's rates are set
   - Verify counter sessions are valid
   - Verify inventory is accurate

### 8.5 Performance Optimization

#### Caching Strategy
```php
// Cache today's rates
Cache::remember('pos:rates:today', 3600, function () {
    return PosDailyRate::where('rate_date', today())->get();
});

// Cache inventory aggregation
Cache::remember('pos:inventory:aggregate', 300, function () {
    return PosInventoryService::getAggregateInventory();
});

// Cache customer search results
Cache::remember("pos:customer:search:{$query}", 60, function () use ($query) {
    return PosCustomerService::searchCustomers($query);
});
```

#### Database Indexing
```sql
-- Existing indexes (no changes needed)
-- New POS indexes
CREATE INDEX idx_pos_daily_rates_date_currency ON pos_daily_rates(rate_date, currency_code);
CREATE INDEX idx_pos_receipts_transaction ON pos_receipts(transaction_id);
CREATE INDEX idx_pos_corporate_customer ON pos_corporate_profiles(customer_id);
CREATE INDEX idx_pos_signatories_corporate ON pos_authorized_signatories(corporate_profile_id);
```

#### Query Optimization
- Use eager loading to prevent N+1 queries
- Use pagination for large datasets
- Use database indexes for frequent queries
- Cache expensive aggregations

### 8.6 Maintenance Tasks

#### Daily Tasks
- Verify today's rates are set
- Monitor EOD completion
- Review error logs
- Check low stock alerts

#### Weekly Tasks
- Review transaction volume trends
- Analyze EOD variance patterns
- Update rate history reports
- Review compliance flags

#### Monthly Tasks
- Archive old transaction logs
- Review and optimize indexes
- Update receipt templates
- Review user access permissions

### 8.7 Documentation

#### User Documentation
- POS user manual (teller guide)
- EOD procedures
- Rate management guide
- Troubleshooting guide

#### Technical Documentation
- API documentation
- Database schema documentation
- Service layer documentation
- Deployment guide

#### Compliance Documentation
- BNM compliance checklist
- Audit trail procedures
- Data retention policies
- Security procedures

## 9. Implementation Phases

### Phase 1: Foundation (Weeks 1-2)
**Goal:** Set up POS module structure and core infrastructure.

**Tasks:**
1. Create POS module directory structure
2. Add `customer_type` field to `customers` table
3. Create `pos_daily_rates` table and migration
4. Create `PosDailyRate` model
5. Create `PosRateService` with basic CRUD operations
6. Create `PosRateController` with rate management endpoints
7. Create POS rate management views
8. Add POS routes to `routes/web.php`
9. Write unit tests for `PosRateService`
10. Write feature tests for `PosRateController`

**Deliverables:**
- POS module structure created
- Daily rate management functional
- Rate setting and copying from previous day working
- Tests passing

### Phase 2: Transaction Processing (Weeks 3-4)
**Goal:** Build POS transaction workflow.

**Tasks:**
1. Create `PosTransactionService` wrapping existing `TransactionService`
2. Create `PosTransactionController` with transaction endpoints
3. Create transaction quote calculation endpoint
4. Create transaction creation form
5. Integrate with existing `TransactionService` for compliance checks
6. Create `pos_receipts` table and migration
7. Create `PosReceipt` model
8. Create `PosReceiptService` for thermal and PDF generation
9. Create receipt views (thermal and PDF templates)
10. Write unit tests for `PosTransactionService`
11. Write unit tests for `PosReceiptService`
12. Write feature tests for `PosTransactionController`

**Deliverables:**
- POS transaction creation functional
- Real-time quote calculation working
- Receipt generation (thermal + PDF) working
- Compliance integration verified
- Tests passing

### Phase 3: Inventory Management (Weeks 5-6)
**Goal:** Build real-time inventory tracking and EOD balancing.

**Tasks:**
1. Create `PosInventoryService` for inventory aggregation
2. Create `PosInventoryController` with inventory endpoints
3. Create inventory dashboard views
4. Integrate with existing `CurrencyPositionService`
5. Integrate with existing `CounterService` for EOD
6. Create EOD variance calculation logic
7. Create EOD approval workflow
8. Create EOD report generation
9. Add low stock alerts
10. Write unit tests for `PosInventoryService`
11. Write feature tests for `PosInventoryController`
12. Write integration tests for EOD workflow

**Deliverables:**
- Real-time inventory tracking functional
- EOD balancing workflow working
- Variance calculation and approval working
- Low stock alerts functional
- Tests passing

### Phase 4: Customer Management (Weeks 7-8)
**Goal:** Build customer profile and CRM features.

**Tasks:**
1. Create `pos_corporate_profiles` table and migration
2. Create `pos_authorized_signatories` table and migration
3. Create `PosCorporateProfile` model
4. Create `PosAuthorizedSignatory` model
5. Create `PosCustomerService` for customer operations
6. Create `PosCustomerController` with customer endpoints
7. Create customer search functionality
8. Create customer profile views (individual and corporate)
9. Create corporate profile management forms
10. Create authorized signatory management
11. Write unit tests for `PosCustomerService`
12. Write feature tests for `PosCustomerController`

**Deliverables:**
- Customer search functional
- Individual customer profiles working
- Corporate customer profiles working
- Authorized signatory management working
- Tests passing

### Phase 5: Dashboard & Integration (Weeks 9-10)
**Goal:** Build POS dashboard and integrate all features.

**Tasks:**
1. Create `PosDashboardController`
2. Create POS dashboard view
3. Integrate rate display on dashboard
4. Integrate inventory summary on dashboard
5. Integrate recent transactions on dashboard
6. Add quick action buttons
7. Create POS navigation menu
8. Integrate with existing sidebar navigation
9. Add POS-specific middleware
10. Write feature tests for `PosDashboardController`
11. Write end-to-end tests for complete workflows
12. Performance optimization and caching

**Deliverables:**
- POS dashboard functional
- All features integrated
- Navigation working
- Performance optimized
- Tests passing

### Phase 6: Testing & QA (Weeks 11-12)
**Goal:** Comprehensive testing and quality assurance.

**Tasks:**
1. Run full test suite and fix failures
2. Perform integration testing
3. Perform security testing
4. Perform performance testing
5. Perform UAT with tellers
6. Fix bugs and issues
7. Update documentation
8. Create user manuals
9. Create troubleshooting guides
10. Prepare deployment checklist

**Deliverables:**
- All tests passing
- Security verified
- Performance optimized
- UAT completed
- Documentation complete
- Deployment ready

### Phase 7: Deployment & Training (Weeks 13-14)
**Goal:** Deploy to production and train users.

**Tasks:**
1. Deploy to staging environment
2. Perform staging UAT
3. Fix staging issues
4. Schedule production deployment
5. Perform production deployment
6. Monitor production for issues
7. Train tellers on POS system
8. Train managers on approval workflows
9. Train admins on rate management
10. Create training materials
11. Provide ongoing support

**Deliverables:**
- Production deployment successful
- Monitoring operational
- Users trained
- Support documentation available
- System stable

## 10. Success Criteria

### 10.1 Functional Requirements

#### Must Have (P0)
- [ ] Teller can set daily exchange rates manually
- [ ] Teller can copy previous day's rates with one click
- [ ] Teller can create Buy/Sell transactions
- [ ] System auto-calculates local amount from foreign amount
- [ ] System displays CDD level for each transaction
- [ ] System validates till is open before transaction
- [ ] System validates sufficient stock for Sell transactions
- [ ] System generates thermal receipt after transaction
- [ ] System generates PDF receipt on demand
- [ ] Teller can view real-time inventory per currency
- [ ] Teller can view inventory per counter
- [ ] Teller can process EOD balancing
- [ ] System calculates variance between expected and actual
- [ ] System requires notes for variance RM 100-500
- [ ] System requires manager approval for variance > RM 500
- [ ] Teller can search customers by name/ID/phone
- [ ] Teller can view customer profile and transaction history
- [ ] Admin can create corporate customer profiles
- [ ] Admin can add authorized signatories to corporate accounts
- [ ] All operations logged in audit trail

#### Should Have (P1)
- [ ] Dashboard shows today's rates at a glance
- [ ] Dashboard shows inventory summary
- [ ] Dashboard shows recent transactions
- [ ] Low stock alerts displayed
- [ ] Rate history view (last 7 days)
- [ ] Transaction reprint functionality
- [ ] EOD report generation
- [ ] Customer risk rating display
- [ ] Compliance flag display
- [ ] PEP status indicator

#### Could Have (P2)
- [ ] Quick transaction from dashboard
- [ ] Favorite customers list
- [ ] Transaction templates
- [ ] Bulk rate updates
- [ ] Advanced inventory reports
- [ ] Customer behavior analytics
- [ ] Rate trend charts
- [ ] Mobile-responsive views

### 10.2 Non-Functional Requirements

#### Performance
- [ ] Rate query response time < 100ms
- [ ] Transaction creation time < 500ms
- [ ] Inventory aggregation time < 1s
- [ ] Receipt generation time < 2s
- [ ] Dashboard load time < 2s
- [ ] Support 50 concurrent users
- [ ] Support 1000 transactions per day

#### Security
- [ ] All POS operations require authentication
- [ ] Sensitive operations require MFA verification
- [ ] Customer data encrypted at rest
- [ ] Audit trail for all operations
- [ ] SQL injection prevention verified
- [ ] XSS prevention verified
- [ ] CSRF protection enabled
- [ ] Rate limiting enforced
- [ ] Session timeout enforced
- [ ] Role-based access control enforced

#### Reliability
- [ ] System uptime > 99.5%
- [ ] Data loss prevention verified
- [ ] Backup and recovery tested
- [ ] Error handling comprehensive
- [ ] Graceful degradation on failures
- [ ] Database connection pooling
- [ ] Cache fallback mechanisms

#### Usability
- [ ] Teller can complete transaction in < 2 minutes
- [ ] Teller can process EOD in < 5 minutes
- [ ] User interface intuitive and consistent
- [ ] Error messages clear and actionable
- [ ] Help documentation available
- [ ] Training materials comprehensive
- [ ] User feedback mechanism

#### Maintainability
- [ ] Code follows existing patterns
- [ ] Service layer separation maintained
- [ ] Test coverage > 80%
- [ ] Documentation complete
- [ ] Code review process followed
- [ ] Deployment automated
- [ ] Monitoring operational

### 10.3 Compliance Requirements

#### BNM Compliance
- [ ] CDD levels correctly determined
- [ ] Enhanced CDD transactions require approval
- [ ] Sanctions screening performed
- [ ] CTOS reports generated for ≥ RM 10k
- [ ] STR reports generated when required
- [ ] Audit trail tamper-evident
- [ ] Data retention policy followed
- [ ] Customer data protected

#### Internal Compliance
- [ ] Segregation of duties enforced
- [ ] Manager approval for large transactions
- [ ] Manager approval for large variance
- [ ] Dual control for sensitive operations
- [ ] Access control reviewed regularly
- [ ] Security incidents logged
- [ ] Compliance training completed

### 10.4 Success Metrics

#### Adoption Metrics
- [ ] 100% of tellers using POS system
- [ ] 95% of transactions through POS
- [ ] 90% of EOD completed on time
- [ ] 85% user satisfaction score

#### Efficiency Metrics
- [ ] Transaction time reduced by 30%
- [ ] EOD time reduced by 40%
- [ ] Error rate reduced by 50%
- [ ] Training time reduced by 20%

#### Quality Metrics
- [ ] Bug rate < 5 per month
- [ ] Support tickets < 10 per week
- [ ] System availability > 99.5%
- [ ] Data accuracy > 99.9%

## Appendix A: Glossary

- **POS**: Point of Sale - The teller-facing interface for daily operations
- **CDD**: Customer Due Diligence - Risk assessment process for customers
- **EOD**: End of Day - Daily closing and reconciliation process
- **CTOS**: Credit Tip-off Service - Malaysian credit reporting agency
- **STR**: Suspicious Transaction Report - BNM-required report for suspicious activities
- **BNM**: Bank Negara Malaysia - Malaysian central bank and regulator
- **MSB**: Money Services Business - Licensed currency exchange operators
- **PEP**: Politically Exposed Person - High-risk customer category
- **RBAC**: Role-Based Access Control - Permission system based on user roles
- **MFA**: Multi-Factor Authentication - Additional security layer for sensitive operations

## Appendix B: References

- CEMS-MY System Documentation: `/docs/`
- Laravel Documentation: https://laravel.com/docs
- BNM AML/CFT Guidelines: https://www.bnm.gov.my
- PHP 8.1 Documentation: https://www.php.net/docs.php

## Appendix C: Change Log

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2026-04-15 | Design Team | Initial design specification |

---

**Document Status:** Approved
**Next Step:** Implementation Planning
