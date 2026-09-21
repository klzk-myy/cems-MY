<?php

namespace App\Services\Risk;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Services\Compliance\RoundTripDetector;
use App\Services\System\MathService;
use Illuminate\Database\Eloquent\Collection;

class PatternRiskService
{
    public function __construct(
        protected MathService $mathService,
        protected RoundTripDetector $roundTripDetector
    ) {}

    /**
     * Calculate pattern risk score.
     *
     * Checks buy/sell reversal patterns and currency switches.
     *
     * @return int Risk score (0-20)
     */
    public function calculateScore(int $customerId): int
    {
        $score = 0;

        $patternRisk = $this->calculatePatternRisk($customerId);

        if ($patternRisk['pattern_reversal']) {
            $score += 10;
        }

        if ($patternRisk['currency_switch']) {
            $score += 10;
        }

        return min($score, 20);
    }

    /**
     * Calculate pattern risk details.
     *
     * @return array{pattern_reversal: bool, currency_switch: bool, details: array}
     */
    public function calculatePatternRisk(int $customerId): array
    {
        $details = [];

        $recentTransactions = Transaction::where('customer_id', $customerId)
            ->notCancelled()
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

        $buyCount = $recentTransactions->where('type', TransactionType::Buy->value)->count();
        $sellCount = $recentTransactions->where('type', TransactionType::Sell->value)->count();
        $patternReversal = false;

        if ($buyCount >= 7 && $sellCount >= 2) {
            $lastType = $recentTransactions->first()->type;
            $prevType = $recentTransactions->skip(1)->first()->type;

            if ($lastType === TransactionType::Sell && $prevType === TransactionType::Buy) {
                $patternReversal = true;
                $details[] = 'Pattern change: Previously buying, now selling';
            }
        }

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
     * Check for currency round-tripping pattern.
     *
     * @param  int  $timeWindowHours  Time window in hours
     * @param  string  $threshold  Round-trip amount threshold
     */
    public function checkRoundTripping(int $customerId, int $timeWindowHours = 72, string $threshold = '5000'): array
    {
        $cutoffTime = now()->subHours($timeWindowHours);

        $recentTransactions = Transaction::where('customer_id', $customerId)
            ->where('created_at', '>=', $cutoffTime)
            ->notCancelled()
            ->orderBy('created_at', 'asc')
            ->get();

        if ($recentTransactions->count() < 2) {
            return ['has_round_trip' => false, 'patterns' => []];
        }

        $patterns = $this->detectRoundTrips($recentTransactions, $timeWindowHours, $threshold);

        return [
            'has_round_trip' => ! empty($patterns),
            'patterns' => $patterns,
        ];
    }

    /**
     * Detect round-trip patterns in transactions.
     *
     * @param  Collection  $transactions
     */
    protected function detectRoundTrips($transactions, int $timeWindowHours, string $threshold): array
    {
        return $this->roundTripDetector->detect($transactions, $timeWindowHours, $threshold);
    }
}
