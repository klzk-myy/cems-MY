<?php

namespace App\Enums;

enum HighRiskCountryRiskLevel: string
{
    case High = 'High';
    case Grey = 'Grey';

    public function isHigh(): bool
    {
        return $this === self::High;
    }

    public function isGrey(): bool
    {
        return $this === self::Grey;
    }

    public function label(): string
    {
        return match ($this) {
            self::High => 'High Risk',
            self::Grey => 'Grey List',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::High => 'danger',
            self::Grey => 'warning',
        };
    }
}
