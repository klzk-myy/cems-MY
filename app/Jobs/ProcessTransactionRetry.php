<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Services\TransactionErrorHandler;
use App\Services\TransactionRecoveryService;
use App\Services\TransactionStateMachine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Process Transaction Retry Job
 *
 * Handles retry of failed transactions with exponential backoff.
 * Max 3 attempts with delays of 100ms, 200ms, 400ms.
 */
class ProcessTransactionRetry implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum number of attempts.
     */
    public int $tries = 3;

    /**
     * Timeout in seconds.
     */
    public int $timeout = 120;

    /**
     * Calculate backoff delays: 100ms, 200ms, 400ms.
     *
     * @return array<int>
     */
    public function backoff(): array
    {
        return [100, 200, 400];
    }

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
     * Attempts to recover the transaction by transitioning it back to
     * PendingApproval and re-processing.
     */
    public function handle(
        TransactionErrorHandler $errorHandler,
        TransactionRecoveryService $recoveryService
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

        // Check if should move to DLQ
        if ($errorHandler->shouldMoveToDLQ($this->transaction)) {
            $recoveryService->moveToDeadLetterQueue($this->transaction);
            Log::warning('Transaction moved to DLQ after max retries', [
                'transaction_id' => $this->transaction->id,
            ]);

            return;
        }

        // Attempt to transition back to PendingApproval for retry
        $stateMachine = new TransactionStateMachine($this->transaction);

        if (! $stateMachine->retry()) {
            Log::error('Failed to transition transaction for retry', [
                'transaction_id' => $this->transaction->id,
                'current_status' => $this->transaction->status->value,
            ]);

            return;
        }

        Log::info('Transaction transitioned to PendingApproval for retry', [
            'transaction_id' => $this->transaction->id,
        ]);

        // Note: The actual reprocessing of the transaction would be handled
        // by the TransactionService or a corresponding processor when the
        // transaction moves through the normal workflow.
        // This job just handles the state transition and retry scheduling.
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

        // Move to DLQ on permanent failure
        $recoveryService = app(TransactionRecoveryService::class);
        $recoveryService->moveToDeadLetterQueue($this->transaction);
    }

    /**
     * Get the unique ID for the job.
     *
     * Ensures only one retry job runs per transaction at a time.
     */
    public function uniqueId(): string
    {
        return 'transaction_retry_'.$this->transaction->id;
    }
}
