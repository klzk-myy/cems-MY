<?php

namespace App\Enums;

/**
 * Stock Transfer Status Enum
 *
 * Represents the various statuses in the stock transfer workflow.
 */
enum StockTransferStatus: string
{
    case Requested = 'Requested';
    case BranchManagerApproved = 'BranchManagerApproved';
    case HqApproved = 'HqApproved';
    case InTransit = 'InTransit';
    case PartiallyReceived = 'PartiallyReceived';
    case Completed = 'Completed';
    case Cancelled = 'Cancelled';
    case Rejected = 'Rejected';

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Requested',
            self::BranchManagerApproved => 'Branch Manager Approved',
            self::HqApproved => 'HQ Approved',
            self::InTransit => 'In Transit',
            self::PartiallyReceived => 'Partially Received',
            self::Completed => 'Completed',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Get the color class for the status badge.
     */
    public function color(): string
    {
        return match ($this) {
            self::Requested => 'badge-warning',
            self::BranchManagerApproved => 'badge-info',
            self::HqApproved => 'badge-success',
            self::InTransit => 'badge-primary',
            self::PartiallyReceived => 'badge-info',
            self::Completed => 'badge-success',
            self::Rejected => 'badge-danger',
            self::Cancelled => 'badge-secondary',
        };
    }
}
