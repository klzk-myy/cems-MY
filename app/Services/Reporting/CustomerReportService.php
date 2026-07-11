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

        $transactions = $baseQuery->get(['created_at', 'amount_local', 'type']);

        $volumes = app(TransactionReportQuery::class)->buySellVolumes($transactions);
        $totalCount = $transactions->count();
        $totalVolume = $this->mathService->add($volumes['buy_volume'], $volumes['sell_volume']);

        return [
            'total_count' => $totalCount,
            'buy_count' => $volumes['buy_count'],
            'sell_count' => $volumes['sell_count'],
            'buy_volume' => $volumes['buy_volume'],
            'sell_volume' => $volumes['sell_volume'],
            'total_volume' => $totalVolume,
            'avg_transaction' => $totalCount > 0 ? $this->mathService->divide($totalVolume, (string) $totalCount) : '0',
            'first_transaction' => $transactions->min('created_at'),
            'last_transaction' => $transactions->max('created_at'),
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

        $transactions = $baseQuery->get(['created_at', 'amount_local', 'type']);

        // Get last 12 months of labels
        $chartLabels = [];
        $chartBuyData = [];
        $chartSellData = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $chartLabels[] = $date->format('M Y');

            $monthTransactions = $transactions->filter(
                fn ($t) => $t->created_at->year === $date->year && $t->created_at->month === $date->month
            );

            $buyTotal = $monthTransactions->where('type', TransactionType::Buy)->sum('amount_local');
            $sellTotal = $monthTransactions->where('type', TransactionType::Sell)->sum('amount_local');

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
