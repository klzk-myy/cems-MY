<?php

namespace App\Services\Transaction;

use App\Enums\ErrorType;
use App\Models\Transaction;
use App\Models\TransactionError;
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

        // Create error record. Carry the previous retry count forward so the
        // max-retries limit is respected across attempts. Starting every record
        // at 0 (then incrementing to 1) means canRetry() is always true and the
        // transaction retries forever without ever reaching the DLQ.
        $error = TransactionError::create([
            'transaction_id' => $transaction->id,
            'error_type' => $errorType,
            'error_message' => $message,
            'error_context' => $context,
            'retry_count' => $latestError->retry_count ?? 0,
            'max_retries' => self::DEFAULT_MAX_RETRIES,
            'next_retry_at' => now(),
        ]);

        // Increment retry count and set next retry delay (exponential backoff
        // based on the carried-forward count: 100ms, 200ms, 400ms)
        if ($error->canRetry()) {
            $delay = $this->getDelayForRetryCount($error->retry_count);
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
     * Get the latest unresolved error for a transaction.
     *
     * Uses the eager-loaded collection when available to avoid extra queries,
     * falling back to the relationship query builder otherwise.
     *
     * @param  Transaction  $transaction  The transaction
     * @return TransactionError|null The latest unresolved error, or null if none exists
     */
    protected function getLatestError(Transaction $transaction): ?TransactionError
    {
        // Order by id (not created_at): multiple failures within the same second
        // tie on created_at, and picking an arbitrary row breaks retry accounting.
        if ($transaction->relationLoaded('transactionErrors')) {
            $latest = $transaction->transactionErrors
                ->whereNull('resolved_at')
                ->sortByDesc('id')
                ->first();

            assert($latest instanceof TransactionError || $latest === null);

            return $latest;
        }

        return $transaction->transactionErrors()
            ->whereNull('resolved_at')
            ->orderByDesc('id')
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

        // Also move to DLQ for non-retryable error types.
        // error_type is cast to the ErrorType enum, so compare enum values rather
        // than the raw string constants (a strict in_array on the enum object
        // against strings never matches).
        if (! $latestError->error_type->isRetryable()) {
            return true;
        }

        return false;
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

        return $latestError->retry_count ?? 0;
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
