<?php

namespace App\Models;

use App\Enums\StrStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StrReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'str_no',
        'branch_id',
        'customer_id',
        'alert_id',
        'transaction_ids',
        'reason',
        'supporting_documents',
        'status',
        'submitted_at',
        'bnm_reference',
        'created_by',
        'reviewed_by',
        'approved_by',
        'suspicion_date',
        'filing_deadline',
        'retry_count',
        'last_error',
        'last_retry_at',
    ];

    protected $casts = [
        'transaction_ids' => 'array',
        'supporting_documents' => 'array',
        'status' => StrStatus::class,
        'submitted_at' => 'datetime',
        'suspicion_date' => 'datetime',
        'filing_deadline' => 'datetime',
        'last_retry_at' => 'datetime',
        'retry_count' => 'integer',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function alert()
    {
        return $this->belongsTo(FlaggedTransaction::class, 'alert_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function transactions()
    {
        return Transaction::whereIn('id', $this->transaction_ids ?? [])->get();
    }

    public function isDraft(): bool
    {
        return $this->status === StrStatus::Draft;
    }

    public function isPendingReview(): bool
    {
        return $this->status === StrStatus::PendingReview;
    }

    public function isPendingApproval(): bool
    {
        return $this->status === StrStatus::PendingApproval;
    }

    public function isSubmitted(): bool
    {
        return $this->status === StrStatus::Submitted;
    }

    public function isAcknowledged(): bool
    {
        return $this->status === StrStatus::Acknowledged;
    }

    public function isFailed(): bool
    {
        return $this->status === StrStatus::Failed;
    }

    public function canRetry(): bool
    {
        return $this->status->canRetry();
    }

    /**
     * Check if the STR filing deadline has been exceeded.
     *
     * BNM requires STR to be filed within 3 working days of suspicion arising.
     */
    public function isOverdue(): bool
    {
        if (! $this->filing_deadline) {
            return false;
        }

        return now()->isAfter($this->filing_deadline);
    }

    /**
     * Get the number of days remaining until the filing deadline.
     *
     * @return int Negative if overdue, positive if days remaining
     */
    public function daysUntilDeadline(): int
    {
        if (! $this->filing_deadline) {
            return 0;
        }

        return (int) now()->diffInDays($this->filing_deadline, false);
    }

    /**
     * Get the severity of overdue status.
     *
     * @return string|null 'warning' (1-2 days overdue), 'critical' (3+ days overdue), or null
     */
    public function overdueSeverity(): ?string
    {
        if (! $this->isOverdue()) {
            return null;
        }

        $daysOverdue = abs($this->daysUntilDeadline());

        if ($daysOverdue >= 3) {
            return 'critical';
        }

        return 'warning';
    }
}
