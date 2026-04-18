<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Services\AlertTriageService;
use Illuminate\Http\Request;

class AlertTriageController extends Controller
{
    public function __construct(
        protected AlertTriageService $alertTriageService
    ) {}

    public function index(Request $request)
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

    public function show(Alert $alert)
    {
        $alert->load(['customer', 'flaggedTransaction', 'flaggedTransaction.transaction', 'assignedTo', 'case']);

        return view('compliance.alerts.show', compact('alert'));
    }

    public function assign(Request $request, Alert $alert)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $this->alertTriageService->assignToOfficer($alert, $request->user_id);

        return redirect()->back()->with('success', 'Alert assigned successfully');
    }

    public function resolve(Request $request, Alert $alert)
    {
        $request->validate([
            'notes' => 'nullable|string',
        ]);

        $this->alertTriageService->resolveAlert($alert, auth()->id(), $request->notes);

        return redirect()->route('compliance.alerts.index')->with('success', 'Alert resolved successfully');
    }

    public function dismiss(Request $request, Alert $alert)
    {
        if ($alert->status === \App\Enums\FlagStatus::Resolved || $alert->status === \App\Enums\FlagStatus::Rejected) {
            abort(403, 'Cannot dismiss an already resolved or rejected alert.');
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $alert->update([
            'status' => \App\Enums\FlagStatus::Rejected,
        ]);

        return redirect()->route('compliance.alerts.index')->with('success', 'Alert dismissed');
    }
}
