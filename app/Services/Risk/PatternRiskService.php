<?php

namespace App\Services\Risk;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Services\MathService;
use Illuminate\Database\Eloquent\Collection;

class PatternRiskService
{
    public function __construct(
        protected MathService $mathService
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
            ->where('status', '!=', TransactionStatus::Cancelled->value)
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
        $patterns = [];

        $byCurrency = $transactions->groupBy('currency_code');

        foreach ($byCurrency as $currencyCode => $currencyTxns) {
            $sells = $currencyTxns->filter(fn ($t) => $t->type->value === 'Sell');
            $buys = $currencyTxns->filter(fn ($t) => $t->type->value === 'Buy');

            if ($sells->isEmpty() || $buys->isEmpty()) {
                continue;
            }

            foreach ($sells as $sell) {
                foreach ($buys as $buy) {
                    if ($buy->created_at->lte($sell->created_at)) {
                        continue;
                    }

                    $hoursDiff = $sell->created_at->diffInHours($buy->created_at);

                    if ($hoursDiff > $timeWindowHours) {
                        continue;
                    }

                    $sellForeign = ltrim((string) $sell->amount_foreign, '-');
                    $buyForeign = ltrim((string) $buy->amount_foreign, '-');
                    $roundTripAmount = $this->math->compare($sellForeign, $buyForeign) <= 0
                        ? $sellForeign
                        : $buyForeign;

                    if ($this->math->compare((string) $roundTripAmount, $threshold) < 0) {
                        continue;
                    }

                    $patterns[] = [
                        'currency' => $currencyCode,
                        'sell_transaction_id' => $sell->id,
                        'sell_amount_foreign' => (string) $sell->amount_foreign,
                        'sell_amount_local' => (string) $sell->amount_local,
                        'sell_at' => $sell->created_at->toDateTimeString(),
                        'buy_transaction_id' => $buy->id,
                        'buy_amount_foreign' => (string) $buy->amount_foreign,
                        'buy_amount_local' => (string) $buy->amount_local,
                        'buy_at' => $buy->created_at->toDateTimeString(),
                        'hours_between' => $hoursDiff,
                        'round_trip_foreign_amount' => $roundTripAmount,
                    ];
                }
            }
        }

        return $patterns;
    }
}
