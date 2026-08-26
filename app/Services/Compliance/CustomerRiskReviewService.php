<?php

namespace App\Services\Compliance;

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
                $rescreenResult = $this->riskScoringService->rescreenCustomer($customer->id, 'review');

                // Locked profiles are left untouched by the rescreen; count
                // them as processed without flagging a change.
                if (! empty($rescreenResult['locked'])) {
                    $results['processed']++;

                    continue;
                }

                // Both values come from the snapshot series, so the comparison
                // is now same-scale (previously it compared a stored customer
                // score against a freshly computed snapshot score).
                $oldScore = $rescreenResult['previous_score'];
                $newScore = $rescreenResult['new_score'];

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
