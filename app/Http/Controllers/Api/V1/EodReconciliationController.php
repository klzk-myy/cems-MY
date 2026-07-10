<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Eod\CounterReconciliationRequest;
use App\Http\Requests\Api\V1\Eod\GenerateReportRequest;
use App\Http\Requests\Api\V1\Eod\ShowReconciliationRequest;
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
    use ApiResponse;

    public function __construct(
        protected EodReconciliationService $eodService
    ) {}

    /**
     * Get daily reconciliation summary.
     *
     * @param  string  $date  Date in YYYY-MM-DD format
     */
    public function show(ShowReconciliationRequest $request, string $date): JsonResponse
    {
        $validated = $request->validated();

        $carbonDate = Carbon::parse($date);

        $user = auth()->user();
        if (! $this->canAccessEod($user)) {
            return $this->errorResponse('Unauthorized. Manager, Compliance Officer, or Admin access required.', [], 403);
        }

        $branchId = $validated['branch_id'] ?? null;

        if ($branchId && ! $user->isAdmin() && $user->branch_id !== $branchId) {
            if (! $user->isComplianceOfficer()) {
                return $this->errorResponse('You can only view reports for your own branch.', [], 403);
            }
        }

        try {
            $report = $this->eodService->generateDailyReconciliationSummary($carbonDate, $branchId);

            return $this->successResponse($report);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to generate reconciliation report: '.$e->getMessage(), [], 500);
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

        if (! $this->canAccessEod(auth()->user())) {
            return $this->errorResponse('Unauthorized. Manager, Compliance Officer, or Admin access required.', [], 403);
        }

        try {
            $report = $this->eodService->generateCounterReconciliation($counterId, $carbonDate);

            return $this->successResponse($report);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to generate counter reconciliation: '.$e->getMessage(), [], 500);
        }
    }

    /**
     * Generate and download PDF reconciliation report.
     *
     * @param  string  $date  Date in YYYY-MM-DD format
     */
    public function report(GenerateReportRequest $request, string $date): JsonResponse|Response
    {
        $validated = $request->validated();

        $carbonDate = Carbon::parse($date);

        if (! $this->canAccessEod(auth()->user())) {
            return $this->errorResponse('Unauthorized. Manager, Compliance Officer, or Admin access required.', [], 403);
        }

        $branchId = $validated['branch_id'] ?? null;
        $counterId = $validated['counter_id'] ?? null;
        $format = $validated['format'] ?? 'pdf';

        try {
            $report = $this->eodService->generateReconciliationReport($carbonDate, $branchId, $counterId);

            if ($format === 'json') {
                return $this->successResponse($report);
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
            return $this->errorResponse('Failed to generate reconciliation report: '.$e->getMessage(), [], 500);
        }
    }

    private function canAccessEod($user): bool
    {
        return $user->isManager() || $user->isComplianceOfficer() || $user->isAdmin();
    }
}
