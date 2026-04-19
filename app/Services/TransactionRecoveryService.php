<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Jobs\ProcessTransactionRetry;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Transaction Recovery Service
 *
 * Handles recovery of failed transactions through retry and dead letter queue management.
 */
class TransactionRecoveryService
{
    public function __construct(
        protected TransactionErrorHandler $errorHandler
    ) {}

    /**
     * Attempt to recover a failed transaction.
     *
     * If the transaction is ready for retry, dispatches a retry job.
     * If it has exceeded retries, moves it to the dead letter queue.
     *
     * @param  Transaction  $transaction  The transaction to recover
     * @return bool True if recovery was initiated
     */
    public function attemptRecovery(Transaction $transaction): bool
    {
        // Only recover from Failed status
        if (! $transaction->status->isFailed()) {
            Log::warning('Cannot recover transaction - not in Failed status', [
                'transaction_id' => $transaction->id,
                'status' => $transaction->status->value,
            ]);

            return false;
        }

        // Check if should move to DLQ
        if ($this->errorHandler->shouldMoveToDLQ($transaction)) {
            return $this->moveToDeadLetterQueue($transaction);
        }

        // Check if ready for retry
        if ($this->errorHandler->shouldRetry($transaction)) {
            return $this->dispatchRetryJob($transaction);
        }

        Log::info('Transaction not yet ready for recovery', [
            'transaction_id' => $transaction->id,
            'next_retry_at' => $this->getNextRetryTime($transaction)?->toIso8601String(),
        ]);

        return false;
    }

    /**
     * Move a transaction to the dead letter queue.
     *
     * Marks the transaction as DLQ-ready by tagging it and setting appropriate status.
     *
     * @param  Transaction  $transaction  The transaction to move to DLQ
     * @return bool True if move was successful
     */
    public function moveToDeadLetterQueue(Transaction $transaction): bool
    {
        Log::warning('Moving transaction to dead letter queue', [
            'transaction_id' => $transaction->id,
            'failure_reason' => $transaction->failure_reason,
        ]);

        // Update transaction with DLQ marker
        // We use the existing failure_reason to store DLQ info
        $dlqReason = '[DLQ] '.($transaction->failure_reason ?? 'Max retries exceeded');

        $transaction->failure_reason = $dlqReason;

        // Transition to a terminal failed state (stays Failed but with DLQ marker)
        // The state machine doesn't have a specific DLQ state, so we use Failed with reason
        $stateMachine = new TransactionStateMachine($transaction);
        $stateMachine->forceStatus(TransactionStatus::Failed, $dlqReason);

        // Store DLQ metadata in error record if exists
        $latestError = $transaction->transactionErrors()
            ->whereNull('resolved_at')
            ->orderBy('created_at', 'desc')
            ->first();

        if ($latestError) {
            $latestError->resolution_notes = 'Moved to DLQ - max retries exceeded';
            $latestError->save();
        }

        Log::warning('Transaction moved to dead letter queue', [
            'transaction_id' => $transaction->id,
        ]);

        return true;
    }

    /**
     * Get transactions that need recovery attempts.
     *
     * Returns transactions that are in Failed status and have unresolved errors.
     *
     * @return Collection Collection of Transaction models
     */
    public function getTransactionsNeedingRecovery(): Collection
    {
        return Transaction::where('status', TransactionStatus::Failed)
            ->whereHas('transactionErrors', function ($query) {
                $query->whereNull('resolved_at');
            })
            ->get();
    }

    /**
     * Get all dead letter queue transactions.
     *
     * Returns transactions that have been moved to DLQ (failure_reason starts with [DLQ]).
     *
     * @return Collection Collection of Transaction models
     */
    public function getDeadLetterQueueTransactions(): Collection
    {
        return Transaction::where('status', TransactionStatus::Failed)
            ->whereNotNull('failure_reason')
            ->where('failure_reason', 'like', '[DLQ]%')
            ->get();
    }

    /**
     * Retry a transaction from the dead letter queue.
     *
     * Resets the transaction for a new recovery attempt.
     *
     * @param  Transaction  $transaction  The DLQ transaction to retry
     * @return bool True if retry was dispatched
     */
    public function retryFromDLQ(Transaction $transaction): bool
    {
        // Verify this is actually a DLQ transaction
        if (! $this->isInDeadLetterQueue($transaction)) {
            Log::warning('Cannot retry from DLQ - transaction not in DLQ', [
                'transaction_id' => $transaction->id,
            ]);

            return false;
        }

        // Remove DLQ marker from failure reason
        $originalReason = preg_replace('/^\[DLQ\]\s*/', '', $transaction->failure_reason ?? '');
        $transaction->failure_reason = $originalReason;

        // Reset the transaction status to retry
        $stateMachine = new TransactionStateMachine($transaction);
        $stateMachine->transitionTo(TransactionStatus::PendingApproval);

        // Reset error retry count for new attempt
        $latestError = $transaction->transactionErrors()
            ->whereNull('resolved_at')
            ->orderBy('created_at', 'desc')
            ->first();

        if ($latestError) {
            $latestError->retry_count = 0;
            $latestError->next_retry_at = now();
            $latestError->resolution_notes = 'Retrying from DLQ';
            $latestError->save();
        }

        Log::info('Retrying transaction from DLQ', [
            'transaction_id' => $transaction->id,
        ]);

        // Dispatch retry job
        return $this->dispatchRetryJob($transaction);
    }

    /**
     * Check if a transaction is in the dead letter queue.
     *
     * @param  Transaction  $transaction  The transaction to check
     * @return bool True if in DLQ
     */
    public function isInDeadLetterQueue(Transaction $transaction): bool
    {
        return $transaction->failure_reason !== null
            && str_starts_with($transaction->failure_reason, '[DLQ]');
    }

    /**
     * Get the next retry time for a transaction.
     *
     * @param  Transaction  $transaction  The transaction
     */
    protected function getNextRetryTime(Transaction $transaction): ?Carbon
    {
        $latestError = $transaction->transactionErrors()
            ->whereNull('resolved_at')
            ->orderBy('created_at', 'desc')
            ->first();

        return $latestError?->next_retry_at;
    }

    /**
     * Dispatch a retry job for a transaction.
     *
     * @param  Transaction  $transaction  The transaction to retry
     * @return bool True if job was dispatched
     */
    protected function dispatchRetryJob(Transaction $transaction): bool
    {
        $delayMs = $this->errorHandler->getNextRetryDelay($transaction);

        ProcessTransactionRetry::dispatch($transaction)
            ->delay(now()->addMilliseconds($delayMs));

        Log::info('Dispatched transaction retry job', [
            'transaction_id' => $transaction->id,
            'delay_ms' => $delayMs,
        ]);

        return true;
    }

    /**
     * Process a recovery for all transactions needing recovery.
     *
     * Called by a scheduled job to process pending recoveries.
     *
     * @return array Statistics about the recovery run
     */
    public function processPendingRecoveries(): array
    {
        $stats = [
            'total' => 0,
            'retried' => 0,
            'moved_to_dlq' => 0,
            'not_ready' => 0,
        ];

        $transactions = $this->getTransactionsNeedingRecovery();
        $stats['total'] = $transactions->count();

        foreach ($transactions as $transaction) {
            if ($this->attemptRecovery($transaction)) {
                if ($this->isInDeadLetterQueue($transaction)) {
                    $stats['moved_to_dlq']++;
                } else {
                    $stats['retried']++;
                }
            } else {
                $stats['not_ready']++;
            }
        }

        Log::info('Processed pending transaction recoveries', $stats);

        return $stats;
    }
}
