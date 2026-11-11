<?php

namespace App\Enums;

enum BiAlertComparison: string
{
    case Lt = 'lt';
    case Lte = 'lte';
    case Gt = 'gt';
    case Gte = 'gte';

    public function label(): string
    {
        return match ($this) {
            self::Lt => 'below',
            self::Lte => 'at or below',
            self::Gt => 'above',
            self::Gte => 'at or above',
        };
    }

    /**
     * Whether a bccomp() result (-1/0/1) breaches the threshold.
     */
    public function breaches(int $cmp): bool
    {
        return match ($this) {
            self::Lt => $cmp < 0,
            self::Lte => $cmp <= 0,
            self::Gt => $cmp > 0,
            self::Gte => $cmp >= 0,
        };
    }
}
