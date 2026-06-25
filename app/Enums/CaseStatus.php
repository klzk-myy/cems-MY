<?php

namespace App\Enums;

enum CaseStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case PendingReview = 'pending_review';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::InProgress => 'In Progress',
            self::PendingReview => 'Pending Review',
            self::Resolved => 'Resolved',
            self::Closed => 'Closed',
        };
    }

    public function isFinal(): bool
    {
        return match ($this) {
            self::Resolved, self::Closed => true,
            default => false,
        };
    }
}
