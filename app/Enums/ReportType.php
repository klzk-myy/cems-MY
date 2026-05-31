<?php

namespace App\Enums;

enum ReportType: string
{
    case Msb2 = 'msb2';
    case Lmca = 'lmca';
    case Qlvr = 'qlvr';

    public function label(): string
    {
        return match ($this) {
            self::Msb2 => 'MSB2',
            self::Lmca => 'LMCA',
            self::Qlvr => 'QLVR',
        };
    }
}
