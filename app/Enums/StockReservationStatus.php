<?php

namespace App\Enums;

/**
 * Stock Reservation Status Enum
 *
 * Represents the various statuses of a stock reservation.
 */
enum StockReservationStatus: string
{
    case Pending = 'pending';
    case Consumed = 'consumed';
    case Released = 'released';

    /**
     * Check if the reservation is pending.
     */
    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    /**
     * Check if the reservation has been consumed.
     */
    public function isConsumed(): bool
    {
        return $this === self::Consumed;
    }

    /**
     * Check if the reservation has been released.
     */
    public function isReleased(): bool
    {
        return $this === self::Released;
    }
}
