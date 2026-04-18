<?php

namespace App\Jobs;

use App\Models\StrReport;
use App\Services\StrReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Submit STR to goAML Job
 *
 * Queued job for submitting Suspicious Transaction Reports to BNM goAML system.
 * Implements retry logic with exponential backoff.
 */
class SubmitStrToGoAmlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The STR report to submit
     */
    public StrReport $strReport;

    /**
     * Number of retry attempts
     */
    public int $tries = 1; // We handle retries internally

    /**
     * Job timeout in seconds
     */
    public int $timeout = 300;

    /**
     * Create a new job instance
     */
    public function __construct(StrReport $strReport)
    {
        $this->strReport = $strReport;
    }

    /**
     * Execute the job
     */
    public function handle(StrReportService $service): void
    {
        Log::info('SubmitStrToGoAmlJob started', [
            'str_id' => $this->strReport->id,
            'str_no' => $this->strReport->str_no,
            'attempt' => ($this->strReport->retry_count ?? 0) + 1,
        ]);

        // Refresh the model from database
        $this->strReport->refresh();

        // Only process if still in a retryable state
        if (! $this->strReport->canRetry()) {
            Log::info('STR is not in retryable state, skipping', [
                'str_id' => $this->strReport->id,
                'status' => $this->strReport->status->value,
            ]);

            return;
        }

        // Attempt submission/retry
        $result = $service->retrySubmission($this->strReport);

        if ($result) {
            Log::info('SubmitStrToGoAmlJob completed successfully', [
                'str_id' => $this->strReport->id,
                'str_no' => $this->strReport->str_no,
            ]);
        } else {
            Log::info('SubmitStrToGoAmlJob completed - submission failed, retry scheduled or escalated', [
                'str_id' => $this->strReport->id,
                'str_no' => $this->strReport->str_no,
                'retry_count' => $this->strReport->refresh()->retry_count,
            ]);
        }
    }

    /**
     * Handle a job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('SubmitStrToGoAmlJob failed permanently', [
            'str_id' => $this->strReport->id,
            'str_no' => $this->strReport->str_no,
            'exception' => $exception->getMessage(),
        ]);

        // Mark STR as failed
        $this->strReport->refresh();
        $this->strReport->update([
            'status' => \App\Enums\StrStatus::Failed,
            'last_error' => 'Job failed: '.$exception->getMessage(),
        ]);

        // Escalate if max retries not yet reached
        $maxRetries = config('services.goaml.max_retries', 5);
        if ($this->strReport->retry_count >= $maxRetries) {
            Log::critical('STR submission max retries exceeded after job failure', [
                'str_id' => $this->strReport->id,
                'str_no' => $this->strReport->str_no,
            ]);
        }
    }

    /**
     * Determine the time at which the job should timeout
     */
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addMinutes(10);
    }

    /**
     * Calculate the number of seconds to wait before retrying the job
     */
    public function backoff(): array
    {
        // Exponential backoff: 1min, 5min, 10min, 30min, 60min
        return [60, 300, 600, 1800, 3600];
    }

    /**
     * The unique ID of the job
     */
    public function uniqueId(): string
    {
        return 'submit-str-'.$this->strReport->id;
    }

    /**
     * The tags that should be assigned to the job
     */
    public function tags(): array
    {
        return ['str', 'goaml', 'compliance', 'str-'.$this->strReport->id];
    }
}
