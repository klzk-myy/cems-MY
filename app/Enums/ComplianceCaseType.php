<?php

namespace App\Enums;

/**
 * Compliance Case Type Enum
 *
 * Represents the different types of compliance cases in the system.
 */
enum ComplianceCaseType: string
{
    case Investigation = 'Investigation';
    case Edd = 'Edd';
    case Str = 'Str';
    case SanctionReview = 'SanctionReview';
    case Counterfeit = 'Counterfeit';

    /**
     * Check if this case type requires a link to an STR.
     */
    public function requiresStrLink(): bool
    {
        return $this === self::Str;
    }

    /**
     * Check if this case type requires a link to an EDD record.
     */
    public function requiresEddLink(): bool
    {
        return $this === self::Edd;
    }

    /**
     * Get the default SLA hours for this case type.
     */
    public function defaultSlaHours(): int
    {
        return match ($this) {
            self::Investigation => 120,
            self::Edd => 72,
            self::Str, self::SanctionReview, self::Counterfeit => 24,
        };
    }

    /**
     * Get a human-readable label for the case type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Investigation => 'Investigation',
            self::Edd => 'Enhanced Due Diligence',
            self::Str => 'Suspicious Transaction Report',
            self::SanctionReview => 'Sanction Review',
            self::Counterfeit => 'Counterfeit',
        };
    }
}
