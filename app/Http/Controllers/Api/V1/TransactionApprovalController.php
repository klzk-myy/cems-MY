<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Domain\SelfApprovalException;
use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\Transaction\TransactionApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionApprovalController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TransactionApprovalService $approvalService
    ) {}

    /**
     * Approve a pending transaction.
     */
    public function approve(Request $request, int $transactionId): JsonResponse
    {
        $this->requireManagerOrAdmin();

        $transaction = Transaction::findOrFail($transactionId);

        // Enforce branch-based authorization: managers can only approve transactions within their own branch
        $user = auth()->user();
        if (! $user->isAdmin() && $transaction->branch_id !== $user->branch_id) {
            return $this->errorResponse('You can only approve transactions for your own branch.', [], 403);
        }

        try {
            $this->approvalService->validateApprovalEligibility($transaction, auth()->id());

            $result = $this->approvalService->approve(
                $transaction,
                auth()->id(),
                $request->ip()
            );

            if (! $result->success) {
                return $this->errorResponse($result->message, [], 422);
            }

            return $this->successResponse($result->transaction, $result->message);

        } catch (SelfApprovalException $e) {
            return $this->errorResponse($e->getMessage(), [], 403);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), [], 400);
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), [], 409);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Approval failed due to a system error. Please contact support.', $e);
        }
    }
}
