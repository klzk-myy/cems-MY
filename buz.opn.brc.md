# Business Opening & Daily Branch Opening Workflow

## Overview

This document describes the workflow for:
1. **One-time business setup** - Initial system configuration when starting operations
2. **Daily branch opening** - Recurring workflow each morning before trading begins

---

## 1. ONE-TIME BUSINESS SETUP

### Step 1.1: System Configuration (Admin)

| Task | Command/Action |
|------|----------------|
| Configure exchange currencies | `config/currencies.php` - Add MYR, USD, SGD, etc. |
| Set BNM compliance thresholds | `config/thresholds.php` - Auto-approve (RM 10,000), CTOS (RM 25,000), STR (RM 50,000) |
| Configure rate settings | `config/thresholds.php` - spread (0.02 = 2%), max_deviation (0.05 = 5%), precision (4 decimals) |
| Set up admin users | `php artisan make:user` or seed Admin role |

### Step 1.2: Branch Setup (Admin)

```
POST /api/v1/branches
{
  "code": "HQ01",
  "name": "Head Office 1",
  "address": "123 Main Street",
  "phone": "+60312345678",
  "email": "hq01@company.com"
}
```

### Step 1.3: Create Fiscal Year & Accounting Period

```php
FiscalYear::create([
    'year_code' => 2026,
    'start_date' => '2026-01-01',
    'end_date' => '2026-12-31',
    'status' => 'Open',
    'is_closed' => false,
]);

AccountingPeriod::create([
    'period_code' => '2026-01',
    'year_id' => $fiscalYear->id,
    'start_date' => '2026-01-01',
    'end_date' => '2026-01-31',
    'period_type' => 'month',
    'status' => 'open',
    'is_closed' => false,
]);
```

### Step 1.4: Create Chart of Accounts

```php
php artisan db:seed --class=ChartOfAccountSeeder
```

Default accounts:
| Account Code | Name | Type |
|-------------|------|------|
| 1000 | Cash MYR | Asset |
| 1100 | Foreign Currency Inventory | Asset |
| 2000 | Accounts Payable | Liability |
| 3000 | Forex Trading Revenue | Revenue |
| 4000 | Forex Loss | Expense |

### Step 1.5: Create Users (Admin)

| Role | Permissions |
|------|-------------|
| **Admin** | System configuration, branch management |
| **Manager** | Approve transactions, override rates, open/close counters |
| **Teller** | Execute buy/sell transactions |
| **Compliance Officer** | STR, CTOS, EDD, case management |

### Step 1.6: Initial Capitalization

Manager opens counter with initial MYR float:

```
POST /api/v1/counters/{counter}/open
{
  "floats": {
    "MYR": "100000.0000"
  },
  "notes": "Initial float"
}
```

**Accounting Entry Created:**
```
Dr: Cash MYR (1000)     100,000.00
Cr: Opening Capital     100,000.00
```

---

## 2. DAILY BRANCH OPENING WORKFLOW

### Sequential Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                         MORNING SETUP                                │
│                                                                      │
│  ┌──────────────┐    ┌──────────────────┐    ┌───────────────────┐   │
│  │   STEP 1     │───▶│    STEP 2        │───▶│    STEP 3         │   │
│  │ Rate Mgmt    │    │ Teller Allocation│    │ Counter Opening   │   │
│  │              │    │                  │    │                   │   │
│  │ Manager/Admin│    │ Teller → Manager │    │ Manager → Teller  │   │
│  │              │    │ Request floats    │    │ Approve & Open    │   │
│  └──────────────┘    └──────────────────┘    └───────────────────┘   │
│         │                      │                        │             │
│         ▼                      ▼                        ▼             │
│  ┌──────────────────────────────────────────────────────────────┐    │
│  │  POST /api/v1/rates/fetch    (or copy-previous / override)  │    │
│  │  POST /api/v1/tellers/{teller}/allocations                  │    │
│  │  POST /api/v1/counters/{counter}/open                       │    │
│  └──────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────┘
```

---

### STEP 1: RATE MANAGEMENT (Manager/Admin)

**Purpose:** Set today's exchange rates before any trading begins.

#### 1A: Fetch from External API (Auto)

```
POST /api/v1/rates/fetch
Authorization: Manager or Admin
```

**What happens:**
1. `RateApiService::fetchLatestRates()` calls external API
2. Rates stored in `exchange_rates` table with `rate_buy` and `rate_sell`
3. Spread applied: `sell_rate = mid * (1 + spread/2)`, `buy_rate = mid * (1 - spread/2)`

#### 1B: Copy Previous Day's Rates (Manual)

```
POST /api/v1/rates/copy-previous
{
  "date": "2026-04-24"   // optional, defaults to yesterday
}
Authorization: Manager or Admin
```

**What happens:**
1. Reads rates from `exchange_rate_histories` for the specified date
2. Updates `exchange_rates` table with copied rates
3. Source marked as `copied_from_YYYY-MM-DD`

#### 1C: Manual Override (Special Circumstances)

```
PUT /api/v1/rates/{currencyCode}
{
  "rate_buy": "4.4800",
  "rate_sell": "4.5200",
  "reason": "Special client rate for large transaction"
}
Authorization: Manager or Admin
```

**Validation:**
- Sell rate must be higher than buy rate (positive spread)
- Both rates must be positive
- Change logged to audit trail

#### Rate Check Before Proceeding

```
GET /api/v1/rates/check
{
  "currencies": ["USD", "SGD", "THB"]
}
```

Returns:
```json
{
  "success": true,
  "all_set": true,
  "missing": []
}
```

---

### STEP 2: TELLER ALLOCATION REQUEST (Teller → Manager)

**Purpose:** Teller requests foreign currency allocation for the day.

#### 2A: Teller Initiates Allocation Request

```
POST /api/v1/tellers/{teller}/allocations
{
  "counter_id": 1,
  "requested_floats": {
    "USD": "50000.0000",
    "SGD": "10000.0000"
  },
  "purpose": "daily_float"
}
Authorization: Teller
```

**What happens:**
1. System checks `branch_pools` for sufficient available balance
2. Creates `TellerAllocation` with status `pending`
3. Notifies manager for approval

#### 2B: Manager Reviews and Approves

```
POST /api/v1/allocations/{allocationId}/approve
{
  "approved_floats": {
    "USD": "45000.0000",
    "SGD": "8000.0000"
  },
  "notes": "Reduced USD based on yesterday's usage"
}
Authorization: Manager
```

**What happens:**
1. Manager adjusts amounts if needed
2. `BranchPool.available_balance` reduced by approved amount
3. `BranchPool.allocated_balance` increased
4. `TellerAllocation` status → `approved`

---

### STEP 3: COUNTER SESSION OPENING (Manager → Teller)

**Purpose:** Formally open the till/counter for the day.

#### 3A: Manager Initiates Counter Opening

```
POST /api/v1/counters/{counter}/opening-request
{
  "teller_id": 5,
  "floats": {
    "MYR": "50000.0000",
    "USD": "45000.0000"
  }
}
Authorization: Manager
```

**What happens:**
1. Creates pending counter session request
2. Sets initial floats for the counter

#### 3B: Workflow Service Approves and Opens

```
POST /api/v1/counters/{counter}/approve-and-open
{
  "teller_id": 5,
  "floats": {
    "MYR": "50000.0000",
    "USD": "45000.0000"
  },
  "vault_amounts": {
    "USD": "200000.0000"
  }
}
Authorization: Manager
```

**What happens:**
1. **Creates CounterSession:**
   - Status: `Opened`
   - `opened_at`: timestamp
   - `opened_by`: manager ID

2. **Creates TillBalances (one per currency):**
   ```php
   TillBalance::create([
       'till_id' => '1',
       'currency_code' => 'MYR',
       'opening_balance' => '50000.0000',
       'date' => today(),
   ]);
   ```

3. **Creates/Updates CurrencyPositions:**
   ```php
   CurrencyPosition::updateOrCreate(
       ['till_id' => '1', 'currency_code' => 'USD'],
       [
           'balance' => '45000.0000',
           'avg_cost_rate' => current_rate,
           'last_valuation_rate' => current_rate,
       ]
   );
   ```

4. **Accounting Entry (MYR Float):**
   ```
   Dr: Cash MYR (1000)     50,000.00
   Cr: Counter Cash Holdings (2000)    50,000.00
   ```

5. **Vault Entry (USD from Branch Pool):**
   ```
   Dr: Vault USD (1100)     202,500.00
   Cr: Branch Pool USD      202,500.00
   ```

---

### TRANSACTION TRADING

Once counter is open, tellers can execute transactions:

#### BUY Transaction (Customer sells USD)

```
POST /api/v1/transactions
{
  "customer_id": 123,
  "type": "buy",          // We BUY USD from customer
  "currency_code": "USD",
  "amount_foreign": "1000.0000",
  "rate": "4.5000",
  "purpose": "Personal travel",
  "source_of_funds": "salary"
}
Authorization: Teller (MFA required)
```

**Accounting Entry:**
```
Dr: Cash MYR (1000)           4,500.00
Cr: Foreign Currency Inventory (1100)    4,500.00
```

#### SELL Transaction (Customer buys USD)

```
POST /api/v1/transactions
{
  "customer_id": 123,
  "type": "sell",         // We SELL USD to customer
  "currency_code": "USD",
  "amount_foreign": "1000.0000",
  "rate": "4.5200",
  "purpose": "Business payment",
  "source_of_funds": "business"
}
Authorization: Teller (MFA required)
```

**Accounting Entry:**
```
Dr: Foreign Currency Inventory (1100)    4,520.00
Cr: Cash MYR (1000)           4,520.00
```

---

## 3. COUNTER SESSION CLOSING (EOD)

### End-of-Day Close Workflow

```
┌─────────────────────────────────────────────────────────────────────┐
│                         EOD CLOSE                                   │
│                                                                      │
│  ┌──────────────┐    ┌──────────────────┐    ┌───────────────────┐   │
│  │   STEP 1     │───▶│    STEP 2        │───▶│    STEP 3         │   │
│  │ Teller Close │    │ Manager Review   │    │ Vault Return      │   │
│  │ Request      │    │ & Approve        │    │                   │   │
│  └──────────────┘    └──────────────────┘    └───────────────────┘   │
│                                                                      │
│  POST /api/v1/counters/{counter}/close                              │
│  POST /api/v1/counters/{counter}/handover (if needed)              │
└─────────────────────────────────────────────────────────────────────┘
```

### Close Request

```
POST /api/v1/counters/{counter}/close
{
  "closing_floats": {
    "MYR": "48500.0000",
    "USD": "44000.0000"
  },
  " denominations": {
    "MYR": {"100": 485},
    "USD": {"50": 880}
  },
  "notes": "End of day close"
}
Authorization: Teller
```

### Manager Approves

```
POST /api/v1/counters/{counter}/approve-close
Authorization: Manager
```

---

## 4. KEY DATA STRUCTURES

### BranchPool

Tracks branch-level foreign currency reserves (separate from counter tills):

| Field | Description |
|-------|-------------|
| `branch_id` | Foreign key to branch |
| `currency_code` | e.g., "USD" |
| `available_balance` | Available for allocation |
| `allocated_balance` | Already allocated to tellers |

### TellerAllocation

Tracks teller float requests:

| Field | Description |
|-------|-------------|
| `teller_id` | Requesting teller |
| `counter_id` | Target counter |
| `requested_floats` | JSON: currencies and amounts |
| `approved_floats` | Manager-approved amounts |
| `status` | `pending`, `approved`, `rejected` |

### TillBalance

Tracks daily counter position per currency:

| Field | Description |
|-------|-------------|
| `till_id` | Counter ID |
| `currency_code` | e.g., "MYR" |
| `date` | Business date |
| `opening_balance` | Float at open |
| `transaction_total` | Net change from transactions |

### CurrencyPosition

Tracks actual foreign currency stock at each counter:

| Field | Description |
|-------|-------------|
| `till_id` | Counter ID |
| `currency_code` | e.g., "USD" |
| `balance` | Current stock (4 decimal precision) |
| `avg_cost_rate` | Weighted average cost |
| `last_valuation_rate` | Latest valuation rate |

### ExchangeRate

Stores current day's rates:

| Field | Description |
|-------|-------------|
| `currency_code` | e.g., "USD" |
| `rate_buy` | Buy rate (customer sells to us) |
| `rate_sell` | Sell rate (customer buys from us) |
| `source` | "api", "manual_override", "copied_from_YYYY-MM-DD" |
| `fetched_at` | Timestamp |

---

## 5. RELATIONSHIP FLOW

```
                    ┌─────────────────┐
                    │    Branch        │
                    └────────┬────────┘
                             │
              ┌──────────────┼──────────────┐
              │              │              │
              ▼              ▼              ▼
    ┌──────────────┐ ┌──────────────┐ ┌──────────────┐
    │  BranchPool   │ │    User      │ │   Counter    │
    │ (branch-wide  │ │ (tellers,   │ │ (tills at    │
    │  reserves)    │ │  managers)   │ │  this branch)│
    └──────────────┘ └──────────────┘ └──────┬───────┘
                                             │
                          ┌──────────────────┼──────────────────┐
                          │                  │                  │
                          ▼                  ▼                  ▼
                  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐
                  │TillBalance   │  │CurrencyPosition│ │CounterSession│
                  │(daily MYR    │  │(actual USD    │  │(open/close   │
                  │ float)        │  │stock at till) │  │ timestamps)  │
                  └──────────────┘  └──────────────┘  └──────────────┘
                          │                  │
                          │                  │
                          ▼                  ▼
                  ┌──────────────┐  ┌──────────────┐
                  │Transaction   │  │TellerAllocation│
                  │(buy/sell     │  │(float request │
                  │ transactions)│  │ workflow)     │
                  └──────────────┘  └──────────────┘
```

---

## 6. API ENDPOINTS SUMMARY

### Rate Management
| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/api/v1/rates` | Any | List current rates |
| GET | `/api/v1/rates/summary` | Any | Rates with spread |
| POST | `/api/v1/rates/fetch` | Manager+ | Fetch from API |
| POST | `/api/v1/rates/copy-previous` | Manager+ | Copy previous day |
| PUT | `/api/v1/rates/{currency}` | Manager+ | Manual override |
| GET | `/api/v1/rates/check` | Any | Check rates set |

### Teller Allocation
| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/api/v1/allocations/my-active` | Teller | Get own active allocation |
| GET | `/api/v1/allocations/pending` | Manager+ | Get pending allocations for branch |
| GET | `/api/v1/allocations/active` | Manager+ | Get active allocations for branch |
| GET | `/api/v1/allocations/{id}` | Any | Get specific allocation |
| POST | `/api/v1/allocations/{id}/approve` | Manager+ | Approve allocation |
| POST | `/api/v1/allocations/{id}/reject` | Manager+ | Reject allocation |
| POST | `/api/v1/allocations/{id}/modify` | Manager+ | Modify active allocation |
| POST | `/api/v1/allocations/{id}/return-to-pool` | Manager+ | Return to pool (EOD) |

### Counter Opening Workflow
| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/api/v1/counters/pending-requests` | Manager+ | Get pending opening requests |
| POST | `/api/v1/counters/{id}/opening-request` | Teller | Initiate opening request |
| POST | `/api/v1/counters/{id}/approve-and-open` | Manager+ | Approve & open counter |

### Counter Management (Web)
| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/counters/{id}/open` | Teller | Show counter open form |
| POST | `/counters/{id}/open` | Teller | Direct open counter |
| GET | `/counters/{id}/close` | Teller | Show counter close form |
| POST | `/counters/{id}/close` | Teller | Request close |
| POST | `/counters/{id}/approve-close` | Manager | Approve close |
| POST | `/counters/{id}/handover` | Manager | Transfer custody |

### Transactions
| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/v1/transactions` | Teller (MFA) | Create transaction |
| GET | `/api/v1/transactions/{id}` | Any | View transaction |
| POST | `/api/v1/transactions/{id}/approve` | Manager (MFA) | Approve large txn |
