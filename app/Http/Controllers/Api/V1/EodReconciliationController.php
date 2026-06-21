<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Counter\CounterReconciliationRequest;
use App\Http\Requests\Api\V1\Counter\EodReconciliationRequest;
use App\Http\Requests\Api\V1\Counter\EodReportRequest;
use App\Services\EodReconciliationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use PDF;

/**
 * EOD Reconciliation Controller
 *
 * Handles End-of-Day reconciliation report generation and retrieval.
 * Provides daily summaries, counter-specific reports, and PDF exports.
 */
class EodReconciliationController extends Controller
{
    public function __construct(
        protected EodReconciliationService $eodService
    ) {}

    /**
     * Get daily reconciliation summary.
     *
     * @param  string  $date  Date in YYYY-MM-DD format
     */
    public function show(EodReconciliationRequest $request, string $date): JsonResponse
    {
        $validated = $request->validated();

        $carbonDate = Carbon::parse($date);

        // Managers and compliance officers can view EOD reports
        $user = auth()->user();
        if (! $user->isManager() && ! $user->isComplianceOfficer() && ! $user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Manager, Compliance Officer, or Admin access required.',
            ], 403);
        }

        $branchId = $validated['branch_id'] ?? null;

        // If branch filter is set, verify user has access
        if ($branchId && ! $user->isAdmin() && $user->branch_id !== $branchId) {
            // Check if user has any role that can access other branches
            if (! $user->isComplianceOfficer()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only view reports for your own branch.',
                ], 403);
            }
        }

        try {
            $report = $this->eodService->generateDailyReconciliationSummary($carbonDate, $branchId);

            return response()->json([
                'success' => true,
                'data' => $report,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate reconciliation report: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get counter-specific reconciliation.
     *
     * @param  string  $date  Date in YYYY-MM-DD format
     * @param  int  $counterId  Counter ID
     */
    public function counterReconciliation(CounterReconciliationRequest $request, string $date, int $counterId): JsonResponse
    {
        $validated = $request->validated();

        $carbonDate = Carbon::parse($date);

        // Managers and compliance officers can view EOD reports
        $user = auth()->user();
        if (! $user->isManager() && ! $user->isComplianceOfficer() && ! $user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Manager, Compliance Officer, or Admin access required.',
            ], 403);
        }

        try {
            $report = $this->eodService->generateCounterReconciliation($counterId, $carbonDate);

            return response()->json([
                'success' => true,
                'data' => $report,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate counter reconciliation: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate and download PDF reconciliation report.
     *
     * @param  string  $date  Date in YYYY-MM-DD format
     */
    public function report(EodReportRequest $request, string $date): JsonResponse|Response
    {
        $validated = $request->validated();

        $carbonDate = Carbon::parse($date);

        // Only managers and compliance officers can generate PDF reports
        $user = auth()->user();
        if (! $user->isManager() && ! $user->isComplianceOfficer() && ! $user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Manager, Compliance Officer, or Admin access required.',
            ], 403);
        }

        $branchId = $validated['branch_id'] ?? null;
        $counterId = $validated['counter_id'] ?? null;
        $format = $validated['format'] ?? 'pdf';

        try {
            $report = $this->eodService->generateReconciliationReport($carbonDate, $branchId, $counterId);

            if ($format === 'json') {
                return response()->json([
                    'success' => true,
                    'data' => $report,
                ]);
            }

            // Generate PDF
            $pdf = PDF::loadView('reports.eod-reconciliation', [
                'report' => $report,
                'generatedAt' => now()->format('Y-m-d H:i:s'),
                'date' => $carbonDate->format('Y-m-d'),
            ]);

            $pdf->setPaper('A4', 'portrait');

            $filename = 'EOD-Reconciliation-'.$carbonDate->format('Y-m-d');
            if ($counterId) {
                $filename .= '-Counter-'.$counterId;
            }
            $filename .= '.pdf';

            return $pdf->download($filename);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate reconciliation report: '.$e->getMessage(),
            ], 500);
        }
    }
}
