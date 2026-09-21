<?php

namespace App\Http\Controllers\Compliance;

use App\Exceptions\Domain\CaseManagementException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignAlertRequest;
use App\Http\Requests\BulkAssignAlertsRequest;
use App\Http\Requests\BulkResolveAlertsRequest;
use App\Http\Requests\DismissAlertRequest;
use App\Http\Requests\ResolveAlertRequest;
use App\Models\Compliance\Alert;
use App\Services\Compliance\AlertTriageService;
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
        $officers = $this->alertTriageService->getAvailableOfficers()->pluck('username', 'id');

        return view('compliance.alerts.index', compact('alerts', 'summary', 'officers'));
    }

    /**
     * Bulk-assign the selected alerts to a compliance officer.
     */
    public function bulkAssign(BulkAssignAlertsRequest $request): RedirectResponse
    {
        $this->authorize('assign', Alert::class);

        $validated = $request->validated();

        $results = $this->alertTriageService->bulkAssign(
            $validated['alert_ids'],
            (int) $validated['user_id']
        );

        return redirect()->route('compliance.alerts.index')
            ->with($this->bulkFlashKey($results), "Bulk assign: {$results['success']} succeeded, {$results['failed']} failed");
    }

    /**
     * Bulk-resolve the selected alerts.
     */
    public function bulkResolve(BulkResolveAlertsRequest $request): RedirectResponse
    {
        $this->authorize('updateStatus', Alert::class);

        $validated = $request->validated();

        $results = $this->alertTriageService->bulkResolve(
            $validated['alert_ids'],
            (int) auth()->id(),
            $validated['notes'] ?? null
        );

        return redirect()->route('compliance.alerts.index')
            ->with($this->bulkFlashKey($results), "Bulk resolve: {$results['success']} succeeded, {$results['failed']} failed");
    }

    /**
     * Auto-assign every unassigned alert across the available officers.
     */
    public function autoAssign(): RedirectResponse
    {
        $this->authorize('assign', Alert::class);

        $assigned = $this->alertTriageService->autoAssignAlerts();

        return redirect()->route('compliance.alerts.index')
            ->with('success', 'Auto-assignment completed: '.count($assigned).' alert(s) assigned.');
    }

    /**
     * Flash key for bulk-operation results: a fully failed batch is an error,
     * partial or full success is informational.
     *
     * @param  array{success: int, failed: int, errors: array<int, string>}  $results
     */
    protected function bulkFlashKey(array $results): string
    {
        return $results['success'] === 0 && $results['failed'] > 0 ? 'error' : 'success';
    }

    public function show(Alert $alert): View
    {
        $alert->load(['customer', 'flaggedTransaction', 'flaggedTransaction.transaction', 'assignedTo', 'case']);

        return view('compliance.alerts.show', compact('alert'));
    }

    public function assign(AssignAlertRequest $request, Alert $alert): RedirectResponse
    {
        $this->authorize('assign', $alert);

        $this->alertTriageService->assignToOfficer($alert, $request->assignee_id);

        return redirect()->back()->with('success', 'Alert assigned successfully');
    }

    public function resolve(ResolveAlertRequest $request, Alert $alert): RedirectResponse
    {
        $this->authorize('updateStatus', $alert);

        $this->alertTriageService->resolveAlert($alert, (int) auth()->id(), $request->resolution);

        return redirect()->route('compliance.alerts.index')->with('success', 'Alert resolved successfully');
    }

    public function dismiss(DismissAlertRequest $request, Alert $alert): RedirectResponse
    {
        $this->authorize('updateStatus', $alert);

        try {
            $this->alertTriageService->dismissAlert($alert, (int) auth()->id());
        } catch (CaseManagementException $e) {
            // Already resolved/rejected alerts are refused with the same
            // clean 403 the guard has always produced.
            abort(403, $e->getMessage());
        }

        return redirect()->route('compliance.alerts.index')->with('success', 'Alert dismissed');
    }
}
