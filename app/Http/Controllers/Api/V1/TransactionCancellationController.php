<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AccountCode;
use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SystemLog;
use App\Models\Transaction;
use App\Services\AccountingService;
use App\Services\AuditService;
use App\Services\ComplianceService;
use App\Services\CurrencyPositionService;
use App\Services\MathService;
use App\Services\TransactionCancellationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionCancellationController extends Controller
{
    public function __construct(
        protected CurrencyPositionService $positionService,
        protected ComplianceService $complianceService,
        protected MathService $mathService,
        protected AccountingService $accountingService,
        protected TransactionCancellationService $cancellationService,
        protected AuditService $auditService
    ) {}

    /**
     * Request cancellation of a transaction.
     *
     * POST /api/transactions/{id}/request-cancellation
     *
     * Transitions transaction to PendingCancellation status.
     * Requires manager or admin role.
     */
    public function requestCancellation(Request $request, int $transactionId): JsonResponse
    {
        $transaction = Transaction::findOrFail($transactionId);

        if (! $this->canRequestCancellation(auth()->user(), $transaction)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to request cancellation for this transaction.',
            ], 403);
        }

        $validated = $request->validate([
            'reason' => 'required|string|min:10|max:1000',
        ]);

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
    public function approveCancellation(Request $request, int $transactionId): JsonResponse
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

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

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
    public function rejectCancellation(Request $request, int $transactionId): JsonResponse
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

        $validated = $request->validate([
            'reason' => 'required|string|min:10|max:500',
        ]);

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
     * Cancel a transaction (legacy direct cancel).
     *
     * POST /api/transactions/{id}/cancel
     *
     * State transitions:
     * - draft, pending_approval, approved, processing, failed, rejected -> cancelled
     * - completed -> reversed (not cancelled; creates refund transaction)
     *
     * @deprecated Use requestCancellation/approveCancellation for proper segregation of duties
     */
    public function cancel(Request $request, int $transactionId): JsonResponse
    {
        $transaction = Transaction::findOrFail($transactionId);

        if (! $this->canCancel(auth()->user(), $transaction)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to cancel this transaction.',
            ], 403);
        }

        $validated = $request->validate([
            'cancellation_reason' => 'required|string|min:10|max:1000',
        ]);

        if (! $this->canBeCancelled($transaction)) {
            return response()->json([
                'success' => false,
                'message' => 'This transaction cannot be cancelled in its current state.',
            ], 400);
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

            // Reverse stock position only for completed transactions
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

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'transaction' => $transaction->fresh(),
                    'refund_transaction' => $refundTransaction,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Cancellation failed: '.$e->getMessage(),
            ], 500);
        }
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
     * Check if user can cancel transaction (legacy direct cancel)
     */
    protected function canCancel($user, Transaction $transaction): bool
    {
        return $user->isAdmin() || $user->isManager();
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

    protected function createRefundTransaction(Transaction $original): Transaction
    {
        $refundType = $original->type->opposite();
        $customer = Customer::findOrFail($original->customer_id);
        $amountLocal = $this->mathService->multiply(
            (string) $original->amount_foreign,
            (string) $original->rate
        );

        $holdCheck = $this->complianceService->requiresHold($amountLocal, $customer);

        $status = TransactionStatus::Completed;
        $holdReason = null;

        if ($holdCheck['requires_hold']) {
            $status = TransactionStatus::PendingApproval;
            $holdReason = implode(', ', $holdCheck['reasons']);
        }

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
