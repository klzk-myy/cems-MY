<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Approval Task Model
 *
 * Represents an approval task for transactions that exceed the auto-approve threshold.
 * Tracks the approval workflow from creation through approval/rejection/expiry.
 *
 * @property int $id
 * @property int $transaction_id
 * @property int|null $approver_id
 * @property string $status pending, approved, rejected, expired
 * @property string $threshold_amount
 * @property string $required_role supervisor, manager, admin
 * @property string|null $notes
 * @property Carbon|null $expires_at
 * @property Carbon|null $decided_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ApprovalTask extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXPIRED = 'expired';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'transaction_id',
        'approver_id',
        'status',
        'threshold_amount',
        'required_role',
        'notes',
        'expires_at',
        'decided_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'threshold_amount' => 'decimal:4',
        'expires_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    /**
     * Get the transaction associated with this approval task.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Get the user who acted on this task (approved/rejected).
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /**
     * Alias for approver relationship (for consistency with other models).
     */
    public function approverUser(): BelongsTo
    {
        return $this->approver();
    }

    /**
     * Check if the task is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the task is actionable (pending and not expired).
     */
    public function isActionable(): bool
    {
        if (! $this->isPending()) {
            return false;
        }

        if ($this->expires_at === null) {
            return true;
        }

        return $this->expires_at->isFuture();
    }
}
