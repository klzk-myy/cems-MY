<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiCancelTransactionRequest;
use App\Http\Requests\ApproveCancelRequest;
use App\Http\Requests\RejectCancelRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionCancellationService;
use Illuminate\Http\JsonResponse;

class TransactionCancellationController extends Controller
{
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
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to request cancellation for this transaction.',
            ], 403);
        }

        $validated = $request->validated();

        if (! $this->canBeCancelled($transaction)) {
            return response()->json([
                'success' => false,
                'message' => 'This transaction cannot be cancelled in its current state.',
            ], 400);
        }

        $result = $this->cancellationService->requestCancellation(
            $transaction,
            auth()->user(),
            $validated['reason']
        );

        if (! $result) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to request cancellation. Please try again.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cancellation requested successfully. Awaiting supervisor approval.',
            'data' => [
                'transaction' => $transaction->fresh(),
            ],
        ]);
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
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to approve cancellation for this transaction.',
            ], 403);
        }

        if (! $transaction->status->isPendingCancellation()) {
            return response()->json([
                'success' => false,
                'message' => 'This transaction is not pending cancellation.',
            ], 400);
        }

        $validated = $request->validated();

        $result = $this->cancellationService->approveCancellation(
            $transaction,
            auth()->user(),
            $validated['reason'] ?? null
        );

        if (! $result) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve cancellation. Please try again.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cancellation approved. Transaction has been cancelled.',
            'data' => [
                'transaction' => $transaction->fresh(),
            ],
        ]);
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
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to reject cancellation for this transaction.',
            ], 403);
        }

        if (! $transaction->status->isPendingCancellation()) {
            return response()->json([
                'success' => false,
                'message' => 'This transaction is not pending cancellation.',
            ], 400);
        }

        $validated = $request->validated();

        $previousStatus = $transaction->status;

        $result = $this->cancellationService->rejectCancellation(
            $transaction,
            auth()->user(),
            $validated['reason']
        );

        if (! $result) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject cancellation. Transaction history may be corrupted.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cancellation rejected. Transaction has been restored to its previous status.',
            'data' => [
                'transaction' => $transaction->fresh(),
                'previous_status' => $previousStatus->value,
            ],
        ]);
    }

    /**
     * Determine whether the user is allowed to request a cancellation.
     *
     * Only managers and admins may request a cancellation.
     */
    protected function canRequestCancellation(User $user, Transaction $transaction): bool
    {
        return $user->isAdmin() || $user->isManager();
    }

    /**
     * Determine whether the user is allowed to approve or reject a cancellation.
     *
     * Managers, compliance officers, and admins may approve or reject.
     */
    protected function canApproveCancellation(User $user, Transaction $transaction): bool
    {
        return $user->isAdmin() || $user->isManager() || $user->isComplianceOfficer();
    }

    /**
     * Determine whether the transaction can be cancelled or reversed.
     *
     * Finalized, cancelled, reversed, and refund transactions cannot be
     * cancelled. Completed transactions may only be reversed within the
     * cancellation window.
     */
    protected function canBeCancelled(Transaction $transaction): bool
    {
        $status = $transaction->status;

        if ($status->isFinalized()) {
            return false;
        }

        if ($status->isCancelled() || $status->isReversed()) {
            return false;
        }

        if ($transaction->cancelled_at !== null) {
            return false;
        }

        if ($transaction->is_refund) {
            return false;
        }

        if ($status->isCompleted()) {
            return $this->isWithinCancellationWindow($transaction);
        }

        return true;
    }

    /**
     * Determine whether the completed transaction is still within the cancellation window.
     */
    protected function isWithinCancellationWindow(Transaction $transaction): bool
    {
        return $this->cancellationService->isWithinCancellationWindow($transaction);
    }
}
