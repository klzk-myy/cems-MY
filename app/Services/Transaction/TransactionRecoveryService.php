<?php

namespace App\Services\Transaction;

use App\Enums\TransactionStatus;
use App\Exceptions\Domain\InvalidStateException;
use App\Jobs\ProcessTransactionRetry;
use App\Models\Transaction;
use App\Models\TransactionError;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

        $dlqReason = '[DLQ] '.($transaction->failure_reason ?? 'Max retries exceeded');

        DB::transaction(function () use ($transaction, $dlqReason) {
            // Lock the row so a concurrent manual retry cannot observe a
            // mid-move state or race the is_dlq flag write.
            $locked = Transaction::where('id', $transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            $stateMachine = new TransactionStateMachine($locked);
            $stateMachine->markAsDlq($dlqReason);

            // Store DLQ metadata in error record if exists
            $latestError = $this->latestUnresolvedError($locked);

            if ($latestError) {
                $latestError->resolution_notes = 'Moved to DLQ - max retries exceeded';
                $latestError->save();
            }
        });

        $transaction->refresh();

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
        return Transaction::query()
            ->with('transactionErrors')
            ->where('status', TransactionStatus::Failed->value)
            // DLQ transactions are recovered via the manual retryFromDLQ flow;
            // excluding them here prevents a re-dispatch loop on every sweep.
            ->where('is_dlq', false)
            ->whereHas('transactionErrors', function ($query) {
                $query->whereNull('resolved_at');
            })
            ->get();
    }

    /**
     * Get all dead letter queue transactions.
     *
     * Returns transactions that have been moved to DLQ (is_dlq = true).
     *
     * @return Collection Collection of Transaction models
     */
    public function getDeadLetterQueueTransactions(): Collection
    {
        return Transaction::where('is_dlq', true)->get();
    }

    /**
     * Retry a transaction from the dead letter queue.
     *
     * Resets the DLQ state and transitions the transaction back to
     * PendingApproval for the manual approval flow. No automatic retry job is
     * dispatched: ProcessTransactionRetry skips anything not in Failed status,
     * so dispatching it here would always silently no-op.
     *
     * @param  Transaction  $transaction  The DLQ transaction to retry
     * @return bool True if the transaction was returned to PendingApproval
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

        $transitioned = false;

        DB::transaction(function () use ($transaction, $originalReason, &$transitioned) {
            // Lock the row so the is_dlq check the caller performed cannot be
            // invalidated by a concurrent purge/retry between check and write.
            $locked = Transaction::where('id', $transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            $stateMachine = new TransactionStateMachine($locked);

            $transitioned = $stateMachine->transitionTo(TransactionStatus::PendingApproval);

            if (! $transitioned) {
                // Throwing rolls back everything below/above in this closure,
                // so the DLQ flag is only cleared when the status transition
                // also succeeded.
                throw new InvalidStateException(
                    "Cannot retry transaction {$transaction->id} from DLQ: status '{$locked->status->value}' does not allow transition to PendingApproval."
                );
            }

            // Restore the original failure reason without the [DLQ] marker
            $locked->failure_reason = $originalReason;
            $locked->is_dlq = false;
            $locked->save();

            $latestError = $this->latestUnresolvedError($locked);
            if ($latestError) {
                $latestError->retry_count = 0;
                $latestError->next_retry_at = now();
                $latestError->resolution_notes = 'Retrying from DLQ';
                $latestError->save();
            }
        });

        $transaction->refresh();

        Log::info('Transaction returned to PendingApproval for manual approval', [
            'transaction_id' => $transaction->id,
        ]);

        return $transitioned;
    }

    /**
     * Check if a transaction is in the dead letter queue.
     *
     * @param  Transaction  $transaction  The transaction to check
     * @return bool True if in DLQ
     */
    public function isInDeadLetterQueue(Transaction $transaction): bool
    {
        return $transaction->is_dlq;
    }

    /**
     * Purge (archive) a transaction from the dead letter queue.
     *
     * Archiving soft-deletes the transaction, which removes it from the DLQ
     * listing and the recovery sweep while retaining the row for the 7-year
     * regulatory retention period. Outstanding error records are resolved so
     * the recovery sweep never re-picks it. This is not a hard delete.
     *
     * @param  Transaction  $transaction  The DLQ transaction to archive
     * @return bool True if the transaction was archived
     */
    public function purgeFromDLQ(Transaction $transaction): bool
    {
        // Verify this is actually a DLQ transaction
        if (! $this->isInDeadLetterQueue($transaction)) {
            Log::warning('Cannot purge transaction - not in DLQ', [
                'transaction_id' => $transaction->id,
            ]);

            return false;
        }

        // Resolve ALL outstanding error records and archive atomically: if
        // the soft-delete failed after the errors were resolved the row would
        // linger in the DLQ listing with nothing left to recover it by.
        DB::transaction(function () use ($transaction) {
            $transaction->transactionErrors()
                ->whereNull('resolved_at')
                ->update([
                    'resolved_at' => now(),
                    'resolution_notes' => 'Purged from DLQ - archived',
                ]);

            // Archive: soft-delete. Soft-deleted rows are excluded from the DLQ
            // listing and recovery queries by default but retained in the table.
            $transaction->delete();
        });

        Log::warning('Transaction purged from DLQ (archived)', [
            'transaction_id' => $transaction->id,
        ]);

        return true;
    }

    /**
     * Get the next retry time for a transaction.
     *
     * @param  Transaction  $transaction  The transaction
     */
    protected function getNextRetryTime(Transaction $transaction): ?Carbon
    {
        $latestError = $this->latestUnresolvedError($transaction);

        return $latestError?->next_retry_at;
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
    private function latestUnresolvedError(Transaction $transaction): ?TransactionError
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
     * Dispatch a retry job for a transaction.
     *
     * @param  Transaction  $transaction  The transaction to retry
     * @return bool True if job was dispatched
     */
    protected function dispatchRetryJob(Transaction $transaction): bool
    {
        $delayMs = $this->errorHandler->getNextRetryDelay($transaction);

        // Guard against zero/negative or runaway delays from a misconfigured
        // backoff strategy: clamp to [0, 1h].
        $maxDelayMs = (int) config('cems.retry_max_delay_ms', 3600000);
        $delayMs = max(0, min((int) $delayMs, $maxDelayMs));

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
