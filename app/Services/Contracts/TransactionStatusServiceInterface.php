<?php

namespace App\Services\Contracts;

use App\Models\Transaction;

interface TransactionStatusServiceInterface
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
    public function isRefundable(Transaction $transaction): bool;

    /**
     * Determine if a transaction has been cancelled.
     */
    public function isCancelled(Transaction $transaction): bool;
}
