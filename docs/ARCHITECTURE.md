# CEMS-MY Architecture

## Overview

CEMS-MY (Currency Exchange Management System) is a Laravel 10.x application for Malaysian Money Services Businesses (MSB), compliant with Bank Negara Malaysia (BNM) AML/CFT requirements.

## System Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           CEMS-MY Architecture                          │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────┐    ┌──────────┐ │
│  │   Clients   │    │   Web/UI    │    │    Mobile   │    │   API    │ │
│  │  (Browser)  │    │  (Laravel)  │    │   (Future)  │    │  (REST)  │ │
│  └──────┬──────┘    └──────┬──────┘    └──────┬──────┘    └────┬─────┘ │
│         │                  │                  │                  │       │
│         └──────────────────┼──────────────────┼──────────────────┘       │
│                            │                  │                          │
│                     ┌──────▼──────┐    ┌───────▼───────┐                  │
│                     │  Middleware │    │  Rate Limit   │                  │
│                     │  Auth/MFA   │    │  IP Block     │                  │
│                     └──────┬──────┘    └───────────────┘                  │
│                            │                                              │
│         ┌──────────────────┼──────────────────┐                          │
│         │                  │                  │                          │
│  ┌──────▼──────────────────▼──────────────────▼──────┐                   │
│  │                   Controllers                        │                   │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  │                   │
│  │  │  Transaction│  │  Customer   │  │  Compliance │  │                   │
│  │  │  Controller │  │  Controller │  │  Controller │  │                   │
│  │  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘  │                   │
│  │         │                │                │         │                   │
│  │         └────────────────┼────────────────┘         │                   │
│  │                          │                          │                   │
│  │                   ┌──────▼──────┐                    │                   │
│  │                   │  Services   │                    │                   │
│  │  ┌─────────────────┼────────────┼──────────────────┐│                  │
│  │  │                 │            │                  ││                  │
│  │  ▼                 ▼            ▼                  ▼                   │
│  │ ┌────────┐  ┌────────────┐  ┌─────────────┐  ┌──────────────┐          │
│  │ │Transaction│ │Currency   │  │Compliance  │  │Accounting   │          │
│  │ │Service   │  │Position   │  │Service     │  │Service      │          │
│  │ │         │  │Service    │  │            │  │             │          │
│  │ └────┬───┘  └─────┬──────┘  └──────┬─────┘  └──────┬───────┘          │
│  │      │           │                │                │                  │
│  └──────┼───────────┼────────────────┼────────────────┼─────────────────┘
│         │           │                │                │                  │
│         ▼           ▼                ▼                ▼                  │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐       │
│  │  Database   │  │   Redis     │  │   Events    │  │   Queue     │       │
│  │   MySQL     │  │   Cache/Q   │  │   System    │  │   Horizon   │       │
│  └─────────────┘  └─────────────┘  └─────────────┘  └─────────────┘       │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

## Directory Structure

```
app/
├── Console/
│   └── Commands/          # 35 Artisan CLI commands
├── Enums/                 # 34 PHP 8.1 enums
│   ├── TransactionStatus.php
│   ├── UserRole.php
│   ├── StockReservationStatus.php
│   └── ...
├── Events/                # 12 Event classes
│   ├── TransactionCreated.php
│   ├── TransactionApproved.php
│   ├── TransactionCancelled.php
│   ├── PendingCancellationRequested.php   # NEW: Cancellation workflow
│   └── ...
├── Exceptions/
│   └── Domain/            # 35 typed domain exceptions
├── Http/
│   ├── Controllers/
│   │   ├── Api/V1/        # REST API v1 controllers
│   │   └── ...            # Web controllers
│   ├── Middleware/        # 21 middleware classes
│   ├── Requests/          # Form request validation
│   └── Resources/         # API resource transformers
├── Jobs/                  # 19 background jobs
├── Models/                # 64 Eloquent models
├── Observers/             # Model observers
└── Services/              # 83 services (71 top-level + 12 in Compliance/)
    ├── TransactionCancellationService.php   # Handles cancellation workflow
    ├── TransactionStateMachine.php          # State transition management
    ├── CurrencyPositionService.php          # Stock/reservation management
    └── ...
```

## Core Services

### TransactionStateMachine

Manages transaction state transitions with a defined transition matrix:

```php
// Valid transitions from PendingApproval
'PendingApproval' => [
    'Approved',
    'Rejected',
    'PendingCancellation',
    'Cancelled',
],

// PendingCancellation can only go to Cancelled
'PendingCancellation' => [
    'Cancelled',
],
```

**Key Methods:**
- `canTransitionTo(TransactionStatus $to): bool`
- `transitionTo(TransactionStatus $to, array $context): bool`
- `getAvailableTransitions(): array`

### TransactionCancellationService

Handles the complete cancellation workflow with segregation of duties:

```php
// Step 1: Manager requests cancellation
requestCancellation(Transaction $transaction, User $requester, string $reason): bool
// → Transitions to PendingCancellation, dispatches PendingCancellationRequested event

// Step 2: Admin/Manager approves (different from requester)
approveCancellation(Transaction $transaction, User $approver, ?string $reason): bool
// → Transitions to Cancelled, releases stock reservation

// Step 3: Admin/Manager rejects (returns to previous status)
rejectCancellation(Transaction $transaction, User $rejector, string $reason): bool
// → Returns to status before PendingCancellation
```

### CurrencyPositionService

Manages foreign currency stock and reservations:

```php
// Reserve stock for PendingApproval transactions
createStockReservation(int $transactionId, string $currencyCode, string $amount): StockReservation

// Release reservation on cancellation
releaseStockReservation(int $transactionId): ?StockReservation

// Get available balance (balance - pending reservations)
getAvailableBalance(string $currencyCode, ?string $tillId = null): string
```

## Event-Driven Architecture

Events dispatch async jobs for compliance, notifications, and audit logging:

| Event | Trigger | Handler Action |
|-------|---------|----------------|
| `TransactionCreated` | New transaction | Compliance screening, sanctions check |
| `TransactionApproved` | Manager approves | Position update, journal entries |
| `TransactionCancelled` | Cancellation approved | Position reversal, stock release |
| `PendingCancellationRequested` | Manager requests cancel | Compliance notification |

## Transaction Status Flow

```
                    ┌─────────────────────────────────────────────────┐
                    │                                                 │
                    ▼                                                 │
    Draft ───► PendingApproval ───► Approved ───► Processing ───► Completed ───► Finalized
       │              │                │              │                │
       │              │                │              │                │
       ▼              ▼                ▼              ▼                ▼
    Cancelled    PendingCancellation  Rejected      Failed          Reversed
                    │                                   ▲
                    │                                   │
                    └───────(approve)────► Cancelled ◄─┘
                              ▲
                              │
                    (reject - returns to previous status)
```

## Cancellation Workflow

```
┌──────────────────────────────────────────────────────────────────────────────┐
│                        Cancellation Workflow                                   │
│                                                                               │
│  ┌─────────────┐      Manager requests       ┌─────────────────────────────┐ │
│  │             │ ◄───────────────────────────│ POST /api/v1/transactions/  │ │
│  │ Transaction │      cancellation          │   {id}/request-cancellation │ │
│  │ (Any valid  │                            │   { reason: "...",          │ │
│  │  status)    │                            │     user: manager }        │ │
│  │             │                            └──────────────┬──────────────┘ │
│  └──────┬──────┘                                       │                    │
│         │                                              ▼                    │
│         │                              ┌───────────────────────────────────┐│
│         │                              │ TransactionStateMachine          ││
│         │                              │ .transitionTo(PendingCancellation)││
│         │                              │ .notifyPendingCancellation()     ││
│         │                              └───────────────┬─────────────────┘│
│         │                                              │                    │
│         │                                              ▼                    │
│         │                                    ┌─────────────────────┐        │
│         │                                    │ PendingCancellation │        │
│         │                                    │ (awaiting approval) │        │
│         │                                    └─────────┬───────────┘        │
│         │                                              │                    │
│         │                           Admin approves      │      Admin rejects  │
│         │                      (different from requester)  │                │
│         │                              ▼                 │    ▼             │
│         │                    ┌─────────────────────┐      │  ┌──────────┐  │
│         │                    │ approveCancellation │──────┘  │ reject   │  │
│         │                    │ - release stock     │         │(restore) │  │
│         │                    │ - update status    │         └──────────┘  │
│         │                    │ - dispatch event   │                      │
│         │                    └──────────┬──────────┘                      │
│         │                               │                                 │
│         │                               ▼                                 │
│         │                      ┌────────────────┐                        │
│         │                      │   Cancelled    │                        │
│         │                      └────────────────┘                        │
│         │                                                            ▲    │
│         └────────────────────────────────────────────────────────────┘    │
│                      (Any valid status can be cancelled)                 │
└──────────────────────────────────────────────────────────────────────────────┘
```

## Segregation of Duties

BNM AML/CFT compliance requires different users for cancellation request and approval:

| Step | User Role | Action |
|------|-----------|--------|
| 1 | Manager (any) | Request cancellation |
| 2 | Manager, Compliance Officer, or Admin (different from requester) | Approve or reject |

The system enforces this by checking that `$approver->id !== $cancellationRequest['user_id']`.

## Stock Reservation Flow

```
┌────────────────────────────────────────────────────────────────┐
│              Stock Reservation Flow                             │
│                                                                │
│  ┌──────────────┐    Create Transaction    ┌────────────────┐  │
│  │              │ ◄─────────────────────────│ POST /api/v1/  │  │
│  │   Teller     │      (Sell, ≥ RM10k)     │ transactions   │  │
│  │              │                          └───────┬────────┘  │
│  └──────┬───────┘                                 │            │
│         │                                         ▼            │
│         │                            ┌─────────────────────────┐│
│         │                            │ CurrencyPositionService ││
│         │                            │ .createStockReservation ││
│         │                            └───────────┬─────────────┘│
│         │                                        │              │
│         │                                        ▼              │
│         │                          ┌─────────────────────────┐  │
│         │                          │ StockReservation       │  │
│         │                          │ (status: Pending)      │  │
│         │                          └───────────┬─────────────┘  │
│         │                                        │              │
│         │         ┌──────────────────────────────┴───────────┐  │
│         │         │                                       │  │
│         │         ▼                                       ▼  │
│         │   ┌───────────┐                    ┌────────────────┐│
│         │   │ Approved │                    │    Cancelled   ││
│         │   └─────┬─────┘                    └───────┬────────┘│
│         │         │                                  │         │
│         │         ▼                                  ▼         │
│         │  ┌────────────────┐            ┌────────────────────┐│
│         │  │ consumeStock   │            │ releaseStock       ││
│         │  │ Reservation()  │            │ Reservation()      ││
│         │  │ (status:       │            │ (status: Released) ││
│         │  │  Consumed)     │            └────────────────────┘│
│         │  └────────────────┘                               │
│         │                                                   │
│         │         (24-hour expiry for unused reservations)  │
└────────────────────────────────────────────────────────────────┘
```

## API Endpoints

### Transaction Cancellation

| Method | Endpoint | Role Required | Description |
|--------|----------|---------------|-------------|
| POST | `/api/v1/transactions/{id}/request-cancellation` | Manager | Request cancellation |
| POST | `/api/v1/transactions/{id}/approve-cancellation` | Manager/Compliance/Admin | Approve cancellation |
| POST | `/api/v1/transactions/{id}/reject-cancellation` | Manager/Compliance/Admin | Reject cancellation |

### Transaction Approval

| Method | Endpoint | Role Required | Description |
|--------|----------|---------------|-------------|
| POST | `/api/v1/transactions/{id}/approve` | Manager | Approve PendingApproval transaction |

## Database Schema (Key Tables)

### transactions
- `id`, `customer_id`, `user_id`, `branch_id`, `till_id`
- `type` (Buy/Sell), `currency_code`, `amount_foreign`, `amount_local`, `rate`
- `status` (Draft, PendingApproval, PendingCancellation, Approved, Cancelled, etc.)
- `version` (optimistic locking)
- `transition_history` (JSON array of state transitions)

### stock_reservations
- `id`, `transaction_id`, `currency_code`, `amount_foreign`
- `status` (Pending, Consumed, Released)
- `expires_at` (24-hour expiry)

### currency_positions
- `id`, `currency_code`, `till_id`, `balance`, `avg_cost_rate`

### chart_of_accounts (Branch MYR Cash)
- `account_code` (PK): `1021` = BR001, `1022` = BR002, `1023` = BR003
- Parent: `1000` (Cash-MYR), enabling hierarchical balance sheet reporting
- Each branch MYR cash account tracks branch-specific floating cash

### Chart of Accounts (Key Codes)
- `1000` — Cash-MYR (main house account)
- `1021/1022/1023` — Branch MYR cash (per branch)
- `2000` — Foreign Currency Inventory
- `3000` — Accounts Payable / `3100` — Accruals
- `4000` — Capital (paid-in), `4100` — Retained Earnings
- `5000` — Forex Trading Revenue, `6000` — Forex Loss

## Security Controls

1. **MFA Enforcement**: All sensitive operations require MFA verification
2. **Role-Based Access**: Middleware validates role before controller execution
3. **Rate Limiting**: Per-endpoint limits prevent abuse
4. **IP Blocking**: Automatic block after failed login attempts
5. **Audit Logging**: All state transitions logged with hash chaining

## Testing Strategy

Critical workflows tested in `tests/Feature/CriticalTransactionWorkflowTest.php`:
- `test_pending_cancellation_cannot_transition_to_approved`
- `test_stock_reservation_released_on_cancellation`
- `test_manager_cannot_approve_own_transaction`
- `test_concurrent_transactions_respect_stock_reservations`