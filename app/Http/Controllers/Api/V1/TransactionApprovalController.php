<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Transaction\ApproveTransactionAction;
use App\Exceptions\Domain\SelfApprovalException;
use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\Transaction\TransactionApprovalService;
use App\Services\Transaction\TransactionStateMachineFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionApprovalController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ApproveTransactionAction $approveAction,
        protected TransactionApprovalService $approvalService,
        protected TransactionStateMachineFactory $stateMachineFactory
    ) {}

    /**
     * Approve a pending transaction.
     */
    public function approve(Request $request, int $transactionId): JsonResponse
    {
        $transaction = Transaction::findOrFail($transactionId);

        $this->authorize('approve', $transaction);

        $result = $this->approveAction->execute($transaction, (int) auth()->id(), $request->ip());

        if (! $result->ok) {
            return $this->errorResponse($result->message, [], 422);
        }

        return $this->successResponse($result->transaction, $result->message);
    }

    /**
     * Reject a pending transaction.
     */
    public function reject(Request $request, int $transactionId): JsonResponse
    {
        $transaction = Transaction::findOrFail($transactionId);

        $this->authorize('reject', $transaction);

        $reason = $request->input('reason', 'Rejected by approver');

        try {
            if (! $this->approvalService->reject($transaction, (int) auth()->id(), $reason)) {
                return $this->errorResponse('Transaction cannot be rejected from its current status.', [], 422);
            }

            return $this->successResponse($transaction, 'Transaction has been rejected.');
        } catch (SelfApprovalException $e) {
            return $this->errorResponse('You cannot reject your own transaction. Segregation of duties requires a different approver.', [], 422);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse('The transaction is not eligible for rejection in its current state.', [], 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Rejection failed due to a system error. Please contact support.', [], 500);
        }
    }
}
