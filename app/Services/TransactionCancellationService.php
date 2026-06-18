<?php

namespace App\Services;

use App\Enums\StockReservationStatus;
use App\Enums\TransactionStatus;
use App\Enums\UserRole;
use App\Events\TransactionCancelled;
use App\Exceptions\Domain\SegregationOfDutiesException;
use App\Models\StockReservation;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\TransactionCancellationPendingNotification;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\CurrencyPositionService;
use App\Services\Compliance\ComplianceService;
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
    public function __construct(
        protected MathService $mathService,
        protected AuditService $auditService,
        protected AccountingService $accountingService,
        protected CurrencyPositionService $positionService,
        protected ComplianceService $complianceService,
        protected TellerAllocationService $tellerAllocationService,
        protected TransactionReversalService $reversalService,
        protected StockReleaseService $stockReleaseService,
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
        if (! $requester->role->isManager()) {
            Log::warning('Non-manager attempted transaction cancellation request', [
                'transaction_id' => $transaction->id,
                'user_id' => $requester->id,
                'user_role' => $requester->role->value,
            ]);

            return false;
        }

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

                $this->notifyPendingCancellation($transaction, $requester, $reason);

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
        if (! $transaction->status->isPendingCancellation()) {
            Log::warning('Cannot approve cancellation - transaction not pending', [
                'transaction_id' => $transaction->id,
                'current_status' => $transaction->status->value,
            ]);

            return false;
        }

        if (! $approver->role->isManager() && ! $approver->role->isComplianceOfficer()) {
            Log::warning('Non-authorized user attempted cancellation approval', [
                'transaction_id' => $transaction->id,
                'user_id' => $approver->id,
                'user_role' => $approver->role->value,
            ]);

            return false;
        }

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

            $hasReservation = StockReservation::where('transaction_id', $transaction->id)
                ->where('status', StockReservationStatus::Pending)
                ->exists();

            if ($previousStatus->isCompleted()) {
                $this->reversalService->reversePositions($transaction);
                $this->reversalService->reverseTillBalance($transaction);
                $this->reverseTellerAllocation($transaction);
                $this->reversalService->createReversingJournalEntries($transaction, $approver->id);
            }

            $result = $stateMachine->transitionTo(TransactionStatus::Cancelled, [
                'reason' => $reason ?? 'Cancellation approved',
                'user_id' => $approver->id,
                'approved_by' => $approver->id,
            ]);

            if ($result) {
                if ($hasReservation) {
                    $this->stockReleaseService->releaseReservation($transaction);
                }
                Log::info('Transaction cancellation approved', [
                    'transaction_id' => $transaction->id,
                    'approved_by' => $approver->id,
                    'reason' => $reason,
                ]);

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
        if (! $transaction->status->isPendingCancellation()) {
            Log::warning('Cannot reject cancellation - transaction not pending', [
                'transaction_id' => $transaction->id,
                'current_status' => $transaction->status->value,
            ]);

            return false;
        }

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

            $targetStatus = $this->determinePreviousStatus($transaction);

            if (! $targetStatus) {
                Log::warning('Cannot determine previous status for cancellation rejection', [
                    'transaction_id' => $transaction->id,
                ]);

                $targetStatus = TransactionStatus::Completed;
            }

            if ($targetStatus === $transaction->status) {
                Log::warning('Reject cancellation target status is same as current', [
                    'transaction_id' => $transaction->id,
                    'current_status' => $transaction->status->value,
                ]);

                $history = $transaction->transition_history ?? [];
                $foundPendingCancellation = false;
                $fallbackStatus = null;
                foreach ($history as $entry) {
                    if (($entry['to'] ?? '') === TransactionStatus::PendingCancellation->value) {
                        $foundPendingCancellation = true;

                        continue;
                    }
                    if ($foundPendingCancellation) {
                        try {
                            $candidate = TransactionStatus::from($entry['from']);
                            if ($candidate !== $transaction->status) {
                                $fallbackStatus = $candidate;
                                break;
                            }
                        } catch (\ValueError $e) {
                            continue;
                        }
                    }
                }
                $targetStatus = $fallbackStatus ?? TransactionStatus::Completed;
            }

            $oldStatus = $transaction->status;
            $transaction->status = $targetStatus;
            $transaction->version = $transaction->version + 1;
            $transaction->transition_history = $this->appendStateHistoryEntry($transaction, $oldStatus, $targetStatus, [
                'reason' => "Cancellation rejected: {$reason}",
                'user_id' => $rejector->id,
            ]);
            $updated = $transaction->save();

            if ($updated) {
                Log::info('Transaction cancellation rejected', [
                    'transaction_id' => $transaction->id,
                    'rejected_by' => $rejector->id,
                    'reason' => $reason,
                    'previous_status' => $previousStatus->value,
                    'returned_to_status' => $targetStatus->value,
                ]);

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

            return $updated;
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
        if (! $this->reversalService->canUserReverse($requester, $transaction)) {
            Log::warning('User not authorized to reverse transaction', [
                'transaction_id' => $transaction->id,
                'user_id' => $requester->id,
                'user_role' => $requester->role->value,
            ]);

            return false;
        }

        if (! $this->reversalService->canReverse($transaction)) {
            Log::warning('Transaction cannot be reversed', [
                'transaction_id' => $transaction->id,
                'current_status' => $transaction->status->value,
                'within_window' => $this->reversalService->isWithinCancellationWindow($transaction),
            ]);

            return false;
        }

        if (! $this->reversalService->isWithinCancellationWindow($transaction)) {
            Log::warning('Transaction reversal window has expired', [
                'transaction_id' => $transaction->id,
                'transaction_created_at' => $transaction->created_at->toIso8601String(),
                'window_hours' => config('cems.transaction_cancellation_window_hours', 24),
            ]);

            return false;
        }

        if ($transaction->user_id === $requester->id) {
            Log::warning('Self-reversal attempted - segregation of duties violation', [
                'transaction_id' => $transaction->id,
                'requester_id' => $requester->id,
                'original_transaction_user_id' => $transaction->user_id,
            ]);

            throw new SegregationOfDutiesException('reverse this transaction');
        }

        return $this->reversalService->reverse($transaction, $requester, $reason);
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

    public function canReverse(Transaction $transaction): bool
    {
        return $this->reversalService->canReverse($transaction);
    }

    public function isWithinCancellationWindow(Transaction $transaction): bool
    {
        return $this->reversalService->isWithinCancellationWindow($transaction);
    }

    public function createRefundTransaction(Transaction $original, int $approvedBy): Transaction
    {
        return $this->reversalService->createRefundTransaction($original, $approvedBy);
    }

    public function reversePositions(Transaction $transaction): void
    {
        $this->reversalService->reversePositions($transaction);
    }

    public function createReversingJournalEntries(Transaction $transaction, ?int $reversedBy = null): void
    {
        $this->reversalService->createReversingJournalEntries($transaction, $reversedBy);
    }

    public function getCancellationWindowHours(): int
    {
        return $this->reversalService->getCancellationWindowHours();
    }

    public function canUserCancel(User $user): bool
    {
        return $user->role->isManager();
    }

    public function canUserReverse(User $user, Transaction $transaction): bool
    {
        return $this->reversalService->canUserReverse($user, $transaction);
    }

    protected function notifyPendingCancellation(Transaction $transaction, User $requester, string $reason): void
    {
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

        foreach (array_reverse($history) as $entry) {
            if (($entry['to'] ?? '') === TransactionStatus::PendingCancellation->value) {
                return $entry;
            }
        }

        return null;
    }

    protected function determinePreviousStatus(Transaction $transaction): ?TransactionStatus
    {
        $history = $transaction->transition_history ?? [];

        foreach (array_reverse($history) as $entry) {
            if (($entry['to'] ?? '') === TransactionStatus::PendingCancellation->value) {
                try {
                    return TransactionStatus::from($entry['from']);
                } catch (\ValueError $e) {
                    return null;
                }
            }
        }

        return null;
    }

    protected function appendStateHistoryEntry(Transaction $transaction, TransactionStatus $oldStatus, TransactionStatus $newStatus, array $context): array
    {
        $history = $transaction->transition_history ?? [];
        $history[] = [
            'from' => $oldStatus->value,
            'to' => $newStatus->value,
            'reason' => $context['reason'] ?? null,
            'user_id' => $context['user_id'] ?? auth()->id(),
            'timestamp' => now()->toIso8601String(),
        ];

        return $history;
    }

    protected function reverseTellerAllocation(Transaction $transaction): void
    {
        $user = User::find($transaction->user_id);
        if ($user && $user->isTeller()) {
            $allocation = $this->tellerAllocationService->getActiveAllocation(
                $user,
                $transaction->currency_code
            );
            if ($allocation) {
                if ($transaction->type->isBuy()) {
                    $allocation->deduct((string) $transaction->amount_foreign);
                    $allocation->subtractDailyUsed((string) $transaction->amount_local);
                } else {
                    $allocation->add((string) $transaction->amount_foreign);
                    $allocation->subtractDailyUsed((string) $transaction->amount_local);
                }
            }
        }
    }
}
