<?php

namespace App\Enums;

/**
 * Flag Status Enum
 *
 * Represents the different statuses a compliance flag can have.
 */
enum FlagStatus: string
{
    case Open = 'Open';
    case UnderReview = 'Under_Review';
    case Resolved = 'Resolved';
    case Escalated = 'Escalated';
    case Rejected = 'Rejected';

    /**
     * Check if the flag is open.
     */
    public function isOpen(): bool
    {
        return $this === self::Open;
    }

    /**
     * Check if the flag is under review.
     */
    public function isUnderReview(): bool
    {
        return $this === self::UnderReview;
    }

    /**
     * Check if the flag is resolved.
     */
    public function isResolved(): bool
    {
        return $this === self::Resolved;
    }

    /**
     * Check if the flag is escalated.
     */
    public function isEscalated(): bool
    {
        return $this === self::Escalated;
    }

    /**
     * Check if the flag is still active (not resolved).
     */
    public function isActive(): bool
    {
        return ! $this->isResolved();
    }

    /**
     * Check if the flag can be assigned.
     */
    public function canBeAssigned(): bool
    {
        return in_array($this, [self::Open, self::Escalated], true);
    }

    /**
     * Check if the flag can be resolved.
     */
    public function canBeResolved(): bool
    {
        return in_array($this, [self::Open, self::UnderReview, self::Escalated], true);
    }

    /**
     * Check if the flag can be escalated.
     */
    public function canBeEscalated(): bool
    {
        return in_array($this, [self::Open, self::UnderReview], true);
    }

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::UnderReview => 'Under Review',
            self::Resolved => 'Resolved',
            self::Escalated => 'Escalated',
            self::Rejected => 'Rejected',
        };
    }

    /**
     * Get the color class for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::Open => 'danger',
            self::UnderReview => 'warning',
            self::Resolved => 'success',
            self::Escalated => 'info',
            self::Rejected => 'secondary',
        };
    }
}
