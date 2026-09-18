<?php

namespace App\Enums;

/**
 * Pool Remittance Status Enum
 *
 * Lifecycle of a cash remittance between a trading branch and the
 * head-office branch. Pending means the sender's pool has been debited
 * and the value is parked in the inter-branch clearing account (2300)
 * until the receiving side acknowledges. Cancelled reverses the
 * outbound leg back to the sender.
 */
enum PoolRemittanceStatus: string
{
    case Pending = 'pending';
    case Acknowledged = 'acknowledged';
    case Cancelled = 'cancelled';

    /**
     * Check if the remittance is still in transit.
     */
    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    /**
     * Human-readable label for display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'In Transit',
            self::Acknowledged => 'Acknowledged',
            self::Cancelled => 'Cancelled',
        };
    }
}
