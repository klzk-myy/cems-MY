<?php

namespace App\Enums;

/**
 * System Alert Level Enum
 *
 * Represents the various severity levels for system alerts.
 */
enum SystemAlertLevel: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Critical = 'critical';

    /**
     * Get the numeric value for comparison.
     */
    public function value(): int
    {
        return match ($this) {
            self::Info => 1,
            self::Warning => 2,
            self::Critical => 3,
        };
    }

    /**
     * Check if this level is at least the given level.
     */
    public function isAtLeast(SystemAlertLevel $level): bool
    {
        return $this->value() >= $level->value();
    }

    /**
     * Get a human-readable label for the level.
     */
    public function label(): string
    {
        return match ($this) {
            self::Info => 'Info',
            self::Warning => 'Warning',
            self::Critical => 'Critical',
        };
    }

    /**
     * Get the color class for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::Info => 'blue',
            self::Warning => 'yellow',
            self::Critical => 'red',
        };
    }
}
