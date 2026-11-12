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
     * Ordinal severity used when comparing levels — never rely on declaration
     * order for this.
     */
    public function severity(): int
    {
        return match ($this) {
            self::Simplified => 0,
            self::Specific => 1,
            self::Standard => 2,
            self::Enhanced => 3,
        };
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
