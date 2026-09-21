<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Exceptions\Domain\DomainException;
use App\Exceptions\Domain\PermissionDeniedException;
use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Eod\CounterReconciliationRequest;
use App\Http\Requests\Api\V1\Eod\GenerateReportRequest;
use App\Http\Requests\Api\V1\Eod\ShowReconciliationRequest;
use App\Models\Counter;
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

        $this->assertEodAccess();

        $user = auth()->user();
        $branchId = isset($validated['branch_id']) ? (int) $validated['branch_id'] : null;

        // Restricted roles without an explicit scope see their own branch
        // only - never an all-branches summary.
        if ($branchId === null && ! $this->hasGlobalEodScope($user) && $user->branch_id) {
            $branchId = (int) $user->branch_id;
        }

        $this->assertBranchAccess($branchId);

        try {
            $report = $this->eodService->generateDailyReconciliationSummary($carbonDate, $branchId);

            return $this->successResponse($report);
        } catch (DomainException $e) {
            return $this->domainErrorResponse($e);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to generate reconciliation report. Please try again.', $e);
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

        $this->assertEodAccess();

        // Counter reports bypass branch filtering inside the service, so the
        // counter itself must belong to the caller's branch.
        $this->assertCounterAccess($counterId);

        try {
            $report = $this->eodService->generateCounterReconciliation($counterId, $carbonDate);

            return $this->successResponse($report);
        } catch (DomainException $e) {
            return $this->domainErrorResponse($e);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to generate counter reconciliation. Please try again.', $e);
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

        $this->assertEodAccess();

        $branchId = isset($validated['branch_id']) ? (int) $validated['branch_id'] : null;
        $counterId = isset($validated['counter_id']) ? (int) $validated['counter_id'] : null;
        $format = $validated['format'] ?? 'pdf';

        // Explicit foreign branches are rejected for restricted roles, and a
        // missing scope defaults to the caller's own branch - never global.
        $this->assertBranchAccess($branchId);

        $user = auth()->user();
        if ($branchId === null && ! $this->hasGlobalEodScope($user) && $user->branch_id) {
            $branchId = (int) $user->branch_id;
        }

        // Counter-scoped reports bypass branch filtering inside the service,
        // so the requested counter must belong to the caller's branch.
        $this->assertCounterAccess($counterId);

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

        } catch (DomainException $e) {
            return $this->domainErrorResponse($e);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to generate reconciliation report. Please try again.', $e);
        }
    }

    private function assertEodAccess(): void
    {
        if (! $this->canAccessEod(auth()->user())) {
            throw new PermissionDeniedException('access EOD reconciliation', 'Unauthorized. Manager, Compliance Officer, or Admin access required.');
        }
    }

    /**
     * Admins and compliance officers may report across all branches; every
     * other EOD user is restricted to their own branch.
     */
    private function hasGlobalEodScope($user): bool
    {
        return $user->isAdmin() || $user->isComplianceOfficer();
    }

    /**
     * Reject an explicitly requested foreign branch for restricted roles.
     * Both sides are cast to int: branch_id arrives as a validated string
     * while users.branch_id is an int column, so a strict compare would
     * 403 the user's own branch.
     */
    private function assertBranchAccess(?int $branchId): void
    {
        $user = auth()->user();

        if (
            $branchId !== null
            && ! $this->hasGlobalEodScope($user)
            && (int) $user->branch_id !== $branchId
        ) {
            throw new PermissionDeniedException('You can only view reports for your own branch.');
        }
    }

    /**
     * Ensure a counter-scoped report only touches counters belonging to the
     * caller's branch (admins and compliance officers exempt). Unknown
     * counters fall through to the service's existing not-found handling.
     */
    private function assertCounterAccess(?int $counterId): void
    {
        if ($counterId === null) {
            return;
        }

        $counter = Counter::find($counterId);

        if ($counter === null) {
            return;
        }

        $user = auth()->user();

        if (! $this->hasGlobalEodScope($user) && (int) $counter->branch_id !== (int) $user->branch_id) {
            throw new PermissionDeniedException('You can only view reports for your own branch.');
        }
    }

    /**
     * Requires the view_eod_reconciliation permission (managers and
     * compliance officers by default; admins always).
     */
    private function canAccessEod($user): bool
    {
        return $user->role->canPerform(Permission::ViewEodReconciliation);
    }
}
