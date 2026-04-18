# Fault Analysis Fixes - Implementation Design

## Context

The `fau.md` report identified 5 issues in the CEMS-MY Laravel application. This design covers all 5 fixes in risk-priority order:

1. **Concurrency risk** (HIGH) — Stock overselling on pending transactions
2. **Till MYR balance** (MEDIUM) — Local currency not updated on trades
3. **Audit lock contention** (MEDIUM-HIGH) — Global lock serializing all actions
4. **Domain exceptions** (MEDIUM) — Generic exceptions masking bugs
5. **Blind indexing** (LOW) — KYC search not possible on encrypted PII

---

## 1. Stock Reservation System

### Problem

When a transaction ≥ RM 50,000 is created, it goes to `PendingApproval` status and does **not** deduct stock immediately. When the manager approves it later via `approveTransaction()`, the service deducts stock **without checking availability**. If another transaction consumed the stock while the first was pending, approval forces a negative inventory.

### Solution

Introduce a `StockReservation` model that reserves stock at transaction creation time (for approval-required transactions), and consumes the reservation at approval time.

### Components

#### 1.1 `StockReservation` Model

```php
// app/Models/StockReservation.php
class StockReservation extends Model
{
    protected $fillable = [
        'transaction_id',
        'currency_code',
        'till_id',
        'amount_foreign',
        'status',        // pending, consumed, released
        'expires_at',
        'created_by',
    ];
}
```

**Status transitions:**
- `pending` → `consumed` (on approval)
- `pending` → `released` (on cancel/expire/reject)

**Expiry:** Reservations expire after 24 hours. A scheduled command `reservation:expire` releases stale reservations.

#### 1.2 `CurrencyPositionService` Changes

**Add method to check available stock:**
```php
public function getAvailableBalance(string $currency, string $tillId): string
{
    $position = $this->getPosition($currency, $tillId);
    $reserved = StockReservation::where('currency_code', $currency)
        ->where('till_id', $tillId)
        ->where('status', 'pending')
        ->sum('amount_foreign');

    return bcsub($position->balance ?? '0', $reserved, 8);
}
```

**Add method to reserve stock:**
```php
public function reserveStock(Transaction $transaction): StockReservation
{
    return StockReservation::create([
        'transaction_id' => $transaction->id,
        'currency_code' => $transaction->currency_code,
        'till_id' => $transaction->till_id,
        'amount_foreign' => $transaction->amount_foreign,
        'status' => 'pending',
        'expires_at' => now()->addHours(24),
        'created_by' => $transaction->user_id,
    ]);
}
```

#### 1.3 `TransactionService` Changes

**In `createTransaction()`**, after creating a `PendingApproval` transaction:
```php
if ($status === TransactionStatus::PendingApproval) {
    // Reserve the stock immediately so it cannot be oversold
    $this->positionService->reserveStock($transaction);
    // ... existing approval task creation
}
```

**In `approveTransaction()`**, before calling `updatePosition()`:
```php
// Find and consume the reservation
$reservation = StockReservation::where('transaction_id', $lockedTransaction->id)
    ->where('status', 'pending')
    ->first();

if (! $reservation) {
    throw new StockReservationExpiredException($lockedTransaction->id);
}

// Verify stock is still available (reservation protects this, but double-check)
$available = $this->positionService->getAvailableBalance(
    $lockedTransaction->currency_code,
    $lockedTransaction->till_id
);

if ($this->mathService->compare($available, $lockedTransaction->amount_foreign) < 0) {
    $reservation->update(['status' => 'released']);
    throw new InsufficientStockException(
        "Stock no longer available. Requested: {$lockedTransaction->amount_foreign}, Available: {$available}"
    );
}

$reservation->update(['status' => 'consumed']);
// Then proceed with updatePosition()...
```

**In `cancelTransaction()`** (if exists) or a new `releaseReservation()`:
```php
$reservation = StockReservation::where('transaction_id', $transactionId)->first();
if ($reservation && $reservation->status === 'pending') {
    $reservation->update(['status' => 'released']);
}
```

#### 1.4 Scheduled Command

```bash
# app/Console/Commands/ExpireStockReservations.php
php artisan reservation:expire
```

Releases any `pending` reservation past its `expires_at`.

---

## 2. MYR Till Balance Fix

### Problem

`updateTillBalance()` only updates the foreign currency till balance. MYR (local currency) cash drawer movements are unrecorded.

### Solution

Modify `updateTillBalance()` to also update the MYR till balance.

**For Buy** (customer sells foreign, we pay MYR):
- Foreign currency: `foreign_total += amount_foreign` (we receive foreign)
- MYR: `transaction_total += amount_local` (we pay MYR, cash out)

**For Sell** (customer buys foreign, we receive MYR):
- Foreign currency: `foreign_total -= amount_foreign` (we give away foreign)
- MYR: `transaction_total += amount_local` (we receive MYR, cash in)

### Implementation

```php
protected function updateTillBalance(
    TillBalance $tillBalance,
    string $type,
    string $amountLocal,
    string $amountForeign
): void {
    $this->verifyTillIsOpen($tillBalance);

    // Lock the foreign currency balance
    $lockedForeign = TillBalance::where('id', $tillBalance->id)
        ->lockForUpdate()
        ->first();

    // Lock the MYR balance (always present for active till)
    $myrBalance = TillBalance::where('till_id', $tillBalance->till_id)
        ->where('currency_code', 'MYR')
        ->whereDate('date', today())
        ->whereNull('closed_at')
        ->lockForUpdate()
        ->first();

    if (! $myrBalance) {
        throw new TillBalanceMissingException('MYR', $tillBalance->till_id);
    }

    // Update foreign currency balance
    $foreignTotal = $lockedForeign->foreign_total ?? '0';
    $newForeignTotal = $type === TransactionType::Buy->value
        ? $this->mathService->add($foreignTotal, $amountForeign)
        : $this->mathService->subtract($foreignTotal, $amountForeign);

    $lockedForeign->update(['foreign_total' => $newForeignTotal]);

    // Update MYR balance
    $myrTotal = $myrBalance->transaction_total ?? '0';
    $newMyrTotal = $this->mathService->add($myrTotal, $amountLocal);

    $myrBalance->update(['transaction_total' => $newMyrTotal]);
}
```

---

## 3. Async Audit Hashing

### Problem

`AuditService::logWithSeverity()` calls `SystemLog::orderBy('id', 'desc')->lockForUpdate()->first()` inside a synchronous DB transaction. This global lock serializes all application actions across all users.

### Solution

Remove the synchronous lock. Generate hashes asynchronously via Laravel Queue.

### Implementation

#### 3.1 Job: `SealAuditHashJob`

```php
class SealAuditHashJob implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    public function __construct(
        public int $logId
    ) {}
}
```

The job:
1. Fetches the `SystemLog` by ID
2. Gets the previous log entry's hash (no lock needed for reads)
3. Computes and stores the `entry_hash`
4. Sets `previous_hash` to point to the prior entry's sealed hash (chain link)

#### 3.2 Modify `logWithSeverity()`

Remove the `lockForUpdate()` call and transaction wrapper for the hash computation:

```php
public function logWithSeverity(string $action, array $data = [], string $severity = 'INFO'): SystemLog
{
    // Create log entry with null hash (unsealed)
    $log = SystemLog::create([
        'user_id' => $userId,
        'action' => $action,
        'severity' => $severity,
        'entity_type' => $data['entity_type'] ?? null,
        'entity_id' => $data['entity_id'] ?? null,
        'old_values' => $data['old_values'] ?? null,
        'new_values' => $data['new_values'] ?? null,
        'ip_address' => Request::ip(),
        'user_agent' => Request::userAgent(),
        'session_id' => session()->getId(),
        'previous_hash' => null,      // Initially null
        'entry_hash' => null,          // Initially null
    ]);

    // Dispatch async job to seal the hash chain
    SealAuditHashJob::dispatch($log->id);

    return $log;
}
```

#### 3.3 `SealAuditHashJob` Handle

```php
public function handle(): void
{
    $log = SystemLog::find($this->logId);
    if (! $log || $log->entry_hash !== null) {
        return; // Already sealed or deleted
    }

    // Get the previous log's hash (no lock needed)
    $previousLog = SystemLog::where('id', '<', $log->id)
        ->whereNotNull('entry_hash')
        ->orderBy('id', 'desc')
        ->first();

    $previousHash = $previousLog?->entry_hash;

    $entryHash = $this->computeEntryHash(
        $log->created_at->toIso8601String(),
        $log->user_id,
        $log->action,
        $log->entity_type,
        $log->entity_id,
        $previousHash
    );

    $log->update([
        'previous_hash' => $previousHash,
        'entry_hash' => $entryHash,
    ]);
}
```

#### 3.4 `verifyChainIntegrity()` Adaptation

Since hash sealing is now async, the verification method must handle unsealed entries:

```php
public function verifyChainIntegrity(?int $limit = null): array
{
    // Only verify entries that have been sealed
    $query = SystemLog::whereNotNull('entry_hash')->orderBy('id', 'asc');

    if ($limit !== null) {
        $query = SystemLog::whereNotNull('entry_hash')
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->orderBy('id', 'asc');
    }

    $entries = $query->get();
    // ... existing chain verification logic
}
```

Add a companion `getUnsealedLogs()` method to check for any entries pending hash sealing.

---

## 4. Domain Exceptions

### Problem

Generic `throw new Exception` or `throw new RuntimeException` masks business rule violations and makes error handling fragile.

### Solution

Create domain-specific exceptions in `app/Exceptions/`.

### Exception Classes

| Exception | Use Case |
|-----------|----------|
| `InsufficientStockException` | Sell transaction with insufficient foreign currency |
| `StockReservationExpiredException` | Attempting to approve a transaction with expired reservation |
| `TillAlreadyOpenException` | Counter already open |
| `UserAlreadyAtCounterException` | User already assigned to another counter |
| `PendingTransactionException` | Transaction is pending and cannot be modified |
| `TransactionAlreadyProcessedException` | Transaction was already approved/cancelled |
| `TillBalanceMissingException` | Required MYR till balance not found |

### File Structure

```
app/Exceptions/
├── Domain/
│   ├── InsufficientStockException.php
│   ├── StockReservationExpiredException.php
│   ├── TillAlreadyOpenException.php
│   ├── UserAlreadyAtCounterException.php
│   ├── PendingTransactionException.php
│   └── TransactionAlreadyProcessedException.php
```

### Example: `InsufficientStockException`

```php
namespace App\Exceptions\Domain;

class InsufficientStockException extends \RuntimeException
{
    public function __construct(
        public readonly string $currency,
        public readonly string $requested,
        public readonly string $available,
    ) {
        parent::__construct(
            "Insufficient stock for {$currency}. Requested: {$requested}, Available: {$available}"
        );
    }
}
```

### Refactoring Points

Replace occurrences in:
- `TransactionService::createTransaction()` → `InsufficientStockException`
- `CounterService::openSession()` → `TillAlreadyOpenException`
- `CounterService::openSession()` → `UserAlreadyAtCounterException`
- `TransactionService::approveTransaction()` → `TransactionAlreadyProcessedException`

---

## 5. Blind Indexing for KYC Search

### Problem

`id_number_encrypted` cannot be searched via SQL because it uses random IV encryption. Tellers cannot look up customers by ID number.

### Solution

Add a deterministic HMAC hash (`id_number_hash`) to enable exact-match searches.

### Implementation

#### 5.1 Migration

```php
Schema::table('customers', function (Blueprint $table) {
    $table->string('id_number_hash', 64)->nullable()->after('id_number_encrypted');
    $table->index('id_number_hash');
});
```

#### 5.2 Customer Model Changes

```php
class Customer extends Authenticatable
{
    protected $fillable = [
        // ... existing
        'id_number_hash',
    ];

    // Config key for blind index
    private const BLIND_INDEX_KEY = 'app.blind_index_salt';

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($customer) {
            if ($customer->isDirty('id_number_encrypted')) {
                $customer->id_number_hash = self::computeBlindIndex(
                    $customer->getRawOriginal('id_number_encrypted')
                );
            }
        });
    }

    public static function computeBlindIndex(string $plaintext): string
    {
        $salt = config(self::BLIND_INDEX_KEY, config('app.key'));
        return hash_hmac('sha256', $plaintext, $salt);
    }
}
```

#### 5.3 Search Method

```php
public static function findByIdNumber(string $idNumber): ?self
{
    $hash = self::computeBlindIndex($idNumber);
    return static::where('id_number_hash', $hash)->first();
}
```

#### 5.4 Controller Integration

In `CustomerController::search()` or `CustomerController::show()`:
- Accept `id_number` parameter
- Call `Customer::findByIdNumber()` instead of direct query

---

## Test Plan

### Unit Tests

| Test | File |
|------|------|
| Stock reservation creates on PendingApproval | `tests/Unit/TransactionServiceTest.php` |
| Stock reservation consumed on approval | `tests/Unit/TransactionServiceTest.php` |
| Available balance subtracts reservations | `tests/Unit/CurrencyPositionServiceTest.php` |
| MYR balance updated on Buy transaction | `tests/Unit/TransactionServiceTest.php` |
| MYR balance updated on Sell transaction | `tests/Unit/TransactionServiceTest.php` |
| Domain exceptions thrown correctly | `tests/Unit/TransactionServiceTest.php` |
| Blind index hash is deterministic | `tests/Unit/CustomerTest.php` |
| Blind index search finds correct customer | `tests/Unit/CustomerTest.php` |

### Integration Tests

| Test | File |
|------|------|
| Concurrent Sell transactions do not oversell | `tests/Feature/TransactionWorkflowTest.php` |
| Approval releases reservation on failure | `tests/Feature/TransactionWorkflowTest.php` |
| EOD reconciliation includes MYR transactions | `tests/Feature/CounterHandoverTest.php` |
| Audit log hash chain integrity after async seal | `tests/Unit/AuditServiceTest.php` |
| Customer search by ID number returns correct record | `tests/Feature/CustomerControllerTest.php` |

---

## Dependencies

- All changes are internal to existing services/models
- No new package dependencies
- Laravel Queue must be configured for async audit hashing
- `APP_KEY` used as fallback blind index salt (no new secrets needed)

---

## Estimated Scope

| Issue | Files to Modify | New Files |
|-------|----------------|-----------|
| Stock reservation | `TransactionService`, `CurrencyPositionService` | `StockReservation` model, `ExpireStockReservations` command |
| MYR balance | `TransactionService` | — |
| Audit async | `AuditService` | `SealAuditHashJob` |
| Domain exceptions | `TransactionService`, `CounterService` | 6 exception classes |
| Blind indexing | `Customer` model, `CustomerController` | migration |
