<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\CddLevel;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Bases\TransactionModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Transaction Model
 *
 * Represents foreign currency buy/sell transactions in the CEMS-MY system.
 * Supports compliance monitoring, approval workflows, and refund operations.
 *
 * @property int $id
 * @property int|null $customer_id
 * @property int $user_id
 * @property string|null $till_id
 * @property TransactionType $type
 * @property string $currency_code
 * @property string|null $counterparty_country ISO 3-letter country code
 * @property string $amount_myr MYR amount
 * @property string $quantity Foreign currency amount
 * @property string $rate Exchange rate applied
 * @property string|null $purpose Transaction purpose
 * @property string|null $source_of_funds Source of funds
 * @property string|null $source_of_wealth Source of wealth (required for PEPs per pd-00.md 14C.13.1(c))
 * @property TransactionStatus $status
 * @property string|null $hold_reason Reason for hold status
 * @property int|null $approved_by User ID who approved
 * @property Carbon|null $approved_at
 * @property int|null $reexecuted_by User ID who triggered re-execution (null = system job)
 * @property Carbon|null $reexecuted_at
 * @property CddLevel $cdd_level
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by
 * @property string|null $cancellation_reason
 * @property int|null $original_transaction_id For refunds
 * @property bool $is_refund
 * @property string|null $idempotency_key Duplicate prevention
 * @property int $version Optimistic locking
 * @property array|null $transition_history State machine transition history
 * @property string|null $failure_reason Reason for failed status
 * @property string|null $rejection_reason Reason for rejected status
 * @property string|null $reversal_reason Reason for reversed status
 * @property int|null $branch_id
 * @property int|null $counter_id
 * @property int|null $teller_allocation_id Allocation pinned at creation for balance apply/reverse
 * @property string|null $till_id
 * @property string|null $base_rate
 * @property bool $rate_override
 * @property int|null $rate_override_approved_by
 * @property Carbon|null $rate_override_approved_at
 * @property int|null $journal_entry_id
 * @property int|null $deferred_journal_entry_id
 * @property Carbon|null $journal_entries_created_at
 * @property bool $has_deferred_accounting
 * @property bool $approval_sync_failed
 * @property Carbon|null $approval_sync_failed_at
 * @property string|null $approval_sync_error
 * @property int|null $compliance_cleared_by
 * @property Carbon|null $compliance_cleared_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property bool $is_dlq
 * @property string|null $prev_quantity Pre-mutation position snapshot (reversal restore)
 * @property string|null $prev_average_cost Pre-mutation average cost snapshot (reversal restore)
 * @property-read string $reference Human-readable reference derived from id (TX-XXXXXXXX)
 * @property-read string $status_variant UI status badge variant
 */
class Transaction extends TransactionModel
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     *
     * SECURITY NOTE: Only user-controllable fields are fillable.
     * System-managed fields (approvals, journal entries, sync status, etc.)
     * are set explicitly by service layer methods, not mass assignment.
     */
    protected $fillable = [
        'customer_id',
        'currency_code',
        'counter_id',
        'till_id',
        'type',
        'counterparty_country',
        'amount_myr',
        'quantity',
        'rate',
        'purpose',
        'source_of_funds',
        'source_of_wealth',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Transaction $transaction) {
            // Free the unique idempotency_key when a soft delete occurs so a
            // retried operation presenting the same key can proceed. Assigned
            // quietly so the delete flow is not re-triggered.
            if (! $transaction->isForceDeleting() && $transaction->idempotency_key !== null) {
                $transaction->idempotency_key = null;
                $transaction->saveQuietly();
            }
        });
    }

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount_myr' => MoneyCast::class,
        'quantity' => MoneyCast::class,
        'rate' => MoneyCast::class.':8',
        'base_rate' => MoneyCast::class.':8',
        'rate_override' => 'boolean',
        'is_refund' => 'boolean',
        'type' => TransactionType::class,
        'status' => TransactionStatus::class,
        'cdd_level' => CddLevel::class,
        'cancelled_at' => 'datetime',
        'rate_override_approved_at' => 'datetime',
        'transition_history' => 'array',
        'journal_entries_created_at' => 'datetime',
        'has_deferred_accounting' => 'boolean',
        'customer_id' => 'integer',
        'user_id' => 'integer',
        'branch_id' => 'integer',
        'teller_allocation_id' => 'integer',
        'approved_by' => 'integer',
        'approved_at' => 'datetime',
        'reexecuted_by' => 'integer',
        'reexecuted_at' => 'datetime',
        'compliance_cleared_by' => 'integer',
        'compliance_cleared_at' => 'datetime',
        'approval_sync_failed' => 'boolean',
        'approval_sync_failed_at' => 'datetime',
        'version' => 'integer',
        'hold_reason' => 'string',
        'cancelled_by' => 'integer',
        'cancellation_reason' => 'string',
        'is_dlq' => 'boolean',
        'prev_quantity' => MoneyCast::class,
        'prev_average_cost' => MoneyCast::class.':8',
    ];

    /**
     * Human-readable transaction reference.
     *
     * There is no `reference` column in the transactions table; the reference is
     * derived from the row id so it is stable, unique, and searchable by number.
     */
    public function getReferenceAttribute(): string
    {
        return 'TX-'.str_pad((string) $this->id, 8, '0', STR_PAD_LEFT);
    }

    /**
     * Get the status badge variant for UI display.
     */
    public function getStatusVariantAttribute(): string
    {
        return match ($this->status) {
            TransactionStatus::Completed => 'success',
            TransactionStatus::Pending, TransactionStatus::PendingApproval => 'warning',
            TransactionStatus::Cancelled => 'danger',
            default => 'gray',
        };
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', TransactionStatus::Completed->value);
    }

    public function scopePendingApproval(Builder $query): Builder
    {
        return $query->where('status', TransactionStatus::PendingApproval);
    }

    /**
     * @param  Builder<Transaction>  $query
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->forDateRange(today()->toDateString(), today()->toDateString());
    }

    public function scopeNotCancelled(Builder $query): Builder
    {
        return $query->where('status', '!=', TransactionStatus::Cancelled->value);
    }

    public function scopeForDateRange(Builder $query, string $from, string $to): Builder
    {
        // Range predicate on the raw column (not DATE(created_at)) so an index
        // on created_at can be used on the transactions table. Carbon::parse
        // preserves the previous whereDate semantics even when $from/$to carry
        // a time component, without any string concatenation.
        return $query->whereBetween('created_at', [
            Carbon::parse($from)->startOfDay(),
            Carbon::parse($to)->endOfDay(),
        ]);
    }

    public function scopeForBranch(Builder $query, ?int $branchId): Builder
    {
        return $query->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
    }

    public function scopeBuy(Builder $query): Builder
    {
        return $query->where('type', TransactionType::Buy->value);
    }

    public function scopeSell(Builder $query): Builder
    {
        return $query->where('type', TransactionType::Sell->value);
    }

    protected function activeStatusValues(): array
    {
        return [
            TransactionStatus::Approved->value,
            TransactionStatus::Processing->value,
            TransactionStatus::Completed->value,
            TransactionStatus::Finalized->value,
        ];
    }

    protected function openStatusValues(): array
    {
        return [
            TransactionStatus::Draft->value,
            TransactionStatus::PendingApproval->value,
            TransactionStatus::Pending->value,
            TransactionStatus::OnHold->value,
            TransactionStatus::PendingCancellation->value,
        ];
    }

    /**
     * Get all flagged transactions related to this transaction.
     *
     * @return HasMany<FlaggedTransaction, $this>
     */
    public function flags(): HasMany
    {
        return $this->hasMany(FlaggedTransaction::class);
    }

    /**
     * Get stock reservations for this transaction.
     *
     * @return HasMany<StockReservation, $this>
     */
    public function stockReservations(): HasMany
    {
        return $this->hasMany(StockReservation::class);
    }

    /**
     * Get the manager confirmation requests for this transaction.
     *
     * @return HasMany<TransactionConfirmation, $this>
     */
    public function confirmations(): HasMany
    {
        return $this->hasMany(TransactionConfirmation::class);
    }

    /**
     * Get the refund transaction if this transaction was refunded.
     *
     * @return HasOne<Transaction, $this>
     */
    public function refundTransaction(): HasOne
    {
        return $this->hasOne(Transaction::class, 'original_transaction_id');
    }

    /**
     * Get the original transaction if this is a refund.
     *
     * @return BelongsTo<Transaction, $this>
     */
    public function originalTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'original_transaction_id');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Alias of user() - the teller who created the transaction.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Alias of user() - the teller who handled the transaction.
     *
     * @return BelongsTo<User, $this>
     */
    public function teller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the user who cancelled this transaction.
     *
     * @return BelongsTo<User, $this>
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * Get all transaction errors for this transaction.
     *
     * @return HasMany<TransactionError, $this>
     */
    public function transactionErrors(): HasMany
    {
        return $this->hasMany(TransactionError::class);
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function deferredJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'deferred_journal_entry_id');
    }
}
