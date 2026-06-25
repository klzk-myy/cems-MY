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
    case ClosedNoAction = 'ClosedNoAction';

    public function label(): string
    {
        return match ($this) {
            self::NoConcern => 'No Concern',
            self::WarningIssued => 'Warning Issued',
            self::EddRequired => 'EDD Required',
            self::ClosedNoAction => 'Closed - No Action',
        };
    }

    public function requiresEdd(): bool
    {
        return $this === self::EddRequired;
    }
}
