<?php

namespace App\Services\Branch;

use App\Models\Currency;
use App\Models\TillBalance;
use App\Services\Branch\DTOs\HandoverVarianceResult;
use App\Support\BcmathHelper;
use Illuminate\Support\Collection;

/**
 * Pure variance computation for counter handover: compares each physical
 * count against the expected till balance (opening + net foreign movement)
 * and converts foreign variances to MYR using the supplied per-unit rates.
 * No database access — all inputs are passed in.
 */
final class HandoverVarianceCalculator
{
    /**
     * @param  array<int, array{currency_id: mixed, quantity: string}>  $physicalCounts
     * @param  array<mixed, string>  $currencies  input currency_id => currency_code
     * @param  Collection<string, TillBalance>  $openBalances  keyed by currency_code
     * @param  Collection<string, TillBalance>  $closedBalances  keyed by currency_code
     * @param  array<string, string>  $exchangeRates  currency_code => per-unit sell rate
     */
    public function compute(
        array $physicalCounts,
        array $currencies,
        Collection $openBalances,
        Collection $closedBalances,
        array $exchangeRates,
    ): HandoverVarianceResult {
        $perCurrency = [];
        $totalMyr = '0';

        foreach ($physicalCounts as $count) {
            $currencyCode = $currencies[$count['currency_id']] ?? null;
            if (! $currencyCode) {
                continue;
            }

            $closingBalance = $count['quantity'];
            $balanceRow = $openBalances->get($currencyCode) ?? $closedBalances->get($currencyCode);

            if ($balanceRow) {
                // Expected = opening + buy_quantity - sell_quantity
                $netForeign = BcmathHelper::subtract(
                    (string) ($balanceRow->buy_quantity ?? '0'),
                    (string) ($balanceRow->sell_quantity ?? '0')
                );
                $expected = BcmathHelper::add((string) $balanceRow->opening_balance, $netForeign);
                $variance = BcmathHelper::subtract((string) $closingBalance, $expected);
            } else {
                // No prior balance; variance is zero (new currency added)
                $variance = '0.0000';
            }

            $perCurrency[$currencyCode] = $variance;

            // Convert to MYR for the aggregate total (rate falls back to 1).
            $totalMyr = $currencyCode === Currency::baseCurrency()
                ? BcmathHelper::add($totalMyr, $variance)
                : BcmathHelper::add($totalMyr, BcmathHelper::multiply($variance, $exchangeRates[$currencyCode] ?? '1'));
        }

        $notes = 'Variance during handover: ';
        foreach ($perCurrency as $code => $variance) {
            $notes .= "{$code}: {$variance}; ";
        }

        return new HandoverVarianceResult($perCurrency, $totalMyr, $notes);
    }
}
