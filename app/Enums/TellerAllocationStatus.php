<?php

namespace App\Enums;

enum TellerAllocationStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case ACTIVE = 'active';
    case RETURNED = 'returned';
    case CLOSED = 'closed';
    case AUTO_RETURNED = 'auto_returned';
    case REJECTED = 'rejected';

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isApproved(): bool
    {
        return $this === self::APPROVED;
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function isReturned(): bool
    {
        return $this === self::RETURNED;
    }

    public function isClosed(): bool
    {
        return $this === self::CLOSED;
    }

    public function isAutoReturned(): bool
    {
        return $this === self::AUTO_RETURNED;
    }

    public function isRejected(): bool
    {
        return $this === self::REJECTED;
    }

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::APPROVED => 'Approved',
            self::ACTIVE => 'Active',
            self::RETURNED => 'Returned',
            self::CLOSED => 'Closed',
            self::AUTO_RETURNED => 'Auto Returned',
            self::REJECTED => 'Rejected',
        };
    }
}
