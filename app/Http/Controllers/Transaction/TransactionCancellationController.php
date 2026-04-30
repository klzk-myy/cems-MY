<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveCancelRequest;
use App\Http\Requests\CancelTransactionRequest;
use App\Http\Requests\RejectCancelRequest;
use App\Models\Transaction;
use App\Services\TransactionCancellationService;

class TransactionCancellationController extends Controller
{
    public function __construct(
        protected TransactionCancellationService $cancellationService
    ) {}

    /**
     * Show cancellation confirmation form
     */
    public function showCancel(Transaction $transaction)
    {
        if (! $this->canCancel(auth()->user(), $transaction)) {
            abort(403, 'Unauthorized to cancel this transaction.');
        }

        if (! $this->canBeCancelled($transaction)) {
            return back()->with('error', 'This transaction cannot be cancelled in its current state.');
        }

        return view('transactions.cancel', compact('transaction'));
    }

    /**
     * Process transaction cancellation
     *
     * Requests cancellation of a transaction, transitioning it to PendingCancellation
     * status where a supervisor must approve the cancellation.
     */
    public function cancel(CancelTransactionRequest $request, Transaction $transaction)
    {
        if (! $this->canCancel(auth()->user(), $transaction)) {
            abort(403, 'Unauthorized to cancel this transaction.');
        }

        if (! $this->canBeCancelled($transaction)) {
            return back()->with('error', 'This transaction cannot be cancelled in its current state.');
        }

        $validated = $request->validated();

        try {
            $result = $this->cancellationService->requestCancellation(
                $transaction,
                auth()->user(),
                $validated['cancellation_reason']
            );

            if ($result) {
                return redirect()->route('transactions.show', $transaction)
                    ->with('success', 'Cancellation requested. Awaiting supervisor approval.');
            }

            return back()->with('error', 'Cancellation request failed. Please check your permissions or try again.');

        } catch (\Exception $e) {
            return back()->with('error', 'Cancellation failed: '.$e->getMessage());
        }
    }

    /**
     * Show approve cancellation form
     */
    public function showApproveCancel(Transaction $transaction)
    {
        if (! $this->canApproveOrReject(auth()->user())) {
            abort(403, 'Unauthorized to approve cancellations.');
        }

        if (! $transaction->status->isPendingCancellation()) {
            return back()->with('error', 'This transaction is not pending cancellation.');
        }

        return view('transactions.approve-cancellation', compact('transaction'));
    }

    /**
     * Approve a pending cancellation request.
     */
    public function approveCancel(ApproveCancelRequest $request, Transaction $transaction)
    {
        if (! $this->canApproveOrReject(auth()->user())) {
            abort(403, 'Unauthorized to approve cancellations.');
        }

        if (! $transaction->status->isPendingCancellation()) {
            return back()->with('error', 'This transaction is not pending cancellation.');
        }

        $validated = $request->validated();

        try {
            $result = $this->cancellationService->approveCancellation(
                $transaction,
                auth()->user(),
                $validated['reason'] ?? null
            );

            if ($result) {
                return redirect()->route('transactions.show', $transaction)
                    ->with('success', 'Cancellation approved. Transaction has been cancelled.');
            }

            return back()->with('error', 'Failed to approve cancellation. Please try again.');

        } catch (\Exception $e) {
            return back()->with('error', 'Approval failed: '.$e->getMessage());
        }
    }

    /**
     * Show reject cancellation form
     */
    public function showRejectCancel(Transaction $transaction)
    {
        if (! $this->canApproveOrReject(auth()->user())) {
            abort(403, 'Unauthorized to reject cancellations.');
        }

        if (! $transaction->status->isPendingCancellation()) {
            return back()->with('error', 'This transaction is not pending cancellation.');
        }

        return view('transactions.reject-cancellation', compact('transaction'));
    }

    /**
     * Reject a pending cancellation request.
     */
    public function rejectCancel(RejectCancelRequest $request, Transaction $transaction)
    {
        if (! $this->canApproveOrReject(auth()->user())) {
            abort(403, 'Unauthorized to reject cancellations.');
        }

        if (! $transaction->status->isPendingCancellation()) {
            return back()->with('error', 'This transaction is not pending cancellation.');
        }

        $validated = $request->validated();

        try {
            $result = $this->cancellationService->rejectCancellation(
                $transaction,
                auth()->user(),
                $validated['reason']
            );

            if ($result) {
                return redirect()->route('transactions.show', $transaction)
                    ->with('success', 'Cancellation rejected. Transaction has been restored to its previous status.');
            }

            return back()->with('error', 'Failed to reject cancellation. Transaction history may be corrupted.');

        } catch (\Exception $e) {
            return back()->with('error', 'Rejection failed: '.$e->getMessage());
        }
    }

    /**
     * Check if user can cancel transaction
     *
     * All transaction cancellations require manager or admin approval.
     * This enforces segregation of duties - no user should be able to
     * cancel their own transactions without supervisory approval.
     */
    protected function canCancel($user, Transaction $transaction): bool
    {
        if ($user->isAdmin() || $user->isManager()) {
            return true;
        }

        return false;
    }

    /**
     * Check if user can approve or reject a cancellation.
     */
    protected function canApproveOrReject($user): bool
    {
        return $user->isAdmin() || $user->isManager() || $user->isComplianceOfficer();
    }

    /**
     * Check if transaction can be cancelled (or reversed if completed)
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
            return $this->cancellationService->isWithinCancellationWindow($transaction);
        }

        return true;
    }
}
