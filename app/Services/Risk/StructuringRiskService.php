<?php

namespace App\Services\Risk;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Services\System\MathService;
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

        $config = config('thresholds.structuring', []);
        $highCount = (int) ($config['score_min_count_high'] ?? 3);
        $highScore = (int) ($config['score_high'] ?? 25);
        $lowCount = (int) ($config['score_min_count_low'] ?? 2);
        $lowScore = (int) ($config['score_low'] ?? 10);
        $cap = (int) ($config['score_cap'] ?? 30);

        $subThreshold = $this->thresholdService->getStructuringSubThreshold();
        $window = now()->subHours($windowHours);

        $structuringTransactions = Transaction::where('customer_id', $customerId)
            ->where('created_at', '>=', $window)
            ->where('amount_local', '<=', $subThreshold)
            ->where('status', '!=', TransactionStatus::Cancelled->value)
            ->get();

        $hourlyGroups = $structuringTransactions->groupBy(fn ($t) => $t->created_at->format('Y-m-d H'));

        foreach ($hourlyGroups as $hour => $txns) {
            if ($txns->count() >= $highCount) {
                $score += $highScore;
            } elseif ($txns->count() >= $lowCount) {
                $score += $lowScore;
            }
        }

        return min($score, $cap);
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
            ->where('amount_local', '<=', $subThreshold)
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
        $windowHours = $this->thresholdService->getStructuringHourlyWindow();
        $minTransactions = $this->thresholdService->getStructuringMinTransactions();

        $check = $this->checkThreshold($customerId, $windowHours, $minTransactions);

        return $check['triggered'];
    }

    /**
     * Get structuring transactions for a customer.
     *
     * @param  int  $windowHours  Time window in hours
     */
    public function getStructuringTransactions(int $customerId, int $windowHours = 1): Collection
    {
        $subThreshold = $this->thresholdService->getStructuringSubThreshold();

        return Transaction::where('customer_id', $customerId)
            ->where('created_at', '>=', now()->subHours($windowHours))
            ->where('amount_local', '<=', $subThreshold)
            ->where('status', '!=', TransactionStatus::Cancelled->value)
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
