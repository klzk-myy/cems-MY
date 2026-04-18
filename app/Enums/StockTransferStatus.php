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
    case Rejected = 'Rejected';
    case Cancelled = 'Cancelled';

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Requested',
            self::BranchManagerApproved => 'Branch Manager Approved',
            self::HqApproved => 'HQ Approved',
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
            self::Rejected => 'badge-danger',
            self::Cancelled => 'badge-secondary',
        };
    }
}
