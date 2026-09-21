<?php

namespace App\Enums;

use App\Helpers\Thresholdable;

/**
 * Customer Due Diligence (CDD) Level Enum
 *
 * Represents the different levels of due diligence required
 * based on transaction amount and customer risk profile.
 */
enum CddLevel: string
{
    use Thresholdable;

    case Simplified = 'Simplified';
    case Specific = 'Specific';
    case Standard = 'Standard';
    case Enhanced = 'Enhanced';

    /**
     * Determine CDD level based on amount and customer risk.
     *
     * @param  string  $amountMyr  Transaction amount in MYR
     * @param  bool  $isPep  Whether customer is a Politically Exposed Person
     * @param  bool  $hasSanctionMatch  Whether customer matches sanctions list
     * @param  RiskRating|string  $riskRating  Customer risk rating
     */
    public static function determine(
        string $amountMyr,
        bool $isPep = false,
        bool $hasSanctionMatch = false,
        RiskRating|string $riskRating = 'low'
    ): self {
        // Handle enum or string
        $riskRatingValue = $riskRating instanceof RiskRating
            ? $riskRating->value
            : strtolower($riskRating);

        // Enhanced Due Diligence triggers (risk-based, not amount-based per pd-00.md 14C.13)
        if ($isPep || $hasSanctionMatch || $riskRatingValue === RiskRating::High->value) {
            return self::Enhanced;
        }

        if (! is_numeric($amountMyr)) {
            throw new \InvalidArgumentException('Transaction amount must be numeric.');
        }

        /** @var numeric-string $amountMyr */
        // Amount thresholds per pd-00.md 14C.12
        // >= RM 10,000: Standard CDD
        $standardThreshold = self::getStandardCddThreshold();

        if (! is_numeric($standardThreshold)) {
            throw new \LogicException('Configured standard CDD threshold must be numeric.');
        }

        if (bccomp($amountMyr, $standardThreshold) >= 0) {
            return self::Standard;
        }

        // RM 3,000 - 10,000: Specific CDD
        $specificThreshold = self::getSpecificCddThreshold();

        if (! is_numeric($specificThreshold)) {
            throw new \LogicException('Configured specific CDD threshold must be numeric.');
        }

        if (bccomp($amountMyr, $specificThreshold) >= 0) {
            return self::Specific;
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
        return in_array($this, [self::Specific, self::Standard, self::Enhanced], true);
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
        $specific = self::getSpecificCddThreshold();
        $standard = self::getStandardCddThreshold();

        return match ($this) {
            self::Simplified => "< RM {$specific}",
            self::Specific => "RM {$specific} - ".bcsub($standard, '1'),
            self::Standard => "≥ RM {$standard}",
            self::Enhanced => 'Risk-based',
        };
    }

    /**
     * Get a human-readable label for the level.
     */
    public function label(): string
    {
        return match ($this) {
            self::Simplified => 'Simplified Due Diligence',
            self::Specific => 'Specific Due Diligence',
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
            self::Specific => 'info',
            self::Standard => 'info',
            self::Enhanced => 'warning',
        };
    }
}
