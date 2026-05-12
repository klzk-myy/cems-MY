<?php

namespace App\Enums;

enum ReportType: string
{
    case Msb2 = 'msb2';
    case Lctr = 'lctr';
    case Lmca = 'lmca';
    case Qlvr = 'qlvr';
    case Str = 'str';
    case Ctos = 'ctos';
    case Edd = 'edd';

    public function label(): string
    {
        return match ($this) {
            self::Msb2 => 'MSB2',
            self::Lctr => 'LCTR',
            self::Lmca => 'LMCA',
            self::Qlvr => 'QLVR',
            self::Str => 'STR',
            self::Ctos => 'CTOS',
            self::Edd => 'EDD',
        };
    }
}
