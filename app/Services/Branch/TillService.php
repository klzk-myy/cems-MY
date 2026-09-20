<?php

namespace App\Services\Branch;

use App\Enums\TransactionType;
use App\Models\Currency;
use App\Models\TillBalance;
use App\Services\System\MathService;
use Illuminate\Support\Collection;

/**
 * Till Service
 *
 * Handles till (counter) operations including balance management,
 * variance calculation, and reconciliation.
 */
class TillService
{
    public function __construct(
        protected MathService $mathService,
    ) {}

    /**
     * Calculate sum of transaction amounts using MathService for precision.
     */
    public function calculateTransactionSum(Collection $transactions, TransactionType $type): string
    {
        $sum = '0';
        foreach ($transactions->where('type', $type) as $transaction) {
            $sum = $this->mathService->add($sum, (string) $transaction->amount_myr);
        }

        return $sum;
    }

    /**
     * Calculate expected closing balance based on opening balance and net flow.
     *
     * @param  string  $netFlow  (positive for buy, negative for sell)
     */
    public function calculateExpectedClosing(string $openingBalance, string $netFlow): string
    {
        return $this->mathService->add($openingBalance, $netFlow);
    }

    /**
     * Calculate variance between actual and expected closing balance.
     */
    public function calculateVariance(string $actualClosing, string $expectedClosing): string
    {
        return $this->mathService->subtract($actualClosing, $expectedClosing);
    }

    /**
     * Generate reconciliation data for a till across all of its currency
     * balances.
     *
     * A till has one TillBalance row per currency for the date, so the caller
     * passes the full collection. The returned shape matches what
     * resources/views/stock-cash/reconciliation.blade.php renders:
     * opening_myr/opening_fcy/opener_name, a per-currency
     * currency_reconciliation list (currency_code/expected/actual/variance),
     * total_myr_variance/total_fcy_variance and is_balanced.
     *
     * @param  Collection<int, TillBalance>  $tillBalances
     * @return array<string, mixed>
     */
    public function generateReconciliation(Collection $tillBalances): array
    {
        $currencyReconciliation = $tillBalances->map(function (TillBalance $balance): array {
            $expected = $this->expectedClosingForBalance($balance);
            // Open tills have no closing count yet: display the expected value
            // as the placeholder so variance reads zero until the till closes.
            $actual = $balance->closing_balance !== null
                ? (string) $balance->closing_balance
                : $expected;
            $variance = $this->mathService->subtract($actual, $expected);

            return [
                'currency_code' => $balance->currency_code,
                'expected' => $expected,
                'actual' => $actual,
                'variance' => $variance,
            ];
        })->values()->all();

        $myrBalance = $tillBalances->firstWhere('currency_code', Currency::baseCurrency());
        $fcyBalances = $tillBalances->reject(fn (TillBalance $balance) => $balance->currency_code === Currency::baseCurrency());

        $openingMyr = $myrBalance ? (string) $myrBalance->opening_balance : '0';
        $openingFcy = $fcyBalances->reduce(
            fn (string $carry, TillBalance $balance) => $this->mathService->add($carry, (string) $balance->opening_balance),
            '0'
        );

        $totalMyrVariance = '0';
        $totalFcyVariance = '0';
        foreach ($currencyReconciliation as $row) {
            if ($row['currency_code'] === Currency::baseCurrency()) {
                $totalMyrVariance = $this->mathService->add($totalMyrVariance, $row['variance']);
            } else {
                $totalFcyVariance = $this->mathService->add($totalFcyVariance, $row['variance']);
            }
        }

        return [
            'opening_myr' => $openingMyr,
            'opening_fcy' => $openingFcy,
            'opener_name' => ($myrBalance ?? $tillBalances->first())?->opener?->username,
            'currency_reconciliation' => $currencyReconciliation,
            'total_myr_variance' => $totalMyrVariance,
            'total_fcy_variance' => $totalFcyVariance,
            'is_balanced' => $this->mathService->compare($totalMyrVariance, '0') === 0
                && $this->mathService->compare($totalFcyVariance, '0') === 0,
        ];
    }

    /**
     * Expected closing balance for one till-balance row.
     *
     * MYR balances track net MYR movement in transaction_total_myr (buys subtract,
     * sells add). FCY balances track position in buy_quantity /
     * sell_quantity. The formula lives on the model so every close
     * path (counter, emergency, EOD) computes the same expected value.
     */
    public function expectedClosingForBalance(TillBalance $balance): string
    {
        return $balance->getExpectedBalance();
    }

    /**
     * Get today's MYR cash in hand from till balances.
     *
     * @param  int|null  $branchId  Optional branch filter
     */
    public function getMyrCashInHand(?int $branchId = null): string
    {
        $query = TillBalance::whereDate('date', now()->toDateString())
            ->where('currency_code', Currency::baseCurrency());

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        $myrBalances = $query->get();
        $myrCashInHand = '0';
        foreach ($myrBalances as $balance) {
            // Use closing_balance if closed, otherwise opening_balance
            $balanceAmount = $balance->closed_at
                ? ($balance->closing_balance ?? '0')
                : ($balance->opening_balance ?? '0');
            $myrCashInHand = $this->mathService->add($myrCashInHand, (string) $balanceAmount);
        }

        return $myrCashInHand;
    }
}
