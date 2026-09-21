<?php

namespace App\Enums;

enum AllocationDirection: string
{
    case Increase = 'increase';
    case Decrease = 'decrease';

    public function label(): string
    {
        return match ($this) {
            self::Increase => 'Increase',
            self::Decrease => 'Decrease',
        };
    }
}
