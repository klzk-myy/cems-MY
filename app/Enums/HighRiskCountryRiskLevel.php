<?php

namespace App\Enums;

/**
 * High Risk Country Risk Level Enum
 *
 * Represents the various risk levels for high risk countries.
 */
enum HighRiskCountryRiskLevel: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    /**
     * Check if the risk level is low.
     */
    public function isLow(): bool
    {
        return $this === self::Low;
    }

    /**
     * Check if the risk level is medium.
     */
    public function isMedium(): bool
    {
        return $this === self::Medium;
    }

    /**
     * Check if the risk level is high.
     */
    public function isHigh(): bool
    {
        return $this === self::High;
    }

    /**
     * Check if the risk level is critical.
     */
    public function isCritical(): bool
    {
        return $this === self::Critical;
    }

    /**
     * Get a human-readable label for the risk level.
     */
    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
            self::Critical => 'Critical',
        };
    }

    /**
     * Get the color class for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::Low => 'success',
            self::Medium => 'warning',
            self::High => 'danger',
            self::Critical => 'dark',
        };
    }
}
