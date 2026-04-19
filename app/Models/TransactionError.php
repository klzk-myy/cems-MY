<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Transaction Error Model
 *
 * Records transaction processing errors for retry tracking and dead letter queue management.
 *
 * @property int $id
 * @property int $transaction_id
 * @property string $error_type
 * @property string $error_message
 * @property array|null $error_context
 * @property int $retry_count
 * @property int $max_retries
 * @property Carbon|null $next_retry_at
 * @property Carbon|null $resolved_at
 * @property int|null $resolved_by
 * @property string|null $resolution_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TransactionError extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'error_type',
        'error_message',
        'error_context',
        'retry_count',
        'max_retries',
        'next_retry_at',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
    ];

    protected $casts = [
        'error_context' => 'array',
        'next_retry_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /**
     * Get the transaction associated with this error.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Get the user who resolved this error.
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Check if this error can be retried.
     */
    public function canRetry(): bool
    {
        return $this->retry_count < $this->max_retries;
    }

    /**
     * Check if this error has been resolved.
     */
    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    /**
     * Increment retry count and set next retry time.
     *
     * @param  int  $delayMs  Delay in milliseconds
     */
    public function incrementRetry(int $delayMs): bool
    {
        $this->retry_count++;
        $this->next_retry_at = now()->addMilliseconds($delayMs);

        return $this->save();
    }
}
