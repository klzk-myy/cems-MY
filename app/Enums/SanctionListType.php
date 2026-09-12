<?php

namespace App\Enums;

enum SanctionListType: string
{
    case MOHA = 'MOHA';
    case UNSCR = 'UNSCR';
    case Domestic = 'Domestic';
    case Internal = 'Internal';

    public function label(): string
    {
        return match ($this) {
            self::MOHA => 'MOHA',
            self::UNSCR => 'UNSCR',
            self::Domestic => 'Domestic',
            self::Internal => 'Internal',
        };
    }
}
