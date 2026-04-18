<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StrReport;
use App\Services\AuditService;
use App\Services\StrReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StrController extends Controller
{
    public function __construct(
        protected StrReportService $strService,
        protected AuditService $auditService
    ) {}

    /**
     * List STR reports with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = StrReport::with(['customer', 'creator', 'reviewer', 'approver', 'alert']);

        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $perPage = $request->get('per_page', 20);
        $strReports = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $strReports,
        ]);
    }

    /**
     * Create a new STR report.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'alert_id' => 'nullable|exists:flagged_transactions,id',
            'transaction_ids' => 'required|array',
            'transaction_ids.*' => 'exists:transactions,id',
            'reason' => 'required|string|min:20',
        ]);

        try {
            $strReport = StrReport::create([
                'str_no' => $this->strService->generateStrNumber(),
                'branch_id' => Auth::user()->branch_id,
                'customer_id' => $request->customer_id,
                'alert_id' => $request->alert_id,
                'transaction_ids' => $request->transaction_ids,
                'reason' => $request->reason,
                'supporting_documents' => [],
                'status' => 'draft',
                'created_by' => Auth::id(),
                'suspicion_date' => now(),
            ]);

            $complianceService = app(\App\Services\ComplianceService::class);
            $deadlineInfo = $complianceService->calculateStrDeadline(now());
            $strReport->filing_deadline = $deadlineInfo['deadline'];
            $strReport->save();

            $this->auditService->logStrAction('str_created', $strReport->id, [
                'new' => [
                    'str_no' => $strReport->str_no,
                    'customer_id' => $strReport->customer_id,
                    'suspicion_date' => $strReport->suspicion_date->toDateTimeString(),
                    'filing_deadline' => $deadlineInfo['deadline']->toDateTimeString(),
                ],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'STR draft created successfully.',
                'data' => $strReport->load(['customer', 'transactions']),
                'filing_deadline' => $deadlineInfo['deadline']->toDateTimeString(),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create STR: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display a specific STR report.
     */
    public function show(int $id): JsonResponse
    {
        $str = StrReport::with(['customer', 'creator', 'reviewer', 'approver', 'alert'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $str,
            'transactions' => $str->transactions(),
        ]);
    }

    /**
     * Submit STR to goAML.
     */
    public function submit(int $id): JsonResponse
    {
        $str = StrReport::findOrFail($id);

        if (! $str->status->canSubmit()) {
            return response()->json([
                'success' => false,
                'message' => 'STR cannot be submitted in its current status.',
            ], 400);
        }

        $oldStatus = $str->status->value;
        $submitted = $this->strService->submitToGoAML($str);

        if ($submitted) {
            $this->auditService->logStrAction('str_submitted_to_goaml', $str->id, [
                'old' => ['status' => $oldStatus],
                'new' => [
                    'status' => 'submitted',
                    'submitted_at' => now()->toDateTimeString(),
                    'submitted_by' => Auth::id(),
                ],
                'severity' => 'CRITICAL',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'STR submitted to goAML successfully.',
                'data' => $str->fresh(),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to submit STR to goAML.',
        ], 500);
    }
}
