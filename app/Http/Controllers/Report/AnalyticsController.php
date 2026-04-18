<?php

namespace App\Http\Controllers\Report;

use App\Enums\CddLevel;
use App\Enums\ComplianceFlagType;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\FlaggedTransaction;
use App\Models\Transaction;
use App\Services\MathService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends \App\Http\Controllers\Controller
{
    protected MathService $mathService;

    public function __construct(MathService $mathService)
    {
        $this->mathService = $mathService;
    }

    /**
     * Monthly transaction trends
     */
    public function monthlyTrends(Request $request)
    {
        $this->requireManagerOrAdmin();

        $year = $request->input('year', now()->year);
        $currency = $request->input('currency', 'all');

        // Query monthly data
        $query = Transaction::whereYear('created_at', $year)
            ->where('status', TransactionStatus::Completed);

        if ($currency !== 'all') {
            $query->where('currency_code', $currency);
        }

        $monthlyData = $query->select(
            DB::raw('MONTH(created_at) as month'),
            DB::raw('COUNT(*) as count'),
            DB::raw("SUM(CASE WHEN type = '".TransactionType::Buy->value."' THEN amount_local ELSE 0 END) as buy_volume"),
            DB::raw("SUM(CASE WHEN type = '".TransactionType::Sell->value."' THEN amount_local ELSE 0 END) as sell_volume"),
            DB::raw('SUM(amount_local) as total_volume')
        )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Calculate trends
        $trends = $this->calculateTrends($monthlyData);

        // Get available currencies
        $currencies = Currency::where('is_active', true)->pluck('code');

        return view('reports.monthly-trends', compact('monthlyData', 'trends', 'year', 'currency', 'currencies'));
    }

    /**
     * Calculate month-over-month trends
     */
    protected function calculateTrends($data): array
    {
        $trends = [];
        $previousVolume = null;

        foreach ($data as $row) {
            $trend = null;
            if ($previousVolume !== null && $previousVolume > 0) {
                $diff = $this->mathService->subtract((string) $row->total_volume, (string) $previousVolume);
                $trend = $this->mathService->multiply(
                    $this->mathService->divide($diff, (string) $previousVolume),
                    '100'
                );
            }
            $trends[$row->month] = [
                'volume' => $row->total_volume,
                'trend' => $trend,
                'direction' => $this->mathService->compare($trend, '0') > 0
                    ? 'up'
                    : ($this->mathService->compare($trend, '0') < 0 ? 'down' : 'neutral'),
            ];
            $previousVolume = $row->total_volume;
        }

        return $trends;
    }

    /**
     * Profitability analysis by currency
     */
    public function profitability(Request $request)
    {
        $this->requireManagerOrAdmin();

        $startDate = $request->input('start_date', now()->subMonth()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->subMonth()->endOfMonth()->toDateString());

        // Get currency positions with profit/loss
        $positions = CurrencyPosition::with('currency')
            ->get()
            ->map(function ($position) use ($startDate, $endDate) {
                $stats = $this->calculateCurrencyProfitability(
                    $position->currency_code,
                    $startDate,
                    $endDate
                );

                return [
                    'currency' => $position->currency,
                    'balance' => $position->balance,
                    'avg_cost_rate' => $position->avg_cost_rate,
                    'current_rate' => $this->getCurrentRate($position->currency_code),
                    'unrealized_pnl' => $stats['unrealized_pnl'],
                    'realized_pnl' => $stats['realized_pnl'],
                    'total_pnl' => $stats['total_pnl'],
                    'buy_volume' => $stats['buy_volume'],
                    'sell_volume' => $stats['sell_volume'],
                ];
            });

        // Calculate totals
        $totals = [
            'total_unrealized' => $positions->sum('unrealized_pnl'),
            'total_realized' => $positions->sum('realized_pnl'),
            'total_pnl' => $positions->sum('total_pnl'),
        ];

        return view('reports.profitability', compact('positions', 'totals', 'startDate', 'endDate'));
    }

    /**
     * Calculate profitability for a currency
     */
    protected function calculateCurrencyProfitability(string $currencyCode, string $startDate, string $endDate): array
    {
        $position = CurrencyPosition::where('currency_code', $currencyCode)->first();

        if (! $position) {
            return [
                'unrealized_pnl' => 0,
                'realized_pnl' => 0,
                'total_pnl' => 0,
                'buy_volume' => 0,
                'sell_volume' => 0,
            ];
        }

        // Current market rate
        $currentRate = $this->getCurrentRate($currencyCode);

        // Unrealized P&L (on current balance)
        $avgCost = (string) $position->avg_cost_rate;
        $balance = (string) $position->balance;
        $unrealizedPnl = $this->mathService->multiply(
            $this->mathService->subtract((string) $currentRate, $avgCost),
            $balance
        );

        // Realized P&L (from sell transactions in period)
        $sells = Transaction::where('currency_code', $currencyCode)
            ->where('type', TransactionType::Sell)
            ->where('status', TransactionStatus::Completed)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $realizedPnl = '0';
        foreach ($sells as $sell) {
            $sellRate = (string) $sell->rate;
            $sellAmount = (string) $sell->amount_foreign;
            // Gain = (sell rate - avg cost) * amount
            $gain = $this->mathService->multiply(
                $this->mathService->subtract($sellRate, $avgCost),
                $sellAmount
            );
            $realizedPnl = $this->mathService->add((string) $realizedPnl, $gain);
        }

        // Buy volume in period
        $buyVolume = Transaction::where('currency_code', $currencyCode)
            ->where('type', TransactionType::Buy)
            ->where('status', TransactionStatus::Completed)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount_local');

        // Sell volume in period
        $sellVolume = Transaction::where('currency_code', $currencyCode)
            ->where('type', TransactionType::Sell)
            ->where('status', TransactionStatus::Completed)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount_local');

        return [
            'unrealized_pnl' => $unrealizedPnl,
            'realized_pnl' => $realizedPnl,
            'total_pnl' => $this->mathService->add($unrealizedPnl, $realizedPnl),
            'buy_volume' => $buyVolume,
            'sell_volume' => $sellVolume,
        ];
    }

    /**
     * Get current exchange rate
     */
    protected function getCurrentRate(string $currencyCode): float
    {
        $rate = ExchangeRate::where('currency_code', $currencyCode)
            ->where('is_active', true)
            ->latest()
            ->first();

        return $rate ? (float) $rate->rate : 0;
    }

    /**
     * Customer transaction analysis
     */
    public function customerAnalysis(Request $request)
    {
        $this->requireManagerOrAdmin();

        $topCustomers = Customer::withCount('transactions')
            ->withSum('transactions', 'amount_local')
            ->orderBy('transactions_count', 'desc')
            ->take(50)
            ->get()
            ->map(function ($customer) {
                return [
                    'customer' => $customer,
                    'transaction_count' => $customer->transactions_count,
                    'total_volume' => $customer->transactions_sum_amount_local,
                    'avg_transaction' => $customer->transactions_count > 0
                        ? $this->mathService->divide(
                            (string) $customer->transactions_sum_amount_local,
                            (string) $customer->transactions_count
                        )
                        : '0',
                    'first_transaction' => $customer->transactions()->min('created_at'),
                    'last_transaction' => $customer->transactions()->max('created_at'),
                    'risk_rating' => $customer->risk_rating,
                ];
            });

        // Risk distribution
        $riskDistribution = Customer::select('risk_rating', DB::raw('COUNT(*) as count'))
            ->groupBy('risk_rating')
            ->get();

        return view('reports.customer-analysis', compact('topCustomers', 'riskDistribution'));
    }

    /**
     * Compliance summary report
     */
    public function complianceSummary(Request $request)
    {
        $this->requireManagerOrAdmin();

        $startDate = $request->input('start_date', today()->subMonth()->toDateString());
        $endDate = $request->input('end_date', today()->toDateString());

        // Flagged transactions
        $flaggedStats = FlaggedTransaction::whereBetween('created_at', [$startDate, $endDate])
            ->select('flag_type', DB::raw('COUNT(*) as count'))
            ->groupBy('flag_type')
            ->get();

        // Large transactions (≥RM 50,000)
        $largeTransactions = Transaction::where('amount_local', '>=', 50000)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        // EDD required count
        $eddCount = Transaction::where('cdd_level', CddLevel::Enhanced)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        // Suspicious activity
        $suspiciousCount = FlaggedTransaction::whereIn('flag_type', [ComplianceFlagType::Structuring, ComplianceFlagType::SanctionMatch])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        return view('reports.compliance-summary', compact(
            'flaggedStats',
            'largeTransactions',
            'eddCount',
            'suspiciousCount',
            'startDate',
            'endDate'
        ));
    }
}
