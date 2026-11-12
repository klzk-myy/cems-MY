<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Concerns\RequiresPermission;
use App\Http\Controllers\Concerns\ResolvesBiFilters;
use App\Http\Controllers\Concerns\ResolvesBranchScope;
use App\Http\Controllers\Controller;
use App\Services\Bi\AnalyticsSnapshotBuilder;
use App\Services\Bi\DemandForecaster;
use App\Services\Bi\KpiService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only BI analytics over the same KpiService the web pages use, so an
 * API consumer and a browser render identical numbers for the same period.
 * Every action applies the same branch scope and permission gate as its web
 * counterpart.
 */
class BiController extends Controller
{
    use RequiresPermission;
    use ResolvesBiFilters;
    use ResolvesBranchScope;

    public function __construct(
        protected KpiService $kpiService,
        protected AnalyticsSnapshotBuilder $snapshotBuilder,
        protected DemandForecaster $forecaster,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $this->requirePermission(Permission::ViewBiDashboard);
        $this->refreshTodaysRollups($this->snapshotBuilder);

        [$from, $to] = $this->resolveBiPeriod($request);
        $branchId = $this->resolveReportBranchId($request->user(), $request);

        return $this->biResponse([
            'cards' => $this->kpiService->cards($from, $to, $branchId),
            'sparklines' => $this->kpiService->cardSparklines($from, $to, $branchId),
            'last_computed_at' => $this->kpiService->lastComputedAt($branchId)?->toDateTimeString(),
        ], $from, $to);
    }

    public function branches(Request $request): JsonResponse
    {
        $this->requirePermission(Permission::ViewBiDashboard);
        $this->refreshTodaysRollups($this->snapshotBuilder);

        [$from, $to] = $this->resolveBiPeriod($request);
        $branchId = $this->resolveReportBranchId($request->user(), $request);

        $rows = array_values(array_filter(
            $this->kpiService->branchComparison($from, $to),
            fn ($row) => $branchId === null || (int) $row['branch_id'] === $branchId
        ));

        return $this->biResponse(['rows' => $rows], $from, $to);
    }

    public function currencies(Request $request): JsonResponse
    {
        $this->requirePermission(Permission::ViewBiDashboard);
        $this->refreshTodaysRollups($this->snapshotBuilder);

        [$from, $to] = $this->resolveBiPeriod($request);
        $branchId = $this->resolveReportBranchId($request->user(), $request);

        $selected = strtoupper((string) $request->input('currency', ''));

        return $this->biResponse([
            'rows' => $this->kpiService->currencyBreakdown($from, $to, $branchId),
            'trend' => $selected === ''
                ? ['labels' => [], 'buy' => [], 'sell' => [], 'margin' => []]
                : $this->kpiService->currencyTrend($from, $to, $branchId, $selected),
        ], $from, $to);
    }

    public function rates(Request $request): JsonResponse
    {
        $this->requirePermission(Permission::ViewBiDashboard);
        $this->refreshTodaysRollups($this->snapshotBuilder);

        [$from, $to] = $this->resolveBiPeriod($request);
        $branchId = $this->resolveReportBranchId($request->user(), $request);

        $selected = strtoupper((string) $request->input('currency', ''));

        return $this->biResponse(
            $this->kpiService->rateAnalytics($from, $to, $branchId, $selected ?: null),
            $from,
            $to
        );
    }

    public function operations(Request $request): JsonResponse
    {
        $this->requirePermission(Permission::ViewBiDashboard);
        $this->refreshTodaysRollups($this->snapshotBuilder);

        [$from, $to] = $this->resolveBiPeriod($request);
        $user = $request->user();
        $branchId = $this->resolveReportBranchId($user, $request);
        $canViewTellers = $user->role->canPerform(Permission::ViewTellerProductivity);

        return $this->biResponse([
            'trend' => $this->kpiService->operationsTrend($from, $to, $branchId),
            'sla' => $this->kpiService->approvalSla($from, $to, $branchId),
            'tellers' => $canViewTellers ? $this->kpiService->tellerProductivity($from, $to, $branchId) : [],
        ], $from, $to);
    }

    public function customers(Request $request): JsonResponse
    {
        $this->requirePermission(Permission::ViewBiDashboard);
        $this->refreshTodaysRollups($this->snapshotBuilder);

        [$from, $to] = $this->resolveBiPeriod($request, today()->subMonths(5)->startOfMonth());
        $branchId = $this->resolveReportBranchId($request->user(), $request);

        return $this->biResponse([
            'trend' => $this->kpiService->activeCustomersTrend($from, $to, $branchId),
            'analytics' => $this->kpiService->customerAnalytics($from, $to, $branchId),
            'top_customers' => $this->kpiService->topCustomers($from, $to, $branchId),
        ], $from, $to);
    }

    public function stock(Request $request): JsonResponse
    {
        $this->requirePermission(Permission::ViewBiDashboard);
        $this->refreshTodaysRollups($this->snapshotBuilder);

        [$from, $to] = $this->resolveBiPeriod($request);
        $branchId = $this->resolveReportBranchId($request->user(), $request);

        return $this->biResponse(
            $this->kpiService->stockAndCompliance($from, $to, $branchId),
            $from,
            $to
        );
    }

    public function forecast(Request $request): JsonResponse
    {
        $this->requirePermission(Permission::ViewBiDashboard);

        $branchId = $this->resolveReportBranchId($request->user(), $request);
        $currency = strtoupper((string) $request->input('currency', ''));

        $historyDays = 28;
        $history = $currency === ''
            ? ['labels' => [], 'buy' => [], 'sell' => []]
            : $this->forecaster->history($branchId, $currency, today(), $historyDays);

        return $this->biResponse([
            'currency' => $currency,
            'history' => $history,
            'forecast' => $currency === ''
                ? []
                : $this->forecaster->forecastFor($branchId, $currency, Carbon::tomorrow(), 7)
                    ->map(fn ($row) => [
                        'date' => $row->date->toDateString(),
                        'predicted_buy_qty' => (string) $row->predicted_buy_qty,
                        'predicted_sell_qty' => (string) $row->predicted_sell_qty,
                    ])->all(),
            'summary' => $this->forecaster->forecastSummary($branchId, Carbon::tomorrow(), 7),
        ], Carbon::tomorrow(), Carbon::tomorrow()->addDays(6));
    }

    /**
     * Wrap a payload with the period echo so a consumer can confirm which
     * window it asked for.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function biResponse(array $payload, Carbon $from, Carbon $to): JsonResponse
    {
        return response()->json([
            'success' => true,
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ] + $payload);
    }
}
