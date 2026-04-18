<?php

namespace App\Enums;

/**
 * Finding Severity Enum
 *
 * Represents the severity level of compliance findings.
 */
enum FindingSeverity: string
{
    case Low = 'Low';
    case Medium = 'Medium';
    case High = 'High';
    case Critical = 'Critical';

    /**
     * Get the weight value for ordering (higher = more severe).
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
     * Get the Bootstrap color class for UI display.
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

    /**
     * Get the icon class for UI display.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Low => 'fa-info-circle text-success',
            self::Medium => 'fa-exclamation-triangle text-warning',
            self::High => 'fa-exclamation-circle text-danger',
            self::Critical => 'fa-skull-crossbones text-dark',
        };
    }

    /**
     * Get a human-readable label for the severity.
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
}
