<?php

namespace App\Http\Controllers\Admin;

use App\Enums\KpiMetric;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateKpiTargetsRequest;
use App\Models\Bi\KpiTarget;
use App\Models\Branch;
use App\Services\Bi\KpiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin UI for operational KPI targets — BI-PLAN.md §8 open question 2.
 * GL budgets cover revenue; volume and turnaround targets live here.
 * Route middleware enforces role:manage_thresholds + mfa.verified and the
 * mutation additionally requires password.confirm.
 */
class KpiTargetController extends Controller
{
    public function __construct(
        protected KpiService $kpiService,
    ) {}

    /**
     * Targets grouped by metric, with the company-wide row and each branch
     * override side by side.
     */
    public function index(): View
    {
        $this->requirePermission(Permission::ManageThresholds);

        $targets = KpiTarget::query()
            ->orderBy('kpi_metric')
            ->orderBy('branch_id')
            ->get()
            ->keyBy(fn (KpiTarget $target) => $this->key($target->kpi_metric, $target->branch_id, $target->period));

        return view('admin.kpi-targets.index', [
            'metrics' => KpiMetric::cases(),
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name']),
            'targets' => $targets,
        ]);
    }

    public function update(UpdateKpiTargetsRequest $request): RedirectResponse
    {
        $this->requirePermission(Permission::ManageThresholds);

        foreach ($request->validated('targets', []) as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            [$metric, $branchId, $period] = explode('|', $key);

            $target = KpiTarget::query()
                ->where('kpi_metric', $metric)
                ->where('period', $period)
                ->where('branch_id', $branchId === 'all' ? null : (int) $branchId)
                ->first() ?? new KpiTarget;

            $target->kpi_metric = $metric;
            $target->branch_id = $branchId === 'all' ? null : (int) $branchId;
            $target->period = $period;
            $target->target_value = $value;
            $target->updated_by = (int) $request->user()->id;
            $target->save();
        }

        return redirect()
            ->route('admin.kpi-targets.index')
            ->with('success', 'KPI targets saved.');
    }

    /**
     * Stable key shared by the index grouping and the update payload.
     */
    protected function key(string $metric, ?int $branchId, string $period): string
    {
        return $metric.'|'.($branchId ?? 'all').'|'.$period;
    }
}
