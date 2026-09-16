<?php

namespace App\Services\Transaction;

use App\Enums\TransactionStatus;
use App\Exceptions\Domain\TransactionBlockedException;
use App\Exceptions\Domain\TransactionValidationException;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AuditService;
use App\Support\ActorContext;
use Illuminate\Support\Facades\Log;

/**
 * Transaction State Machine
 *
 * Manages the 10-state lifecycle of currency exchange transactions.
 * Enforces valid state transitions and maintains transition history.
 */
class TransactionStateMachine
{
    /**
     * Valid state transitions map.
     * Key is the current state, value is an array of valid target states.
     */
    protected const TRANSITIONS = [
        'Draft' => [
            'PendingApproval',
            'PendingCancellation',
            'Cancelled',
        ],
        'PendingApproval' => [
            'Approved',
            'Rejected',
            'PendingCancellation',
            'Cancelled',
            'Completed',  // Direct completion for manager approval
        ],
        'Approved' => [
            'Processing',
            'PendingCancellation',
            'Cancelled',
        ],
        'Processing' => [
            'Completed',
            'Failed',
            'PendingCancellation',
            'Cancelled',
        ],
        'Completed' => [
            'Finalized',
            'Reversed',
            'PendingCancellation',
            'Cancelled',
            // Booking failure after a persisted Completed record (e.g. accounting
            // or position write failure during create()). The side effects were
            // rolled back, so the record is marked Failed and re-executed by the
            // recovery flow (Failed -> Completed), never double-booked.
            'Failed',
        ],
        'Finalized' => [],
        'Cancelled' => [],
        'Reversed' => [],
        'Failed' => [
            'PendingApproval',
            'PendingCancellation',
            'Cancelled',
            'Completed',  // Automated re-execution of a previously failed transaction
        ],
        'Rejected' => [
            'Cancelled',
        ],
        'PendingCancellation' => [
            'Cancelled',
            'Completed',
        ],
    ];

    /**
     * Transition history stored on the transaction.
     */
    protected array $history = [];

    /**
     * Create a new TransactionStateMachine instance.
     *
     * @param  Transaction  $transaction  The transaction to manage
     * @param  AuditService|null  $auditService  Optional audit service for compliance logging
     */
    public function __construct(
        protected Transaction $transaction,
        protected ?AuditService $auditService = null
    ) {
        $this->loadHistory();
    }

    /**
     * Load transition history from the transaction.
     */
    protected function loadHistory(): void
    {
        $this->history = $this->transaction->transition_history ?? [];
    }

    /**
     * Check if a transition to the given status is valid.
     *
     * @param  TransactionStatus  $to  The target status
     * @return bool True if the transition is valid
     */
    public function canTransitionTo(TransactionStatus $to): bool
    {
        $from = $this->transaction->status->value;
        $validTransitions = self::TRANSITIONS[$from] ?? [];

        return in_array($to->value, $validTransitions, true);
    }

    /**
     * Transition the transaction to a new status.
     *
     * IMPORTANT: The caller MUST have loaded $this->transaction with lockForUpdate()
     * inside an active database transaction before calling this method.
     *
     * @param  TransactionStatus  $to  The target status
     * @param  array  $context  Optional context (reason, user_id, etc.)
     * @return bool True if the transition was successful
     */
    public function transitionTo(TransactionStatus $to, array $context = []): bool
    {
        if (! $this->canTransitionTo($to)) {
            Log::warning('Invalid state transition attempted', [
                'transaction_id' => $this->transaction->id,
                'from' => $this->transaction->status->value,
                'to' => $to->value,
                'context' => $context,
            ]);

            return false;
        }

        $from = $this->transaction->status;
        $now = now();

        // Record the transition in history
        $this->history[] = [
            'from' => $from->value,
            'to' => $to->value,
            'reason' => $context['reason'] ?? null,
            'user_id' => $context['user_id'] ?? ActorContext::capture()->userIdOrSystem(),
            'timestamp' => $now->toIso8601String(),
        ];

        // Update the transaction
        $this->transaction->status = $to;
        $this->transaction->transition_history = $this->history;

        // Set additional fields based on transition
        $this->applyTransitionMetadata($from, $to, $context);

        return $this->transaction->save();
    }

    /**
     * Apply metadata fields based on the transition.
     *
     * @param  TransactionStatus  $from  The previous status
     * @param  TransactionStatus  $to  The new status
     * @param  array  $context  Transition context
     */
    protected function applyTransitionMetadata(
        TransactionStatus $from,
        TransactionStatus $to,
        array $context
    ): void {
        // Track approval
        if ($to === TransactionStatus::Approved) {
            $this->transaction->approved_by = $context['user_id'] ?? ActorContext::capture()->userIdOrSystem();
            $this->transaction->approved_at = now();
        }

        // Track cancellation
        if ($to === TransactionStatus::Cancelled) {
            $this->transaction->cancelled_at = now();
            $this->transaction->cancelled_by = $context['user_id'] ?? ActorContext::capture()->userIdOrSystem();
            $this->transaction->cancellation_reason = $context['reason'] ?? null;
        }

        // Track failure reason
        if ($to === TransactionStatus::Failed) {
            $this->transaction->failure_reason = $context['reason'] ?? null;
        }

        // Track rejection
        if ($to === TransactionStatus::Rejected) {
            $this->transaction->rejection_reason = $context['reason'] ?? null;
        }

        // Track reversal
        if ($to === TransactionStatus::Reversed) {
            $this->transaction->reversal_reason = $context['reason'] ?? null;
        }

        // Optimistic concurrency version bump
        $this->transaction->version = ($this->transaction->version ?? 0) + 1;
    }

    /**
     * Get available transitions from the current state.
     *
     * @return array Array of valid TransactionStatus values
     */
    public function getAvailableTransitions(): array
    {
        $currentValue = $this->transaction->status->value;
        $validTransitions = self::TRANSITIONS[$currentValue] ?? [];

        return array_map(
            fn (string $value) => TransactionStatus::from($value),
            $validTransitions
        );
    }

    /**
     * Get the transition history for this transaction.
     *
     * @return array Array of transition records
     */
    public function getTransitionHistory(): array
    {
        return $this->history;
    }

    /**
     * Submit a draft transaction for approval.
     * Draft -> PendingApproval
     *
     * @return bool True if transition was successful
     */
    public function submit(): bool
    {
        return $this->transitionTo(TransactionStatus::PendingApproval);
    }

    /**
     * Approve a pending transaction.
     * PendingApproval -> Approved
     *
     * @return bool True if transition was successful
     */
    public function approve(): bool
    {
        return $this->transitionTo(TransactionStatus::Approved);
    }

    /**
     * Reject a pending transaction.
     * PendingApproval -> Rejected
     *
     * @param  string  $reason  The reason for rejection
     * @return bool True if transition was successful
     */
    public function reject(string $reason): bool
    {
        return $this->transitionTo(TransactionStatus::Rejected, ['reason' => $reason]);
    }

    /**
     * Start processing an approved transaction.
     * Approved -> Processing
     *
     * @return bool True if transition was successful
     */
    public function startProcessing(): bool
    {
        return $this->transitionTo(TransactionStatus::Processing);
    }

    /**
     * Complete a processing transaction.
     * Processing -> Completed
     *
     * @return bool True if transition was successful
     */
    public function complete(): bool
    {
        return $this->transitionTo(TransactionStatus::Completed);
    }

    /**
     * Mark a processing transaction as failed.
     * Processing -> Failed
     *
     * @param  string  $reason  The reason for failure
     * @return bool True if transition was successful
     */
    public function fail(string $reason): bool
    {
        return $this->transitionTo(TransactionStatus::Failed, ['reason' => $reason]);
    }

    /**
     * Retry a failed transaction.
     * Failed -> PendingApproval
     *
     * @return bool True if transition was successful
     */
    public function retry(): bool
    {
        // retry() is only valid from Failed state
        if (! $this->transaction->status->isFailed()) {
            return false;
        }

        return $this->transitionTo(TransactionStatus::PendingApproval);
    }

    /**
     * Re-execute a failed transaction to completion.
     * Failed -> Completed
     *
     * Automated recovery path: the caller re-runs the booking side effects and
     * marks the transaction as completed in one step. Only valid from Failed status.
     *
     * @return bool True if transition was successful
     */
    public function reprocess(): bool
    {
        // reprocess() is only valid from Failed state
        if (! $this->transaction->status->isFailed()) {
            return false;
        }

        return $this->transitionTo(TransactionStatus::Completed, [
            'reason' => 'Automated retry after failure',
        ]);
    }

    /**
     * Reverse a completed transaction.
     * Completed -> Reversed
     *
     * @param  string  $reason  The reason for reversal
     * @return bool True if transition was successful
     */
    public function reverse(string $reason): bool
    {
        return $this->transitionTo(TransactionStatus::Reversed, ['reason' => $reason]);
    }

    /**
     * Finalize a completed transaction.
     * Completed -> Finalized
     *
     * @return bool True if transition was successful
     */
    public function finalize(): bool
    {
        return $this->transitionTo(TransactionStatus::Finalized);
    }

    /**
     * Cancel the transaction.
     * Any valid state -> Cancelled (with guards based on current state)
     *
     * @param  string  $reason  The reason for cancellation
     * @return bool True if transition was successful
     */
    public function cancel(string $reason): bool
    {
        return $this->transitionTo(TransactionStatus::Cancelled, ['reason' => $reason]);
    }

    /**
     * Place transaction on hold (compliance review required).
     * Transitions to PendingApproval for manager review.
     *
     * This is the sanctioned hold/release entry point: holds map to
     * PendingApproval, and manager approve/reject acts as the release path.
     * The legacy OnHold enum case is NOT part of the transition map and must
     * not be introduced into new flows.
     *
     * @param  string  $reason  The reason for hold
     * @return bool True if transition was successful
     */
    public function hold(string $reason): bool
    {
        return $this->transitionTo(TransactionStatus::PendingApproval, ['reason' => $reason]);
    }

    /**
     * Approve and complete a pending transaction in one step (manager override).
     * PendingApproval -> Completed
     * This is the proper method for manager approval that completes the transaction directly.
     * NOT allowed for refund transactions - they must go through the two-step approval flow.
     * Requires explicit approver authorization (manager, compliance officer,
     * or admin — the amount-tier check happens in the approval service).
     *
     * @param  string  $reason  The reason for the direct completion
     * @param  User  $manager  The approving user authorizing this action
     * @return bool True if transition was successful
     *
     * @throws \RuntimeException If called on a refund transaction or user is not an approver
     */
    public function approveAndComplete(string $reason, User $manager): bool
    {
        // Approval authority is tiered upstream (manager below the large
        // threshold, compliance officer at/above it); this gate only verifies
        // the caller is a recognized approver role.
        if (! $manager->role->canApproveTransactions() && ! $manager->role->canApproveLargeTransactions()) {
            throw new TransactionBlockedException(
                'approveAndComplete requires manager, compliance officer, or admin authorization.'
            );
        }

        if ($this->transaction->is_refund) {
            throw new TransactionValidationException(
                message: 'Refund transactions cannot use approveAndComplete; they must go through the two-step flow (approve() then complete()).'
            );
        }

        return $this->transitionTo(TransactionStatus::Completed, [
            'reason' => $reason,
            'user_id' => $manager->id,
        ]);
    }

    /**
     * Force a status change (admin recovery/emergency only).
     * Allows transitioning to any valid state regardless of normal flow.
     * MUST be called by an admin user with explicit authorization.
     *
     * @param  TransactionStatus  $status  The target status
     * @param  string  $reason  The reason for the override (required)
     * @param  User  $adminUser  Admin user performing the override
     * @return bool True if transition was successful
     *
     * @throws \RuntimeException If user is not admin or not explicitly authorized
     */
    public function forceStatus(TransactionStatus $status, string $reason, User $adminUser): bool
    {
        if (! $adminUser->role->isAdmin()) {
            throw new TransactionBlockedException(
                'forceStatus requires admin authorization. Only administrators can force status changes. '.
                'Use transitionTo() for normal transitions.'
            );
        }

        // Validate this is a legitimate forced transition
        $from = $this->transaction->status;

        // Record the forced transition with 'forced' flag
        $this->history[] = [
            'from' => $from->value,
            'to' => $status->value,
            'reason' => $reason,
            'user_id' => $adminUser->id,
            'timestamp' => now()->toIso8601String(),
            'forced' => true,
        ];

        $this->transaction->status = $status;
        $this->transaction->transition_history = $this->history;

        // Apply ALL metadata via the shared method so that all status types
        // (Approved, Cancelled, Failed, Rejected, Reversed) get their respective
        // audit fields populated consistently, not just Cancelled.
        $this->applyTransitionMetadata($from, $status, [
            'reason' => $reason,
            'user_id' => $adminUser->id,
        ]);

        $saved = $this->transaction->save();

        // Log forced transition to audit trail for BNM compliance
        if ($this->auditService) {
            $this->auditService->logTransaction('force_status_override', $this->transaction->id, [
                'new' => [
                    'from' => $from->value,
                    'to' => $status->value,
                    'reason' => $reason,
                    'user_id' => $adminUser->id,
                    'forced' => true,
                ],
            ]);
        }

        return $saved;
    }

    /**
     * Mark transaction as dead-letter queue (DLQ) - recovery operation.
     * This does NOT change the status (stays Failed), only adds DLQ marker.
     *
     * @param  string  $reason  The DLQ reason
     * @return bool True if update was successful
     */
    public function markAsDlq(string $reason): bool
    {
        // Only valid from Failed status
        if (! $this->transaction->status->isFailed()) {
            throw new TransactionValidationException(message: 'markAsDlq can only be called from Failed status');
        }

        // Just update the DLQ flags without changing status
        $this->transaction->is_dlq = true;
        $this->transaction->failure_reason = $reason;

        // Record in transition history
        $this->history[] = [
            'from' => $this->transaction->status->value,
            'to' => $this->transaction->status->value,
            'reason' => $reason,
            'user_id' => ActorContext::capture()->userIdOrSystem(),
            'timestamp' => now()->toIso8601String(),
            'dlq_marker' => true,
        ];

        $this->transaction->transition_history = $this->history;

        $saved = $this->transaction->save();

        // Log DLQ marker to audit trail
        if ($this->auditService) {
            $this->auditService->logTransaction('dlq_marker_added', $this->transaction->id, [
                'new' => [
                    'reason' => $reason,
                    'user_id' => ActorContext::capture()->userIdOrSystem(),
                    'dlq' => true,
                ],
            ]);
        }

        return $saved;
    }
}
