<?php

namespace App\Enums;

/**
 * Case Resolution Enum
 *
 * Represents the possible resolutions for a compliance case.
 */
enum CaseResolution: string
{
    case NoConcern = 'NoConcern';
    case WarningIssued = 'WarningIssued';
    case EddRequired = 'EddRequired';
    case StrFiled = 'StrFiled';
    case ClosedNoAction = 'ClosedNoAction';

    /**
     * Get a human-readable label for the resolution.
     */
    public function label(): string
    {
        return match ($this) {
            self::NoConcern => 'No Concern',
            self::WarningIssued => 'Warning Issued',
            self::EddRequired => 'EDD Required',
            self::StrFiled => 'STR Filed',
            self::ClosedNoAction => 'Closed - No Action',
        };
    }

    /**
     * Check if this resolution requires an STR to be filed.
     */
    public function requiresStr(): bool
    {
        return $this === self::StrFiled;
    }

    /**
     * Check if this resolution requires an EDD record to be created.
     */
    public function requiresEdd(): bool
    {
        return $this === self::EddRequired;
    }
}
