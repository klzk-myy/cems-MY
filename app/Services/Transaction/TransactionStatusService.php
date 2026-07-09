<?php

namespace App\Services\Transaction;

use App\Models\Transaction;
use App\Services\Contracts\TransactionStatusServiceInterface;

class TransactionStatusService implements TransactionStatusServiceInterface
{
    /**
     * Determine if a transaction is refundable.
     *
     * A transaction is refundable if:
     * - Status is 'Completed'
     * - Not already cancelled
     * - Within the configured cancellation window (default 24 hours)
     * - Not a refund transaction itself
     */
    public function isRefundable(Transaction $transaction): bool
    {
        if (! $transaction->status->isCompleted()) {
            return false;
        }

        if ($transaction->cancelled_at !== null) {
            return false;
        }

        $cancellationWindowHours = config('cems.transaction_cancellation_window_hours', 24);
        if ($transaction->created_at->diffInHours(now()) >= $cancellationWindowHours) {
            return false;
        }

        if ($transaction->is_refund) {
            return false;
        }

        return true;
    }

    /**
     * Determine if a transaction has been cancelled.
     */
    public function isCancelled(Transaction $transaction): bool
    {
        return $transaction->cancelled_at !== null;
    }
}
