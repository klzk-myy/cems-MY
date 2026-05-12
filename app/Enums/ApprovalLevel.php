<?php

namespace App\Enums;

enum ApprovalLevel: string
{
    case Level1 = 'level1';
    case Level2 = 'level2';
    case Level3 = 'level3';

    public function label(): string
    {
        return match ($this) {
            self::Level1 => 'Level 1',
            self::Level2 => 'Level 2',
            self::Level3 => 'Level 3',
        };
    }
}
