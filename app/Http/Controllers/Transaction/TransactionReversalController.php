<?php

namespace App\Http\Controllers\Transaction;

use App\Exceptions\Domain\DomainException;
use App\Http\Concerns\HandlesControllerErrors;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReverseTransactionRequest;
use App\Models\Transaction;
use App\Services\Transaction\TransactionApprovalService;
use App\Services\Transaction\TransactionCancellationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TransactionReversalController extends Controller
{
    use HandlesControllerErrors;

    public function __construct(
        protected TransactionCancellationService $cancellationService,
        protected TransactionApprovalService $approvalService,
    ) {}

    /**
     * Display the reversal confirmation form for a completed transaction.
     */
    public function showReverse(Transaction $transaction): View|RedirectResponse
    {
        $this->authorize('reverse', $transaction);

        if (! $this->cancellationService->canReverse($transaction)) {
            return back()->with('error', 'This transaction cannot be reversed. Only completed transactions within the reversal window are eligible.');
        }

        $transaction->load(['customer', 'user']);

        return view('transactions.reverse', compact('transaction'));
    }

    /**
     * Reverse a completed transaction.
     *
     * Reverses positions, till balances, journal entries and teller
     * allocations on the original transaction, then creates a refund
     * transaction that requires compliance approval and completion before
     * the physical settlement is acknowledged.
     */
    public function reverse(ReverseTransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        $this->authorize('reverse', $transaction);

        try {
            $reversed = $this->cancellationService->requestReversal(
                $transaction,
                auth()->user(),
                $request->validated('reason')
            );
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return $this->handleExceptionWeb($e, 'Transaction reversal failed', 'Reversal failed due to a system error. Please contact support.', [
                'transaction_id' => $transaction->id,
            ]);
        }

        if (! $reversed) {
            return back()->with('error', 'This transaction cannot be reversed. Only completed transactions within the reversal window are eligible.');
        }

        $refund = $transaction->fresh()->refundTransaction;

        return redirect()->route('transactions.show', $refund ?? $transaction)
            ->with('success', 'Transaction reversed. Refund transaction #'.($refund?->id ?? '?').' created and pending compliance approval.');
    }

    /**
     * Complete an approved refund transaction (Approved -> Completed).
     *
     * The compensating financial legs were already booked when the original
     * transaction was reversed; this step records the compliance-approved
     * physical cash settlement on the refund record.
     */
    public function completeRefund(Transaction $transaction): RedirectResponse
    {
        $this->authorize('completeRefund', $transaction);

        try {
            $result = $this->approvalService->completeRefund(
                $transaction,
                (int) auth()->id(),
                request()->ip()
            );
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return $this->handleExceptionWeb($e, 'Refund completion failed', 'Refund completion failed. Please try again.', [
                'transaction_id' => $transaction->id,
            ]);
        }

        if (! $result->success) {
            return back()->with('error', $result->message);
        }

        return redirect()->route('transactions.show', $transaction)
            ->with('success', $result->message);
    }
}
