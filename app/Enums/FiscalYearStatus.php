<?php

namespace App\Enums;

/**
 * Fiscal Year Status Enum
 *
 * Represents the various statuses a fiscal year can have.
 */
enum FiscalYearStatus: string
{
    case Open = 'Open';
    case Closed = 'Closed';
    case Archived = 'Archived';

    /**
     * Check if the fiscal year is open.
     */
    public function isOpen(): bool
    {
        return $this === self::Open;
    }

    /**
     * Check if the fiscal year is closed.
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
            self::Archived => 'Archived',
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
            self::Archived => 'info',
        };
    }
}
