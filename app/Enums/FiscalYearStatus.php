<?php

namespace App\Enums;

/**
 * Fiscal Year Status Enum
 *
 * Represents the various statuses a fiscal year can have.
 */
enum FiscalYearStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Closed = 'closed';
    case Archived = 'archived';
    case Deleted = 'deleted';

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
            self::Draft => 'Draft',
            self::Open => 'Open',
            self::Closed => 'Closed',
            self::Archived => 'Archived',
            self::Deleted => 'Deleted',
        };
    }

    /**
     * Get the color class for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'warning',
            self::Open => 'success',
            self::Closed => 'secondary',
            self::Archived => 'info',
            self::Deleted => 'danger',
        };
    }
}
