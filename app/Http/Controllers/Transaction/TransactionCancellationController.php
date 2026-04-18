<?php

namespace App\Http\Controllers\Transaction;

use App\Enums\AccountCode;
use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SystemLog;
use App\Models\Transaction;
use App\Services\AccountingService;
use App\Services\ComplianceService;
use App\Services\CurrencyPositionService;
use App\Services\MathService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionCancellationController extends Controller
{
    public function __construct(
        protected CurrencyPositionService $positionService,
        protected ComplianceService $complianceService,
        protected MathService $mathService,
        protected AccountingService $accountingService
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

        if (! $this->canBeCancelled($transaction)) {
            return back()->with('error', 'This transaction cannot be cancelled in its current state.');
        }

        DB::beginTransaction();
        try {
            $originalTillId = $transaction->till_id ?? 'MAIN';
            $originalStatus = $transaction->status;

            // Completed transactions are reversed (not cancelled) and require a refund
            $isCompleted = $transaction->status->isCompleted();
            $refundTransaction = null;

            if ($isCompleted) {
                // Completed transactions get reversed with a refund
                $refundTransaction = $this->createRefundTransaction($transaction);
                $newStatus = TransactionStatus::Reversed;
            } else {
                // Non-completed transactions are simply cancelled
                $newStatus = TransactionStatus::Cancelled;
            }

            // Update status and increment version to prevent race conditions
            $transaction->status = $newStatus;
            $transaction->cancelled_at = now();
            $transaction->cancelled_by = auth()->id();
            $transaction->cancellation_reason = $validated['cancellation_reason'];
            $transaction->version = ($transaction->version ?? 0) + 1;
            $transaction->save();

            // Reverse stock position only for completed transactions (they have positions to reverse)
            if ($isCompleted) {
                $this->reverseStockPosition($transaction, $originalTillId);
                $this->createReversingJournalEntries($transaction);
            }

            SystemLog::create([
                'user_id' => auth()->id(),
                'action' => $isCompleted ? 'transaction_reversed' : 'transaction_cancelled',
                'entity_type' => 'Transaction',
                'entity_id' => $transaction->id,
                'old_values' => ['status' => $originalStatus->value],
                'new_values' => [
                    'status' => $newStatus->value,
                    'refund_transaction_id' => $refundTransaction?->id,
                    'reason' => $validated['cancellation_reason'],
                ],
                'ip_address' => $request->ip(),
            ]);

            DB::commit();

            $message = $isCompleted
                ? 'Transaction reversed successfully. Refund transaction created.'
                : 'Transaction cancelled successfully.';

            return redirect()->route('transactions.show', $transaction)
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();

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

    /**
     * Create refund transaction
     */
    protected function createRefundTransaction(Transaction $original): Transaction
    {
        $refundType = $original->type->opposite();
        $customer = Customer::findOrFail($original->customer_id);
        $amountLocal = $this->mathService->multiply(
            (string) $original->amount_foreign,
            (string) $original->rate
        );

        // Evaluate compliance for refund transaction
        $holdCheck = $this->complianceService->requiresHold($amountLocal, $customer);

        $status = TransactionStatus::Completed;
        $holdReason = null;

        if ($holdCheck['requires_hold']) {
            $status = TransactionStatus::PendingApproval;
            $holdReason = implode(', ', $holdCheck['reasons']);
        }

        // Log compliance decision for refund audit trail
        SystemLog::create([
            'user_id' => auth()->id(),
            'action' => 'refund_compliance_check',
            'entity_type' => 'Transaction',
            'entity_id' => null,
            'new_values' => [
                'original_transaction_id' => $original->id,
                'amount_local' => $amountLocal,
                'status' => $status->value,
                'hold_reason' => $holdReason,
                'compliance_reasons' => $holdCheck['reasons'],
            ],
        ]);

        return Transaction::create([
            'customer_id' => $original->customer_id,
            'user_id' => auth()->id(),
            'branch_id' => $original->branch_id,
            'till_id' => $original->till_id,
            'type' => $refundType,
            'currency_code' => $original->currency_code,
            'amount_foreign' => $original->amount_foreign,
            'amount_local' => $amountLocal,
            'rate' => $original->rate,
            'purpose' => 'Refund: '.$original->purpose,
            'source_of_funds' => 'Refund',
            'status' => $status,
            'hold_reason' => $holdReason,
            'cdd_level' => $original->cdd_level,
            'original_transaction_id' => $original->id,
            'is_refund' => true,
            'approved_by' => $status === TransactionStatus::Completed ? auth()->id() : null,
            'approved_at' => $status === TransactionStatus::Completed ? now() : null,
        ]);
    }

    /**
     * Reverse stock position
     */
    protected function reverseStockPosition(Transaction $transaction, ?string $tillId = null): void
    {
        $reverseType = $transaction->type->opposite();
        $this->positionService->updatePosition(
            $transaction->currency_code,
            (string) $transaction->amount_foreign,
            (string) $transaction->rate,
            $reverseType->value,
            $tillId ?? $transaction->till_id ?? 'MAIN'
        );
    }

    /**
     * Create reversing journal entries
     */
    protected function createReversingJournalEntries(Transaction $transaction): void
    {
        $entries = [];
        if ($transaction->type->isBuy()) {
            $entries = [
                [
                    'account_code' => AccountCode::CASH_MYR->value,
                    'debit' => $transaction->amount_local,
                    'credit' => '0',
                    'description' => "Refund for cancelled transaction #{$transaction->id}",
                ],
                [
                    'account_code' => AccountCode::FOREIGN_CURRENCY_INVENTORY->value,
                    'debit' => '0',
                    'credit' => $transaction->amount_local,
                    'description' => "Reversal: {$transaction->currency_code} refund",
                ],
            ];
        } else {
            // SELL cancellation: use cost basis for inventory restoration
            // We sold currency that we had acquired at average cost, not at the sale price
            $position = $this->positionService->getPosition(
                $transaction->currency_code,
                $transaction->till_id ?? 'MAIN'
            );
            $avgCost = $position ? $position->avg_cost_rate : $transaction->rate;
            $costBasis = $this->mathService->multiply((string) $transaction->amount_foreign, $avgCost);

            $entries = [
                [
                    'account_code' => AccountCode::FOREIGN_CURRENCY_INVENTORY->value,
                    'debit' => $costBasis,  // Restore inventory at cost basis, not sale price
                    'credit' => '0',
                    'description' => "Refund for cancelled transaction #{$transaction->id}",
                ],
                [
                    'account_code' => AccountCode::CASH_MYR->value,
                    'debit' => '0',
                    'credit' => $transaction->amount_local,
                    'description' => "Reversal: {$transaction->currency_code} refund",
                ],
            ];

            // Record gain/loss on cancellation if sale proceeds differ from cost basis
            $gainLoss = $this->mathService->subtract($transaction->amount_local, $costBasis);
            if ($this->mathService->compare($gainLoss, '0') !== 0) {
                if ($this->mathService->compare($gainLoss, '0') > 0) {
                    // Gain - we sold higher than cost, refund net of gain
                    $entries[1]['credit'] = $costBasis;
                    $entries[] = [
                        'account_code' => AccountCode::FOREX_TRADING_REVENUE->value,
                        'debit' => $gainLoss,
                        'credit' => '0',
                        'description' => "Loss recovery on {$transaction->currency_code} cancellation",
                    ];
                } else {
                    // Loss - we sold lower than cost, refund net of loss
                    $entries[1]['credit'] = $transaction->amount_local;
                }
            }
        }

        $this->accountingService->createJournalEntry(
            $entries,
            'TransactionCancellation',
            $transaction->id,
            "Cancellation of Transaction #{$transaction->id}"
        );
    }
}
