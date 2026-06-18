<?php

namespace App\Http\Controllers\Report;

use App\Enums\CddLevel;
use App\Enums\ComplianceFlagType;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\FlaggedTransaction;
use App\Models\Transaction;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    protected MathService $mathService;

    protected ThresholdService $thresholdService;

    public function __construct(MathService $mathService, ThresholdService $thresholdService)
    {
        $this->mathService = $mathService;
        $this->thresholdService = $thresholdService;
    }

    /**
     * Monthly transaction trends
     */
    public function monthlyTrends(Request $request): View
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
            DB::raw('SUM(CASE WHEN type = ? THEN amount_local ELSE 0 END) as buy_volume', [TransactionType::Buy->value]),
            DB::raw('SUM(CASE WHEN type = ? THEN amount_local ELSE 0 END) as sell_volume', [TransactionType::Sell->value]),
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
    protected function calculateTrends(array $data): array
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
    public function profitability(Request $request): View
    {
        $this->requireManagerOrAdmin();

        $startDate = $request->input('start_date', now()->subMonth()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->subMonth()->endOfMonth()->toDateString());

        // Get currency positions with profit/loss
        $positionModels = CurrencyPosition::with('currency')
            ->get();

        $currencyCodes = $positionModels->pluck('currency_code')->unique();
        $rates = $this->getCurrentRates($currencyCodes);

        $positions = $positionModels->map(function ($position) use ($startDate, $endDate, $rates) {
            $currentRate = $rates[$position->currency_code] ?? 0;
            $stats = $this->calculateCurrencyProfitability(
                $position,
                $currentRate,
                $startDate,
                $endDate
            );

            return [
                'currency' => $position->currency,
                'balance' => $position->balance,
                'avg_cost_rate' => $position->avg_cost_rate,
                'current_rate' => $currentRate,
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
    protected function calculateCurrencyProfitability(CurrencyPosition $position, float $currentRate, string $startDate, string $endDate): array
    {
        $avgCost = (string) $position->avg_cost_rate;
        $balance = (string) $position->balance;
        $currencyCode = $position->currency_code;

        // Unrealized P&L (on current balance)
        $unrealizedPnl = $this->mathService->multiply(
            $this->mathService->subtract((string) $currentRate, $avgCost),
            $balance
        );

        // Sell transactions in period (used for both realized P&L and volume)
        $sells = Transaction::where('currency_code', $currencyCode)
            ->where('type', TransactionType::Sell)
            ->where('status', TransactionStatus::Completed)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select('rate', 'amount_foreign', 'amount_local')
            ->get();

        $realizedPnl = '0';
        $sellVolume = '0';
        foreach ($sells as $sell) {
            $sellRate = (string) $sell->rate;
            $sellAmount = (string) $sell->amount_foreign;
            $gain = $this->mathService->multiply(
                $this->mathService->subtract($sellRate, $avgCost),
                $sellAmount
            );
            $realizedPnl = $this->mathService->add((string) $realizedPnl, $gain);
            $sellVolume = $this->mathService->add($sellVolume, (string) $sell->amount_local);
        }

        // Buy volume in period
        $buyVolume = Transaction::where('currency_code', $currencyCode)
            ->where('type', TransactionType::Buy)
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
     * Get current exchange rate for a single currency
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
     * Get current exchange rates for multiple currencies (batch)
     *
     * @return array<string, float>
     */
    protected function getCurrentRates(array $currencyCodes): array
    {
        return ExchangeRate::whereIn('currency_code', $currencyCodes)
            ->where('is_active', true)
            ->latest()
            ->get()
            ->keyBy('currency_code')
            ->map(fn ($rate) => (float) $rate->rate)
            ->toArray();
    }

    /**
     * Customer transaction analysis
     */
    public function customerAnalysis(Request $request): View
    {
        $this->requireManagerOrAdmin();

        $topCustomers = Customer::withCount('transactions')
            ->withSum('transactions', 'amount_local')
            ->withMin('transactions', 'created_at')
            ->withMax('transactions', 'created_at')
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
                    'first_transaction' => $customer->transactions_min_created_at,
                    'last_transaction' => $customer->transactions_max_created_at,
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
    public function complianceSummary(Request $request): View
    {
        $this->requireManagerOrAdmin();

        $startDate = $request->input('start_date', today()->subMonth()->toDateString());
        $endDate = $request->input('end_date', today()->toDateString());

        // Flagged transactions
        $flaggedStats = FlaggedTransaction::whereBetween('created_at', [$startDate, $endDate])
            ->select('flag_type', DB::raw('COUNT(*) as count'))
            ->groupBy('flag_type')
            ->get();

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
            'eddCount',
            'suspiciousCount',
            'startDate',
            'endDate'
        ));
    }
}
