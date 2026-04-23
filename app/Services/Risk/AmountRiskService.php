<?php

namespace App\Services\Risk;

use App\Models\Customer;
use App\Models\Transaction;
use App\Services\MathService;
use App\Services\ThresholdService;
use Illuminate\Support\Collection;

class AmountRiskService
{
    public function __construct(
        protected MathService $mathService,
        protected ThresholdService $thresholdService
    ) {}

    /**
     * Calculate amount risk score.
     *
     * @return int Risk score (0-30)
     */
    public function calculateScore(Collection $transactions, Customer $customer): int
    {
        $score = 0;

        $maxTransaction = (string) ($transactions->max('amount_local') ?? '0');

        if ($this->mathService->compare($maxTransaction, $this->thresholdService->getRiskHighThreshold()) >= 0) {
            $score += 30;
        } elseif ($this->mathService->compare($maxTransaction, $this->thresholdService->getRiskMediumThreshold()) >= 0) {
            $score += 20;
        } elseif ($this->mathService->compare($maxTransaction, $this->thresholdService->getRiskLowThreshold()) >= 0) {
            $score += 10;
        }

        return min($score, 30);
    }

    /**
     * Check if amount exceeds threshold.
     */
    public function exceedsThreshold(string $amount, string $threshold): bool
    {
        return $this->mathService->compare($amount, $threshold) >= 0;
    }

    /**
     * Get max transaction amount for a customer.
     *
     * @param  int  $days  Lookback period in days
     */
    public function getMaxAmount(int $customerId, int $days = 90): string
    {
        return Transaction::where('customer_id', $customerId)
            ->where('created_at', '>=', now()->subDays($days))
            ->where('status', '!=', 'cancelled')
            ->max('amount_local') ?? '0';
    }

    /**
     * Get average transaction amount for a customer.
     *
     * @param  int  $days  Lookback period in days
     */
    public function getAverageAmount(int $customerId, int $days = 90): string
    {
        return Transaction::where('customer_id', $customerId)
            ->where('created_at', '>=', now()->subDays($days))
            ->where('status', '!=', 'cancelled')
            ->avg('amount_local') ?? '0';
    }
}
