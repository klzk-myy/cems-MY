<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\AuditService;
use App\Services\ComplianceService;
use App\Services\CurrencyPositionService;
use App\Services\MathService;
use App\Services\TransactionMonitoringService;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionApprovalController extends Controller
{
    public function __construct(
        protected TransactionService $transactionService,
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
     * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
     */
    public function approve(Request $request, int $transactionId): JsonResponse
    {
        $this->requireManagerOrAdmin();

        $transaction = Transaction::findOrFail($transactionId);

        if (! $transaction->status->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction is not pending approval.',
            ], 400);
        }

        try {
            $result = $this->transactionService->approveTransaction(
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
