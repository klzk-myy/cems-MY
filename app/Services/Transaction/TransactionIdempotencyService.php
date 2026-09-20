<?php

namespace App\Services\Transaction;

use App\Exceptions\Domain\DuplicateTransactionException;
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
            // idempotency_key is globally unique (schema constraint, request
            // validators and TransactionImportService all agree) — the lookup
            // must be global too or a cross-user key reuse silently passes
            // the pre-check and dies on the unique index at insert.
            $existingByKey = Transaction::where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existingByKey) {
                // A key replayed by a different user is a conflict, not an
                // idempotent hit — never hand back someone else's record.
                if ((int) $existingByKey->user_id !== $userId) {
                    throw new DuplicateTransactionException;
                }

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
