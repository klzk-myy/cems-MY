<?php

namespace App\Enums;

enum TellerAllocationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Active = 'active';
    case Returned = 'returned';
    case Closed = 'closed';
    case AutoReturned = 'auto_returned';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    public function isApproved(): bool
    {
        return $this === self::Approved;
    }

    public function isActive(): bool
    {
        return $this === self::Active;
    }

    public function isReturned(): bool
    {
        return $this === self::Returned;
    }

    public function isClosed(): bool
    {
        return $this === self::Closed;
    }

    public function isAutoReturned(): bool
    {
        return $this === self::AutoReturned;
    }

    public function isRejected(): bool
    {
        return $this === self::Rejected;
    }

    public function isCancelled(): bool
    {
        return $this === self::Cancelled;
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Active => 'Active',
            self::Returned => 'Returned',
            self::Closed => 'Closed',
            self::AutoReturned => 'Auto Returned',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
        };
    }
}
