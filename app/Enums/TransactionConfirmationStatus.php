<?php

namespace App\Enums;

/**
 * Transaction Confirmation Status Enum
 *
 * Represents the various statuses a transaction confirmation can have.
 */
enum TransactionConfirmationStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
    case Expired = 'expired';

    /**
     * Check if the confirmation is pending.
     */
    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    /**
     * Check if the confirmation is confirmed.
     */
    public function isConfirmed(): bool
    {
        return $this === self::Confirmed;
    }

    /**
     * Check if the confirmation is rejected.
     */
    public function isRejected(): bool
    {
        return $this === self::Rejected;
    }

    /**
     * Check if the confirmation is expired.
     */
    public function isExpired(): bool
    {
        return $this === self::Expired;
    }

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Confirmed => 'Confirmed',
            self::Rejected => 'Rejected',
            self::Expired => 'Expired',
        };
    }

    /**
     * Get the color class for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Confirmed => 'success',
            self::Rejected => 'danger',
            self::Expired => 'secondary',
        };
    }
}
