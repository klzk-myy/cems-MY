<?php

namespace App\Enums;

/**
 * Canonical lifecycle vocabulary for the unified alerts+findings inbox.
 * The two underlying tables keep their own enums (FlagStatus on alerts,
 * FindingStatus on compliance_findings); this enum is the single filter
 * contract exposed to the UI and mapped downstream by
 * UnifiedAlertQueryService.
 */
enum UnifiedAlertStatus: string
{
    case Open = 'open';
    case InReview = 'in_review';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::InReview => 'In Review',
            self::Resolved => 'Resolved',
            self::Dismissed => 'Dismissed',
        };
    }
}
