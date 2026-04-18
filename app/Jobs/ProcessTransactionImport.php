<?php

namespace App\Jobs;

use App\Services\BulkImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Process Transaction Import Job
 *
 * Queued job for processing transaction CSV imports.
 */
class ProcessTransactionImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Job timeout in seconds.
     */
    public int $timeout = 600;

    /**
     * Number of retries.
     */
    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $filePath,
        public int $userId,
        public string $jobId
    ) {
        $this->onQueue('imports');
    }

    /**
     * Execute the job.
     */
    public function handle(BulkImportService $importService): void
    {
        Log::info('Processing transaction import', [
            'job_id' => $this->jobId,
            'file' => $this->filePath,
            'user_id' => $this->userId,
        ]);

        // Update status to processing
        $importService->storeImportStatus($this->jobId, [
            'status' => 'processing',
            'started_at' => now()->toDateTimeString(),
            'total_rows' => 0,
            'success_count' => 0,
            'error_count' => 0,
            'created' => 0,
            'failed' => 0,
            'errors' => [],
        ]);

        // Get full path
        $fullPath = Storage::path($this->filePath);

        // Process import
        $result = $importService->importTransactionsFromCsv($fullPath, $this->userId);

        // Update status with results
        $importService->storeImportStatus($this->jobId, array_merge($result['stats'], [
            'status' => $result['success'] ? 'completed' : 'completed_with_errors',
            'completed_at' => now()->toDateTimeString(),
            'errors' => $result['errors'],
        ]));

        Log::info('Transaction import completed', [
            'job_id' => $this->jobId,
            'result' => $result,
        ]);

        // Clean up file
        Storage::delete($this->filePath);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Transaction import failed', [
            'job_id' => $this->jobId,
            'error' => $exception->getMessage(),
        ]);

        $importService = app(BulkImportService::class);
        $importService->storeImportStatus($this->jobId, [
            'status' => 'failed',
            'failed_at' => now()->toDateTimeString(),
            'error' => $exception->getMessage(),
        ]);
    }
}
