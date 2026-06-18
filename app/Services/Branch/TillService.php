<?php

namespace App\Services\Branch;

use App\Enums\TransactionType;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Services\MathService;
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
            $sum = $this->mathService->add($sum, (string) $transaction->amount_local);
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
     * Calculate net flow from transactions for a till.
     *
     * @return string Net flow (buy - sell)
     */
    public function calculateNetFlow(string $tillId, string $currencyCode, ?string $date = null): string
    {
        $date = $date ?? now()->toDateString();

        $netFlow = Transaction::where('till_id', $tillId)
            ->where('currency_code', $currencyCode)
            ->whereDate('created_at', $date)
            ->selectRaw("SUM(CASE WHEN type='Buy' THEN amount_local ELSE -amount_local END) as net")
            ->value('net') ?? '0';

        return (string) $netFlow;
    }

    /**
     * Generate reconciliation data for a till.
     */
    public function generateReconciliation(TillBalance $tillBalance, Collection $transactions): array
    {
        // Calculate summary statistics using MathService for precision
        $buyAmount = $this->calculateTransactionSum($transactions, TransactionType::Buy);
        $sellAmount = $this->calculateTransactionSum($transactions, TransactionType::Sell);
        $netFlow = $this->mathService->subtract($buyAmount, $sellAmount);

        $summary = [
            'opening_balance' => $tillBalance->opening_balance,
            'total_buy_count' => $transactions->where('type', TransactionType::Buy)->count(),
            'total_buy_amount' => $buyAmount,
            'total_sell_count' => $transactions->where('type', TransactionType::Sell)->count(),
            'total_sell_amount' => $sellAmount,
            'total_transactions' => $transactions->count(),
            'net_flow' => $netFlow,
        ];

        // Calculate expected closing balance
        $expectedClosing = $this->calculateExpectedClosing(
            (string) $tillBalance->opening_balance,
            $netFlow
        );

        // Get actual closing balance (if till is closed) - keep as string for precision
        $actualClosing = $tillBalance->closing_balance
            ? (string) $tillBalance->closing_balance
            : null;

        // Calculate variance
        $variance = $actualClosing !== null
            ? $this->calculateVariance($actualClosing, $expectedClosing)
            : null;

        return [
            'opening_balance' => $summary['opening_balance'],
            'purchases' => [
                'count' => $summary['total_buy_count'],
                'total' => $summary['total_buy_amount'],
            ],
            'sales' => [
                'count' => $summary['total_sell_count'],
                'total' => $summary['total_sell_amount'],
            ],
            'expected_closing' => $expectedClosing,
            'actual_closing' => $actualClosing,
            'variance' => $variance,
            'is_closed' => $tillBalance->closed_at !== null,
        ];
    }

    /**
     * Get today's MYR cash in hand from till balances.
     *
     * @param  int|null  $branchId  Optional branch filter
     */
    public function getMyrCashInHand(?int $branchId = null): string
    {
        $query = TillBalance::whereDate('date', now()->toDateString())
            ->where('currency_code', 'MYR');

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
