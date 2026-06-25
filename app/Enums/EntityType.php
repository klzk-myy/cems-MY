<?php

namespace App\Enums;

enum EntityType: string
{
    case Individual = 'Individual';
    case Organization = 'Organization';
    case Vessel = 'Vessel';
    case Aircraft = 'Aircraft';

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Individual',
            self::Organization => 'Organization',
            self::Vessel => 'Vessel',
            self::Aircraft => 'Aircraft',
        };
    }
}
