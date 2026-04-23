<?php

namespace App\Services\Risk;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Services\MathService;
use App\Services\ThresholdService;

class VelocityRiskService
{
    public function __construct(
        protected MathService $mathService,
        protected ThresholdService $thresholdService
    ) {}

    /**
     * Calculate velocity risk score.
     *
     * Checks transaction velocity (multiple transactions in time window).
     *
     * @param  int  $windowHours  Time window in hours (default 24)
     * @return int Risk score (0-40)
     */
    public function calculateScore(int $customerId, int $windowHours = 24): int
    {
        $score = 0;

        $transactions = Transaction::where('customer_id', $customerId)
            ->where('created_at', '>=', now()->subHours($windowHours))
            ->where('status', '!=', TransactionStatus::Cancelled->value)
            ->get();

        if ($transactions->isEmpty()) {
            return 0;
        }

        $dailyAmounts = $transactions->groupBy(fn ($t) => $t->created_at->format('Y-m-d'))
            ->map(fn ($day) => $day->sum('amount_local'));

        foreach ($dailyAmounts as $date => $amount) {
            $amountStr = (string) $amount;
            if ($this->mathService->compare($amountStr, $this->thresholdService->getRiskHighThreshold()) >= 0) {
                $score += 30;
            } elseif ($this->mathService->compare($amountStr, $this->thresholdService->getRiskMediumThreshold()) >= 0) {
                $score += 20;
            } elseif ($this->mathService->compare($amountStr, $this->thresholdService->getRiskLowThreshold()) >= 0) {
                $score += 10;
            }
        }

        return min($score, 40);
    }

    /**
     * Check velocity threshold (transaction count).
     *
     * @param  int  $windowHours  Time window in hours
     * @param  int  $threshold  Transaction count threshold
     * @return array{triggered: bool, count: int, threshold: int}
     */
    public function checkThreshold(int $customerId, int $windowHours = 24, int $threshold = 3): array
    {
        $count = Transaction::where('customer_id', $customerId)
            ->where('created_at', '>=', now()->subHours($windowHours))
            ->where('status', '!=', TransactionStatus::Cancelled->value)
            ->count();

        return [
            'triggered' => $count >= $threshold,
            'count' => $count,
            'threshold' => $threshold,
        ];
    }

    /**
     * Check velocity amount threshold.
     *
     * @param  string  $newAmount  New transaction amount to add
     * @return array{amount_24h: string, with_new_transaction: string, threshold_exceeded: bool, threshold_amount: string}
     */
    public function checkAmountThreshold(int $customerId, string $newAmount): array
    {
        $startTime = now()->subHours(24);
        $velocity = Transaction::where('customer_id', $customerId)
            ->where('created_at', '>=', $startTime)
            ->selectRaw('CAST(SUM(amount_local) AS CHAR) as total')
            ->value('total') ?? '0';

        $total = $this->mathService->add((string) $velocity, $newAmount);

        return [
            'amount_24h' => (string) $velocity,
            'with_new_transaction' => $total,
            'threshold_exceeded' => $this->mathService->compare($total, $this->thresholdService->getLargeTransactionThreshold()) >= 0,
            'threshold_amount' => $this->thresholdService->getLargeTransactionThreshold(),
        ];
    }

    /**
     * Get 24-hour transaction amount for a customer.
     */
    public function get24hAmount(int $customerId): string
    {
        return Transaction::where('customer_id', $customerId)
            ->where('created_at', '>=', now()->subHours(24))
            ->where('status', '!=', TransactionStatus::Cancelled->value)
            ->selectRaw('CAST(SUM(amount_local) AS CHAR) as total')
            ->value('total') ?? '0';
    }

    /**
     * Get 24-hour transaction count for a customer.
     */
    public function get24hCount(int $customerId): int
    {
        return Transaction::where('customer_id', $customerId)
            ->where('created_at', '>=', now()->subHours(24))
            ->where('status', '!=', TransactionStatus::Cancelled->value)
            ->count();
    }
}
