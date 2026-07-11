<?php

namespace App\Http\Controllers\Report;

use App\Enums\CddLevel;
use App\Enums\ComplianceFlagType;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\FlaggedTransaction;
use App\Models\Transaction;
use App\Services\Reporting\TransactionReportQuery;
use App\Services\System\MathService;
use App\Support\DbDate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function __construct(
        protected MathService $mathService,
    ) {}

    /**
     * Monthly transaction trends
     */
    public function monthlyTrends(Request $request): View
    {
        $this->requireManagerOrAdmin();

        $year = $request->input('year', now()->year);
        $currency = $request->input('currency', 'all');

        $query = Transaction::whereYear('created_at', $year)
            ->completed();

        if ($currency !== 'all') {
            $query->where('currency_code', $currency);
        }

        $monthColumn = DbDate::monthColumn('created_at');

        $monthlyData = app(TransactionReportQuery::class)
            ->buySellSummary(
                $query->select(DB::raw("{$monthColumn} as month")),
                DB::raw($monthColumn),
                'amount_local'
            )
            ->map(function ($row) {
                $volume = $this->mathService->add((string) $row->buy_volume, (string) $row->sell_volume);
                $count = (int) $row->buy_count + (int) $row->sell_count;

                return [
                    'month' => (int) $row->month,
                    'count' => $count,
                    'volume' => $volume,
                    'avg_value' => $count > 0
                        ? $this->mathService->divide($volume, (string) $count)
                        : '0',
                    'mom_change' => null,
                ];
            })
            ->values()
            ->all();

        $previousVolume = null;
        foreach ($monthlyData as $index => $data) {
            if ($previousVolume !== null && $this->mathService->compare($previousVolume, '0') > 0) {
                $change = $this->mathService->multiply(
                    $this->mathService->divide(
                        $this->mathService->subtract($data['volume'], $previousVolume),
                        $previousVolume
                    ),
                    '100'
                );
                $monthlyData[$index]['mom_change'] = $change;
            }
            $previousVolume = $data['volume'];
        }

        $monthlyData = collect($monthlyData);

        $trends = $monthlyData->map(fn ($data) => [
            'month' => $data['month'],
            'count' => $data['count'],
            'volume' => $data['volume'],
            'change' => $data['mom_change'],
        ])->all();

        $currencies = Currency::where('is_active', true)->pluck('code');

        return view('reports.monthly-trends', compact('monthlyData', 'trends', 'year', 'currency', 'currencies'));
    }

    /**
     * Profitability analysis by currency
     */
    public function profitability(Request $request): View
    {
        $this->requireManagerOrAdmin();

        $startDate = $request->input('start_date', now()->subMonth()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->subMonth()->endOfMonth()->toDateString());

        $positionModels = CurrencyPosition::with('currency')->get();
        $currencyCodes = $positionModels->pluck('currency_code')->unique()->values()->toArray();
        $rates = $this->getCurrentRates($currencyCodes);

        $allSells = app(TransactionReportQuery::class)
            ->completed()
            ->forDateRange($startDate, $endDate)
            ->sell()
            ->whereIn('currency_code', $currencyCodes)
            ->select('currency_code', 'rate', 'amount_foreign', 'amount_local')
            ->get()
            ->groupBy('currency_code');

        $positions = $positionModels->map(function ($position) use ($rates, $allSells) {
            $currentRate = $rates[$position->currency_code] ?? '0';
            $avgCost = (string) $position->average_cost;
            $balance = (string) $position->quantity;

            $unrealizedPnl = $this->mathService->multiply(
                $this->mathService->subtract($currentRate, $avgCost),
                $balance
            );

            $sells = $allSells->get($position->currency_code, collect());
            $realizedPnl = '0';
            foreach ($sells as $sell) {
                $gain = $this->mathService->multiply(
                    $this->mathService->subtract((string) $sell->rate, $avgCost),
                    (string) $sell->amount_foreign
                );
                $realizedPnl = $this->mathService->add($realizedPnl, $gain);
            }

            return [
                'currency' => $position->currency?->display_name ?? $position->currency_code,
                'position' => $balance,
                'avg_buy_rate' => $avgCost,
                'avg_sell_rate' => $currentRate,
                'realized_pnl' => $realizedPnl,
                'unrealized_pnl' => $unrealizedPnl,
                'total_pnl' => $this->mathService->add($unrealizedPnl, $realizedPnl),
            ];
        });

        $totals = $positions->reduce(function (array $carry, array $position): array {
            return [
                'realized_pnl' => $this->mathService->add($carry['realized_pnl'], $position['realized_pnl']),
                'unrealized_pnl' => $this->mathService->add($carry['unrealized_pnl'], $position['unrealized_pnl']),
                'total_pnl' => $this->mathService->add($carry['total_pnl'], $position['total_pnl']),
            ];
        }, [
            'realized_pnl' => '0',
            'unrealized_pnl' => '0',
            'total_pnl' => '0',
        ]);

        return view('reports.profitability', compact('positions', 'totals', 'startDate', 'endDate'));
    }

    /**
     * Get current exchange rates for multiple currencies (batch)
     *
     * @return array<string, string>
     */
    protected function getCurrentRates(array $currencyCodes): array
    {
        return ExchangeRate::whereIn('currency_code', $currencyCodes)
            ->orderBy('fetched_at', 'desc')
            ->get()
            ->unique('currency_code')
            ->keyBy('currency_code')
            ->map(fn ($rate) => (string) $rate->rate_sell)
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
                $riskRating = $customer->risk_rating;

                return [
                    'name' => $customer->full_name,
                    'customer_code' => sprintf('CUST-%06d', $customer->id),
                    'id_number' => $customer->id_number_masked,
                    'transaction_count' => $customer->transactions_count,
                    'total_volume' => $customer->transactions_sum_amount_local,
                    'avg_value' => $customer->transactions_count > 0
                        ? $this->mathService->divide(
                            (string) $customer->transactions_sum_amount_local,
                            (string) $customer->transactions_count
                        )
                        : '0',
                    'first_transaction' => $customer->transactions_min_created_at,
                    'last_transaction' => $customer->transactions_max_created_at,
                    'risk_rating' => $this->normalizeRiskRating($riskRating),
                ];
            });

        // Risk distribution
        $riskCounts = Customer::select('risk_rating', DB::raw('COUNT(*) as count'))
            ->groupBy('risk_rating')
            ->get()
            ->mapWithKeys(function ($row) {
                $rating = $this->normalizeRiskRating($row->risk_rating);

                return [$rating => $row->count];
            });

        $riskDistribution = [
            'total' => $riskCounts->sum(),
            'high' => $riskCounts->get('high', 0),
            'medium' => $riskCounts->get('medium', 0),
            'low' => $riskCounts->get('low', 0),
        ];

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
        $startAt = Carbon::parse($startDate)->startOfDay();
        $endAt = Carbon::parse($endDate)->endOfDay();

        // Flagged transactions
        $flaggedStatsRaw = FlaggedTransaction::whereBetween('created_at', [$startAt, $endAt])
            ->select('flag_type', DB::raw('COUNT(*) as count'))
            ->groupBy('flag_type')
            ->get();

        $flaggedStats = [
            'total' => $flaggedStatsRaw->sum('count'),
            'by_type' => $flaggedStatsRaw->mapWithKeys(function ($row) {
                $type = $row->flag_type instanceof \BackedEnum
                    ? $row->flag_type->value
                    : (string) $row->flag_type;

                return [$type => $row->count];
            }),
        ];

        // EDD required count
        $eddCount = Transaction::where('cdd_level', CddLevel::Enhanced)
            ->whereBetween('created_at', [$startAt, $endAt])
            ->count();

        // Suspicious activity
        $suspiciousCount = FlaggedTransaction::whereIn('flag_type', [ComplianceFlagType::Structuring, ComplianceFlagType::SanctionMatch])
            ->whereBetween('created_at', [$startAt, $endAt])
            ->count();

        return view('reports.compliance-summary', compact(
            'flaggedStats',
            'eddCount',
            'suspiciousCount',
            'startDate',
            'endDate'
        ));
    }

    /**
     * Normalize a risk rating value to a lowercase string.
     */
    protected function normalizeRiskRating(mixed $riskRating): string
    {
        $value = $riskRating instanceof \BackedEnum
            ? $riskRating->value
            : (string) $riskRating;

        return $value !== '' ? strtolower($value) : 'unknown';
    }
}
