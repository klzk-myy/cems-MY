<?php

namespace App\Http\Controllers\Transaction;

use App\Actions\Transaction\ApproveCancellationAction;
use App\Actions\Transaction\RejectCancellationAction;
use App\Actions\Transaction\RequestCancellationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveCancelRequest;
use App\Http\Requests\CancelTransactionRequest;
use App\Http\Requests\RejectCancelRequest;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TransactionCancellationController extends Controller
{
    public function __construct(
        protected RequestCancellationAction $requestAction,
        protected ApproveCancellationAction $approveAction,
        protected RejectCancellationAction $rejectAction,
    ) {}

    public function cancel(CancelTransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        $this->authorize('requestCancellation', $transaction);

        $validated = $request->validated();

        $result = $this->requestAction->execute(
            $transaction,
            auth()->user(),
            $validated['reason']
        );

        if (! $result->ok) {
            return back()->with('error', $result->message);
        }

        return redirect()->route('transactions.show', $transaction)
            ->with('success', $result->message);
    }

    public function showApproveCancel(Transaction $transaction): View|RedirectResponse
    {
        $this->authorize('approveCancellation', $transaction);

        if (! $transaction->status->isPendingCancellation()) {
            return back()->with('error', 'This transaction is not pending cancellation.');
        }

        return view('transactions.approve-cancellation', compact('transaction'));
    }

    public function approveCancel(ApproveCancelRequest $request, Transaction $transaction): RedirectResponse
    {
        $this->authorize('approveCancellation', $transaction);

        $validated = $request->validated();

        $result = $this->approveAction->execute(
            $transaction,
            auth()->user(),
            $validated['reason'] ?? null
        );

        if (! $result->ok) {
            return back()->with('error', $result->message);
        }

        return redirect()->route('transactions.show', $transaction)
            ->with('success', $result->message);
    }

    public function showRejectCancel(Transaction $transaction): View|RedirectResponse
    {
        $this->authorize('rejectCancellation', $transaction);

        if (! $transaction->status->isPendingCancellation()) {
            return back()->with('error', 'This transaction is not pending cancellation.');
        }

        return view('transactions.reject-cancellation', compact('transaction'));
    }

    public function rejectCancel(RejectCancelRequest $request, Transaction $transaction): RedirectResponse
    {
        $this->authorize('rejectCancellation', $transaction);

        $validated = $request->validated();

        $result = $this->rejectAction->execute(
            $transaction,
            auth()->user(),
            $validated['reason']
        );

        if (! $result->ok) {
            return back()->with('error', $result->message);
        }

        return redirect()->route('transactions.show', $transaction)
            ->with('success', $result->message);
    }
}
