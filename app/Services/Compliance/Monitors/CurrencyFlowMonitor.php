<?php

namespace App\Services\Compliance\Monitors;

use App\Enums\FindingSeverity;
use App\Enums\FindingType;
use App\Models\Transaction;
use App\Services\System\MathService;
use App\Services\ThresholdService;

/**
 * Monitor for detecting unusual currency round-tripping patterns.
 * Flags when the same currency goes out (Sell) and comes back in (Buy) within a short period.
 */
class CurrencyFlowMonitor extends BaseMonitor
{
    protected ThresholdService $thresholdService;

    public const TIME_WINDOW_HOURS = 72;

    public function __construct(MathService $math, ThresholdService $thresholdService)
    {
        parent::__construct($math);
        $this->thresholdService = $thresholdService;
    }

    protected function getFindingType(): FindingType
    {
        return FindingType::CurrencyFlowAnomaly;
    }

    public function run(): array
    {
        $findings = [];
        $cutoffTime = now()->subDays($this->thresholdService->getCurrencyFlowLookbackDays());

        $grouped = Transaction::with('customer')
            ->where('created_at', '>=', $cutoffTime)
            ->notCancelled()
            ->orderBy('created_at', 'asc')
            ->get()
            ->groupBy('customer_id');

        foreach ($grouped as $customerId => $recentTransactions) {
            $finding = $this->checkCustomerRoundTripping($customerId, $recentTransactions);
            if ($finding !== null) {
                $findings[] = $finding;
            }
        }

        return $findings;
    }

    /**
     * Check a customer for currency round-tripping patterns.
     */
    protected function checkCustomerRoundTripping(int $customerId, $recentTransactions): ?array
    {
        $cutoffTime = now()->subHours(self::TIME_WINDOW_HOURS);
        $recentTransactions = $recentTransactions->where('created_at', '>=', $cutoffTime);

        if ($recentTransactions->count() < 2) {
            return null;
        }

        // Group by currency to find round-trip patterns
        $roundTripPatterns = $this->detectRoundTrips($recentTransactions);

        if (empty($roundTripPatterns)) {
            return null;
        }

        $customer = $recentTransactions->first()->customer;

        return $this->createFinding(
            type: FindingType::CurrencyFlowAnomaly,
            severity: FindingSeverity::Low,
            subjectType: 'Customer',
            subjectId: $customerId,
            details: [
                'customer_name' => $customer?->full_name ?? 'Unknown',
                'round_trip_count' => count($roundTripPatterns),
                'patterns' => $roundTripPatterns,
                'recommendation' => 'Review currency flow patterns for potential layering',
            ]
        );
    }

    /**
     * Detect round-trip patterns in transactions.
     *
     * @return array Array of detected round-trip patterns
     */
    protected function detectRoundTrips($transactions): array
    {
        $patterns = [];

        // Group transactions by currency
        $byCurrency = $transactions->groupBy('currency_code');

        foreach ($byCurrency as $currencyCode => $currencyTxns) {
            $sells = $currencyTxns->filter(fn ($t) => $t->type->value === 'Sell');
            $buys = $currencyTxns->filter(fn ($t) => $t->type->value === 'Buy');

            if ($sells->isEmpty() || $buys->isEmpty()) {
                continue;
            }

            // Look for sell followed by buy of same currency within time window
            foreach ($sells as $sell) {
                foreach ($buys as $buy) {
                    // Buy must come after Sell
                    if ($buy->created_at->lte($sell->created_at)) {
                        continue;
                    }

                    $hoursDiff = $sell->created_at->diffInHours($buy->created_at);

                    // Check if within time window
                    if ($hoursDiff > self::TIME_WINDOW_HOURS) {
                        continue;
                    }

                    // Calculate round-trip amount (use smaller of sell/buy foreign amount)
                    $sellForeign = ltrim((string) $sell->amount_foreign, '-');
                    $buyForeign = ltrim((string) $buy->amount_foreign, '-');
                    $roundTripAmount = $this->math->compare($sellForeign, $buyForeign) <= 0
                        ? $sellForeign
                        : $buyForeign;

                    // Only flag if above threshold
                    if ($this->math->compare((string) $roundTripAmount, $this->thresholdService->getRoundTripThreshold()) < 0) {
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
