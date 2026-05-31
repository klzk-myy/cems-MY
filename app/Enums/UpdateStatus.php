<?php

namespace App\Enums;

enum UpdateStatus: string
{
    case NeverRun = 'never_run';
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::NeverRun => 'Never Run',
            self::Pending => 'Pending',
            self::Success => 'Success',
            self::Failed => 'Failed',
        };
    }
}
