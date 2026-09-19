<?php

namespace App\Services\Transaction;

use App\Models\Transaction;
use App\Services\Contracts\TransactionIdempotencyServiceInterface;
use Carbon\Carbon;

class TransactionIdempotencyService implements TransactionIdempotencyServiceInterface
{
    /**
     * Find an existing transaction by idempotency key.
     *
     * Extracted from TransactionService::createTransaction() lines 311-316.
     */
    public function findDuplicate(?string $idempotencyKey, int $userId, array $data): ?Transaction
    {
        if (! empty($idempotencyKey)) {
            $existingByKey = Transaction::where('idempotency_key', $idempotencyKey)
                ->where('user_id', $userId) // Keys are only unique per user
                ->lockForUpdate() // Prevent concurrent duplicate creation
                ->first();
            if ($existingByKey) {
                return $existingByKey;
            }
        }

        return null;
    }

    /**
     * Check for a recent duplicate transaction (potential double-submit).
     *
     * Extracted from TransactionService::createTransaction() lines 318-341.
     * Checks within a configurable time window (default 30 seconds) BEFORE acquiring position lock.
     *
     * @param  array  $data  Must contain 'currency_code', 'type', 'quantity'
     */
    public function checkRecentDuplicate(int $userId, array $data, int $windowSeconds = 30): ?Transaction
    {
        $recentWindow = Carbon::now()->subSeconds($windowSeconds);

        $recentAmount = Transaction::where('user_id', $userId)
            ->where('created_at', '>=', $recentWindow)
            ->where('quantity', $data['quantity'])
            ->where('currency_code', $data['currency_code'])
            ->where('type', $data['type'])
            ->lockForUpdate()
            ->first();

        return $recentAmount;
    }
}
