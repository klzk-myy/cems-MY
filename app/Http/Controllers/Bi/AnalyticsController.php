<?php

namespace App\Http\Controllers\Bi;

use App\Enums\Permission;
use App\Http\Controllers\Concerns\RequiresPermission;
use App\Http\Controllers\Concerns\ResolvesBiFilters;
use App\Http\Controllers\Concerns\ResolvesBranchScope;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Currency;
use App\Services\Bi\AnalyticsSnapshotBuilder;
use App\Services\Bi\KpiService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * BI report pages (BI-PLAN.md Phase 3): branch comparison, currency
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
            'branches' => $user->role->canManageAllBranches()
                ? Branch::query()->orderBy('name')->get(['id', 'name'])
                : collect(),
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
            'branches' => $user->role->canManageAllBranches()
                ? Branch::query()->orderBy('name')->get(['id', 'name'])
                : collect(),
            'mixChart' => [
                'labels' => array_column($breakdown, 'currency_code'),
                'datasets' => [
                    ['data' => array_column($breakdown, 'volume_myr'), 'backgroundColor' => $this->palette(count($breakdown))],
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

        return view('bi.operations', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'tellers' => $this->kpiService->tellerProductivity($from, $to, $branchId),
            'branchId' => $branchId,
            'canSeeAllBranches' => $user->role->canManageAllBranches(),
            'branches' => $user->role->canManageAllBranches()
                ? Branch::query()->orderBy('name')->get(['id', 'name'])
                : collect(),
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

        return view('bi.customers', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'topCustomers' => $this->kpiService->topCustomers($from, $to, $branchId),
            'branchId' => $branchId,
            'canSeeAllBranches' => $user->role->canManageAllBranches(),
            'branches' => $user->role->canManageAllBranches()
                ? Branch::query()->orderBy('name')->get(['id', 'name'])
                : collect(),
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
     * Chart.js doughnut palette — theme accent first, then a stable
     * rotation so a given rank always renders the same color.
     *
     * @return array<int, string>
     */
    protected function palette(int $count): array
    {
        $colors = ['#d4a843', '#3b82f6', '#22c55e', '#f59e0b', '#ef4444', '#8b5cf6', '#14b8a6', '#f97316'];

        return array_map(fn (int $i) => $colors[$i % count($colors)], range(0, max(0, $count - 1)));
    }
}
