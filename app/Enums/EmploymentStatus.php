<?php

namespace App\Enums;

enum EmploymentStatus: string
{
    case Employed = 'Employed';
    case SelfEmployed = 'Self-Employed';
    case Unemployed = 'Unemployed';
    case Retired = 'Retired';
    case Student = 'Student';

    public function label(): string
    {
        return match ($this) {
            self::Employed => 'Employed',
            self::SelfEmployed => 'Self-Employed',
            self::Unemployed => 'Unemployed',
            self::Retired => 'Retired',
            self::Student => 'Student',
        };
    }
}
