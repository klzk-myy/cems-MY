<?php

namespace App\Enums;

/**
 * Customer Due Diligence (CDD) Level Enum
 *
 * Represents the different levels of due diligence required
 * based on transaction amount and customer risk profile.
 */
enum CddLevel: string
{
    case Simplified = 'Simplified';
    case Standard = 'Standard';
    case Enhanced = 'Enhanced';

    /**
     * Determine CDD level based on amount and customer risk.
     *
     * @param  string  $amount  Transaction amount in MYR
     * @param  bool  $isPep  Whether customer is a Politically Exposed Person
     * @param  bool  $hasSanctionMatch  Whether customer matches sanctions list
     * @param  string  $riskRating  Customer risk rating ('Low', 'Medium', 'High')
     */
    public static function determine(
        string $amount,
        bool $isPep = false,
        bool $hasSanctionMatch = false,
        string $riskRating = 'Low'
    ): self {
        // Enhanced Due Diligence triggers
        if ($isPep || $hasSanctionMatch) {
            return self::Enhanced;
        }

        // Amount thresholds (using string comparison for precision)
        if (bccomp($amount, '50000') >= 0 || $riskRating === 'High') {
            return self::Enhanced;
        }

        if (bccomp($amount, '3000') >= 0) {
            return self::Standard;
        }

        return self::Simplified;
    }

    /**
     * Check if this is simplified due diligence.
     */
    public function isSimplified(): bool
    {
        return $this === self::Simplified;
    }

    /**
     * Check if this is standard due diligence.
     */
    public function isStandard(): bool
    {
        return $this === self::Standard;
    }

    /**
     * Check if this is enhanced due diligence.
     */
    public function isEnhanced(): bool
    {
        return $this === self::Enhanced;
    }

    /**
     * Check if additional documentation is required.
     */
    public function requiresAdditionalDocs(): bool
    {
        return in_array($this, [self::Standard, self::Enhanced], true);
    }

    /**
     * Check if enhanced screening is required.
     */
    public function requiresEnhancedScreening(): bool
    {
        return $this === self::Enhanced;
    }

    /**
     * Check if approval is required for this CDD level.
     */
    public function requiresApproval(): bool
    {
        return $this === self::Enhanced;
    }

    /**
     * Get the threshold amount for this level.
     */
    public function thresholdAmount(): string
    {
        return match ($this) {
            self::Simplified => '< RM 3,000',
            self::Standard => 'RM 3,000 - RM 49,999',
            self::Enhanced => '≥ RM 50,000',
        };
    }

    /**
     * Get a human-readable label for the level.
     */
    public function label(): string
    {
        return match ($this) {
            self::Simplified => 'Simplified Due Diligence',
            self::Standard => 'Standard Due Diligence',
            self::Enhanced => 'Enhanced Due Diligence',
        };
    }

    /**
     * Get the color class for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::Simplified => 'success',
            self::Standard => 'info',
            self::Enhanced => 'warning',
        };
    }
}
