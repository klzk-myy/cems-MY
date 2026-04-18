<?php

namespace App\Enums;

enum ReportStatus: string
{
    case Scheduled = 'scheduled';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Pending = 'Pending';
    case Generated = 'Generated';
    case Submitted = 'Submitted';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::Running => 'Running',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Scheduled => 'blue',
            self::Running => 'yellow',
            self::Completed => 'green',
            self::Failed => 'red',
        };
    }
}
