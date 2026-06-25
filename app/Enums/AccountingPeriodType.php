<?php

namespace App\Enums;

enum AccountingPeriodType: string
{
    case Month = 'month';
    case Quarter = 'quarter';
    case Year = 'year';

    public function label(): string
    {
        return match ($this) {
            self::Month => 'Month',
            self::Quarter => 'Quarter',
            self::Year => 'Year',
        };
    }
}
