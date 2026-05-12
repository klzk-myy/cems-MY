<?php

namespace App\Enums;

enum UpdateStatus: string
{
    case NeverRun = 'never_run';
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::NeverRun => 'Never Run',
            self::Pending => 'Pending',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }
}
