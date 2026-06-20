<?php

namespace App\Http\Controllers\Api\V1\Compliance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Compliance\AlertIndexRequest;
use App\Http\Requests\Api\V1\Compliance\BulkAssignAlertRequest;
use App\Http\Requests\Api\V1\Compliance\BulkResolveAlertRequest;
use App\Models\Alert;
use App\Services\Compliance\AlertTriageService;
use Illuminate\Http\JsonResponse;

class AlertController extends Controller
{
    public function __construct(
        protected AlertTriageService $alertTriageService
    ) {}

    /**
     * List alerts for triage.
     */
    public function index(AlertIndexRequest $request): JsonResponse
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

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $perPage = $request->get('per_page', 50);
        $alerts = $query->orderByRaw("FIELD(priority, 'critical', 'high', 'medium', 'low')")
            ->orderByDesc('risk_score')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $alerts,
        ]);
    }

    /**
     * Get a single alert.
     */
    public function show(int $id): JsonResponse
    {
        $alert = Alert::with([
            'customer',
            'flaggedTransaction',
            'flaggedTransaction.transaction',
            'assignedTo',
            'case',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $alert,
        ]);
    }

    /**
     * Bulk assign alerts to a compliance officer.
     */
    public function bulkAssign(BulkAssignAlertRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $results = $this->alertTriageService->bulkAssign(
            $validated['alert_ids'],
            $validated['user_id']
        );

        return response()->json([
            'success' => true,
            'message' => "Bulk assign completed: {$results['success']} succeeded, {$results['failed']} failed",
            'results' => $results,
        ], $results['failed'] > 0 ? 207 : 200);
    }

    /**
     * Bulk resolve multiple alerts.
     */
    public function bulkResolve(BulkResolveAlertRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $results = $this->alertTriageService->bulkResolve(
            $validated['alert_ids'],
            auth()->id(),
            $validated['notes'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => "Bulk resolve completed: {$results['success']} succeeded, {$results['failed']} failed",
            'results' => $results,
        ], $results['failed'] > 0 ? 207 : 200);
    }

    /**
     * Get alert queue summary.
     */
    public function summary(): JsonResponse
    {
        $summary = $this->alertTriageService->getQueueSummary();

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * Get overdue alerts.
     */
    public function overdue(): JsonResponse
    {
        $alerts = Alert::with(['customer', 'flaggedTransaction'])
            ->whereNull('case_id')
            ->get()
            ->filter(fn ($alert) => $alert->isOverdue())
            ->values();

        return response()->json([
            'success' => true,
            'count' => $alerts->count(),
            'data' => $alerts,
        ]);
    }

    /**
     * Auto-assign alerts to available officers.
     */
    public function autoAssign(): JsonResponse
    {
        $assigned = $this->alertTriageService->autoAssignAlerts();

        return response()->json([
            'success' => true,
            'message' => 'Auto-assignment completed.',
            'assigned_count' => count($assigned),
        ]);
    }
}
