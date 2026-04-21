<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Customer;
use App\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * Unified Risk Calculation Service
 *
 * Consolidates overlapping risk calculation logic across:
 * - CustomerRiskScoringService
 * - RiskScoringEngine
 * - HistoricalRiskAnalysisService
 * - AmlRuleService
 *
 * All monetary calculations use BCMath via MathService.
 */
class RiskCalculationService
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
     * @param  Collection|array  $transactions  Transaction collection or customer ID
     * @param  int  $windowHours  Time window in hours (default 24)
     * @return int Risk score (0-40)
     */
    public function calculateVelocityRisk(int $customerId, int $windowHours = 24): int
    {
        $score = 0;

        $transactions = Transaction::where('customer_id', $customerId)
            ->where('created_at', '>=', now()->subHours($windowHours))
            ->where('status', '!=', TransactionStatus::Cancelled->value)
            ->get();

        if ($transactions->isEmpty()) {
            return 0;
        }

        // Group by day and sum amounts
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
     * Calculate structuring risk score.
     *
     * Detects potential structuring patterns (transactions just below threshold).
     *
     * @param  int  $customerId  Customer ID
     * @param  int  $windowHours  Time window in hours (default 1)
     * @return int Risk score (0-30)
     */
    public function calculateStructuringRisk(int $customerId, int $windowHours = 1): int
    {
        $score = 0;

        $subThreshold = $this->thresholdService->getStructuringSubThreshold();
        $window = now()->subHours($windowHours);

        // Structuring = transactions below threshold but above minimum CDD
        $structuringTransactions = Transaction::where('customer_id', $customerId)
            ->where('created_at', '>=', $window)
            ->where('amount_local', '<', $subThreshold)
            ->where('status', '!=', TransactionStatus::Cancelled->value)
            ->get();

        // Group by hour
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
     * Calculate amount risk score.
     *
     * Checks amount escalation vs 90-day average and maximum transaction.
     *
     * @param  int  $customerId  Customer ID
     * @param  string|null  $currentAmount  Current transaction amount (optional)
     * @return int Risk score (0-30)
     */
    public function calculateAmountRisk(int $customerId, ?string $currentAmount = null): int
    {
        $score = 0;

        $transactions = Transaction::where('customer_id', $customerId)
            ->where('created_at', '>=', now()->subDays(90))
            ->where('status', '!=', TransactionStatus::Cancelled->value)
            ->get();

        if ($transactions->isEmpty()) {
            return 0;
        }

        // Check max transaction against thresholds
        $maxTransaction = (string) ($transactions->max('amount_local') ?? '0');

        if ($this->mathService->compare($maxTransaction, $this->thresholdService->getRiskHighThreshold()) >= 0) {
            $score += 30;
        } elseif ($this->mathService->compare($maxTransaction, $this->thresholdService->getRiskMediumThreshold()) >= 0) {
            $score += 20;
        } elseif ($this->mathService->compare($maxTransaction, $this->thresholdService->getRiskLowThreshold()) >= 0) {
            $score += 10;
        }

        // Check if current amount is significantly above average
        if ($currentAmount !== null) {
            $avgAmount = $transactions->avg('amount_local');
            if ($avgAmount > 0) {
                $avgAmountFormatted = number_format($avgAmount, 2, '.', '');
                $escalation = $this->mathService->divide($currentAmount, $avgAmountFormatted);
                if ($this->mathService->compare($escalation, '2.0') >= 0) {
                    $score += 10;
                }
            }
        }

        return min($score, 30);
    }

    /**
     * Calculate cumulative risk.
     *
     * Checks 7-day cumulative threshold.
     *
     * @param  int  $customerId  Customer ID
     * @param  string|null  $currentAmount  Current transaction amount to add (optional)
     * @return array{triggered: bool, total: string, threshold: string}
     */
    public function calculateCumulativeRisk(int $customerId, ?string $currentAmount = null): array
    {
        $cumulativeThreshold = $this->thresholdService->getVelocityAlertThreshold();
        $window = now()->subDays(7);

        $weekTotal = Transaction::where('customer_id', $customerId)
            ->where('created_at', '>=', $window)
            ->where('status', '!=', TransactionStatus::Cancelled->value)
            ->sum('amount_local');

        $weekTotalFormatted = number_format((float) $weekTotal, 2, '.', '');
        $currentAmountFormatted = $currentAmount ?? '0';
        $total = $this->mathService->add($weekTotalFormatted, $currentAmountFormatted);

        return [
            'triggered' => $this->mathService->compare($total, $cumulativeThreshold) >= 0,
            'total' => $total,
            'threshold' => $cumulativeThreshold,
        ];
    }

    /**
     * Calculate pattern risk.
     *
     * Checks buy/sell reversal patterns and currency switches.
     *
     * @param  int  $customerId  Customer ID
     * @return array{pattern_reversal: bool, currency_switch: bool, details: array}
     */
    public function calculatePatternRisk(int $customerId): array
    {
        $details = [];

        $recentTransactions = Transaction::where('customer_id', $customerId)
            ->where('status', '!=', TransactionStatus::Cancelled->value)
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        if ($recentTransactions->count() < 5) {
            return [
                'pattern_reversal' => false,
                'currency_switch' => false,
                'details' => [],
            ];
        }

        // Check for reversal (always buying, suddenly selling)
        $buyCount = $recentTransactions->where('type', TransactionType::Buy)->count();
        $sellCount = $recentTransactions->where('type', TransactionType::Sell)->count();
        $patternReversal = false;

        if ($buyCount >= 7 && $sellCount >= 2) {
            $lastType = $recentTransactions->first()->type;
            $prevType = $recentTransactions->skip(1)->first()->type;

            if ($lastType === TransactionType::Sell && $prevType === TransactionType::Buy) {
                $patternReversal = true;
                $details[] = 'Pattern change: Previously buying, now selling';
            }
        }

        // Check for currency switch
        $currencies = $recentTransactions->pluck('currency_code')->unique();
        $currencySwitch = $currencies->count() >= 3;

        if ($currencySwitch) {
            $details[] = 'Multiple currency types in recent transactions';
        }

        return [
            'pattern_reversal' => $patternReversal,
            'currency_switch' => $currencySwitch,
            'details' => $details,
        ];
    }

    /**
     * Get overall risk score combining all factors.
     *
     * @param  int  $customerId  Customer ID
     * @param  string|null  $currentAmount  Current transaction amount (optional)
     * @return array{velocity: int, structuring: int, amount: int, cumulative: array, pattern: array, overall: int}
     */
    public function getOverallRiskScore(int $customerId, ?string $currentAmount = null): array
    {
        $velocity = $this->calculateVelocityRisk($customerId);
        $structuring = $this->calculateStructuringRisk($customerId);
        $amount = $this->calculateAmountRisk($customerId, $currentAmount);
        $cumulative = $this->calculateCumulativeRisk($customerId, $currentAmount);
        $pattern = $this->calculatePatternRisk($customerId);

        // Calculate overall score (weighted)
        $overall = $velocity + $structuring + $amount;

        // Add pattern risk if detected
        if ($pattern['pattern_reversal']) {
            $overall += 10;
        }
        if ($pattern['currency_switch']) {
            $overall += 5;
        }

        return [
            'velocity' => $velocity,
            'structuring' => $structuring,
            'amount' => $amount,
            'cumulative' => $cumulative,
            'pattern' => $pattern,
            'overall' => min($overall, 100),
        ];
    }

    /**
     * Check if customer has high velocity (transaction count in window).
     *
     * @param  int  $customerId  Customer ID
     * @param  int  $windowHours  Time window in hours
     * @param  int  $threshold  Transaction count threshold
     * @return array{triggered: bool, count: int, threshold: int}
     */
    public function checkVelocityThreshold(int $customerId, int $windowHours = 24, int $threshold = 3): array
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
     * Check structuring threshold.
     *
     * @param  int  $customerId  Customer ID
     * @param  int  $windowHours  Time window in hours
     * @param  int  $threshold  Transaction count threshold
     * @return array{triggered: bool, count: int, threshold: int}
     */
    public function checkStructuringThreshold(int $customerId, int $windowHours = 1, int $threshold = 2): array
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
}
