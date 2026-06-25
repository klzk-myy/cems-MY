<?php

namespace App\Enums;

enum ReportRunStatus: string
{
    case Scheduled = 'scheduled';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    public function isScheduled(): bool
    {
        return $this === self::Scheduled;
    }

    public function isRunning(): bool
    {
        return $this === self::Running;
    }

    public function isCompleted(): bool
    {
        return $this === self::Completed;
    }

    public function isFailed(): bool
    {
        return $this === self::Failed;
    }

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
