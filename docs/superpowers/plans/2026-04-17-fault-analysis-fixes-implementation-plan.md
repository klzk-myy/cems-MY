# Fault Analysis Fixes - Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix 5 issues from fau.md: (1) stock reservation to prevent overselling, (2) MYR till balance updates, (3) async audit hash chaining, (4) domain exceptions, (5) blind indexing for KYC search.

**Architecture:** Independent fixes applied in risk-priority order. Stock reservation uses a new StockReservation model. Audit hashing uses Laravel Queue. Blind indexing uses HMAC-SHA256 with APP_KEY salt.

**Tech Stack:** Laravel 10, BCMath, MySQL, Laravel Queue

---

## File Map

### New Files
- `app/Exceptions/Domain/InsufficientStockException.php`
- `app/Exceptions/Domain/StockReservationExpiredException.php`
- `app/Exceptions/Domain/TillAlreadyOpenException.php`
- `app/Exceptions/Domain/UserAlreadyAtCounterException.php`
- `app/Exceptions/Domain/PendingTransactionException.php`
- `app/Exceptions/Domain/TransactionAlreadyProcessedException.php`
- `app/Exceptions/Domain/TillBalanceMissingException.php`
- `app/Models/StockReservation.php`
- `app/Jobs/Audit/SealAuditHashJob.php`
- `app/Console/Commands/ExpireStockReservations.php`
- `database/migrations/YYYY_MM_DD_HHMMSS_add_id_number_hash_to_customers_table.php`

### Modified Files
- `app/Services/CurrencyPositionService.php` — add `getAvailableBalance()` and `reserveStock()`
- `app/Services/TransactionService.php` — add stock reservation on PendingApproval, fix MYR till balance
- `app/Services/AuditService.php` — remove lockForUpdate, dispatch async job
- `app/Services/CounterService.php` — use domain exceptions
- `app/Models/Customer.php` — add blind indexing

---

## Task 1: Create Domain Exceptions

**Files:**
- Create: `app/Exceptions/Domain/InsufficientStockException.php`
- Create: `app/Exceptions/Domain/StockReservationExpiredException.php`
- Create: `app/Exceptions/Domain/TillAlreadyOpenException.php`
- Create: `app/Exceptions/Domain/UserAlreadyAtCounterException.php`
- Create: `app/Exceptions/Domain/PendingTransactionException.php`
- Create: `app/Exceptions/Domain/TransactionAlreadyProcessedException.php`
- Create: `app/Exceptions/Domain/TillBalanceMissingException.php`

- [ ] **Step 1: Create directory**

```bash
mkdir -p app/Exceptions/Domain
```

- [ ] **Step 2: Create InsufficientStockException.php**

```php
<?php

namespace App\Exceptions\Domain;

use RuntimeException;

class InsufficientStockException extends RuntimeException
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

- [ ] **Step 3: Create StockReservationExpiredException.php**

```php
<?php

namespace App\Exceptions\Domain;

use RuntimeException;

class StockReservationExpiredException extends RuntimeException
{
    public function __construct(public readonly int $transactionId)
    {
        parent::__construct("Stock reservation expired or not found for transaction {$transactionId}");
    }
}
```

- [ ] **Step 4: Create TillAlreadyOpenException.php**

```php
<?php

namespace App\Exceptions\Domain;

use RuntimeException;

class TillAlreadyOpenException extends RuntimeException
{
    public function __construct(public readonly string $counterCode)
    {
        parent::__construct("Counter {$counterCode} is already open today");
    }
}
```

- [ ] **Step 5: Create UserAlreadyAtCounterException.php**

```php
<?php

namespace App\Exceptions\Domain;

use RuntimeException;

class UserAlreadyAtCounterException extends RuntimeException
{
    public function __construct(public readonly int $userId)
    {
        parent::__construct("User {$userId} is already assigned to another counter");
    }
}
```

- [ ] **Step 6: Create PendingTransactionException.php**

```php
<?php

namespace App\Exceptions\Domain;

use RuntimeException;

class PendingTransactionException extends RuntimeException
{
    public function __construct(public readonly int $transactionId, public readonly string $status)
    {
        parent::__construct("Transaction {$transactionId} is pending ({$status}) and cannot be modified");
    }
}
```

- [ ] **Step 7: Create TransactionAlreadyProcessedException.php**

```php
<?php

namespace App\Exceptions\Domain;

use RuntimeException;

class TransactionAlreadyProcessedException extends RuntimeException
{
    public function __construct(public readonly int $transactionId)
    {
        parent::__construct("Transaction {$transactionId} was already processed or modified");
    }
}
```

- [ ] **Step 8: Create TillBalanceMissingException.php**

```php
<?php

namespace App\Exceptions\Domain;

use RuntimeException;

class TillBalanceMissingException extends RuntimeException
{
    public function __construct(public readonly string $currency, public readonly string $tillId)
    {
        parent::__construct("Till balance not found for {$currency} at till {$tillId}");
    }
}
```

- [ ] **Step 9: Commit**

```bash
git add app/Exceptions/Domain/ && git commit -m "feat: add domain exception classes"
```

---

## Task 2: Create StockReservation Model

**Files:**
- Create: `app/Models/StockReservation.php`
- Test: `tests/Unit/TransactionServiceTest.php`

- [ ] **Step 1: Create StockReservation model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockReservation extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONSUMED = 'consumed';
    public const STATUS_RELEASED = 'released';

    protected $fillable = [
        'transaction_id',
        'currency_code',
        'till_id',
        'amount_foreign',
        'status',
        'expires_at',
        'created_by',
    ];

    protected $casts = [
        'amount_foreign' => 'string',
        'expires_at' => 'datetime',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isConsumed(): bool
    {
        return $this->status === self::STATUS_CONSUMED;
    }

    public function isReleased(): bool
    {
        return $this->status === self::STATUS_RELEASED;
    }

    public function isExpired(): bool
    {
        return $this->isPending() && $this->expires_at->isPast();
    }
}
```

- [ ] **Step 2: Run existing tests to verify nothing breaks**

```bash
php artisan test --filter=TransactionWorkflowTest --stop-on-failure 2>&1 | head -50
```

- [ ] **Step 3: Commit**

```bash
git add app/Models/StockReservation.php && git commit -m "feat: add StockReservation model"
```

---

## Task 3: Add Stock Reservation Methods to CurrencyPositionService

**Files:**
- Modify: `app/Services/CurrencyPositionService.php` — add `getAvailableBalance()` and `reserveStock()`

- [ ] **Step 1: Read current end of CurrencyPositionService to find insertion point**

```bash
tail -20 app/Services/CurrencyPositionService.php
```

- [ ] **Step 2: Add new methods before the closing brace**

In `app/Services/CurrencyPositionService.php`, add these methods after the `aggregateForUser` method and before the final `}`:

```php
/**
     * Get available balance excluding pending reservations.
     *
     * @param  string  $currencyCode  Currency code
     * @param  string  $tillId  Till identifier
     * @return string Available balance as string
     */
    public function getAvailableBalance(string $currencyCode, string $tillId): string
    {
        $position = $this->getPosition($currencyCode, $tillId);
        $balance = $position ? $position->balance : '0';

        $reserved = StockReservation::where('currency_code', $currencyCode)
            ->where('till_id', $tillId)
            ->where('status', StockReservation::STATUS_PENDING)
            ->where('expires_at', '>', now())
            ->sum('amount_foreign');

        return $this->mathService->subtract($balance, (string) $reserved);
    }

    /**
     * Reserve stock for a pending approval transaction.
     *
     * @param  Transaction  $transaction  Transaction to reserve stock for
     * @return StockReservation Created reservation
     */
    public function reserveStock(Transaction $transaction): StockReservation
    {
        return StockReservation::create([
            'transaction_id' => $transaction->id,
            'currency_code' => $transaction->currency_code,
            'till_id' => $transaction->till_id,
            'amount_foreign' => $transaction->amount_foreign,
            'status' => StockReservation::STATUS_PENDING,
            'expires_at' => now()->addHours(24),
            'created_by' => $transaction->user_id,
        ]);
    }

    /**
     * Consume an existing stock reservation (called at approval time).
     *
     * @param  int  $transactionId  Transaction ID
     * @return StockReservation|null The consumed reservation or null
     */
    public function consumeStockReservation(int $transactionId): ?StockReservation
    {
        $reservation = StockReservation::where('transaction_id', $transactionId)
            ->where('status', StockReservation::STATUS_PENDING)
            ->first();

        if ($reservation) {
            $reservation->update(['status' => StockReservation::STATUS_CONSUMED]);
        }

        return $reservation;
    }

    /**
     * Release a pending stock reservation.
     *
     * @param  int  $transactionId  Transaction ID
     * @return StockReservation|null The released reservation or null
     */
    public function releaseStockReservation(int $transactionId): ?StockReservation
    {
        $reservation = StockReservation::where('transaction_id', $transactionId)
            ->where('status', StockReservation::STATUS_PENDING)
            ->first();

        if ($reservation) {
            $reservation->update(['status' => StockReservation::STATUS_RELEASED]);
        }

        return $reservation;
    }
```

- [ ] **Step 3: Add StockReservation import to CurrencyPositionService**

Add after the existing `use` statements:

```php
use App\Models\StockReservation;
```

- [ ] **Step 4: Run tests to verify**

```bash
php artisan test --filter=CurrencyPositionServiceTest 2>&1 | tail -20
```

- [ ] **Step 5: Commit**

```bash
git add app/Services/CurrencyPositionService.php && git commit -m "feat: add stock reservation methods to CurrencyPositionService"
```

---

## Task 4: Integrate Stock Reservation into TransactionService

**Files:**
- Modify: `app/Services/TransactionService.php` — add reservation on PendingApproval, consume on approve, release on fail

- [ ] **Step 1: Read the PendingApproval section in createTransaction (around line 268)**

```bash
sed -n '260,290p' app/Services/TransactionService.php
```

- [ ] **Step 2: Add stock reservation after PendingApproval transaction creation**

Find this block in `createTransaction()` (around line 268):
```php
if ($status === TransactionStatus::PendingApproval) {
    try {
        $this->approvalWorkflowService->createApprovalTask($transaction);
    } catch (\Exception $e) {
```

Add reservation before the try block:
```php
if ($status === TransactionStatus::PendingApproval) {
    // Reserve the stock immediately so it cannot be oversold
    $this->positionService->reserveStock($transaction);

    try {
        $this->approvalWorkflowService->createApprovalTask($transaction);
    } catch (\Exception $e) {
```

- [ ] **Step 3: Read approveTransaction to find where to consume reservation (around line 680)**

```bash
sed -n '680,720p' app/Services/TransactionService.php
```

- [ ] **Step 4: Add stock reservation consumption before updatePosition call**

Find this in `approveTransaction()` (around line 683):
```php
// Execute position and till balance updates
$this->positionService->updatePosition(
```

Add before it:
```php
// Find and consume the stock reservation
$reservation = $this->positionService->consumeStockReservation($lockedTransaction->id);

if (! $reservation) {
    throw new StockReservationExpiredException($lockedTransaction->id);
}

// Verify stock is still available (reservation protects this, but double-check)
$available = $this->positionService->getAvailableBalance(
    $lockedTransaction->currency_code,
    $lockedTransaction->till_id ?? 'MAIN'
);

if ($this->mathService->compare($available, (string) $lockedTransaction->amount_foreign) < 0) {
    $this->positionService->releaseStockReservation($lockedTransaction->id);
    throw new InsufficientStockException(
        $lockedTransaction->currency_code,
        (string) $lockedTransaction->amount_foreign,
        $available
    );
}
```

- [ ] **Step 5: Add domain exception imports to TransactionService**

Find the existing `use Exception;` line and add after it:
```php
use App\Exceptions\Domain\InsufficientStockException;
use App\Exceptions\Domain\StockReservationExpiredException;
```

- [ ] **Step 6: Run tests**

```bash
php artisan test --filter=TransactionWorkflowTest 2>&1 | tail -30
```

- [ ] **Step 7: Commit**

```bash
git add app/Services/TransactionService.php && git commit -m "feat: integrate stock reservation into transaction workflow"
```

---

## Task 5: Fix MYR Till Balance in TransactionService

**Files:**
- Modify: `app/Services/TransactionService.php` — `updateTillBalance()` method

- [ ] **Step 1: Read the updateTillBalance method (line 373-396)**

```bash
sed -n '373,396p' app/Services/TransactionService.php
```

- [ ] **Step 2: Replace updateTillBalance method**

Replace the entire `updateTillBalance` method with:

```php
/**
     * Update till balance after transaction.
     * Updates both foreign currency and MYR (local currency) balances.
     * Uses lockForUpdate to prevent race conditions on concurrent transactions.
     */
    protected function updateTillBalance(TillBalance $tillBalance, string $type, string $amountLocal, string $amountForeign): void
    {
        $this->verifyTillIsOpen($tillBalance);

        // Lock the foreign currency balance
        $lockedForeign = TillBalance::where('id', $tillBalance->id)
            ->lockForUpdate()
            ->first();

        // Lock the MYR balance (always present for active till)
        $myrBalance = TillBalance::where('till_id', $lockedForeign->till_id)
            ->where('currency_code', 'MYR')
            ->whereDate('date', today())
            ->whereNull('closed_at')
            ->lockForUpdate()
            ->first();

        if (! $myrBalance) {
            throw new TillBalanceMissingException('MYR', $lockedForeign->till_id);
        }

        // Update foreign currency balance
        $foreignTotal = $lockedForeign->foreign_total ?? '0';
        $newForeignTotal = $type === TransactionType::Buy->value
            ? $this->mathService->add($foreignTotal, $amountForeign)
            : $this->mathService->subtract($foreignTotal, $amountForeign);

        $lockedForeign->update(['foreign_total' => $newForeignTotal]);

        // Update MYR balance - always add (cash in on Sell, cash out on Buy is recorded separately)
        $myrTotal = $myrBalance->transaction_total ?? '0';
        $newMyrTotal = $this->mathService->add($myrTotal, $amountLocal);

        $myrBalance->update(['transaction_total' => $newMyrTotal]);
    }
```

- [ ] **Step 3: Add TillBalanceMissingException import**

Add to imports:
```php
use App\Exceptions\Domain\TillBalanceMissingException;
```

- [ ] **Step 4: Run tests**

```bash
php artisan test --filter=TransactionWorkflowTest 2>&1 | tail -30
```

- [ ] **Step 5: Commit**

```bash
git add app/Services/TransactionService.php && git commit -m "fix: update MYR till balance on foreign currency transactions"
```

---

## Task 6: Create SealAuditHashJob

**Files:**
- Create: `app/Jobs/Audit/SealAuditHashJob.php`

- [ ] **Step 1: Create jobs/Audit directory**

```bash
mkdir -p app/Jobs/Audit
```

- [ ] **Step 2: Create SealAuditHashJob.php**

```php
<?php

namespace App\Jobs\Audit;

use App\Models\SystemLog;
use App\Services\AuditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SealAuditHashJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public int $logId
    ) {}

    public function handle(AuditService $auditService): void
    {
        $log = SystemLog::find($this->logId);

        // Skip if log was deleted or already sealed
        if (! $log || $log->entry_hash !== null) {
            return;
        }

        // Get the previous log's sealed hash (no lock needed)
        $previousLog = SystemLog::where('id', '<', $log->id)
            ->whereNotNull('entry_hash')
            ->orderBy('id', 'desc')
            ->first();

        $previousHash = $previousLog?->entry_hash;

        // Compute this entry's hash
        $entryHash = $auditService->computeEntryHash(
            $log->created_at->toIso8601String(),
            $log->user_id,
            $log->action,
            $log->entity_type,
            $log->entity_id,
            $previousHash
        );

        // Seal the entry
        $log->update([
            'previous_hash' => $previousHash,
            'entry_hash' => $entryHash,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SealAuditHashJob failed permanently', [
            'log_id' => $this->logId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Jobs/Audit/SealAuditHashJob.php && git commit -m "feat: add SealAuditHashJob for async audit hash chaining"
```

---

## Task 7: Modify AuditService to Use Async Hashing

**Files:**
- Modify: `app/Services/AuditService.php` — remove lockForUpdate, dispatch job

- [ ] **Step 1: Read logWithSeverity method (line 47-87)**

```bash
sed -n '47,87p' app/Services/AuditService.php
```

- [ ] **Step 2: Replace logWithSeverity method**

Replace the `logWithSeverity` method with:

```php
/**
     * Log with severity level (tamper-evident with hash chaining)
     *
     * Hash sealing is done asynchronously via SealAuditHashJob to avoid
     * global lock contention. The entry is created with null hash values
     * and sealed by the queued job.
     */
    public function logWithSeverity(
        string $action,
        array $data = [],
        string $severity = 'INFO'
    ): SystemLog {
        $userId = $data['user_id'] ?? auth()->id();

        // Create log entry with null hash (will be sealed async)
        $log = SystemLog::create([
            'user_id' => $userId,
            'action' => $action,
            'severity' => $severity,
            'entity_type' => $data['entity_type'] ?? null,
            'entity_id' => $data['entity_id'] ?? null,
            'old_values' => ! empty($data['old_values'] ?? []) ? $data['old_values'] : null,
            'new_values' => ! empty($data['new_values'] ?? []) ? $data['new_values'] : null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'session_id' => session()->getId(),
            'previous_hash' => null,
            'entry_hash' => null,
        ]);

        // Dispatch async job to seal the hash chain
        \App\Jobs\Audit\SealAuditHashJob::dispatch($log->id);

        return $log;
    }
```

- [ ] **Step 3: Update verifyChainIntegrity to only verify sealed entries**

Find the `verifyChainIntegrity` method and update the query:

```php
public function verifyChainIntegrity(?int $limit = null): array
{
    $query = SystemLog::whereNotNull('entry_hash')->orderBy('id', 'asc');

    if ($limit !== null) {
        $query = SystemLog::whereNotNull('entry_hash')
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->orderBy('id', 'asc');
    }

    $entries = $query->get();
    // ... rest of method unchanged
```

- [ ] **Step 4: Add helper method to check for unsealed logs**

Add after `verifyChainIntegrity`:

```php
/**
     * Get count of unsealed audit log entries.
     * Useful for monitoring/alerting on the async hash sealing pipeline.
     */
    public function getUnsealedCount(): int
    {
        return SystemLog::whereNull('entry_hash')->count();
    }
```

- [ ] **Step 5: Run tests**

```bash
php artisan test --filter=AuditServiceTest 2>&1 | tail -20
```

- [ ] **Step 6: Commit**

```bash
git add app/Services/AuditService.php && git commit -m "feat: make audit hash sealing async via queue job"
```

---

## Task 8: Add ExpireStockReservations Command

**Files:**
- Create: `app/Console/Commands/ExpireStockReservations.php`

- [ ] **Step 1: Create the command**

```php
<?php

namespace App\Console\Commands;

use App\Models\StockReservation;
use Illuminate\Console\Command;

class ExpireStockReservations extends Command
{
    protected $signature = 'reservation:expire';

    protected $description = 'Release expired stock reservations';

    public function handle(): int
    {
        $expired = StockReservation::where('status', StockReservation::STATUS_PENDING)
            ->where('expires_at', '<=', now())
            ->get();

        $count = $expired->count();

        foreach ($expired as $reservation) {
            $reservation->update(['status' => StockReservation::STATUS_RELEASED]);
        }

        $this->info("Released {$count} expired stock reservations.");

        return Command::SUCCESS;
    }
}
```

- [ ] **Step 2: Register in scheduler (optional — check app/Console/Kernel.php)**

```bash
grep -n "reservation:expire" app/Console/Kernel.php || echo "Not found - needs adding"
```

If not found, add to Kernel.php schedule method:
```php
$schedule->command('reservation:expire')->hourly();
```

- [ ] **Step 3: Commit**

```bash
git add app/Console/Commands/ExpireStockReservations.php && git commit -m "feat: add reservation:expire command"
```

---

## Task 9: Update CounterService to Use Domain Exceptions

**Files:**
- Modify: `app/Services/CounterService.php` — replace generic exceptions with domain exceptions

- [ ] **Step 1: Find the two throw new Exception lines in openSession**

```bash
grep -n "throw new Exception" app/Services/CounterService.php
```

- [ ] **Step 2: Add domain exception imports**

Add after existing imports in CounterService.php:
```php
use App\Exceptions\Domain\TillAlreadyOpenException;
use App\Exceptions\Domain\UserAlreadyAtCounterException;
```

- [ ] **Step 3: Replace first exception (counter already open)**

Find line 47: `throw new Exception('Counter is already open today');`

Replace with:
```php
throw new TillAlreadyOpenException($counter->code ?? (string) $counter->id);
```

- [ ] **Step 4: Replace second exception (user already at counter)**

Find line 57: `throw new Exception('User is already at another counter');`

Replace with:
```php
throw new UserAlreadyAtCounterException($user->id);
```

- [ ] **Step 5: Run tests**

```bash
php artisan test --filter=CounterHandoverTest 2>&1 | tail -20
```

- [ ] **Step 6: Commit**

```bash
git add app/Services/CounterService.php && git commit -m "fix: use domain exceptions in CounterService"
```

---

## Task 10: Add Blind Indexing for Customer ID Search

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_add_id_number_hash_to_customers_table.php`
- Modify: `app/Models/Customer.php`
- Modify: `app/Http/Controllers/Api/V1/CustomerController.php`

- [ ] **Step 1: Create migration**

```bash
php artisan make:migration add_id_number_hash_to_customers_table --table=customers
```

Then edit the migration file:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('id_number_hash', 64)->nullable()->after('id_number_encrypted');
            $table->index('id_number_hash');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['id_number_hash']);
            $table->dropColumn('id_number_hash');
        });
    }
};
```

- [ ] **Step 2: Add blind index methods to Customer model**

Add to `app/Models/Customer.php`:

After the existing imports, add:
```php
use App\Services\EncryptionService;
```

In the `fillable` array, add:
```php
'id_number_hash',
```

Add these methods to the Customer class:

```php
/**
     * Boot the model and register saving hook for blind index.
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($customer) {
            if ($customer->isDirty('id_number_encrypted') && $customer->id_number_encrypted) {
                // Get the decrypted value to hash
                $plaintext = app(EncryptionService::class)->decrypt($customer->id_number_encrypted);
                $customer->id_number_hash = self::computeBlindIndex($plaintext);
            }
        });
    }

    /**
     * Compute a deterministic HMAC hash of the ID number for blind indexing.
     */
    public static function computeBlindIndex(string $plaintext): string
    {
        $key = config('app.key');
        return hash_hmac('sha256', $plaintext, $key);
    }

    /**
     * Find a customer by their ID number using the blind index.
     */
    public static function findByIdNumber(string $idNumber): ?self
    {
        $hash = self::computeBlindIndex($idNumber);
        return static::where('id_number_hash', $hash)->first();
    }
```

- [ ] **Step 3: Update CustomerController to support ID number search**

In `app/Http/Controllers/Api/V1/CustomerController.php`, find the search method and add ID number lookup. The exact change depends on existing search implementation — typically add near the start of the search method:

```php
// Support search by id_number via blind index
if ($request->filled('id_number')) {
    $customer = Customer::findByIdNumber($request->input('id_number'));
    if ($customer) {
        return $customer;
    }
    return response()->json(['data' => null], 200);
}
```

- [ ] **Step 4: Run migration**

```bash
php artisan migrate 2>&1 | tail -10
```

- [ ] **Step 5: Run tests**

```bash
php artisan test --filter=CustomerControllerTest 2>&1 | tail -20
```

- [ ] **Step 6: Commit**

```bash
git add database/migrations/*id_number_hash* app/Models/Customer.php app/Http/Controllers/Api/V1/CustomerController.php && git commit -m "feat: add blind indexing for customer ID number search"
```

---

## Task 11: Add Unit Tests for Stock Reservation

**Files:**
- Modify: `tests/Unit/TransactionServiceTest.php` or `tests/Unit/CurrencyPositionServiceTest.php`

- [ ] **Step 1: Add test for available balance subtracts reservations**

```php
public function test_get_available_balance_excludes_pending_reservations(): void
{
    // Create a position with 1000 USD
    $position = CurrencyPosition::create([
        'currency_code' => 'USD',
        'till_id' => 'TEST-TILL',
        'balance' => '1000.00',
        'avg_cost_rate' => '4.50',
        'last_valuation_rate' => '4.50',
    ]);

    // Create a pending reservation for 300 USD
    StockReservation::create([
        'transaction_id' => 99999, // dummy
        'currency_code' => 'USD',
        'till_id' => 'TEST-TILL',
        'amount_foreign' => '300.00',
        'status' => StockReservation::STATUS_PENDING,
        'expires_at' => now()->addHours(24),
        'created_by' => $this->user->id,
    ]);

    $available = $this->positionService->getAvailableBalance('USD', 'TEST-TILL');

    $this->assertEquals('700.00000000', $available);
}
```

- [ ] **Step 2: Add test for reservation consumed on approval**

```php
public function test_reservation_consumed_on_transaction_approval(): void
{
    $customer = Customer::factory()->create();
    $counter = Counter::factory()->create();

    // Create transaction that will go to PendingApproval
    $data = [
        'customer_id' => $customer->id,
        'currency_code' => 'USD',
        'type' => TransactionType::Sell->value,
        'amount_foreign' => '500.00',
        'rate' => '4.50',
        'purpose' => 'Test',
        'source_of_funds' => 'salary',
        'till_id' => $counter->id,
    ];

    // This should create a reservation since amount >= 3000
    $transaction = $this->transactionService->createTransaction($data, $this->user->id);

    $this->assertEquals(TransactionStatus::PendingApproval->value, $transaction->status);

    // Verify reservation was created
    $reservation = StockReservation::where('transaction_id', $transaction->id)->first();
    $this->assertNotNull($reservation);
    $this->assertEquals(StockReservation::STATUS_PENDING, $reservation->status);

    // Approve the transaction
    $manager = User::factory()->manager()->create();
    $result = $this->transactionService->approveTransaction($transaction, $manager->id);

    $this->assertTrue($result['success']);

    // Verify reservation was consumed
    $reservation->refresh();
    $this->assertEquals(StockReservation::STATUS_CONSUMED, $reservation->status);
}
```

- [ ] **Step 3: Add test that approval fails if stock no longer available**

```php
public function test_approval_fails_if_stock_no_longer_available(): void
{
    $customer = Customer::factory()->create();

    // Position has 500 USD
    $position = CurrencyPosition::create([
        'currency_code' => 'USD',
        'till_id' => 'TEST-TILL',
        'balance' => '500.00',
        'avg_cost_rate' => '4.50',
        'last_valuation_rate' => '4.50',
    ]);

    // Create a PendingApproval transaction for 300 USD (reservation created)
    $data = [
        'customer_id' => $customer->id,
        'currency_code' => 'USD',
        'type' => TransactionType::Sell->value,
        'amount_foreign' => '300.00',
        'rate' => '4.50',
        'purpose' => 'Test',
        'source_of_funds' => 'salary',
        'till_id' => 'TEST-TILL',
    ];

    $transaction = $this->transactionService->createTransaction($data, $this->user->id);

    // Manually reduce position to 100 (simulating another transaction)
    $position->update(['balance' => '100.00']);

    // Approval should now fail
    $manager = User::factory()->manager()->create();
    $result = $this->transactionService->approveTransaction($transaction, $manager->id);

    $this->assertFalse($result['success']);
    $this->assertStringContainsString('Insufficient stock', $result['message']);
}
```

- [ ] **Step 4: Run tests**

```bash
php artisan test --filter=TransactionServiceTest 2>&1 | tail -30
```

- [ ] **Step 5: Commit**

```bash
git add tests/Unit/TransactionServiceTest.php && git commit -m "test: add unit tests for stock reservation system"
```

---

## Task 12: Add Unit Tests for MYR Till Balance Fix

**Files:**
- Modify: `tests/Unit/TransactionServiceTest.php`

- [ ] **Step 1: Add test that MYR balance is updated on Buy transaction**

```php
public function test_myr_till_balance_updated_on_buy_transaction(): void
{
    $customer = Customer::factory()->create();

    // Create USD and MYR till balances
    TillBalance::create([
        'till_id' => 'TEST-TILL',
        'currency_code' => 'USD',
        'opening_balance' => '0',
        'date' => today(),
        'opened_by' => $this->user->id,
    ]);

    TillBalance::create([
        'till_id' => 'TEST-TILL',
        'currency_code' => 'MYR',
        'opening_balance' => '10000.00',
        'date' => today(),
        'opened_by' => $this->user->id,
    ]);

    $data = [
        'customer_id' => $customer->id,
        'currency_code' => 'USD',
        'type' => TransactionType::Buy->value,
        'amount_foreign' => '100.00',
        'rate' => '4.50',
        'purpose' => 'Test',
        'source_of_funds' => 'salary',
        'till_id' => 'TEST-TILL',
    ];

    $transaction = $this->transactionService->createTransaction($data, $this->user->id);

    $this->assertEquals(TransactionStatus::Completed->value, $transaction->status);

    // Verify MYR balance was deducted
    $myrBalance = TillBalance::where('till_id', 'TEST-TILL')
        ->where('currency_code', 'MYR')
        ->first();

    // Paid 450 MYR for 100 USD (450 = 100 * 4.50)
    $this->assertEquals('450.00', $myrBalance->transaction_total);
}
```

- [ ] **Step 2: Add test that MYR balance is updated on Sell transaction**

```php
public function test_myr_till_balance_updated_on_sell_transaction(): void
{
    $customer = Customer::factory()->create();

    // Create USD position and MYR till balance
    CurrencyPosition::create([
        'currency_code' => 'USD',
        'till_id' => 'TEST-TILL',
        'balance' => '1000.00',
        'avg_cost_rate' => '4.50',
        'last_valuation_rate' => '4.50',
    ]);

    TillBalance::create([
        'till_id' => 'TEST-TILL',
        'currency_code' => 'USD',
        'opening_balance' => '0',
        'date' => today(),
        'opened_by' => $this->user->id,
    ]);

    TillBalance::create([
        'till_id' => 'TEST-TILL',
        'currency_code' => 'MYR',
        'opening_balance' => '10000.00',
        'date' => today(),
        'opened_by' => $this->user->id,
    ]);

    $data = [
        'customer_id' => $customer->id,
        'currency_code' => 'USD',
        'type' => TransactionType::Sell->value,
        'amount_foreign' => '100.00',
        'rate' => '4.50',
        'purpose' => 'Test',
        'source_of_funds' => 'salary',
        'till_id' => 'TEST-TILL',
    ];

    $transaction = $this->transactionService->createTransaction($data, $this->user->id);

    // Verify MYR balance was increased (received MYR)
    $myrBalance = TillBalance::where('till_id', 'TEST-TILL')
        ->where('currency_code', 'MYR')
        ->first();

    // Received 450 MYR for 100 USD (450 = 100 * 4.50)
    $this->assertEquals('450.00', $myrBalance->transaction_total);
}
```

- [ ] **Step 3: Run tests**

```bash
php artisan test --filter=TransactionServiceTest 2>&1 | tail -30
```

- [ ] **Step 4: Commit**

```bash
git add tests/Unit/TransactionServiceTest.php && git commit -m "test: add unit tests for MYR till balance updates"
```

---

## Task 13: Add Test for Audit Async Hash Sealing

**Files:**
- Modify: `tests/Unit/AuditServiceTest.php`

- [ ] **Step 1: Add test for async hash sealing**

```php
public function test_audit_hash_sealed_async_by_job(): void
{
    // Create a log entry
    $log = $this->auditService->logWithSeverity('test_action', ['entity_type' => 'Test'], 'INFO');

    // Entry should have null hash initially
    $this->assertNull($log->entry_hash);

    // Dispatch and run the seal job synchronously
    \App\Jobs\Audit\SealAuditHashJob::dispatchSync($log->id);

    // Entry should now be sealed
    $log->refresh();
    $this->assertNotNull($log->entry_hash);
    $this->assertNotNull($log->previous_hash);
}

public function test_verify_chain_integrity_skips_unsealed_entries(): void
{
    // Create unsealed log
    $log = $this->auditService->logWithSeverity('test_action', [], 'INFO');

    $result = $this->auditService->verifyChainIntegrity();

    // Should pass since unsealed entries are skipped
    $this->assertTrue($result['valid']);
}
```

- [ ] **Step 2: Run tests**

```bash
php artisan test --filter=AuditServiceTest 2>&1 | tail -20
```

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/AuditServiceTest.php && git commit -m "test: add tests for async audit hash sealing"
```

---

## Task 14: Add Test for Blind Indexing

**Files:**
- Modify: `tests/Unit/CustomerTest.php` or create if not exists

- [ ] **Step 1: Create or add to CustomerTest.php**

```php
public function test_blind_index_hash_is_deterministic(): void
{
    $hash1 = Customer::computeBlindIndex('A123456');
    $hash2 = Customer::computeBlindIndex('A123456');

    $this->assertEquals($hash1, $hash2);
}

public function test_blind_index_different_inputs_produce_different_hashes(): void
{
    $hash1 = Customer::computeBlindIndex('A123456');
    $hash2 = Customer::computeBlindIndex('B123456');

    $this->assertNotEquals($hash1, $hash2);
}

public function test_find_by_id_number_returns_correct_customer(): void
{
    $customer = Customer::factory()->create([
        'id_number_encrypted' => app(\App\Services\EncryptionService::class)->encrypt('A123456'),
    ]);

    // Save to trigger blind index computation
    $customer->save();

    $found = Customer::findByIdNumber('A123456');

    $this->assertNotNull($found);
    $this->assertEquals($customer->id, $found->id);
}

public function test_find_by_id_number_returns_null_for_non_existent(): void
{
    $found = Customer::findByIdNumber('NONEXISTENT');
    $this->assertNull($found);
}
```

- [ ] **Step 2: Run tests**

```bash
php artisan test --filter=CustomerTest 2>&1 | tail -20
```

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/CustomerTest.php && git commit -m "test: add tests for customer blind indexing"
```

---

## Task 15: Final Integration Test - Concurrent Sell Scenario

**Files:**
- Modify: `tests/Feature/TransactionWorkflowTest.php`

- [ ] **Step 1: Add integration test for concurrent sell with reservation**

```php
public function test_concurrent_sell_transactions_do_not_oversell_with_reservation(): void
{
    $customer = Customer::factory()->create();
    $counter = Counter::factory()->create();

    // Create USD position with exactly 500 USD
    $position = CurrencyPosition::create([
        'currency_code' => 'USD',
        'till_id' => $counter->id,
        'balance' => '500.00',
        'avg_cost_rate' => '4.50',
        'last_valuation_rate' => '4.50',
    ]);

    TillBalance::create([
        'till_id' => (string) $counter->id,
        'currency_code' => 'USD',
        'opening_balance' => '0',
        'date' => today(),
        'opened_by' => $this->user->id,
    ]);

    TillBalance::create([
        'till_id' => (string) $counter->id,
        'currency_code' => 'MYR',
        'opening_balance' => '100000.00',
        'date' => today(),
        'opened_by' => $this->user->id,
    ]);

    // Transaction 1: Sell 300 USD (PendingApproval, reserves 300)
    $data1 = [
        'customer_id' => $customer->id,
        'currency_code' => 'USD',
        'type' => TransactionType::Sell->value,
        'amount_foreign' => '300.00',
        'rate' => '4.50',
        'purpose' => 'Test',
        'source_of_funds' => 'salary',
        'till_id' => (string) $counter->id,
    ];

    $t1 = $this->transactionService->createTransaction($data1, $this->user->id);
    $this->assertEquals(TransactionStatus::PendingApproval->value, $t1->status);

    // Transaction 2: Sell 300 USD (should see only 200 available)
    $data2 = [
        'customer_id' => $customer->id,
        'currency_code' => 'USD',
        'type' => TransactionType::Sell->value,
        'amount_foreign' => '300.00',
        'rate' => '4.50',
        'purpose' => 'Test',
        'source_of_funds' => 'salary',
        'till_id' => (string) $counter->id,
    ];

    // This should fail because only 200 USD is available (500 - 300 reserved)
    $this->expectException(\App\Exceptions\Domain\InsufficientStockException::class);
    $this->transactionService->createTransaction($data2, $this->user->id);
}
```

- [ ] **Step 2: Run tests**

```bash
php artisan test --filter=TransactionWorkflowTest 2>&1 | tail -30
```

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/TransactionWorkflowTest.php && git commit -m "test: add concurrent sell test with stock reservation"
```

---

## Task 16: Run Full Test Suite

- [ ] **Step 1: Run full test suite**

```bash
php artisan test 2>&1 | tail -50
```

- [ ] **Step 2: If failures, diagnose and fix inline**

Common issues:
- Missing imports — add them
- Factory issues — check field names match migrations
- Transaction rollback in tests — wrap in `DB::beginTransaction()` / `DB::rollBack()`

---

## Spec Coverage Check

| Spec Section | Task(s) |
|-------------|---------|
| 1. Stock Reservation System | Tasks 1-4, 8, 11, 15 |
| 2. MYR Till Balance Fix | Tasks 5, 12 |
| 3. Async Audit Hashing | Tasks 6, 7, 13 |
| 4. Domain Exceptions | Tasks 1, 9 |
| 5. Blind Indexing | Task 10, 14 |

All spec sections have corresponding tasks.
