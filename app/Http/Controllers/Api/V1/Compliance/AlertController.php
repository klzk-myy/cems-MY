<?php

namespace App\Http\Controllers\Api\V1\Compliance;

use App\Enums\AlertPriority;
use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Compliance\AlertIndexRequest;
use App\Http\Requests\Api\V1\Compliance\BulkAssignAlertRequest;
use App\Http\Requests\Api\V1\Compliance\BulkResolveAlertRequest;
use App\Models\Alert;
use App\Services\Compliance\AlertTriageService;
use Illuminate\Http\JsonResponse;

class AlertController extends Controller
{
    use ApiResponse;

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

        return $this->successResponse($alerts, 'Alerts retrieved successfully.');
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

        return $this->successResponse($alert, 'Alert retrieved successfully.');
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

        $code = $results['failed'] > 0 ? 207 : 200;

        return $this->successResponse(
            null,
            "Bulk assign completed: {$results['success']} succeeded, {$results['failed']} failed",
            $code,
            ['results' => $results]
        );
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

        $code = $results['failed'] > 0 ? 207 : 200;

        return $this->successResponse(
            null,
            "Bulk resolve completed: {$results['success']} succeeded, {$results['failed']} failed",
            $code,
            ['results' => $results]
        );
    }

    /**
     * Get alert queue summary.
     */
    public function summary(): JsonResponse
    {
        $summary = $this->alertTriageService->getQueueSummary();

        return $this->successResponse($summary, 'Alert queue summary retrieved successfully.');
    }

    /**
     * Get overdue alerts.
     */
    public function overdue(): JsonResponse
    {
        $alerts = Alert::with(['customer', 'flaggedTransaction'])
            ->whereNull('case_id')
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->where('priority', AlertPriority::Critical->value)
                        ->where('created_at', '<', now()->subHours(AlertPriority::Critical->slaHours()));
                })->orWhere(function ($q) {
                    $q->where('priority', AlertPriority::High->value)
                        ->where('created_at', '<', now()->subHours(AlertPriority::High->slaHours()));
                })->orWhere(function ($q) {
                    $q->where('priority', AlertPriority::Medium->value)
                        ->where('created_at', '<', now()->subHours(AlertPriority::Medium->slaHours()));
                })->orWhere(function ($q) {
                    $q->where('priority', AlertPriority::Low->value)
                        ->where('created_at', '<', now()->subHours(AlertPriority::Low->slaHours()));
                });
            })
            ->get();

        return $this->successResponse($alerts, 'Overdue alerts retrieved successfully.', 200, [
            'count' => $alerts->count(),
        ]);
    }

    /**
     * Auto-assign alerts to available officers.
     */
    public function autoAssign(): JsonResponse
    {
        $assigned = $this->alertTriageService->autoAssignAlerts();

        return $this->successResponse(null, 'Auto-assignment completed.', 200, [
            'assigned_count' => count($assigned),
        ]);
    }
}
