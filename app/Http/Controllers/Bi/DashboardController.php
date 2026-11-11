<?php

namespace App\Http\Controllers\Bi;

use App\Enums\Permission;
use App\Http\Controllers\Concerns\RequiresPermission;
use App\Http\Controllers\Concerns\ResolvesBiFilters;
use App\Http\Controllers\Concerns\ResolvesBranchScope;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\Bi\AnalyticsSnapshotBuilder;
use App\Services\Bi\KpiService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Executive BI dashboard — KPI cards and charts over the analytics_*
 * rollups (see BI-PLAN.md, Phase 2). A separate /bi route: the operational
 * dashboard stays untouched. Rollups are refreshed for today at most once
 * per five minutes so the page reads fresh data without paying the nightly
 * rebuild's cost on every request.
 */
class DashboardController extends Controller
{
    use RequiresPermission;
    use ResolvesBiFilters;
    use ResolvesBranchScope;

    public function __construct(
        protected KpiService $kpiService,
        protected AnalyticsSnapshotBuilder $snapshotBuilder,
    ) {}

    public function index(Request $request): View
    {
        $this->requirePermission(Permission::ViewBiDashboard);

        $this->refreshTodaysRollups($this->snapshotBuilder);

        [$from, $to] = $this->resolveBiPeriod($request);

        $user = auth()->user();
        $canSeeAllBranches = $user->role->canManageAllBranches();
        $branchId = $this->resolveReportBranchId($user, $request);

        $trends = $this->kpiService->dailyTrends($from, $to, $branchId);
        $currencyMix = $this->kpiService->currencyMix($from, $to, $branchId);

        $branches = $canSeeAllBranches
            ? Branch::query()->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('bi.dashboard', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'branchId' => $branchId,
            'canSeeAllBranches' => $canSeeAllBranches,
            'branches' => $branches,
            'cards' => $this->kpiService->cards($from, $to, $branchId),
            'volumeChart' => [
                'labels' => $trends['labels'],
                'datasets' => [
                    ['label' => 'Bought (MYR)', 'data' => $trends['buy'], 'borderColor' => '#3b82f6', 'backgroundColor' => 'rgba(59, 130, 246, 0.15)', 'fill' => true, 'tension' => 0.3],
                    ['label' => 'Sold (MYR)', 'data' => $trends['sell'], 'borderColor' => '#22c55e', 'backgroundColor' => 'rgba(34, 197, 94, 0.15)', 'fill' => true, 'tension' => 0.3],
                ],
            ],
            'marginChart' => [
                'labels' => $trends['labels'],
                'datasets' => [
                    ['label' => 'Realized margin (MYR)', 'data' => $trends['margin'], 'borderColor' => '#d4a843', 'backgroundColor' => '#d4a843', 'tension' => 0.3],
                ],
            ],
            'currencyChart' => [
                'labels' => array_column($currencyMix, 'currency_code'),
                'datasets' => [
                    ['data' => array_column($currencyMix, 'volume_myr'), 'backgroundColor' => $this->palette(count($currencyMix))],
                ],
            ],
            'comparisonChart' => $canSeeAllBranches
                ? $this->branchComparisonChart($from, $to)
                : $this->statusMixChart($from, $to, $branchId),
            'lastComputedAt' => $this->kpiService->lastComputedAt($branchId),
        ]);
    }

    /**
     * Cross-branch viewers get the per-branch volume/margin comparison.
     *
     * @return array{labels: array<int, string>, datasets: array<int, array<string, mixed>>}
     */
    protected function branchComparisonChart(Carbon $from, Carbon $to): array
    {
        $rows = $this->kpiService->branchComparison($from, $to);

        return [
            'labels' => array_column($rows, 'branch_name'),
            'datasets' => [
                ['label' => 'Volume (MYR)', 'data' => array_column($rows, 'volume_myr'), 'backgroundColor' => '#3b82f6'],
                ['label' => 'Realized margin (MYR)', 'data' => array_column($rows, 'realized_margin_myr'), 'backgroundColor' => '#d4a843'],
            ],
        ];
    }

    /**
     * Branch-scoped viewers get the completed/cancelled/rejected mix
     * instead of cross-branch data they must never see.
     *
     * @return array{labels: array<int, string>, datasets: array<int, array<string, mixed>>}
     */
    protected function statusMixChart(Carbon $from, Carbon $to, ?int $branchId): array
    {
        $mix = $this->kpiService->statusMix($from, $to, $branchId);

        return [
            'labels' => ['Completed', 'Cancelled', 'Rejected', 'Other'],
            'datasets' => [
                [
                    'data' => [$mix['completed'], $mix['cancelled'], $mix['rejected'], $mix['other']],
                    'backgroundColor' => ['#22c55e', '#f59e0b', '#ef4444', '#6b7280'],
                ],
            ],
        ];
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
