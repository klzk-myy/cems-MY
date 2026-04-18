<?php

namespace App\Enums;

enum StrStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case PendingApproval = 'pending_approval';
    case Submitted = 'submitted';
    case Acknowledged = 'acknowledged';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingReview => 'Pending Review',
            self::PendingApproval => 'Pending Approval',
            self::Submitted => 'Submitted',
            self::Acknowledged => 'Acknowledged',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::PendingReview => 'warning',
            self::PendingApproval => 'info',
            self::Submitted => 'primary',
            self::Acknowledged => 'success',
            self::Failed => 'danger',
        };
    }

    public function canSubmit(): bool
    {
        return $this === self::PendingApproval;
    }

    public function canApprove(): bool
    {
        return $this === self::PendingApproval;
    }

    public function canReview(): bool
    {
        return $this === self::Draft;
    }

    public function canRetry(): bool
    {
        return in_array($this, [self::Failed, self::PendingApproval]);
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Draft, self::PendingReview, self::PendingApproval]);
    }
}
