<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\CancellableTransaction;
use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApiCancelTransactionRequest;
use App\Http\Requests\ApproveCancelRequest;
use App\Http\Requests\RejectCancelRequest;
use App\Models\Transaction;
use App\Services\Transaction\TransactionCancellationService;
use Illuminate\Http\JsonResponse;

class TransactionCancellationController extends Controller
{
    use ApiResponse;
    use CancellableTransaction;

    public function __construct(
        protected TransactionCancellationService $cancellationService
    ) {}

    /**
     * Request cancellation of a transaction.
     *
     * POST /api/transactions/{id}/request-cancellation
     *
     * Transitions the transaction to PendingCancellation status. Only managers
     * and admins may request a cancellation.
     */
    public function requestCancellation(ApiCancelTransactionRequest $request, int $transactionId): JsonResponse
    {
        $transaction = Transaction::findOrFail($transactionId);

        if (! $this->canRequestCancellation(auth()->user(), $transaction)) {
            return $this->errorResponse('Unauthorized to request cancellation for this transaction.', [], 403);
        }

        $validated = $request->validated();

        if (! $this->canBeCancelled($transaction)) {
            return $this->errorResponse('This transaction cannot be cancelled in its current state.', [], 400);
        }

        $result = $this->cancellationService->requestCancellation(
            $transaction,
            auth()->user(),
            $validated['reason']
        );

        if (! $result) {
            return $this->errorResponse('Failed to request cancellation. Please try again.', [], 500);
        }

        return $this->successResponse([
            'transaction' => $transaction->fresh(),
        ], 'Cancellation requested successfully. Awaiting supervisor approval.');
    }

    /**
     * Approve a pending cancellation request.
     *
     * POST /api/transactions/{id}/approve-cancellation
     *
     * Transitions the transaction to Cancelled status. Managers, compliance
     * officers, and admins may approve a cancellation.
     */
    public function approveCancellation(ApproveCancelRequest $request, int $transactionId): JsonResponse
    {
        $transaction = Transaction::findOrFail($transactionId);

        if (! $this->canApproveCancellation(auth()->user(), $transaction)) {
            return $this->errorResponse('Unauthorized to approve cancellation for this transaction.', [], 403);
        }

        if (! $transaction->status->isPendingCancellation()) {
            return $this->errorResponse('This transaction is not pending cancellation.', [], 400);
        }

        $validated = $request->validated();

        $result = $this->cancellationService->approveCancellation(
            $transaction,
            auth()->user(),
            $validated['reason'] ?? null
        );

        if (! $result) {
            return $this->errorResponse('Failed to approve cancellation. Please try again.', [], 500);
        }

        return $this->successResponse([
            'transaction' => $transaction->fresh(),
        ], 'Cancellation approved. Transaction has been cancelled.');
    }

    /**
     * Reject a pending cancellation request.
     *
     * POST /api/transactions/{id}/reject-cancellation
     *
     * Returns the transaction to its previous status. Managers, compliance
     * officers, and admins may reject a cancellation.
     */
    public function rejectCancellation(RejectCancelRequest $request, int $transactionId): JsonResponse
    {
        $transaction = Transaction::findOrFail($transactionId);

        if (! $this->canApproveCancellation(auth()->user(), $transaction)) {
            return $this->errorResponse('Unauthorized to reject cancellation for this transaction.', [], 403);
        }

        if (! $transaction->status->isPendingCancellation()) {
            return $this->errorResponse('This transaction is not pending cancellation.', [], 400);
        }

        $validated = $request->validated();

        $previousStatus = $transaction->status;

        $result = $this->cancellationService->rejectCancellation(
            $transaction,
            auth()->user(),
            $validated['reason']
        );

        if (! $result) {
            return $this->errorResponse('Failed to reject cancellation. Transaction history may be corrupted.', [], 500);
        }

        return $this->successResponse([
            'transaction' => $transaction->fresh(),
            'previous_status' => $previousStatus->value,
        ], 'Cancellation rejected. Transaction has been restored to its previous status.');
    }

    protected function isWithinCancellationWindow(Transaction $transaction): bool
    {
        return $this->cancellationService->isWithinCancellationWindow($transaction);
    }
}
