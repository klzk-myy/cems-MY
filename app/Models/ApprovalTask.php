<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

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
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $decided_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
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
     * Check if the task has been approved.
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if the task has been rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Check if the task has expired.
     */
    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    /**
     * Check if the task is still actionable (pending and not expired).
     */
    public function isActionable(): bool
    {
        if (! $this->isPending()) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Get pending tasks for a specific user based on their role.
     *
     * Only returns tasks that the user CAN actually approve based on role hierarchy.
     * - Admins can see supervisor, manager, and admin tasks
     * - Managers can see supervisor and manager tasks
     * - Tellers and Compliance Officers cannot approve anything (empty result)
     */
    public static function getPendingForUser(User $user): Collection
    {
        $query = self::where('status', self::STATUS_PENDING)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });

        // Determine which required roles the user can approve based on their role
        // Admin can approve admin and manager tasks
        // Manager can approve manager tasks only
        // Tellers and Compliance Officers cannot approve anything
        $requiredRoles = match (true) {
            $user->role->isAdmin() => ['admin', 'manager'],
            $user->role->isManager() => ['manager'],
            default => [],
        };

        if (empty($requiredRoles)) {
            return new Collection;
        }

        return $query->whereIn('required_role', $requiredRoles)
            ->with(['transaction', 'approver'])
            ->orderBy('created_at', 'asc')
            ->get();
    }
}
