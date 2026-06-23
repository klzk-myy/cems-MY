<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Domain\SelfApprovalException;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\Transaction\TransactionApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TransactionApprovalController extends Controller
{
    public function __construct(
        protected TransactionApprovalService $approvalService
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
     */
    public function approve(Request $request, int $transactionId): JsonResponse
    {
        $this->requireManagerOrAdmin();

        $transaction = Transaction::findOrFail($transactionId);

        // Enforce branch-based authorization: managers can only approve transactions within their own branch
        $user = auth()->user();
        if (! $user->isAdmin() && $transaction->branch_id !== $user->branch_id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only approve transactions for your own branch.',
            ], 403);
        }

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
            Log::error('Transaction approval failed (API)', [
                'transaction_id' => $transaction->id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Approval failed due to a system error. Please contact support.',
                'code' => 'INTERNAL_ERROR',
            ], 500);
        }
    }
}
