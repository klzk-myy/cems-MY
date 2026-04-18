<?php

namespace App\Enums;

enum CtosStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Acknowledged = 'acknowledged';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::Acknowledged => 'Acknowledged',
            self::Rejected => 'Rejected',
        };
    }
}
