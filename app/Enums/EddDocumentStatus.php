<?php

namespace App\Enums;

/**
 * EDD Document Request Status Enum
 */
enum EddDocumentStatus: string
{
    case Pending = 'Pending';
    case Received = 'Received';
    case Verified = 'Verified';
    case Rejected = 'Rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Received => 'Received',
            self::Verified => 'Verified',
            self::Rejected => 'Rejected',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'secondary',
            self::Received => 'info',
            self::Verified => 'success',
            self::Rejected => 'danger',
        };
    }
}
