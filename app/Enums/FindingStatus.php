<?php

namespace App\Enums;

/**
 * Finding Status Enum
 *
 * Represents the different statuses a compliance finding can have.
 */
enum FindingStatus: string
{
    case New = 'New';
    case Reviewed = 'Reviewed';
    case Dismissed = 'Dismissed';
    case CaseCreated = 'Case_Created';

    /**
     * Check if the finding can be reviewed.
     */
    public function canBeReviewed(): bool
    {
        return $this === self::New;
    }

    /**
     * Check if the finding can be dismissed.
     */
    public function canBeDismissed(): bool
    {
        return in_array($this, [self::New, self::Reviewed], true);
    }

    /**
     * Check if a case can be created from this finding.
     */
    public function canCreateCase(): bool
    {
        return in_array($this, [self::New, self::Reviewed], true);
    }

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Reviewed => 'Reviewed',
            self::Dismissed => 'Dismissed',
            self::CaseCreated => 'Case Created',
        };
    }
}
