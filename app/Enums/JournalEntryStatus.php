<?php

namespace App\Enums;

/**
 * Journal Entry Status Enum
 *
 * Represents the various statuses a journal entry can have in its workflow.
 */
enum JournalEntryStatus: string
{
    case Draft = 'Draft';
    case Pending = 'Pending';
    case Posted = 'Posted';
    case Rejected = 'Rejected';
    case Reversed = 'Reversed';

    /**
     * Check if the entry is in draft state.
     */
    public function isDraft(): bool
    {
        return $this === self::Draft;
    }

    /**
     * Check if the entry is pending approval.
     */
    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    /**
     * Check if the entry has been posted.
     */
    public function isPosted(): bool
    {
        return $this === self::Posted;
    }

    /**
     * Check if the entry was rejected.
     */
    public function isRejected(): bool
    {
        return $this === self::Rejected;
    }

    /**
     * Check if the entry was reversed.
     */
    public function isReversed(): bool
    {
        return $this === self::Reversed;
    }

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Pending Approval',
            self::Posted => 'Posted',
            self::Rejected => 'Rejected',
            self::Reversed => 'Reversed',
        };
    }

    /**
     * Get the color class for the status badge.
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'badge-secondary',
            self::Pending => 'badge-warning',
            self::Posted => 'badge-success',
            self::Rejected => 'badge-danger',
            self::Reversed => 'badge-info',
        };
    }
}
