<?php

namespace App\Models;

use App\Enums\ApprovalLevel;
use App\Enums\ApprovalStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PEP Approval Request Model
 *
 * Tracks head office Senior Management approval for establishing or continuing
 * business relationships with PEP customers per pd-00.md 14C.13.1(d).
 *
 * @property int $id
 * @property int $customer_id
 * @property string $transaction_type
 * @property ApprovalStatus $status
 * @property string $approval_level
 * @property Carbon|null $requested_at
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property int|null $rejected_by
 * @property Carbon|null $rejected_at
 * @property string|null $rejection_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PepApprovalRequest extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'transaction_type',
        'status',
        'approval_level',
        'requested_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
    ];

    protected $casts = [
        'status' => ApprovalStatus::class,
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'approval_level' => ApprovalLevel::class,
    ];

    /**
     * Get the customer that owns this approval request.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the user who approved this request.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the user who rejected this request.
     */
    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * Check if the request is pending.
     */
    public function isPending(): bool
    {
        return $this->status === ApprovalStatus::Pending;
    }

    /**
     * Check if the request has been approved.
     */
    public function isApproved(): bool
    {
        return $this->status === ApprovalStatus::Approved;
    }

    /**
     * Check if the request has been rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === ApprovalStatus::Rejected;
    }
}
