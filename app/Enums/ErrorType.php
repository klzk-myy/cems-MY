<?php

namespace App\Enums;

enum ErrorType: string
{
    case Validation = 'validation';
    case Processing = 'processing';
    case System = 'system';
    case Data = 'data';

    public function label(): string
    {
        return match ($this) {
            self::Validation => 'Validation',
            self::Processing => 'Processing',
            self::System => 'System',
            self::Data => 'Data',
        };
    }
}
