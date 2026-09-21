<?php

namespace App\Enums;

/**
 * Risk Rating Enum
 *
 * Represents customer risk ratings based on AML/CFT compliance scoring.
 */
enum RiskRating: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    /**
     * Get the refresh frequency in years for this risk rating.
     */
    public function refreshFrequencyYears(): int
    {
        return match ($this) {
            self::Low => 3,
            self::Medium => 2,
            self::High => 1,
            self::Critical => 0,
        };
    }

    /**
     * Ordering weight — higher means more severe.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Medium => 2,
            self::High => 3,
            self::Critical => 4,
        };
    }

    /**
     * Check if this is a high risk rating.
     */
    public function isHigh(): bool
    {
        return $this === self::High;
    }

    /**
     * Check if this is a low risk rating.
     */
    public function isLow(): bool
    {
        return $this === self::Low;
    }

    /**
     * Check if this is a medium risk rating.
     */
    public function isMedium(): bool
    {
        return $this === self::Medium;
    }

    /**
     * Get a human-readable label for the rating.
     */
    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low Risk',
            self::Medium => 'Medium Risk',
            self::High => 'High Risk',
            self::Critical => 'Critical Risk',
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
            self::Critical => 'danger',
        };
    }
}
