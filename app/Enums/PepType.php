<?php

namespace App\Enums;

/**
 * Politically Exposed Person (PEP) Type Enum
 *
 * Distinguishes between different types of PEPs per pd-00.md 15.2 and 15.3:
 * - Foreign PEPs: Foreign politicians and their associates (15.2) - require Enhanced CDD always
 * - Domestic PEPs: Local politicians and their associates (15.3) - risk-based Enhanced CDD
 * - International Organisation: International org officials
 * - Family Member: Immediate family members of PEPs
 * - Close Associate: Known close associates of PEPs
 */
enum PepType: string
{
    case Foreign = 'foreign';
    case Domestic = 'domestic';
    case InternationalOrg = 'international_organisation';
    case FamilyMember = 'family_member';
    case CloseAssociate = 'close_associate';

    /**
     * Check if this PEP type requires enhanced CDD always (per pd-00.md 15.2).
     */
    public function requiresEnhancedCddAlways(): bool
    {
        return match ($this) {
            self::Foreign => true,
            self::Domestic, self::InternationalOrg, self::FamilyMember, self::CloseAssociate => false,
        };
    }

    /**
     * Check if this PEP type requires risk-based enhanced CDD assessment.
     */
    public function requiresRiskBasedEnhancedCdd(): bool
    {
        return match ($this) {
            self::Foreign => false, // Already handled by requiresEnhancedCddAlways
            self::Domestic, self::InternationalOrg, self::FamilyMember, self::CloseAssociate => true,
        };
    }

    /**
     * Get a human-readable label for the PEP type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Foreign => 'Foreign PEP',
            self::Domestic => 'Domestic PEP',
            self::InternationalOrg => 'International Organisation PEP',
            self::FamilyMember => 'PEP Family Member',
            self::CloseAssociate => 'PEP Close Associate',
        };
    }
}
