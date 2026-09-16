<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Services\Transaction\TransactionApprovalService;
use App\Services\Transaction\TransactionErrorHandler;
use App\Services\Transaction\TransactionRecoveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Process Transaction Retry Job
 *
 * Re-executes a failed transaction by re-running the booking side effects
 * (position, till, teller allocation, accounting entries) and marking it
 * Completed - no manual re-approval is required.
 * Retry scheduling is managed by TransactionRecoveryService with exponential backoff.
 */
class ProcessTransactionRetry implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Timeout in seconds.
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     *
     * @param  Transaction  $transaction  The transaction to retry
     */
    public function __construct(
        public Transaction $transaction
    ) {}

    /**
     * Execute the job.
     *
     * Re-executes the failed transaction via TransactionApprovalService.
     * If re-execution fails, the transaction is parked in the dead letter
     * queue for manual review instead of being retried forever.
     */
    public function handle(
        TransactionErrorHandler $errorHandler,
        TransactionRecoveryService $recoveryService,
        TransactionApprovalService $approvalService
    ): void {
        Log::info('ProcessTransactionRetry job started', [
            'transaction_id' => $this->transaction->id,
            'attempt' => $this->attempts(),
        ]);

        // Refresh transaction from database
        $this->transaction->refresh();

        // Verify transaction is still in Failed status
        if (! $this->transaction->status->isFailed()) {
            Log::info('Transaction no longer in Failed status, skipping retry', [
                'transaction_id' => $this->transaction->id,
                'current_status' => $this->transaction->status->value,
            ]);

            return;
        }

        // DLQ transactions are recovered through the manual retryFromDLQ flow.
        // Without this guard, a DLQ'd transaction (still Failed with an
        // unresolved retryable error) would be re-picked by the recovery sweep
        // and re-dispatched here forever.
        if ($this->transaction->is_dlq) {
            Log::info('Transaction is in the dead letter queue, skipping automatic retry', [
                'transaction_id' => $this->transaction->id,
            ]);

            return;
        }

        // Check if should move to DLQ
        if ($errorHandler->shouldMoveToDLQ($this->transaction)) {
            if (! $this->isStillFailed()) {
                Log::info('Transaction changed state before DLQ move, skipping', [
                    'transaction_id' => $this->transaction->id,
                    'current_status' => $this->transaction->status->value,
                ]);

                return;
            }

            $recoveryService->moveToDeadLetterQueue($this->transaction);
            Log::warning('Transaction moved to DLQ after max retries', [
                'transaction_id' => $this->transaction->id,
            ]);

            return;
        }

        // Re-execute the failed transaction (position, till, allocation,
        // accounting entries) and book it as Completed.
        $result = $approvalService->reprocessFailed($this->transaction);

        if (! $result->success) {
            Log::error('Failed to re-execute transaction', [
                'transaction_id' => $this->transaction->id,
                'message' => $result->message,
            ]);

            if (! $this->isStillFailed()) {
                Log::info('Transaction changed state during retry, skipping DLQ move', [
                    'transaction_id' => $this->transaction->id,
                    'current_status' => $this->transaction->status->value,
                ]);

                return;
            }

            // A failed re-execution cannot be booked; park it in the DLQ for
            // manual review instead of repeating the same failed work forever.
            $recoveryService->moveToDeadLetterQueue($this->transaction);

            return;
        }

        // Resolve the outstanding error record and reset retry accounting
        $errorHandler->recordSuccessfulRetry($this->transaction);

        Log::info('Transaction re-executed and completed successfully', [
            'transaction_id' => $this->transaction->id,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessTransactionRetry job permanently failed', [
            'transaction_id' => $this->transaction->id,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Mirror handle()'s isStillFailed() guard: refresh and only DLQ when
        // the transaction is still Failed and not already parked. A timeout
        // firing after a successful retry would otherwise call markAsDlq() on
        // a Completed transaction, which only accepts Failed status.
        $this->transaction->refresh();

        if (! $this->transaction->status->isFailed() || $this->transaction->is_dlq) {
            Log::info('Transaction no longer eligible for DLQ after job failure, skipping', [
                'transaction_id' => $this->transaction->id,
                'current_status' => $this->transaction->status->value,
                'is_dlq' => $this->transaction->is_dlq,
            ]);

            return;
        }

        // Move to DLQ on permanent failure. failed() is invoked by the queue
        // worker without DI, so the service is resolved here intentionally.
        app(TransactionRecoveryService::class)->moveToDeadLetterQueue($this->transaction);
    }

    /**
     * Get the unique ID for the job.
     *
     * Together with ShouldBeUnique, ensures only one retry job per
     * transaction is queued at a time.
     */
    public function uniqueId(): string
    {
        return 'transaction_retry_'.$this->transaction->id;
    }

    /**
     * Re-read the transaction and verify it is still Failed.
     *
     * Guards against races with a concurrent retry job that completed or moved
     * the transaction between our initial status check and now - calling
     * moveToDeadLetterQueue() on such a transaction would throw, because
     * markAsDlq() only accepts Failed status.
     */
    protected function isStillFailed(): bool
    {
        $this->transaction->refresh();

        return $this->transaction->status->isFailed();
    }
}
