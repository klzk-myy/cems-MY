<?php

namespace App\Enums;

enum TestResultStatus: string
{
    case Passed = 'passed';
    case Failed = 'failed';
    case Error = 'error';
    case Running = 'running';

    public function label(): string
    {
        return match ($this) {
            self::Passed => 'Passed',
            self::Failed => 'Failed',
            self::Error => 'Error',
            self::Running => 'Running',
        };
    }
}
