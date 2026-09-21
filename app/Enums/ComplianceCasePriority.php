<?php

namespace App\Enums;

/**
 * Compliance Case Priority Enum
 *
 * Represents the priority level of a compliance case.
 */
enum ComplianceCasePriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    /**
     * SLA hours for this priority. Mirrors AlertPriority::slaHours() so alert-
     * derived cases and manually created cases apply the same deadline policy.
     */
    public function slaHours(): int
    {
        return match ($this) {
            self::Critical => 4,
            self::High => 8,
            self::Medium => 24,
            self::Low => 72,
        };
    }

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
            self::Low => 'info',
            self::Medium => 'warning',
            self::High => 'danger',
            self::Critical => 'dark',
        };
    }

    /**
     * Get a human-readable label for the priority.
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
