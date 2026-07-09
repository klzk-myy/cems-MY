<?php

namespace App\Services\Contracts;

use App\Models\Transaction;

interface TransactionIdempotencyServiceInterface
{
    /**
     * Find an existing transaction by idempotency key.
     *
     * @param  string|null  $idempotencyKey  Unique key for duplicate detection
     * @param  int  $userId  User creating the transaction
     * @param  array  $data  Transaction data (for additional context if needed)
     * @return Transaction|null Returns existing transaction or null
     */
    public function findDuplicate(?string $idempotencyKey, int $userId, array $data): ?Transaction;

    /**
     * Check for a recent duplicate transaction (potential double-submit).
     *
     * Looks for transactions by same user with same currency, type, and foreign amount
     * within the last 30 seconds.
     *
     * @param  array  $data  Must contain 'currency_code', 'type', 'amount_foreign'
     * @param  int  $windowSeconds  Time window in seconds (default 30)
     * @return Transaction|null Returns recent duplicate or null
     */
    public function checkRecentDuplicate(int $userId, array $data, int $windowSeconds = 30): ?Transaction;
}
