<?php

namespace App\Services\Reporting;

use App\Models\Customer;
use App\Services\System\MathService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Customer Report Service
 *
 * Generates transaction reports and analytics for customers.
 * Handles stats calculation, chart data, and export data preparation.
 */
class CustomerReportService
{
    public function __construct(
        protected MathService $mathService,
    ) {}

    /**
     * Apply date range filters using the model scope when both bounds are present.
     */
    private function applyDateRangeFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['date_from']) && ! empty($filters['date_to'])) {
            $query->forDateRange($filters['date_from'], $filters['date_to']);

            return;
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
    }

    /**
     * Calculate statistics for customer transactions.
     *
     * @param  array  $filters  Date range and other filters
     * @return array Statistics summary
     */
    public function calculateStats(Customer $customer, array $filters): array
    {
        $baseQuery = $customer->transactions()->completed();
        $this->applyDateRangeFilters($baseQuery, $filters);

        $buyVolume = (clone $baseQuery)->buy()->sum('amount_local');
        $sellVolume = (clone $baseQuery)->sell()->sum('amount_local');
        $buyCount = (clone $baseQuery)->buy()->count();
        $sellCount = (clone $baseQuery)->sell()->count();
        $totalCount = $baseQuery->count();
        $totalVolume = $this->mathService->add($buyVolume, $sellVolume);

        return [
            'total_count' => $totalCount,
            'buy_count' => $buyCount,
            'sell_count' => $sellCount,
            'buy_volume' => $buyVolume,
            'sell_volume' => $sellVolume,
            'total_volume' => $totalVolume,
            'avg_transaction' => $totalCount > 0 ? $this->mathService->divide($totalVolume, (string) $totalCount) : '0',
            'first_transaction' => $baseQuery->min('created_at'),
            'last_transaction' => $baseQuery->max('created_at'),
        ];
    }

    /**
     * Calculate chart data for customer transactions (last 12 months).
     *
     * @param  array  $filters  Date range and other filters
     * @return array Chart labels and data
     */
    public function calculateChartData(Customer $customer, array $filters): array
    {
        $baseQuery = $customer->transactions()->completed();
        $this->applyDateRangeFilters($baseQuery, $filters);

        $buyTransactions = (clone $baseQuery)
            ->buy()
            ->get(['created_at', 'amount_local']);

        $sellTransactions = (clone $baseQuery)
            ->sell()
            ->get(['created_at', 'amount_local']);

        // Get last 12 months of labels
        $chartLabels = [];
        $chartBuyData = [];
        $chartSellData = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $chartLabels[] = $date->format('M Y');

            $buyTotal = $buyTransactions
                ->filter(fn ($t) => $t->created_at->year === $date->year && $t->created_at->month === $date->month)
                ->sum('amount_local');

            $sellTotal = $sellTransactions
                ->filter(fn ($t) => $t->created_at->year === $date->year && $t->created_at->month === $date->month)
                ->sum('amount_local');

            $chartBuyData[] = $buyTotal ?: 0;
            $chartSellData[] = $sellTotal ?: 0;
        }

        return [
            'chartLabels' => $chartLabels,
            'chartBuyData' => $chartBuyData,
            'chartSellData' => $chartSellData,
        ];
    }

    /**
     * Prepare transaction data for export.
     *
     * @return array Export data
     */
    public function prepareExportData(Collection $transactions): array
    {
        return $transactions->map(function ($transaction) {
            return [
                'Transaction ID' => $transaction->id,
                'Date' => $transaction->created_at->format('Y-m-d H:i:s'),
                'Type' => $transaction->type->label(),
                'Currency' => $transaction->currency_code,
                'Foreign Amount' => $transaction->amount_foreign,
                'MYR Amount' => $transaction->amount_local,
                'Rate' => $transaction->rate,
                'Status' => $transaction->status->label(),
                'Processed By' => $transaction->user?->name ?? 'N/A',
                'Purpose' => $transaction->purpose ?? 'N/A',
                'Source of Funds' => $transaction->source_of_funds ?? 'N/A',
                'CDD Level' => $transaction->cdd_level?->label() ?? 'N/A',
            ];
        })->toArray();
    }

    /**
     * Get transaction summary for customer history.
     *
     * @return array Summary with stats and chart data
     */
    public function getTransactionSummary(Customer $customer, array $filters): array
    {
        return [
            'stats' => $this->calculateStats($customer, $filters),
            'chart' => $this->calculateChartData($customer, $filters),
        ];
    }
}
