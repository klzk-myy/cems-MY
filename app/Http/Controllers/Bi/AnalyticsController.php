<?php

namespace App\Http\Controllers\Bi;

use App\Enums\Permission;
use App\Http\Controllers\Concerns\RequiresPermission;
use App\Http\Controllers\Concerns\ResolvesBiFilters;
use App\Http\Controllers\Concerns\ResolvesBranchScope;
use App\Http\Controllers\Controller;
use App\Models\Bi\AnalyticsDailyCurrency;
use App\Models\Currency;
use App\Models\Customer;
use App\Services\Bi\AnalyticsSnapshotBuilder;
use App\Services\Bi\DemandForecaster;
use App\Services\Bi\KpiService;
use App\Support\ChartPalette;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * BI report pages: branch comparison, currency
 * deep-dive, operational SLA, and customer analytics — all reading the
 * analytics_* rollups through KpiService. Same filter conventions and
 * branch scoping as the BI dashboard.
 */
class AnalyticsController extends Controller
{
    use RequiresPermission;
    use ResolvesBiFilters;
    use ResolvesBranchScope;

    public function __construct(
        protected KpiService $kpiService,
        protected AnalyticsSnapshotBuilder $snapshotBuilder,
        protected DemandForecaster $forecaster,
    ) {}

    /**
     * Per-branch volume, margin and productivity comparison.
     */
    public function branches(Request $request): View
    {
        $this->requirePermission(Permission::ViewBiDashboard);
        $this->refreshTodaysRollups($this->snapshotBuilder);
        [$from, $to] = $this->resolveBiPeriod($request);

        $user = auth()->user();
        $branchId = $this->resolveReportBranchId($user, $request);

        // A branch-scoped viewer comparing "all branches" would still only
        // see their own row — scope the result so the table is honest.
        $rows = array_values(array_filter(
            $this->kpiService->branchComparison($from, $to),
            fn ($row) => $branchId === null || (int) $row['branch_id'] === $branchId
        ));

        return view('bi.branches', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'rows' => $rows,
            'comparisonChart' => [
                'labels' => array_column($rows, 'branch_name'),
                'datasets' => [
                    ['label' => 'Volume (MYR)', 'data' => array_column($rows, 'volume_myr'), 'backgroundColor' => '#3b82f6'],
                    ['label' => 'Realized margin (MYR)', 'data' => array_column($rows, 'realized_margin_myr'), 'backgroundColor' => '#d4a843'],
                ],
            ],
            'branches' => $this->branchSelectOptions($user),
            'branchId' => $branchId,
            'canSeeAllBranches' => $user->role->canManageAllBranches(),
        ]);
    }

    /**
     * Currency deep-dive: breakdown table, mix, and a per-currency trend.
     */
    public function currencies(Request $request): View
    {
        $this->requirePermission(Permission::ViewBiDashboard);
        $this->refreshTodaysRollups($this->snapshotBuilder);
        [$from, $to] = $this->resolveBiPeriod($request);

        $user = auth()->user();
        $branchId = $this->resolveReportBranchId($user, $request);

        $breakdown = $this->kpiService->currencyBreakdown($from, $to, $branchId);

        $currencyOptions = Currency::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->pluck('code', 'code');

        $selected = strtoupper((string) $request->input('currency', ''));
        if (! $currencyOptions->has($selected)) {
            $selected = (string) ($breakdown[0]['currency_code'] ?? $currencyOptions->keys()->first() ?? '');
        }

        $trend = $selected === ''
            ? ['labels' => [], 'buy' => [], 'sell' => [], 'margin' => []]
            : $this->kpiService->currencyTrend($from, $to, $branchId, $selected);

        return view('bi.currencies', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'rows' => $breakdown,
            'selected' => $selected,
            'currencyOptions' => $currencyOptions,
            'branchId' => $branchId,
            'canSeeAllBranches' => $user->role->canManageAllBranches(),
            'branches' => $this->branchSelectOptions($user),
            'mixChart' => [
                'labels' => array_column($breakdown, 'currency_code'),
                'datasets' => [
                    ['data' => array_column($breakdown, 'volume_myr'), 'backgroundColor' => ChartPalette::colors(count($breakdown))],
                ],
            ],
            'trendChart' => [
                'labels' => $trend['labels'],
                'datasets' => [
                    ['label' => 'Bought (MYR)', 'data' => $trend['buy'], 'borderColor' => '#3b82f6', 'backgroundColor' => 'rgba(59, 130, 246, 0.15)', 'fill' => true, 'tension' => 0.3],
                    ['label' => 'Sold (MYR)', 'data' => $trend['sell'], 'borderColor' => '#22c55e', 'backgroundColor' => 'rgba(34, 197, 94, 0.15)', 'fill' => true, 'tension' => 0.3],
                ],
            ],
            'marginChart' => [
                'labels' => $trend['labels'],
                'datasets' => [
                    ['label' => 'Realized margin (MYR)', 'data' => $trend['margin'], 'borderColor' => '#d4a843', 'backgroundColor' => '#d4a843', 'tension' => 0.3],
                ],
            ],
        ]);
    }

    /**
     * Rate analytics: spread capture over time, override frequency, and
     * the per-currency gap between the transacted rate and the market mid.
     */
    public function rates(Request $request): View
    {
        $this->requirePermission(Permission::ViewBiDashboard);
        $this->refreshTodaysRollups($this->snapshotBuilder);
        [$from, $to] = $this->resolveBiPeriod($request);

        $user = auth()->user();
        $branchId = $this->resolveReportBranchId($user, $request);

        $currencyOptions = Currency::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->pluck('code', 'code');

        $selected = strtoupper((string) $request->input('currency', ''));
        if (! $currencyOptions->has($selected)) {
            $selected = '';
        }

        $analytics = $this->kpiService->rateAnalytics($from, $to, $branchId, $selected ?: null);

        return view('bi.rates', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'rows' => $analytics['currencies'],
            'selected' => $selected,
            'currencyOptions' => $currencyOptions,
            'branchId' => $branchId,
            'canSeeAllBranches' => $user->role->canManageAllBranches(),
            'branches' => $this->branchSelectOptions($user),
            'spreadChart' => [
                'labels' => $analytics['labels'],
                'datasets' => [
                    ['label' => 'Spread (sell − buy rate)', 'data' => $analytics['spread'], 'borderColor' => '#3b82f6', 'backgroundColor' => 'rgba(59, 130, 246, 0.15)', 'fill' => true, 'tension' => 0.3],
                ],
            ],
            'overrideChart' => [
                'labels' => $analytics['labels'],
                'datasets' => [
                    ['label' => 'Override rate %', 'data' => $analytics['override_rate_pct'], 'borderColor' => '#f59e0b', 'backgroundColor' => '#f59e0b', 'tension' => 0.3],
                ],
            ],
        ]);
    }

    /**
     * Operational SLA: approval turnaround, cancel/reject rates, and
     * per-teller productivity.
     */
    public function operations(Request $request): View
    {
        $this->requirePermission(Permission::ViewBiDashboard);
        $this->refreshTodaysRollups($this->snapshotBuilder);
        [$from, $to] = $this->resolveBiPeriod($request);

        $user = auth()->user();
        $branchId = $this->resolveReportBranchId($user, $request);

        $trend = $this->kpiService->operationsTrend($from, $to, $branchId);
        $canViewTellers = $user->role->canPerform(Permission::ViewTellerProductivity);
        $sla = $this->kpiService->approvalSla($from, $to, $branchId);

        return view('bi.operations', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'canViewTellers' => $canViewTellers,
            'sla' => $sla,
            'tellers' => $canViewTellers ? $this->kpiService->tellerProductivity($from, $to, $branchId) : [],
            'branchId' => $branchId,
            'canSeeAllBranches' => $user->role->canManageAllBranches(),
            'branches' => $this->branchSelectOptions($user),
            'approvalChart' => [
                'labels' => $trend['labels'],
                'datasets' => [
                    ['label' => 'Avg approval time (s)', 'data' => $trend['avg_approval_seconds'], 'borderColor' => '#3b82f6', 'backgroundColor' => 'rgba(59, 130, 246, 0.15)', 'fill' => true, 'tension' => 0.3],
                ],
            ],
            'rateChart' => [
                'labels' => $trend['labels'],
                'datasets' => [
                    ['label' => 'Cancel rate %', 'data' => $trend['cancel_rate_pct'], 'borderColor' => '#f59e0b', 'backgroundColor' => '#f59e0b', 'tension' => 0.3],
                    ['label' => 'Reject rate %', 'data' => $trend['reject_rate_pct'], 'borderColor' => '#ef4444', 'backgroundColor' => '#ef4444', 'tension' => 0.3],
                ],
            ],
        ]);
    }

    /**
     * Customer analytics: monthly active/new customers and the period's
     * top accounts by volume.
     */
    public function customers(Request $request): View
    {
        $this->requirePermission(Permission::ViewBiDashboard);
        $this->refreshTodaysRollups($this->snapshotBuilder);
        // Default the customer report to a 6-month window — monthly-grain
        // analytics read poorly over a 30-day span.
        [$from, $to] = $this->resolveBiPeriod($request, today()->subMonths(5)->startOfMonth());

        $user = auth()->user();
        $branchId = $this->resolveReportBranchId($user, $request);

        $trend = $this->kpiService->activeCustomersTrend($from, $to, $branchId);
        $topCustomers = $this->kpiService->topCustomers($from, $to, $branchId);
        $analytics = $this->kpiService->customerAnalytics($from, $to, $branchId);

        return view('bi.customers', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'analytics' => $analytics,
            'topCustomers' => $topCustomers,
            'customerModels' => Customer::query()
                ->whereIn('id', array_column($topCustomers, 'customer_id') ?: [0])
                ->get()
                ->keyBy('id'),
            'branchId' => $branchId,
            'canSeeAllBranches' => $user->role->canManageAllBranches(),
            'branches' => $this->branchSelectOptions($user),
            'activeChart' => [
                'labels' => $trend['labels'],
                'datasets' => [
                    ['label' => 'Returning', 'data' => array_map(
                        fn ($active, $new) => max(0, $active - $new),
                        $trend['active'], $trend['new']
                    ), 'backgroundColor' => '#3b82f6'],
                    ['label' => 'New', 'data' => $trend['new'], 'backgroundColor' => '#22c55e'],
                ],
            ],
        ]);
    }

    /**
     * Currency demand forecast: historical demand vs the seasonal-blend
     * predictions generated nightly into analytics_daily_forecast.
     */
    public function forecast(Request $request): View
    {
        $this->requirePermission(Permission::ViewBiDashboard);

        $user = auth()->user();
        $branchId = $this->resolveReportBranchId($user, $request);

        $currencyOptions = Currency::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->pluck('code', 'code');

        $selected = strtoupper((string) $request->input('currency', ''));
        if (! $currencyOptions->has($selected)) {
            $selected = (string) ($currencyOptions->keys()->first() ?? '');
        }

        $historyDays = 28;
        $history = $selected === ''
            ? ['labels' => [], 'buy' => [], 'sell' => []]
            : $this->forecaster->history($branchId, $selected, today(), $historyDays);

        $forecasts = $selected === ''
            ? collect()
            : $this->forecaster->forecastFor($branchId, $selected, Carbon::tomorrow(), 7);

        // Merge history + forecast onto one label axis; Chart.js renders
        // nulls as gaps so the dashed forecast line starts where the
        // solid history ends.
        $labels = array_merge($history['labels'], $forecasts->pluck('date')->map(fn ($d) => $d->toDateString())->all());
        $forecastPad = array_fill(0, count($history['labels']), null);
        $forecastBuy = array_merge($forecastPad, $forecasts->pluck('predicted_buy_qty')->all());
        $forecastSell = array_merge($forecastPad, $forecasts->pluck('predicted_sell_qty')->all());
        $historyPad = array_fill(0, $forecasts->count(), null);

        // All-currency demand summary for the horizon — the stocking table.
        $summary = $this->forecaster->forecastSummary($branchId, Carbon::tomorrow(), 7);

        // The plan asks for >=6 months of rollup history before trusting
        // demand forecasts; surface a warning rather than block the page.
        $oldestRollup = AnalyticsDailyCurrency::min('date');
        $insufficientHistory = $oldestRollup === null
            || Carbon::parse($oldestRollup)->gt(today()->subMonths(6));

        return view('bi.forecast', [
            'from' => Carbon::tomorrow()->toDateString(),
            'to' => Carbon::tomorrow()->addDays(6)->toDateString(),
            'branchId' => $branchId,
            'canSeeAllBranches' => $user->role->canManageAllBranches(),
            'branches' => $this->branchSelectOptions($user),
            'selected' => $selected,
            'currencyOptions' => $currencyOptions,
            'forecasts' => $forecasts,
            'summary' => $summary,
            'insufficientHistory' => $insufficientHistory,
            'chart' => [
                'labels' => $labels,
                'datasets' => [
                    ['label' => 'Buy qty (actual)', 'data' => array_merge($history['buy'], $historyPad), 'borderColor' => '#3b82f6', 'backgroundColor' => 'rgba(59, 130, 246, 0.15)', 'fill' => true, 'tension' => 0.3],
                    ['label' => 'Sell qty (actual)', 'data' => array_merge($history['sell'], $historyPad), 'borderColor' => '#22c55e', 'backgroundColor' => 'rgba(34, 197, 94, 0.15)', 'fill' => true, 'tension' => 0.3],
                    ['label' => 'Buy qty (forecast)', 'data' => $forecastBuy, 'borderColor' => '#3b82f6', 'borderDash' => [6, 4], 'pointRadius' => 3, 'tension' => 0.3],
                    ['label' => 'Sell qty (forecast)', 'data' => $forecastSell, 'borderColor' => '#d4a843', 'borderDash' => [6, 4], 'pointRadius' => 3, 'tension' => 0.3],
                ],
            ],
        ]);
    }
}
