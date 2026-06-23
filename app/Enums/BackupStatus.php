<?php

namespace App\Enums;

enum BackupStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Verified = 'verified';
    case VerificationFailed = 'verification_failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Running => 'Running',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::Verified => 'Verified',
            self::VerificationFailed => 'Verification Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'yellow',
            self::Running => 'blue',
            self::Completed => 'green',
            self::Failed => 'red',
            self::Verified => 'green',
            self::VerificationFailed => 'red',
        };
    }

    public function isSuccessful(): bool
    {
        return in_array($this, [self::Completed, self::Verified]);
    }
}
