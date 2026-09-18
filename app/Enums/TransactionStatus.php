<?php

namespace App\Enums;

/**
 * Transaction Status Enum
 *
 * Represents the various statuses a transaction can have in its lifecycle.
 * Implements a 12-state state machine (10 active + 2 legacy) for currency exchange transactions.
 */
enum TransactionStatus: string
{
    /**
     * Legacy state — retained only so historical rows deserialize. Absent
     * from TransactionStateMachine::TRANSITIONS; no code path creates it.
     */
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Processing = 'processing';
    case Completed = 'completed';
    case Finalized = 'finalized';
    case Cancelled = 'cancelled';
    case Reversed = 'reversed';
    case Failed = 'failed';
    case Rejected = 'rejected';
    case PendingCancellation = 'pending_cancellation';

    /**
     * Legacy states — retained only so historical rows deserialize. They are
     * absent from TransactionStateMachine::TRANSITIONS: no code path may
     * create or transition into them. Holds map to PendingApproval instead of
     * OnHold; there is no Draft intake and no Completed -> Finalized sweep.
     */
    case Pending = 'pending';
    case OnHold = 'on_hold';

    /**
     * Check if the transaction is in draft state.
     * Initial state, transaction being created, not yet submitted.
     */
    public function isDraft(): bool
    {
        return $this === self::Draft;
    }

    /**
     * Check if the transaction is pending approval.
     * Submitted and awaiting approval based on amount/role rules.
     */
    public function isPendingApproval(): bool
    {
        return $this === self::PendingApproval;
    }

    /**
     * Check if the transaction has been approved.
     * Approved and ready for processing.
     */
    public function isApproved(): bool
    {
        return $this === self::Approved;
    }

    /**
     * Check if the transaction is currently being processed.
     * Stock movements, accounting, compliance are running.
     */
    public function isProcessing(): bool
    {
        return $this === self::Processing;
    }

    /**
     * Check if the transaction was completed successfully.
     * All side effects completed.
     */
    public function isCompleted(): bool
    {
        return $this === self::Completed;
    }

    /**
     * Check if the transaction has been finalized.
     * Day-end processed, cannot be modified.
     */
    public function isFinalized(): bool
    {
        return $this === self::Finalized;
    }

    /**
     * Check if the transaction was cancelled.
     * Cancelled before completion.
     */
    public function isCancelled(): bool
    {
        return $this === self::Cancelled;
    }

    /**
     * Check if the transaction was reversed.
     * Reversed after completion with compensating transactions.
     */
    public function isReversed(): bool
    {
        return $this === self::Reversed;
    }

    /**
     * Check if the transaction processing failed.
     * Processing failed, awaiting recovery.
     */
    public function isFailed(): bool
    {
        return $this === self::Failed;
    }

    /**
     * Check if the transaction was rejected.
     * Rejected during approval (distinct from cancelled).
     */
    public function isRejected(): bool
    {
        return $this === self::Rejected;
    }

    /**
     * Check if the transaction is pending cancellation.
     * Awaiting supervisor approval for cancellation.
     */
    public function isPendingCancellation(): bool
    {
        return $this === self::PendingCancellation;
    }

    /**
     * Check if the transaction is in a final state.
     * Final states cannot be changed without special handling.
     */
    public function isFinal(): bool
    {
        return in_array($this, [
            self::Completed,
            self::Finalized,
            self::Cancelled,
            self::Reversed,
            self::Rejected,
        ], true);
    }

    /**
     * Check if the transaction is in a pending state.
     * Pending states are awaiting some action.
     */
    public function isPending(): bool
    {
        return in_array($this, [
            self::PendingApproval,
            self::Approved,
            self::Processing,
            self::PendingCancellation,
        ], true);
    }

    /**
     * Check if the transaction is on hold (legacy support).
     */
    public function isOnHold(): bool
    {
        return $this === self::OnHold;
    }

    /**
     * Get a human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingApproval => 'Pending Approval',
            self::Approved => 'Approved',
            self::Processing => 'Processing',
            self::Completed => 'Completed',
            self::Finalized => 'Finalized',
            self::Cancelled => 'Cancelled',
            self::Reversed => 'Reversed',
            self::Failed => 'Failed',
            self::Rejected => 'Rejected',
            self::Pending => 'Pending Approval', // Legacy
            self::OnHold => 'On Hold', // Legacy
            self::PendingCancellation => 'Pending Cancellation',
        };
    }

    /**
     * Get the color class for UI display.
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::PendingApproval => 'warning',
            self::Approved => 'info',
            self::Processing => 'primary',
            self::Completed => 'success',
            self::Finalized => 'success',
            self::Cancelled => 'secondary',
            self::Reversed => 'danger',
            self::Failed => 'danger',
            self::Rejected => 'danger',
            self::Pending => 'warning', // Legacy
            self::OnHold => 'danger', // Legacy
            self::PendingCancellation => 'warning',
        };
    }
}
