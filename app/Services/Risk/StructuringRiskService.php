<?php

namespace App\Services\Risk;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Services\MathService;
use App\Services\ThresholdService;
use Illuminate\Database\Eloquent\Collection;

class StructuringRiskService
{
    public function __construct(
        protected MathService $mathService,
        protected ThresholdService $thresholdService
    ) {}

    /**
     * Calculate structuring risk score.
     *
     * Detects potential structuring patterns (transactions just below threshold).
     *
     * @param  int  $windowHours  Time window in hours (default 1)
     * @return int Risk score (0-30)
     */
    public function calculateScore(int $customerId, int $windowHours = 1): int
    {
        $score = 0;

        $subThreshold = $this->thresholdService->getStructuringSubThreshold();
        $window = now()->subHours($windowHours);

        $structuringTransactions = Transaction::where('customer_id', $customerId)
            ->where('created_at', '>=', $window)
            ->where('amount_local', '<', $subThreshold)
            ->where('status', '!=', TransactionStatus::Cancelled->value)
            ->get();

        $hourlyGroups = $structuringTransactions->groupBy(fn ($t) => $t->created_at->format('Y-m-d H'));

        foreach ($hourlyGroups as $hour => $txns) {
            if ($txns->count() >= 3) {
                $score += 25;
            } elseif ($txns->count() >= 2) {
                $score += 10;
            }
        }

        return min($score, 30);
    }

    /**
     * Check structuring threshold.
     *
     * @param  int  $windowHours  Time window in hours
     * @param  int  $threshold  Transaction count threshold
     * @return array{triggered: bool, count: int, threshold: int}
     */
    public function checkThreshold(int $customerId, int $windowHours = 1, int $threshold = 3): array
    {
        $subThreshold = $this->thresholdService->getStructuringSubThreshold();

        $count = Transaction::where('customer_id', $customerId)
            ->where('created_at', '>=', now()->subHours($windowHours))
            ->where('amount_local', '<', $subThreshold)
            ->where('status', '!=', TransactionStatus::Cancelled->value)
            ->count();

        return [
            'triggered' => $count >= $threshold,
            'count' => $count,
            'threshold' => $threshold,
        ];
    }

    /**
     * Check if customer is structuring (3+ transactions under threshold in 1 hour).
     */
    public function isStructuring(int $customerId): bool
    {
        $check = $this->checkThreshold($customerId, 1, 3);

        return $check['triggered'];
    }

    /**
     * Get structuring transactions for a customer.
     *
     * @param  int  $windowHours  Time window in hours
     * @return Collection
     */
    public function getStructuringTransactions(int $customerId, int $windowHours = 1)
    {
        $subThreshold = $this->thresholdService->getStructuringSubThreshold();

        return Transaction::where('customer_id', $customerId)
            ->where('created_at', '>=', now()->subHours($windowHours))
            ->where('amount_local', '<', $subThreshold)
            ->where('status', '!=', TransactionStatus::Cancelled->value)
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
