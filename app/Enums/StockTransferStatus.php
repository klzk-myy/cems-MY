<?php

namespace App\Enums;

/**
 * Stock Transfer Status Enum
 *
 * Represents the various statuses in the stock transfer workflow.
 */
enum StockTransferStatus: string
{
    case Requested = 'requested';
    case BranchManagerApproved = 'branch_manager_approved';
    case HqApproved = 'hq_approved';
    case InTransit = 'in_transit';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';

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
            self::Received => 'Received',
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
            self::Received => 'badge-success',
            self::Completed => 'badge-success',
            self::Rejected => 'badge-danger',
            self::Cancelled => 'badge-secondary',
        };
    }
}
