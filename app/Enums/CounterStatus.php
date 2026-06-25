<?php

namespace App\Enums;

/**
 * Counter Status Enum
 *
 * Represents the various statuses a counter can have.
 */
enum CounterStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Maintenance = 'maintenance';

    /**
     * Check if the counter is active.
     */
    public function isActive(): bool
    {
        return $this === self::Active;
    }

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Maintenance => 'Maintenance',
        };
    }

    /**
     * Get the color class for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'secondary',
            self::Maintenance => 'warning',
        };
    }
}
