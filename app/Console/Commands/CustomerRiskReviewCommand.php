<?php

namespace App\Console\Commands;

use App\Services\CustomerRiskReviewService;
use App\Services\ThresholdService;
use Illuminate\Console\Command;

class CustomerRiskReviewCommand extends Command
{
    protected $signature = 'customer:risk-review
                            {--batch-size= : Number of customers to process per run}';

    protected $description = 'Process customers due for periodic risk score recalculation

This command runs daily to recalculate risk scores for customers whose
next_screening_date has passed. It uses ThresholdService to determine
the batch size for processing.';

    public function handle(CustomerRiskReviewService $service, ThresholdService $thresholdService): int
    {
        $batchSize = $this->option('batch-size')
            ?? $thresholdService->getRiskReviewBatchSize();

        $this->info("Starting customer risk review (batch size: {$batchSize})...");

        $results = $service->processDueReviews((int) $batchSize);

        $this->info("Processed: {$results['processed']}, Changed: {$results['changed']}, Errors: {$results['errors']}");

        if ($results['errors'] > 0) {
            $this->warn("Completed with {$results['errors']} errors. Check logs for details.");

            return self::FAILURE;
        }

        $this->info('Risk review completed successfully.');

        return self::SUCCESS;
    }
}
