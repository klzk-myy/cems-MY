<?php

namespace App\Services\Compliance\Monitors;

use App\Enums\FindingSeverity;
use App\Enums\FindingType;
use App\Enums\TransactionStatus;
use App\Models\Customer;
use App\Models\Transaction;

/**
 * Monitor for detecting unusual currency round-tripping patterns.
 * Flags when the same currency goes out (Sell) and comes back in (Buy) within a short period.
 */
class CurrencyFlowMonitor extends BaseMonitor
{
    public const LOOKBACK_DAYS = 7;

    public const ROUND_TRIP_THRESHOLD = '5000';

    public const TIME_WINDOW_HOURS = 72;

    protected function getFindingType(): FindingType
    {
        return FindingType::CurrencyFlowAnomaly;
    }

    public function run(): array
    {
        $findings = [];
        $cutoffTime = now()->subDays(self::LOOKBACK_DAYS);

        // Get all customers with transactions in the lookback period
        $customerIds = Transaction::where('created_at', '>=', $cutoffTime)
            ->where('status', '!=', TransactionStatus::Cancelled->value)
            ->distinct('customer_id')
            ->pluck('customer_id');

        foreach ($customerIds as $customerId) {
            $finding = $this->checkCustomerRoundTripping($customerId);
            if ($finding !== null) {
                $findings[] = $finding;
            }
        }

        return $findings;
    }

    /**
     * Check a customer for currency round-tripping patterns.
     */
    protected function checkCustomerRoundTripping(int $customerId): ?array
    {
        $cutoffTime = now()->subHours(self::TIME_WINDOW_HOURS);

        // Get recent transactions
        $recentTransactions = Transaction::where('customer_id', $customerId)
            ->where('created_at', '>=', $cutoffTime)
            ->where('status', '!=', TransactionStatus::Cancelled->value)
            ->orderBy('created_at', 'asc')
            ->get();

        if ($recentTransactions->count() < 2) {
            return null;
        }

        // Group by currency to find round-trip patterns
        $roundTripPatterns = $this->detectRoundTrips($recentTransactions);

        if (empty($roundTripPatterns)) {
            return null;
        }

        $customer = Customer::find($customerId);

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
                    if ($this->math->compare((string) $roundTripAmount, self::ROUND_TRIP_THRESHOLD) < 0) {
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
