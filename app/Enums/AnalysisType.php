<?php

namespace App\Enums;

enum AnalysisType: string
{
    case Sanction = 'sanction';
    case Pep = 'pep';
    case Risk = 'risk';
    case RelatedPartyDueDiligence = 'related_party_due_diligence';

    public function label(): string
    {
        return match ($this) {
            self::Sanction => 'Sanction',
            self::Pep => 'PEP',
            self::Risk => 'Risk',
            self::RelatedPartyDueDiligence => 'Related Party Due Diligence',
        };
    }
}
