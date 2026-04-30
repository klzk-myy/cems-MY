<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiCancelTransactionRequest;
use App\Http\Requests\ApproveCancelRequest;
use App\Http\Requests\RejectCancelRequest;
use App\Models\Transaction;
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
     * Transitions transaction to PendingCancellation status.
     * Requires manager or admin role.
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
     * Transitions transaction to Cancelled status.
     * Requires manager, compliance officer, or admin role.
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
     * Returns transaction to its previous status (InProgress, Completed, etc.).
     * Requires manager, compliance officer, or admin role.
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
     * Check if user can request cancellation
     */
    protected function canRequestCancellation($user, Transaction $transaction): bool
    {
        return $user->isAdmin() || $user->isManager();
    }

    /**
     * Check if user can approve cancellation (approve or reject)
     */
    protected function canApproveCancellation($user, Transaction $transaction): bool
    {
        // Manager, compliance officer, or admin
        return $user->isAdmin() || $user->isManager() || $user->isComplianceOfficer();
    }

    /**
     * Check if transaction can be cancelled (or reversed if completed)
     */
    protected function canBeCancelled(Transaction $transaction): bool
    {
        $status = $transaction->status;

        // Already in a final state that cannot be changed
        if ($status->isFinalized()) {
            return false;
        }

        // Already cancelled or reversed cannot be cancelled again
        if ($status->isCancelled() || $status->isReversed()) {
            return false;
        }

        // Already cancelled (indicated by cancelled_at being set even if status hasn't updated)
        if ($transaction->cancelled_at !== null) {
            return false;
        }

        // Cannot cancel a refund transaction
        if ($transaction->is_refund) {
            return false;
        }

        // Completed transactions can be reversed (within time window)
        if ($status->isCompleted()) {
            return $this->isWithinCancellationWindow($transaction);
        }

        // All other non-final states can be cancelled
        return true;
    }

    /**
     * Check if transaction is within the cancellation window
     */
    protected function isWithinCancellationWindow(Transaction $transaction): bool
    {
        $cancellationWindowHours = config('cems.transaction_cancellation_window_hours', 24);

        return $transaction->created_at->diffInHours(now()) <= $cancellationWindowHours;
    }
}
