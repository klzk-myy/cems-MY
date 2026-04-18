<?php

namespace App\Http\Controllers\Api\V1\Compliance;

use App\Http\Controllers\Controller;
use App\Models\CtosReport;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CtosReportController extends Controller
{
    public function __construct(
        protected AuditService $auditService,
    ) {}

    /**
     * List CTOS reports with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = CtosReport::with(['customer', 'creator', 'branch']);

        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->branch_id) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->from_date) {
            $query->where('report_date', '>=', $request->from_date);
        }

        if ($request->to_date) {
            $query->where('report_date', '<=', $request->to_date);
        }

        $perPage = $request->get('per_page', 20);
        $ctosReports = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $ctosReports,
        ]);
    }

    /**
     * Display a specific CTOS report.
     */
    public function show(int $id): JsonResponse
    {
        $ctos = CtosReport::with(['customer', 'creator', 'branch', 'transaction'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $ctos,
        ]);
    }

    /**
     * Submit CTOS report to BNM.
     * This marks the report as submitted and generates a BNM reference number.
     */
    public function submit(int $id): JsonResponse
    {
        $ctos = CtosReport::findOrFail($id);

        if (! $ctos->isDraft()) {
            return response()->json([
                'success' => false,
                'message' => 'CTOS report can only be submitted from Draft status.',
            ], 400);
        }

        $oldStatus = $ctos->status->value;
        $submittedBy = Auth::id();

        // Generate BNM submission reference number
        $submissionRef = $this->generateBnmReference($ctos);

        // Update the report
        $ctos->markAsSubmitted($submittedBy, $submissionRef);

        // Audit logging
        $this->auditService->logRegulatoryReportEvent('ctos_submitted', $ctos->id, [
            'old' => ['status' => $oldStatus],
            'new' => [
                'status' => 'Submitted',
                'submitted_at' => $ctos->submitted_at->toDateTimeString(),
                'submitted_by' => $submittedBy,
                'bnm_reference' => $submissionRef,
            ],
        ]);

        $this->auditService->logWithSeverity(
            'ctos_report_submitted_to_bnm',
            [
                'user_id' => $submittedBy,
                'entity_type' => 'CtosReport',
                'entity_id' => $ctos->id,
                'new_values' => [
                    'ctos_number' => $ctos->ctos_number,
                    'bnm_reference' => $submissionRef,
                    'submitted_at' => $ctos->submitted_at->toDateTimeString(),
                ],
            ],
            'WARNING'
        );

        return response()->json([
            'success' => true,
            'message' => 'CTOS report submitted to BNM successfully.',
            'data' => $ctos->fresh()->load(['creator', 'branch']),
            'submission_ref' => $submissionRef,
        ]);
    }

    /**
     * Generate a BNM submission reference number.
     * Format: CTOS-SUB-{YEAR}{MONTH}-{SEQUENCE}
     */
    private function generateBnmReference(CtosReport $ctos): string
    {
        $year = date('Y');
        $month = date('m');
        $prefix = "CTOS-SUB-{$year}{$month}-";

        $lastSubmission = CtosReport::where('bnm_reference', 'like', $prefix.'%')
            ->orderBy('bnm_reference', 'desc')
            ->first();

        if ($lastSubmission) {
            $lastNumber = (int) substr($lastSubmission->bnm_reference, -5);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix.str_pad($newNumber, 5, '0', STR_PAD_LEFT);
    }
}
