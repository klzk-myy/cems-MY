<?php

namespace App\Services\Transaction;

use App\Enums\StockReservationStatus;
use App\Enums\TransactionStatus;
use App\Enums\UserRole;
use App\Events\TransactionCancelled;
use App\Exceptions\Domain\SegregationOfDutiesException;
use App\Exceptions\Domain\TillBalanceMissingException;
use App\Models\StockReservation;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\TransactionCancellationPendingNotification;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\CurrencyPositionService;
use App\Services\AuditService;
use App\Services\Branch\TellerAllocationService;
use App\Services\Compliance\ComplianceService;
use App\Services\System\CacheInvalidationService;
use App\Services\System\MathService;
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
        protected CacheInvalidationService $cacheInvalidationService,
    ) {}

    /**
     * Request cancellation of a transaction.
     *
     * Transitions transaction to PendingCancellation status, awaiting supervisor approval.
     * Authorization is handled by the controller via policies.
     *
     * @param  Transaction  $transaction  The transaction to cancel
     * @param  User  $requester  The user requesting cancellation
     * @param  string  $reason  Reason for cancellation
     * @return bool True if cancellation request was successful
     */
    public function requestCancellation(Transaction $transaction, User $requester, string $reason): bool
    {
        if (! $this->canCancel($transaction)) {
            Log::warning('Transaction cannot be cancelled', [
                'transaction_id' => $transaction->id,
                'current_status' => $transaction->status->value,
            ]);

            return false;
        }

        $result = DB::transaction(function () use ($transaction, $requester, $reason) {
            $lockedTransaction = Transaction::where('id', $transaction->id)
                ->lockForUpdate()
                ->firstOrFail();
            $stateMachine = new TransactionStateMachine($lockedTransaction);

            $previousStatus = $lockedTransaction->status;

            $result = $stateMachine->transitionTo(TransactionStatus::PendingCancellation, [
                'reason' => $reason,
                'user_id' => $requester->id,
                'previous_status' => $previousStatus->value, // Store for rejectCancellation
            ]);

            if ($result) {
                Log::info('Transaction cancellation requested', [
                    'transaction_id' => $lockedTransaction->id,
                    'requested_by' => $requester->id,
                    'reason' => $reason,
                    'previous_status' => $previousStatus->value,
                ]);

                $this->notifyPendingCancellation($lockedTransaction, $requester, $reason);

                $this->auditService->logTransactionSealed(
                    'cancellation_requested',
                    $transaction->id,
                    [
                        'old' => ['status' => $previousStatus->value],
                        'new' => [
                            'status' => TransactionStatus::PendingCancellation->value,
                            'reason' => $reason,
                            'requested_by' => $requester->id,
                        ],
                        'severity' => 'CRITICAL',
                    ]
                );
            }

            return $result;
        });

        $transaction->refresh();

        return $result;
    }

    /**
     * Approve a pending cancellation request.
     *
     * Transitions transaction to Cancelled status.
     * Authorization is handled by the controller via policies.
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

        $cancellationRequest = $this->getLastCancellationRequest($transaction);
        if ($cancellationRequest && ($cancellationRequest['user_id'] ?? null) === $approver->id) {
            Log::warning('Self-approval of cancellation attempted - segregation of duties violation', [
                'transaction_id' => $transaction->id,
                'approver_id' => $approver->id,
                'requester_id' => $cancellationRequest['user_id'],
            ]);

            return false;
        }

        $result = DB::transaction(function () use ($transaction, $approver, $reason) {
            $lockedTransaction = Transaction::where('id', $transaction->id)
                ->lockForUpdate()
                ->firstOrFail();
            $stateMachine = new TransactionStateMachine($lockedTransaction);

            $previousStatus = $lockedTransaction->status;

            $hasReservation = StockReservation::where('transaction_id', $lockedTransaction->id)
                ->where('status', StockReservationStatus::Pending)
                ->exists();

            // Enforce the state transition FIRST. Compensating side effects must not
            // run when the transition fails, otherwise positions/till/journal are
            // reversed while the transaction is still marked Completed.
            $result = $stateMachine->transitionTo(TransactionStatus::Cancelled, [
                'reason' => $reason ?? 'Cancellation approved',
                'user_id' => $approver->id,
                'approved_by' => $approver->id,
            ]);

            if ($result) {
                if ($previousStatus->isCompleted()) {
                    $this->reversalService->reversePositions($lockedTransaction);

                    // The till may have no open balance today (e.g. it was
                    // closed before an older transaction is cancelled). Skip
                    // the till leg - its books were already sealed - but still
                    // complete the FX, journal and allocation reversal legs.
                    try {
                        $this->reversalService->reverseTillBalance($lockedTransaction);
                    } catch (TillBalanceMissingException $e) {
                        Log::warning('Skipping till balance reversal - no open till balance', [
                            'transaction_id' => $lockedTransaction->id,
                            'till_id' => $lockedTransaction->till_id,
                            'currency_code' => $lockedTransaction->currency_code,
                        ]);
                    }

                    $this->reverseTellerAllocation($lockedTransaction);
                    $this->reversalService->createReversingJournalEntries($lockedTransaction, $approver->id);
                }

                if ($hasReservation) {
                    $this->stockReleaseService->releaseReservation($lockedTransaction);
                }
                Log::info('Transaction cancellation approved', [
                    'transaction_id' => $lockedTransaction->id,
                    'approved_by' => $approver->id,
                    'reason' => $reason,
                ]);

                $this->auditService->logTransactionSealed(
                    'cancellation_approved',
                    $lockedTransaction->id,
                    [
                        'old' => ['status' => $previousStatus->value],
                        'new' => [
                            'status' => TransactionStatus::Cancelled->value,
                            'reason' => $reason,
                            'approved_by' => $approver->id,
                        ],
                        'severity' => 'CRITICAL',
                    ]
                );

                Event::dispatch(new TransactionCancelled($lockedTransaction, $reason, $approver->id));

                // A cancellation changes positions, tills and journal state —
                // flush dashboard/report caches so readers never see stale data.
                DB::afterCommit(function (): void {
                    $this->cacheInvalidationService->invalidate('dashboard');
                    $this->cacheInvalidationService->invalidate('ledger');
                    $this->cacheInvalidationService->invalidate('reports');
                });
            }

            return $result;
        });

        $transaction->refresh();

        return $result;
    }

    /**
     * Reject a pending cancellation request.
     *
     * Returns transaction to its previous status (InProgress, Completed, etc.).
     * Authorization is handled by the controller via policies.
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

        $updated = DB::transaction(function () use ($transaction, $rejector, $reason) {
            $lockedTransaction = Transaction::where('id', $transaction->id)
                ->lockForUpdate()
                ->firstOrFail();
            $previousStatus = $lockedTransaction->status;

            $targetStatus = $this->determinePreviousStatus($lockedTransaction);

            if (! $targetStatus) {
                Log::warning('Cannot determine previous status for cancellation rejection', [
                    'transaction_id' => $lockedTransaction->id,
                ]);

                return false;
            }

            if ($targetStatus === $lockedTransaction->status) {
                Log::warning('Reject cancellation target status is same as current', [
                    'transaction_id' => $lockedTransaction->id,
                    'current_status' => $lockedTransaction->status->value,
                ]);

                $history = $lockedTransaction->transition_history ?? [];
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
                            if ($candidate !== $lockedTransaction->status) {
                                $fallbackStatus = $candidate;
                                break;
                            }
                        } catch (\ValueError $e) {
                            continue;
                        }
                    }
                }
                if (! $fallbackStatus) {
                    Log::warning('Cannot determine fallback status for cancellation rejection', [
                        'transaction_id' => $lockedTransaction->id,
                    ]);

                    return false;
                }
                $targetStatus = $fallbackStatus;
            }

            $stateMachine = new TransactionStateMachine($lockedTransaction, $this->auditService);

            $updated = $stateMachine->transitionTo($targetStatus, [
                'reason' => "Cancellation rejected: {$reason}",
                'user_id' => $rejector->id,
            ]);

            if ($updated) {
                Log::info('Transaction cancellation rejected', [
                    'transaction_id' => $lockedTransaction->id,
                    'rejected_by' => $rejector->id,
                    'reason' => $reason,
                    'previous_status' => $previousStatus->value,
                    'returned_to_status' => $targetStatus->value,
                ]);

                $this->auditService->logTransactionSealed(
                    'cancellation_rejected',
                    $lockedTransaction->id,
                    [
                        'old' => ['status' => $previousStatus->value],
                        'new' => [
                            'status' => $targetStatus->value,
                            'reason' => $reason,
                            'rejected_by' => $rejector->id,
                        ],
                        'severity' => 'CRITICAL',
                    ]
                );
            }

            return $updated;
        });

        $transaction->refresh();

        return $updated;
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

    public function isWithinCancellationWindow(Transaction $transaction): bool
    {
        return $this->reversalService->isWithinCancellationWindow($transaction);
    }

    public function canReverse(Transaction $transaction): bool
    {
        return $this->reversalService->canReverse($transaction);
    }

    public function reversePositions(Transaction $transaction): void
    {
        $this->reversalService->reversePositions($transaction);
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
                // First, try to get the explicitly stored previous_status
                if (isset($entry['previous_status'])) {
                    try {
                        return TransactionStatus::from($entry['previous_status']);
                    } catch (\ValueError $e) {
                        // Fall through to fallback logic
                    }
                }

                // Fallback: use the 'from' field of the transition
                try {
                    return TransactionStatus::from($entry['from']);
                } catch (\ValueError $e) {
                    return null;
                }
            }
        }

        return null;
    }

    protected function reverseTellerAllocation(Transaction $transaction): void
    {
        $this->tellerAllocationService->reverseTransactionAllocation($transaction);
    }
}
