<?php

namespace App\Services;

use App\Enums\StockReservationStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Events\TransactionCancelled;
use App\Models\CurrencyPosition;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\StockReservation;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\TransactionCancellationPendingNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Transaction Cancellation Service
 *
 * Handles transaction cancellation and reversal workflows using the state machine.
 * Manages the complete lifecycle of cancelling transactions including:
 * - Cancellation requests (manager approval required)
 * - Cancellation approval/rejection (supervisor approval required)
 * - Reversal of completed transactions (within 24-hour window)
 * - Position reversal (stock/cash)
 * - Reversing journal entries
 * - Refund transaction creation
 */
class TransactionCancellationService
{
    /**
     * Create a new TransactionCancellationService instance.
     *
     * @param  MathService  $mathService  Math service for precise calculations
     * @param  AuditService  $auditService  Audit service for logging
     */
    public function __construct(
        protected MathService $mathService,
        protected AuditService $auditService,
        protected AccountingService $accountingService,
        protected CurrencyPositionService $positionService,
        protected ComplianceService $complianceService,
    ) {}

    /**
     * Cancel a transaction directly (without pending approval workflow).
     *
     * This method is deprecated. ALL cancellations must go through the
     * PendingCancellation state machine via requestCancellation() to enforce
     * dual-control (segregation of duties) as required by BNM AML/CFT regulations.
     *
     * @param  Transaction  $transaction  The transaction to cancel
     * @param  int  $userId  The user ID performing the cancellation
     * @param  string  $reason  Reason for cancellation
     *
     * @throws \RuntimeException Always - direct cancellation is not allowed
     */
    public function cancelTransaction(Transaction $transaction, int $userId, string $reason): never
    {
        throw new \RuntimeException(
            'Direct cancellation is not allowed. All cancellations must go through '.
            'PendingCancellation status via requestCancellation() method. '.
            'This enforces dual-control segregation of duties as required by BNM AML/CFT regulations.'
        );
    }

    /**
     * Request cancellation of a transaction.
     *
     * Requires manager or admin role. Transitions transaction to PendingCancellation
     * status, awaiting supervisor approval.
     *
     * @param  Transaction  $transaction  The transaction to cancel
     * @param  User  $requester  The user requesting cancellation
     * @param  string  $reason  Reason for cancellation
     * @return bool True if cancellation request was successful
     *
     * @throws \InvalidArgumentException If user is not authorized or transaction cannot be cancelled
     */
    public function requestCancellation(Transaction $transaction, User $requester, string $reason): bool
    {
        // Authorization check: must be manager or admin
        if (! $requester->role->isManager()) {
            Log::warning('Non-manager attempted transaction cancellation request', [
                'transaction_id' => $transaction->id,
                'user_id' => $requester->id,
                'user_role' => $requester->role->value,
            ]);

            return false;
        }

        // Check if transaction can be cancelled
        if (! $this->canCancel($transaction)) {
            Log::warning('Transaction cannot be cancelled', [
                'transaction_id' => $transaction->id,
                'current_status' => $transaction->status->value,
            ]);

            return false;
        }

        return DB::transaction(function () use ($transaction, $requester, $reason) {
            $stateMachine = new TransactionStateMachine($transaction);

            $previousStatus = $transaction->status;

            $result = $stateMachine->transitionTo(TransactionStatus::PendingCancellation, [
                'reason' => $reason,
                'user_id' => $requester->id,
            ]);

            if ($result) {
                Log::info('Transaction cancellation requested', [
                    'transaction_id' => $transaction->id,
                    'requested_by' => $requester->id,
                    'reason' => $reason,
                    'previous_status' => $previousStatus->value,
                ]);

                // Notify compliance team of pending cancellation
                $this->notifyPendingCancellation($transaction, $requester, $reason);

                // Audit logging
                $this->auditService->logTransaction(
                    'cancellation_requested',
                    $transaction->id,
                    [
                        'old' => ['status' => $previousStatus->value],
                        'new' => [
                            'status' => TransactionStatus::PendingCancellation->value,
                            'reason' => $reason,
                            'requested_by' => $requester->id,
                        ],
                    ]
                );
            }

            return $result;
        });
    }

    /**
     * Approve a pending cancellation request.
     *
     * Requires manager, compliance officer, or admin role (different from requester).
     * Transitions transaction to Cancelled status.
     *
     * @param  Transaction  $transaction  The transaction to approve cancellation for
     * @param  User  $approver  The user approving the cancellation
     * @param  string|null  $reason  Optional reason for approval
     * @return bool True if approval was successful
     */
    public function approveCancellation(Transaction $transaction, User $approver, ?string $reason = null): bool
    {
        // Must be in PendingCancellation status
        if (! $transaction->status->isPendingCancellation()) {
            Log::warning('Cannot approve cancellation - transaction not pending', [
                'transaction_id' => $transaction->id,
                'current_status' => $transaction->status->value,
            ]);

            return false;
        }

        // Authorization check: must be manager, compliance officer, or admin
        if (! $approver->role->isManager() && ! $approver->role->isComplianceOfficer()) {
            Log::warning('Non-authorized user attempted cancellation approval', [
                'transaction_id' => $transaction->id,
                'user_id' => $approver->id,
                'user_role' => $approver->role->value,
            ]);

            return false;
        }

        // Segregation of duties check: approver cannot be the same user who requested cancellation
        // This enforces dual-control as required by BNM AML/CFT regulations
        $cancellationRequest = $this->getLastCancellationRequest($transaction);
        if ($cancellationRequest && ($cancellationRequest['user_id'] ?? null) === $approver->id) {
            Log::warning('Self-approval of cancellation attempted - segregation of duties violation', [
                'transaction_id' => $transaction->id,
                'approver_id' => $approver->id,
                'requester_id' => $cancellationRequest['user_id'],
            ]);

            return false;
        }

        return DB::transaction(function () use ($transaction, $approver, $reason) {
            $stateMachine = new TransactionStateMachine($transaction);

            $previousStatus = $transaction->status;

            // Check if this transaction has an active stock reservation
            // When a transaction is created with PendingApproval status, a stock reservation
            // is created to prevent overselling. We need to release it on cancellation.
            $hasReservation = StockReservation::where('transaction_id', $transaction->id)
                ->where('status', StockReservationStatus::Pending)
                ->exists();

            $result = $stateMachine->transitionTo(TransactionStatus::Cancelled, [
                'reason' => $reason ?? 'Cancellation approved',
                'user_id' => $approver->id,
                'approved_by' => $approver->id,
            ]);

            if ($result) {
                // Release stock reservation if one exists
                if ($hasReservation) {
                    $this->positionService->releaseStockReservation($transaction->id);
                    Log::info('Stock reservation released for cancelled transaction', [
                        'transaction_id' => $transaction->id,
                    ]);
                }
                Log::info('Transaction cancellation approved', [
                    'transaction_id' => $transaction->id,
                    'approved_by' => $approver->id,
                    'reason' => $reason,
                ]);

                // Audit logging
                $this->auditService->logTransaction(
                    'cancellation_approved',
                    $transaction->id,
                    [
                        'old' => ['status' => $previousStatus->value],
                        'new' => [
                            'status' => TransactionStatus::Cancelled->value,
                            'reason' => $reason,
                            'approved_by' => $approver->id,
                        ],
                    ]
                );

                // Dispatch TransactionCancelled event for async listeners
                Event::dispatch(new TransactionCancelled($transaction, $reason, $approver->id));
            }

            return $result;
        });
    }

    /**
     * Reject a pending cancellation request.
     *
     * Requires manager, compliance officer, or admin role. Returns transaction
     * to its previous status (InProgress, Completed, etc.).
     *
     * @param  Transaction  $transaction  The transaction to reject cancellation for
     * @param  User  $rejector  The user rejecting the cancellation
     * @param  string  $reason  Reason for rejection
     * @return bool True if rejection was successful
     */
    public function rejectCancellation(Transaction $transaction, User $rejector, string $reason): bool
    {
        // Must be in PendingCancellation status
        if (! $transaction->status->isPendingCancellation()) {
            Log::warning('Cannot reject cancellation - transaction not pending', [
                'transaction_id' => $transaction->id,
                'current_status' => $transaction->status->value,
            ]);

            return false;
        }

        // Authorization check: must be manager, compliance officer, or admin
        if (! $rejector->role->isManager() && ! $rejector->role->isComplianceOfficer()) {
            Log::warning('Non-authorized user attempted cancellation rejection', [
                'transaction_id' => $transaction->id,
                'user_id' => $rejector->id,
                'user_role' => $rejector->role->value,
            ]);

            return false;
        }

        return DB::transaction(function () use ($transaction, $rejector, $reason) {
            $previousStatus = $transaction->status;
            $previousHistory = $transaction->transition_history ?? [];

            // Determine the target status based on transition history
            // Find the status before PendingCancellation was applied
            $targetStatus = $this->determinePreviousStatus($transaction);

            if (! $targetStatus) {
                Log::warning('Cannot determine previous status for cancellation rejection', [
                    'transaction_id' => $transaction->id,
                ]);

                return false;
            }

            // Use forceStatus to bypass normal transition rules since we're going back
            $stateMachine = new TransactionStateMachine($transaction);
            $result = $stateMachine->forceStatus($targetStatus, "Cancellation rejected: {$reason}");

            if ($result) {
                Log::info('Transaction cancellation rejected', [
                    'transaction_id' => $transaction->id,
                    'rejected_by' => $rejector->id,
                    'reason' => $reason,
                    'previous_status' => $previousStatus->value,
                    'returned_to_status' => $targetStatus->value,
                ]);

                // Audit logging
                $this->auditService->logTransaction(
                    'cancellation_rejected',
                    $transaction->id,
                    [
                        'old' => ['status' => $previousStatus->value],
                        'new' => [
                            'status' => $targetStatus->value,
                            'reason' => $reason,
                            'rejected_by' => $rejector->id,
                        ],
                    ]
                );
            }

            return $result;
        });
    }

    /**
     * Request reversal of a completed transaction.
     *
     * Reversals are only allowed for completed transactions within the 24-hour
     * cancellation window. Creates a refund transaction and reverses positions.
     *
     * @param  Transaction  $transaction  The transaction to reverse
     * @param  User  $requester  The user requesting reversal
     * @param  string  $reason  Reason for reversal
     * @return bool True if reversal was successful
     *
     * @throws \InvalidArgumentException If transaction cannot be reversed
     */
    public function requestReversal(Transaction $transaction, User $requester, string $reason): bool
    {
        // Check user permissions
        if (! $this->canUserReverse($requester, $transaction)) {
            Log::warning('User not authorized to reverse transaction', [
                'transaction_id' => $transaction->id,
                'user_id' => $requester->id,
                'user_role' => $requester->role->value,
            ]);

            return false;
        }

        // Check if transaction can be reversed
        if (! $this->canReverse($transaction)) {
            Log::warning('Transaction cannot be reversed', [
                'transaction_id' => $transaction->id,
                'current_status' => $transaction->status->value,
                'within_window' => $this->isWithinCancellationWindow($transaction),
            ]);

            return false;
        }

        // Verify 24-hour window
        if (! $this->isWithinCancellationWindow($transaction)) {
            Log::warning('Transaction reversal window has expired', [
                'transaction_id' => $transaction->id,
                'transaction_created_at' => $transaction->created_at->toIso8601String(),
                'window_hours' => config('cems.transaction_cancellation_window_hours', 24),
            ]);

            return false;
        }

        return DB::transaction(function () use ($transaction, $requester, $reason) {
            // Create refund transaction first (approved by the reversal requester)
            $refundTransaction = $this->createRefundTransaction($transaction, $requester->id);

            // Reverse positions
            $this->reversePositions($transaction);

            // Create reversing journal entries
            $this->createReversingJournalEntries($transaction, $requester->id);

            // Transition to reversed status
            $stateMachine = new TransactionStateMachine($transaction);
            $result = $stateMachine->transitionTo(TransactionStatus::Reversed, [
                'reason' => $reason,
                'user_id' => $requester->id,
            ]);

            if ($result) {
                Log::info('Transaction reversal processed', [
                    'transaction_id' => $transaction->id,
                    'refund_transaction_id' => $refundTransaction->id,
                    'reversed_by' => $requester->id,
                    'reason' => $reason,
                ]);
            }

            return $result;
        });
    }

    /**
     * Check if a transaction can be cancelled.
     *
     * A transaction can be cancelled if it's in a state that allows cancellation
     * (Draft, PendingApproval, Approved, Processing, Completed, Failed).
     * Finalized transactions cannot be cancelled.
     *
     * @param  Transaction  $transaction  The transaction to check
     * @return bool True if the transaction can be cancelled
     */
    public function canCancel(Transaction $transaction): bool
    {
        $cancellableStatuses = [
            TransactionStatus::Draft,
            TransactionStatus::PendingApproval,
            TransactionStatus::Approved,
            TransactionStatus::Processing,
            TransactionStatus::Completed,
            TransactionStatus::Failed,
        ];

        return in_array($transaction->status, $cancellableStatuses, true);
    }

    /**
     * Check if a transaction can be reversed.
     *
     * A transaction can be reversed if it's completed and within the
     * 24-hour cancellation window. Reversed transactions cannot be reversed again.
     *
     * @param  Transaction  $transaction  The transaction to check
     * @return bool True if the transaction can be reversed
     */
    public function canReverse(Transaction $transaction): bool
    {
        // Must be completed
        if (! $transaction->status->isCompleted()) {
            return false;
        }

        // Cannot be already reversed
        if ($transaction->status->isReversed()) {
            return false;
        }

        // Cannot be a refund transaction itself
        if ($transaction->is_refund) {
            return false;
        }

        // Must be within cancellation window
        return $this->isWithinCancellationWindow($transaction);
    }

    /**
     * Check if a transaction is within the cancellation window.
     *
     * Default window is 24 hours from transaction creation, configurable
     * via cems.transaction_cancellation_window_hours.
     *
     * @param  Transaction  $transaction  The transaction to check
     * @return bool True if within the cancellation window
     */
    public function isWithinCancellationWindow(Transaction $transaction): bool
    {
        $windowHours = config('cems.transaction_cancellation_window_hours', 24);

        return $transaction->created_at->diffInHours(now()) <= $windowHours;
    }

    /**
     * Create a refund transaction for a reversed original transaction.
     *
     * The refund transaction has opposite type (Buy becomes Sell, Sell becomes Buy),
     * same amounts, and links back to the original transaction.
     *
     * @param  Transaction  $original  The original transaction being reversed
     * @return Transaction The created refund transaction
     */
    public function createRefundTransaction(Transaction $original, int $approvedBy): Transaction
    {
        $oppositeType = $original->type === TransactionType::Buy
            ? TransactionType::Sell
            : TransactionType::Buy;

        // Calculate refund amount_local
        $amountLocal = $this->mathService->multiply(
            (string) $original->amount_foreign,
            (string) $original->rate
        );

        // Determine if refund requires hold (same rules as normal transactions)
        $customer = Customer::findOrFail($original->customer_id);
        $holdCheck = $this->complianceService->requiresHold($amountLocal, $customer);

        $status = TransactionStatus::Completed;
        $holdReason = null;
        if ($holdCheck['requires_hold']) {
            $status = TransactionStatus::PendingApproval;
            $holdReason = implode(', ', $holdCheck['reasons']);
        }

        // Create refund transaction (approved by the reversal approver if auto-completed)
        $refund = Transaction::create([
            'customer_id' => $original->customer_id,
            'user_id' => $original->user_id, // Original teller
            'branch_id' => $original->branch_id,
            'till_id' => $original->till_id,
            'type' => $oppositeType,
            'currency_code' => $original->currency_code,
            'amount_foreign' => $original->amount_foreign,
            'amount_local' => $amountLocal,
            'rate' => $original->rate,
            'purpose' => 'Reversal: '.($original->purpose ?? 'Transaction reversal'),
            'source_of_funds' => $original->source_of_funds,
            'status' => $status,
            'hold_reason' => $holdReason,
            'cdd_level' => $original->cdd_level,
            'original_transaction_id' => $original->id,
            'is_refund' => true,
            'approved_by' => $status->isCompleted() ? $approvedBy : null,
            'approved_at' => $status->isCompleted() ? now() : null,
        ]);

        // Log refund compliance decision
        $this->auditService->logWithSeverity(
            'refund_compliance_check',
            [
                'user_id' => $approvedBy,
                'entity_type' => 'Transaction',
                'entity_id' => $refund->id,
                'new_values' => [
                    'original_transaction_id' => $original->id,
                    'amount_local' => $amountLocal,
                    'status' => $status->value,
                    'hold_reason' => $holdReason,
                    'compliance_reasons' => $holdCheck['reasons'],
                ],
            ],
            'INFO'
        );

        return $refund;
    }

    /**
     * Reverse stock/cash positions for a transaction.
     *
     * For a Buy transaction, decreases the currency position.
     * For a Sell transaction, increases the currency position.
     *
     * @param  Transaction  $transaction  The transaction to reverse positions for
     *
     * @throws \InvalidArgumentException If position update fails
     */
    public function reversePositions(Transaction $transaction): void
    {
        $positionService = $this->positionService;

        // Acquire lock before getting position to prevent race conditions
        $position = CurrencyPosition::where('currency_code', $transaction->currency_code)
            ->where('till_id', $transaction->till_id)
            ->lockForUpdate()
            ->first();

        if (! $position) {
            Log::warning('No position found for reversal', [
                'transaction_id' => $transaction->id,
                'currency_code' => $transaction->currency_code,
                'till_id' => $transaction->till_id,
            ]);

            return;
        }

        // For reversal, we use opposite type
        // If original was Buy (bought foreign currency), reversal Sell (sell foreign currency back)
        // If original was Sell (sold foreign currency), reversal Buy (buy foreign currency back)
        $reversalType = $transaction->type === TransactionType::Buy
            ? TransactionType::Sell
            : TransactionType::Buy;

        $positionService->updatePosition(
            $transaction->currency_code,
            $transaction->amount_foreign,
            $transaction->rate,
            $reversalType->value,
            $transaction->till_id
        );

        Log::info('Positions reversed for transaction', [
            'transaction_id' => $transaction->id,
            'currency_code' => $transaction->currency_code,
            'amount_foreign' => $transaction->amount_foreign,
            'reversal_type' => $reversalType->value,
        ]);
    }

    /**
     * Create reversing journal entries for a transaction.
     *
     * Creates a reversal journal entry that swaps debits and credits from the
     * original transaction's journal entries.
     *
     * @param  Transaction  $transaction  The transaction to create reversing entries for
     * @param  int|null  $reversedBy  User ID performing the reversal
     */
    public function createReversingJournalEntries(Transaction $transaction, ?int $reversedBy = null): void
    {
        $accountingService = $this->accountingService;
        $reversedBy = $reversedBy ?? auth()->id();

        // Find original journal entries for this transaction
        $originalEntries = JournalEntry::where('reference_type', 'Transaction')
            ->where('reference_id', $transaction->id)
            ->where('status', 'Posted')
            ->get();

        foreach ($originalEntries as $originalEntry) {
            try {
                $accountingService->reverseJournalEntry(
                    $originalEntry,
                    "Reversal of transaction {$transaction->id}",
                    $reversedBy
                );

                Log::info('Reversed journal entry', [
                    'original_entry_id' => $originalEntry->id,
                    'transaction_id' => $transaction->id,
                ]);
            } catch (\InvalidArgumentException $e) {
                Log::warning('Failed to reverse journal entry', [
                    'original_entry_id' => $originalEntry->id,
                    'transaction_id' => $transaction->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Record state history for a transaction.
     *
     * Stores the state transition in the transaction's transition_history array
     * for audit and tracking purposes.
     *
     * @param  Transaction  $transaction  The transaction
     * @param  string  $fromStatus  Previous status
     * @param  string  $toStatus  New status
     * @param  array  $context  Additional context data
     */
    public function recordStateHistory(
        Transaction $transaction,
        string $fromStatus,
        string $toStatus,
        array $context
    ): void {
        $history = $transaction->transition_history ?? [];

        $history[] = [
            'from' => $fromStatus,
            'to' => $toStatus,
            'reason' => $context['reason'] ?? null,
            'user_id' => $context['user_id'] ?? auth()->id(),
            'timestamp' => now()->toIso8601String(),
            'metadata' => $context,
        ];

        $transaction->transition_history = $history;
        $transaction->save();

        Log::debug('Recorded state history', [
            'transaction_id' => $transaction->id,
            'from' => $fromStatus,
            'to' => $toStatus,
        ]);
    }

    /**
     * Get the cancellation window hours from configuration.
     *
     * @return int Number of hours in the cancellation window
     */
    public function getCancellationWindowHours(): int
    {
        return (int) config('cems.transaction_cancellation_window_hours', 24);
    }

    /**
     * Check if a user can cancel transactions.
     *
     * Only managers and admins can cancel transactions.
     *
     * @param  User  $user  The user to check
     * @return bool True if the user can cancel transactions
     */
    public function canUserCancel(User $user): bool
    {
        return $user->role->isManager();
    }

    /**
     * Check if a user can reverse transactions.
     *
     * Any authenticated user can request reversal of their own transactions,
     * but managers can reverse any transaction.
     *
     * @param  User  $user  The user to check
     * @param  Transaction  $transaction  The transaction to potentially reverse
     * @return bool True if the user can reverse the transaction
     */
    public function canUserReverse(User $user, Transaction $transaction): bool
    {
        // Managers can reverse any transaction
        if ($user->role->isManager()) {
            return true;
        }

        // Regular users can only reverse their own transactions
        return $transaction->user_id === $user->id;
    }

    /**
     * Notify compliance team of pending cancellation request.
     *
     * @param  Transaction  $transaction  The transaction with pending cancellation
     * @param  User  $requester  The user who requested cancellation
     * @param  string  $reason  Reason for cancellation
     */
    protected function notifyPendingCancellation(Transaction $transaction, User $requester, string $reason): void
    {
        // Get all compliance officers and admins
        $notifiableUsers = User::whereIn('role', [
            UserRole::ComplianceOfficer->value,
            UserRole::Admin->value,
        ])->get();

        if ($notifiableUsers->isEmpty()) {
            Log::warning('No compliance officers or admins found for notification', [
                'transaction_id' => $transaction->id,
            ]);

            return;
        }

        try {
            Notification::send(
                $notifiableUsers,
                new TransactionCancellationPendingNotification(
                    $transaction,
                    $requester,
                    $reason
                )
            );

            Log::info('Pending cancellation notification sent', [
                'transaction_id' => $transaction->id,
                'notification_count' => $notifiableUsers->count(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to send pending cancellation notification', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function getLastCancellationRequest(Transaction $transaction): ?array
    {
        $history = $transaction->transition_history ?? [];

        // Find the most recent PendingCancellation transition
        foreach (array_reverse($history) as $entry) {
            if (($entry['to'] ?? '') === TransactionStatus::PendingCancellation->value) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Determine the previous status before PendingCancellation was applied.
     *
     * @param  Transaction  $transaction  The transaction
     * @return TransactionStatus|null The previous status, or null if not found
     */
    protected function determinePreviousStatus(Transaction $transaction): ?TransactionStatus
    {
        $history = $transaction->transition_history ?? [];

        // Find the last status before PendingCancellation
        foreach (array_reverse($history) as $entry) {
            if (($entry['to'] ?? '') === TransactionStatus::PendingCancellation->value) {
                continue;
            }

            // This is the status we were in before PendingCancellation
            try {
                return TransactionStatus::from($entry['to']);
            } catch (\ValueError $e) {
                // Skip if the status value is not valid
                continue;
            }
        }

        // Fallback: determine based on allowed transitions from Cancelled
        // If we can transition from Cancelled to a status, that was likely the previous state
        // But this is a last resort - normally the history should have it
        return null;
    }
}
