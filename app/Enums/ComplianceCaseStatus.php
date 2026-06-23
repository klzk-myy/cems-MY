<?php

namespace App\Enums;

/**
 * Compliance Case Status Enum
 *
 * Represents the status of a compliance case in the workflow.
 */
enum ComplianceCaseStatus: string
{
    case Open = 'Open';
    case UnderReview = 'UnderReview';
    case PendingApproval = 'PendingApproval';
    case Closed = 'Closed';
    case Escalated = 'Escalated';

    /**
     * Check if this status can transition to the target status.
     */
    public function canMoveTo(ComplianceCaseStatus $target): bool
    {
        return match ($this) {
            self::Open => in_array($target, [self::UnderReview, self::Closed, self::Escalated]),
            self::UnderReview => in_array($target, [self::PendingApproval, self::Closed, self::Escalated]),
            self::PendingApproval => in_array($target, [self::Closed, self::UnderReview]),
            self::Escalated => in_array($target, [self::UnderReview, self::Closed]),
            self::Closed => false,
        };
    }

    /**
     * Check if this status is terminal (no further transitions possible).
     */
    public function isTerminal(): bool
    {
        return $this === self::Closed;
    }

    /**
     * Check if this status represents an active case.
     */
    public function isActive(): bool
    {
        return $this !== self::Closed;
    }

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::UnderReview => 'Under Review',
            self::PendingApproval => 'Pending Approval',
            self::Closed => 'Closed',
            self::Escalated => 'Escalated',
        };
    }

    /**
     * Get the Bootstrap color class for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::Open => 'primary',
            self::UnderReview => 'warning',
            self::PendingApproval => 'info',
            self::Closed => 'success',
            self::Escalated => 'danger',
        };
    }
}
