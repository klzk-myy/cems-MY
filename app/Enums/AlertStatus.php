<?php

namespace App\Enums;

/**
 * Alert Status Enum
 *
 * Lifecycle states for compliance alerts (App\Models\Compliance\Alert).
 * Values mirror FlagStatus for storage compatibility, but alerts are a
 * distinct entity — they must not borrow the flagged-transaction vocabulary.
 */
enum AlertStatus: string
{
    case Open = 'open';
    case UnderReview = 'under_review';
    case Resolved = 'resolved';
    case Escalated = 'escalated';
    case Rejected = 'rejected';

    /**
     * Check if the alert is open.
     */
    public function isOpen(): bool
    {
        return $this === self::Open;
    }

    /**
     * Check if the alert is under review.
     */
    public function isUnderReview(): bool
    {
        return $this === self::UnderReview;
    }

    /**
     * Check if the alert is resolved.
     */
    public function isResolved(): bool
    {
        return $this === self::Resolved;
    }

    /**
     * Check if the alert is escalated.
     */
    public function isEscalated(): bool
    {
        return $this === self::Escalated;
    }

    /**
     * Check if the alert is still active (not resolved).
     */
    public function isActive(): bool
    {
        return ! $this->isResolved();
    }

    /**
     * Statuses that mean compliance has dispositioned the alert — it was
     * reviewed and is no longer actionable on its own.
     *
     * @return array<int, self>
     */
    public static function terminalStatuses(): array
    {
        return [self::Resolved, self::Rejected];
    }

    /**
     * Check if the alert has been dispositioned (resolved or rejected).
     * Distinct from isActive(): Rejected is terminal but not "resolved",
     * and historically counted as active.
     */
    public function isTerminal(): bool
    {
        return in_array($this, self::terminalStatuses(), true);
    }

    /**
     * Backing values of the terminal statuses, for query bindings.
     *
     * @return array<int, string>
     */
    public static function terminalValues(): array
    {
        return array_map(fn (self $s) => $s->value, self::terminalStatuses());
    }

    /**
     * Check if the alert can be assigned.
     */
    public function canBeAssigned(): bool
    {
        return in_array($this, [self::Open, self::Escalated], true);
    }

    /**
     * Check if the alert can be resolved.
     */
    public function canBeResolved(): bool
    {
        return in_array($this, [self::Open, self::UnderReview, self::Escalated], true);
    }

    /**
     * Check if the alert can be escalated.
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
