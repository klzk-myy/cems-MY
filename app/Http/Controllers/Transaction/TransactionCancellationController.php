<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\TransactionCancellationService;
use Illuminate\Http\Request;

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
     * State transitions:
     * - draft, pending_approval, approved, processing, failed, rejected -> cancelled
     * - completed -> reversed (not cancelled; creates refund transaction)
     */
    public function cancel(Request $request, Transaction $transaction)
    {
        if (! $this->canCancel(auth()->user(), $transaction)) {
            abort(403, 'Unauthorized to cancel this transaction.');
        }

        if (! $this->canBeCancelled($transaction)) {
            return back()->with('error', 'This transaction cannot be cancelled in its current state.');
        }

        $validated = $request->validate([
            'cancellation_reason' => [
                'required',
                'string',
                'min:20',
                'max:1000',
            ],
            'confirm_understanding' => 'required|accepted',
        ], [
            'cancellation_reason.min' => 'Cancellation reason must be at least 20 characters for AML audit compliance. Please provide a detailed explanation of why this transaction is being cancelled.',
        ]);

        try {
            $result = $this->cancellationService->cancelTransaction(
                $transaction,
                auth()->id(),
                $validated['cancellation_reason']
            );

            $message = $result['refund_transaction']
                ? 'Transaction reversed successfully. Refund transaction created.'
                : 'Transaction cancelled successfully.';

            return redirect()->route('transactions.show', $transaction)
                ->with('success', $message);

        } catch (\Exception $e) {
            return back()->with('error', 'Cancellation failed: '.$e->getMessage());
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
        // Managers and admins can cancel any transaction
        if ($user->isAdmin() || $user->isManager()) {
            return true;
        }

        // Tellers cannot cancel any transactions - requires manager approval
        // This enforces segregation of duties
        return false;
    }

    /**
     * Check if transaction can be cancelled (or reversed if completed)
     *
     * State transitions:
     * - draft, pending_approval, approved, processing, failed, rejected -> can be cancelled
     * - completed -> can be reversed (different from cancelled)
     * - finalized, already cancelled/reversed/rejected -> cannot be changed
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
            return $this->cancellationService->isWithinCancellationWindow($transaction);
        }

        // All other non-final states can be cancelled
        return true;
    }
}
