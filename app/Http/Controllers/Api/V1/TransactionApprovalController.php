<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Domain\SelfApprovalException;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\AuditService;
use App\Services\ComplianceService;
use App\Services\CurrencyPositionService;
use App\Services\MathService;
use App\Services\TransactionApprovalService;
use App\Services\TransactionMonitoringService;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class TransactionApprovalController extends Controller
{
    public function __construct(
        protected TransactionService $transactionService,
        protected TransactionApprovalService $approvalService,
        protected CurrencyPositionService $positionService,
        protected ComplianceService $complianceService,
        protected TransactionMonitoringService $monitoringService,
        protected MathService $mathService,
        protected AuditService $auditService
    ) {}

    /**
     * Approve a pending transaction.
     *
     * This method delegates to TransactionService::approveTransaction() which handles:
     * - Status transition from Pending to Completed
     * - Position and till balance updates
     * - Double-entry accounting journal entries
     * - AML/Compliance monitoring before approval
     * - Audit logging
     *
     * @throws AccessDeniedHttpException
     */
    public function approve(Request $request, int $transactionId): JsonResponse
    {
        $this->requireManagerOrAdmin();

        $transaction = Transaction::findOrFail($transactionId);

        try {
            $this->approvalService->validateApprovalEligibility($transaction, auth()->id());

            $result = $this->approvalService->approve(
                $transaction,
                auth()->id(),
                $request->ip()
            );

            if (! $result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['transaction'],
            ]);

        } catch (SelfApprovalException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Approval failed: '.$e->getMessage(),
            ], 500);
        }
    }
}
