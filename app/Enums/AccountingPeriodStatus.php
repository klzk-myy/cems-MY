<?php

namespace App\Enums;

/**
 * Accounting Period Status Enum
 *
 * Represents the various statuses an accounting period can have.
 */
enum AccountingPeriodStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    /**
     * Check if the period is open.
     */
    public function isOpen(): bool
    {
        return $this === self::Open;
    }

    /**
     * Check if the period is closed.
     */
    public function isClosed(): bool
    {
        return $this === self::Closed;
    }

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Closed => 'Closed',
        };
    }

    /**
     * Get the color class for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::Open => 'success',
            self::Closed => 'secondary',
        };
    }
}
