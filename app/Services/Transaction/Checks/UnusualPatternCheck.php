<?php

namespace App\Services\Transaction\Checks;

use App\Enums\ComplianceFlagType;
use App\Models\Transaction;
use App\Services\System\MathService;
use App\Services\ThresholdService;

/**
 * Transaction deviates from the customer's rolling average by more than the
 * configured multiplier.
 */
class UnusualPatternCheck implements TransactionCheck
{
    public function __construct(
        protected ThresholdService $thresholdService,
        protected MathService $mathService,
    ) {}

    public function check(Transaction $transaction): array
    {
        if (! $this->isUnusualPattern($transaction)) {
            return [];
        }

        $deviationPct = (float) $this->thresholdService->get('monitoring', 'unusual_pattern_multiplier', 2) * 100;

        return [new FlagDescriptor(
            type: ComplianceFlagType::ManualReview,
            reason: "Transaction deviates {$deviationPct}% from customer average",
        )];
    }

    protected function isUnusualPattern(Transaction $transaction): bool
    {
        $lookbackDays = (int) $this->thresholdService->get('monitoring', 'unusual_pattern_lookback_days', 90);
        $multiplier = (string) $this->thresholdService->get('monitoring', 'unusual_pattern_multiplier', 2);

        $customerAvg = Transaction::where('customer_id', $transaction->customer_id)
            ->where('created_at', '>=', now()->subDays($lookbackDays))
            ->avg('amount_myr');

        if (! $customerAvg || $this->mathService->compare((string) $customerAvg, '0') === 0) {
            return false;
        }

        $deviation = $this->mathService->divide(
            (string) $transaction->amount_myr,
            (string) $customerAvg
        );

        return $this->mathService->compare($deviation, $multiplier) > 0;
    }
}
