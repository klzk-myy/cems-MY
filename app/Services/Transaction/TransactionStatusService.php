<?php

namespace App\Services\Transaction;

use App\Models\Transaction;

class TransactionStatusService
{
    public function __construct(
        protected TransactionReversalService $reversalService
    ) {}

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

        if (! $this->reversalService->isWithinCancellationWindow($transaction)) {
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
