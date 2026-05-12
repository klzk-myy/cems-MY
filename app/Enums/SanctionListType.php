<?php

namespace App\Enums;

enum SanctionListType: string
{
    case MOHA = 'MOHA';
    case UNSCR = 'UNSCR';
    case Internal = 'Internal';

    public function label(): string
    {
        return match ($this) {
            self::MOHA => 'MOHA',
            self::UNSCR => 'UNSCR',
            self::Internal => 'Internal',
        };
    }
}
