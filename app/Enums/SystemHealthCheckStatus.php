<?php

namespace App\Enums;

/**
 * System Health Check Status Enum
 *
 * Represents the various statuses for system health checks.
 */
enum SystemHealthCheckStatus: string
{
    case Ok = 'ok';
    case Warning = 'warning';
    case Critical = 'critical';

    /**
     * Check if the status is healthy.
     */
    public function isHealthy(): bool
    {
        return $this === self::Ok;
    }

    /**
     * Check if the status is warning.
     */
    public function isWarning(): bool
    {
        return $this === self::Warning;
    }

    /**
     * Check if the status is critical.
     */
    public function isCritical(): bool
    {
        return $this === self::Critical;
    }

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Ok => 'OK',
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
            self::Ok => 'green',
            self::Warning => 'yellow',
            self::Critical => 'red',
        };
    }
}
