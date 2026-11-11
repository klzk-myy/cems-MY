<?php

namespace App\Http\Controllers\Bi;

use App\Enums\Permission;
use App\Http\Controllers\Concerns\RequiresPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBiAlertRuleRequest;
use App\Models\Bi\BiAlertRule;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRUD for BI alert rules (threshold checks evaluated nightly against
 * the rollups). Listing follows the BI permission; creating and
 * deleting rules is an administrative action gated by
 * manage_report_schedules — the existing "configure automated
 * reporting" capability.
 */
class AlertRuleController extends Controller
{
    use RequiresPermission;

    /**
     * List the configured rules plus the create form.
     */
    public function index(Request $request): View
    {
        $this->requirePermission(Permission::ViewBiDashboard);

        $user = $request->user();
        $canManage = $user->role->canPerform(Permission::ManageReportSchedules);

        $rules = BiAlertRule::query()
            ->with(['branch', 'creator'])
            ->when(
                ! $user->role->canManageAllBranches(),
                fn ($q) => $q->where(function ($w) use ($user) {
                    $w->where('branch_id', $user->branch_id)->orWhereNull('branch_id');
                })
            )
            ->orderBy('id')
            ->get();

        return view('bi.alerts', [
            'rules' => $rules,
            'canManage' => $canManage,
            'metrics' => BiAlertRule::availableMetrics(),
            'comparisons' => BiAlertRule::availableComparisons(),
            'branches' => $user->role->canManageAllBranches()
                ? Branch::query()->orderBy('name')->get(['id', 'name'])
                : collect(),
            'canSeeAllBranches' => $user->role->canManageAllBranches(),
        ]);
    }

    public function store(StoreBiAlertRuleRequest $request): RedirectResponse
    {
        $user = $request->user();
        $branchId = $request->integer('branch_id') ?: null;

        // A branch-scoped manager's rules always apply to their own
        // branch only — a submitted branch_id is ignored.
        if (! $user->role->canManageAllBranches()) {
            $branchId = $user->branch_id;
        }

        BiAlertRule::create([
            'metric' => $request->string('metric')->toString(),
            'comparison' => $request->string('comparison')->toString(),
            'threshold' => $request->string('threshold')->toString(),
            'branch_id' => $branchId,
            'level' => $request->string('level')->toString(),
            'notification_recipients' => $request->input('notification_recipients') ?: null,
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        return redirect()
            ->route('bi.alerts')
            ->with('success', 'Alert rule created.');
    }

    public function destroy(BiAlertRule $rule): RedirectResponse
    {
        $rule->delete();

        return redirect()
            ->route('bi.alerts')
            ->with('success', 'Alert rule deleted.');
    }
}
