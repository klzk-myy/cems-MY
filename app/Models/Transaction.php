<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
 * @property string $till_id
 * @property TransactionType $type
 * @property string $currency_code
 * @property string $amount_local MYR amount
 * @property string $amount_foreign Foreign currency amount
 * @property string $rate Exchange rate applied
 * @property string|null $purpose Transaction purpose
 * @property string|null $source_of_funds Source of funds
 * @property TransactionStatus $status
 * @property string|null $hold_reason Reason for hold status
 * @property int|null $approved_by User ID who approved
 * @property Carbon|null $approved_at
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
 */
class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     *
     * SECURITY NOTE: These fields are protected by controller validation, not just
     * model-level fillable guards. The controller validates all inputs before
     * calling create()/update() with these fields.
     */
    protected $fillable = [
        'customer_id',
        'user_id',
        'branch_id',
        'till_id',
        'type',
        'currency_code',
        'amount_local',
        'amount_foreign',
        'rate',
        'base_rate',
        'rate_override',
        'rate_override_approved_by',
        'rate_override_approved_at',
        'purpose',
        'source_of_funds',
        'status',
        'hold_reason',
        'approved_by',
        'approved_at',
        'cdd_level',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'original_transaction_id',
        'is_refund',
        'idempotency_key',
        'version',
        'transition_history',
        'failure_reason',
        'rejection_reason',
        'reversal_reason',
        'journal_entry_id',
        'deferred_journal_entry_id',
        'journal_entries_created_at',
        'has_deferred_accounting',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount_local' => 'decimal:4',
        'amount_foreign' => 'decimal:4',
        'rate' => 'decimal:6',
        'base_rate' => 'decimal:6',
        'rate_override' => 'boolean',
        'type' => \App\Enums\TransactionType::class,
        'status' => \App\Enums\TransactionStatus::class,
        'cdd_level' => \App\Enums\CddLevel::class,
        'approved_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'rate_override_approved_at' => 'datetime',
        'transition_history' => 'array',
        'journal_entries_created_at' => 'datetime',
        'has_deferred_accounting' => 'boolean',
    ];

    /**
     * Get the customer associated with this transaction.
     *
     * @return BelongsTo
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the user who created this transaction.
     *
     * @return BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the branch associated with this transaction.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the user who approved this transaction.
     *
     * @return BelongsTo
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the currency associated with this transaction.
     *
     * @return BelongsTo
     */
    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_code');
    }

    /**
     * Get all flagged transactions related to this transaction.
     *
     * @return HasMany
     */
    public function flags()
    {
        return $this->hasMany(FlaggedTransaction::class);
    }

    /**
     * Get stock reservations for this transaction.
     *
     * @return HasMany
     */
    public function stockReservations()
    {
        return $this->hasMany(StockReservation::class);
    }

    /**
     * Get the refund transaction if this transaction was refunded.
     *
     * @return HasOne
     */
    public function refundTransaction()
    {
        return $this->hasOne(Transaction::class, 'original_transaction_id');
    }

    /**
     * Get the original transaction if this is a refund.
     *
     * @return BelongsTo
     */
    public function originalTransaction()
    {
        return $this->belongsTo(Transaction::class, 'original_transaction_id');
    }

    /**
     * Get the user who cancelled this transaction.
     *
     * @return BelongsTo
     */
    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * Get all transaction errors for this transaction.
     *
     * @return HasMany
     */
    public function transactionErrors()
    {
        return $this->hasMany(TransactionError::class);
    }
}
