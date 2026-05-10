<?php

namespace App\Enums;

/**
 * Bank Reconciliation Status Enum
 *
 * Represents the various statuses for bank reconciliation records.
 */
enum BankReconciliationStatus: string
{
    case Unmatched = 'unmatched';
    case Matched = 'matched';
    case Exception = 'exception';

    /**
     * Check if the record is unmatched.
     */
    public function isUnmatched(): bool
    {
        return $this === self::Unmatched;
    }

    /**
     * Check if the record is matched.
     */
    public function isMatched(): bool
    {
        return $this === self::Matched;
    }

    /**
     * Check if the record is an exception.
     */
    public function isException(): bool
    {
        return $this === self::Exception;
    }

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Unmatched => 'Unmatched',
            self::Matched => 'Matched',
            self::Exception => 'Exception',
        };
    }

    /**
     * Get the color class for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::Unmatched => 'warning',
            self::Matched => 'success',
            self::Exception => 'danger',
        };
    }
}

/**
 * Check Status Enum
 *
 * Represents the various statuses for checks in bank reconciliation.
 */
enum CheckStatus: string
{
    case Issued = 'issued';
    case Presented = 'presented';
    case Cleared = 'cleared';
    case Returned = 'returned';
    case Stopped = 'stopped';

    /**
     * Check if the check is outstanding (issued or presented but not cleared).
     */
    public function isOutstanding(): bool
    {
        return in_array($this, [self::Issued, self::Presented]);
    }

    /**
     * Check if the check has cleared.
     */
    public function isCleared(): bool
    {
        return $this === self::Cleared;
    }

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Issued => 'Issued',
            self::Presented => 'Presented',
            self::Cleared => 'Cleared',
            self::Returned => 'Returned',
            self::Stopped => 'Stopped',
        };
    }

    /**
     * Get the color class for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::Issued => 'info',
            self::Presented => 'warning',
            self::Cleared => 'success',
            self::Returned => 'danger',
            self::Stopped => 'secondary',
        };
    }
}
