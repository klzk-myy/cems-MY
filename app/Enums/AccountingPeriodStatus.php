<?php

namespace App\Enums;

enum AccountingPeriodStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Locked = 'locked';

    public function isOpen(): bool
    {
        return $this === self::Open;
    }

    public function isClosed(): bool
    {
        return $this === self::Closed;
    }

    public function isLocked(): bool
    {
        return $this === self::Locked;
    }

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Closed => 'Closed',
            self::Locked => 'Locked',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'success',
            self::Closed => 'secondary',
            self::Locked => 'dark',
        };
    }
}
