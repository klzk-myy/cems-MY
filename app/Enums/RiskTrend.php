<?php

namespace App\Enums;

enum RiskTrend: string
{
    case Improving = 'improving';
    case Stable = 'stable';
    case Deteriorating = 'deteriorating';

    public function label(): string
    {
        return match ($this) {
            self::Improving => 'Improving',
            self::Stable => 'Stable',
            self::Deteriorating => 'Deteriorating',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Improving => 'trending-up',
            self::Stable => 'minus',
            self::Deteriorating => 'trending-down',
        };
    }
}
