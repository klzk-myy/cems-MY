<?php

namespace App\Services;

use App\Models\RiskScoreSnapshot;
use Illuminate\Support\Facades\Log;

class CustomerRiskReviewService
{
    public function __construct(
        protected CustomerRiskScoringService $riskScoringService,
    ) {}

    public function processDueReviews(int $batchSize = 50): array
    {
        $dueSnapshots = RiskScoreSnapshot::needsRescreening()
            ->with('customer')
            ->take($batchSize)
            ->get();

        $results = ['processed' => 0, 'changed' => 0, 'errors' => 0];

        foreach ($dueSnapshots as $snapshot) {
            $customer = $snapshot->customer;

            if (! $customer) {
                $results['errors']++;

                continue;
            }

            try {
                $oldScore = $customer->risk_score;

                $rescreenResult = $this->riskScoringService->rescreenCustomer($customer->id);

                $customer->refresh();

                $newSnapshot = $rescreenResult['snapshot'] ?? null;
                $newScore = $newSnapshot?->overall_score ?? $customer->risk_score;

                if ($oldScore !== $newScore) {
                    $results['changed']++;
                }

                $results['processed']++;
            } catch (\Exception $e) {
                $results['errors']++;
                Log::error("Risk review failed for customer {$customer->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }
}
