<?php

namespace App\Enums;

enum CustomerIdType: string
{
    case MyKad = 'MyKad';
    case Passport = 'Passport';
    case Others = 'Others';

    public function label(): string
    {
        return match ($this) {
            self::MyKad => 'MyKad',
            self::Passport => 'Passport',
            self::Others => 'Others',
        };
    }
}
