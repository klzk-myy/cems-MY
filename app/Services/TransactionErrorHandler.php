<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Models\TransactionError;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Transaction Error Handler
 *
 * Handles transaction processing errors with retry logic and dead letter queue management.
 * Implements exponential backoff: 100ms, 200ms, 400ms delays between retries.
 */
class TransactionErrorHandler
{
    /**
     * Default maximum number of retry attempts.
     */
    public const DEFAULT_MAX_RETRIES = 3;

    /**
     * Exponential backoff base delay in milliseconds.
     */
    public const BACKOFF_BASE_MS = 100;

    /**
     * Error types for transaction processing failures.
     */
    public const ERROR_TYPE_PROCESSING = 'processing_error';

    public const ERROR_TYPE_VALIDATION = 'validation_error';

    public const ERROR_TYPE_COMPLIANCE = 'compliance_error';

    public const ERROR_TYPE_ACCOUNTING = 'accounting_error';

    public const ERROR_TYPE_STOCK = 'stock_error';

    public const ERROR_TYPE_NETWORK = 'network_error';

    public const ERROR_TYPE_DEADLOCK = 'deadlock_error';

    public const ERROR_TYPE_TIMEOUT = 'timeout_error';

    /**
     * Handle a transaction processing error.
     *
     * Records the error, increments retry count, and schedules next retry if applicable.
     *
     * @param  Transaction  $transaction  The transaction that encountered an error
     * @param  string  $errorType  Type of error (use ERROR_TYPE_* constants)
     * @param  string  $message  Human-readable error message
     * @param  array  $context  Additional error context (stack trace, variables, etc.)
     * @return bool True if error was handled and can be retried
     */
    public function handleProcessingError(
        Transaction $transaction,
        string $errorType,
        string $message,
        array $context = []
    ): bool {
        Log::error('Transaction processing error', [
            'transaction_id' => $transaction->id,
            'error_type' => $errorType,
            'message' => $message,
            'context' => $context,
        ]);

        // Check if transaction already has an unresolved error with max retries exceeded
        $latestError = $this->getLatestError($transaction);
        if ($latestError !== null && ! $latestError->canRetry()) {
            Log::warning('Transaction already has max retries exceeded', [
                'transaction_id' => $transaction->id,
                'retry_count' => $latestError->retry_count,
            ]);

            return false;
        }

        // Create error record
        $error = TransactionError::create([
            'transaction_id' => $transaction->id,
            'error_type' => $errorType,
            'error_message' => $message,
            'error_context' => $context,
            'retry_count' => 0,
            'max_retries' => self::DEFAULT_MAX_RETRIES,
            'next_retry_at' => now(),
        ]);

        // Increment retry count and set next retry delay
        if ($error->canRetry()) {
            $delay = $this->getDelayForRetryCount(0);
            $error->incrementRetry($delay);

            // Transition transaction to Failed status
            $stateMachine = new TransactionStateMachine($transaction);
            $stateMachine->fail($message);

            return true;
        }

        return false;
    }

    /**
     * Check if a transaction should be retried.
     *
     * @param  Transaction  $transaction  The transaction to check
     * @return bool True if transaction has errors that can be retried
     */
    public function shouldRetry(Transaction $transaction): bool
    {
        $latestError = $this->getLatestError($transaction);

        if ($latestError === null) {
            return false;
        }

        // Can retry if under max retries and not yet time for next retry
        if ($latestError->canRetry()) {
            // If next_retry_at is in the past or now, it's ready for retry
            return $latestError->next_retry_at !== null
                && $latestError->next_retry_at->lte(now());
        }

        return false;
    }

    /**
     * Get the next retry delay in milliseconds for a transaction.
     *
     * Implements exponential backoff: 100ms, 200ms, 400ms
     *
     * @param  Transaction  $transaction  The transaction to check
     * @return int Delay in milliseconds
     */
    public function getNextRetryDelay(Transaction $transaction): int
    {
        $latestError = $this->getLatestError($transaction);

        if ($latestError === null) {
            return 0;
        }

        return $this->getDelayForRetryCount($latestError->retry_count);
    }

    /**
     * Calculate delay for a given retry count using exponential backoff.
     *
     * @param  int  $retryCount  Current retry count (0-based)
     * @return int Delay in milliseconds
     */
    protected function getDelayForRetryCount(int $retryCount): int
    {
        return self::BACKOFF_BASE_MS * pow(2, $retryCount);
    }

    /**
     * Mark an error as resolved.
     *
     * @param  Transaction  $transaction  The transaction with the error
     * @param  int  $resolvedBy  User ID who resolved the error
     * @param  string|null  $notes  Resolution notes
     * @return bool True if resolution was successful
     */
    public function markErrorResolved(
        Transaction $transaction,
        int $resolvedBy,
        ?string $notes = null
    ): bool {
        $latestError = $this->getLatestError($transaction);

        if ($latestError === null) {
            return false;
        }

        $latestError->resolved_at = now();
        $latestError->resolved_by = $resolvedBy;
        $latestError->resolution_notes = $notes;

        return $latestError->save();
    }

    /**
     * Get all errors for a transaction.
     *
     * @param  Transaction  $transaction  The transaction
     * @return Collection Collection of TransactionError models
     */
    public function getTransactionErrors(Transaction $transaction): Collection
    {
        return $transaction->transactionErrors()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get the latest unresolved error for a transaction.
     *
     * @param  Transaction  $transaction  The transaction
     */
    protected function getLatestError(Transaction $transaction): ?TransactionError
    {
        return $transaction->transactionErrors()
            ->whereNull('resolved_at')
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Check if a transaction should be moved to the dead letter queue.
     *
     * A transaction should be moved to DLQ when:
     * - It has exceeded max retries
     * - Or it has an unresolved error that is not retryable
     *
     * @param  Transaction  $transaction  The transaction to check
     * @return bool True if should be moved to DLQ
     */
    public function shouldMoveToDLQ(Transaction $transaction): bool
    {
        // Must be in Failed status
        if (! $transaction->status->isFailed()) {
            return false;
        }

        $latestError = $this->getLatestError($transaction);

        if ($latestError === null) {
            // No unresolved errors, no need for DLQ
            return false;
        }

        // Move to DLQ if max retries exceeded
        if (! $latestError->canRetry()) {
            return true;
        }

        // Also move to DLQ for non-retryable error types
        $nonRetryableTypes = [
            self::ERROR_TYPE_VALIDATION,
            self::ERROR_TYPE_COMPLIANCE,
        ];

        if (in_array($latestError->error_type, $nonRetryableTypes, true)) {
            return true;
        }

        return false;
    }

    /**
     * Check if a transaction has any unresolved errors.
     *
     * @param  Transaction  $transaction  The transaction to check
     * @return bool True if has unresolved errors
     */
    public function hasUnresolvedErrors(Transaction $transaction): bool
    {
        return $transaction->transactionErrors()
            ->whereNull('resolved_at')
            ->exists();
    }

    /**
     * Get the count of retry attempts for a transaction.
     *
     * @param  Transaction  $transaction  The transaction
     * @return int Current retry count
     */
    public function getRetryCount(Transaction $transaction): int
    {
        $latestError = $this->getLatestError($transaction);

        return $latestError?->retry_count ?? 0;
    }

    /**
     * Get all transactions with unresolved errors ready for retry.
     *
     * @return Collection Collection of transactions
     */
    public function getTransactionsReadyForRetry(): Collection
    {
        return Transaction::where('status', TransactionStatus::Failed)
            ->whereHas('transactionErrors', function ($query) {
                $query->whereNull('resolved_at')
                    ->whereColumn('retry_count', '<', 'max_retries')
                    ->where('next_retry_at', '<=', now());
            })
            ->get();
    }

    /**
     * Record a successful retry and reset error tracking.
     *
     * @param  Transaction  $transaction  The transaction that was retried successfully
     * @return bool True if successful
     */
    public function recordSuccessfulRetry(Transaction $transaction): bool
    {
        $latestError = $this->getLatestError($transaction);

        if ($latestError === null) {
            return false;
        }

        // Mark the error as resolved since retry succeeded
        $latestError->resolved_at = now();
        $latestError->resolution_notes = 'Retry successful';

        return $latestError->save();
    }
}
