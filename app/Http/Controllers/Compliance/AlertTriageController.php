<?php

namespace App\Http\Controllers\Compliance;

use App\Enums\FlagStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignAlertRequest;
use App\Http\Requests\DismissAlertRequest;
use App\Http\Requests\ResolveAlertRequest;
use App\Models\Alert;
use App\Services\AlertTriageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AlertTriageController extends Controller
{
    public function __construct(
        protected AlertTriageService $alertTriageService
    ) {}

    public function index(Request $request): View
    {
        $query = Alert::with(['customer', 'flaggedTransaction', 'assignedTo'])
            ->whereNull('case_id');

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->has('assigned')) {
            if ($request->assigned === 'unassigned') {
                $query->whereNull('assigned_to');
            } else {
                $query->whereNotNull('assigned_to');
            }
        }

        $alerts = $query->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END")
            ->orderByDesc('risk_score')
            ->paginate(50);

        $summary = $this->alertTriageService->getQueueSummary();

        return view('compliance.alerts.index', compact('alerts', 'summary'));
    }

    public function show(Alert $alert): View
    {
        $alert->load(['customer', 'flaggedTransaction', 'flaggedTransaction.transaction', 'assignedTo', 'case']);

        return view('compliance.alerts.show', compact('alert'));
    }

    public function assign(AssignAlertRequest $request, Alert $alert): RedirectResponse
    {
        $this->alertTriageService->assignToOfficer($alert, $request->assignee_id);

        return redirect()->back()->with('success', 'Alert assigned successfully');
    }

    public function resolve(ResolveAlertRequest $request, Alert $alert): RedirectResponse
    {
        $this->alertTriageService->resolveAlert($alert, auth()->id(), $request->resolution);

        return redirect()->route('compliance.alerts.index')->with('success', 'Alert resolved successfully');
    }

    public function dismiss(DismissAlertRequest $request, Alert $alert): RedirectResponse
    {
        if ($alert->status === FlagStatus::Resolved || $alert->status === FlagStatus::Rejected) {
            abort(403, 'Cannot dismiss an already resolved or rejected alert.');
        }

        $alert->update([
            'status' => FlagStatus::Rejected,
        ]);

        return redirect()->route('compliance.alerts.index')->with('success', 'Alert dismissed');
    }
}
