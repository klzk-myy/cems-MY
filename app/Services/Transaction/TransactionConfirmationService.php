<?php

namespace App\Services\Transaction;

use App\Enums\TransactionConfirmationStatus;
use App\Enums\TransactionStatus;
use App\Exceptions\Domain\InvalidStateException;
use App\Models\Transaction;
use App\Models\TransactionConfirmation;
use App\Models\User;
use App\Notifications\ConfirmationRequiredNotification;
use App\Notifications\LargeTransactionNotification;
use App\Services\AuditService;
use App\Services\Compliance\AlertTriageService;
use App\Services\DTOs\ConfirmationResult;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use App\Support\ActorContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionConfirmationService
{
    public function __construct(
        protected AuditService $auditService,
        protected ThresholdService $thresholdService,
        protected MathService $mathService,
        protected AlertTriageService $alertTriageService,
        protected StockReleaseService $stockReleaseService
    ) {}

    /**
     * Determine if a transaction requires manager confirmation.
     *
     * Single source for the confirmation gate and the large-transaction
     * escalation: the cdd.large_transaction threshold. (The STR reporting
     * threshold is a different control — reporting, not gating.)
     */
    public function requiresConfirmation(Transaction $transaction): bool
    {
        $threshold = $this->thresholdService->getLargeTransactionThreshold();

        return $this->mathService->compare($transaction->amount_myr, $threshold) >= 0;
    }

    /**
     * Fetch the pending confirmation for a transaction, auto-expiring stale ones.
     *
     * Returns null when no pending confirmation exists or the only pending one
     * has expired (in which case it is marked Expired before returning null).
     */
    public function pendingConfirmationFor(Transaction $transaction): ?TransactionConfirmation
    {
        $confirmation = TransactionConfirmation::where('transaction_id', $transaction->id)
            ->where('status', TransactionConfirmationStatus::Pending->value)
            ->first();

        if ($confirmation === null) {
            return null;
        }

        if ($confirmation->isExpired()) {
            $confirmation->markExpired();

            return null;
        }

        return $confirmation;
    }

    /**
     * Request confirmation for a large transaction.
     *
     * Creates a new TransactionConfirmation record if one doesn't already exist
     * in pending or confirmed status, then notifies branch managers and
     * escalates to compliance officers. Notifications fire only when a NEW
     * confirmation is created - re-opening the confirmation page for an
     * existing request never re-notifies.
     *
     * @throws \Exception If creation fails
     */
    public function requestConfirmation(Transaction $transaction, int $userId): TransactionConfirmation
    {
        [$confirmation, $created] = DB::transaction(function () use ($transaction, $userId) {
            // Lock the transaction row to serialise concurrent confirmation requests
            $lockedTransaction = Transaction::where('id', $transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Check for existing pending or confirmed confirmation
            $existing = TransactionConfirmation::where('transaction_id', $lockedTransaction->id)
                ->whereIn('status', [
                    TransactionConfirmationStatus::Pending->value,
                    TransactionConfirmationStatus::Confirmed->value,
                ])
                ->first();

            if ($existing) {
                return [$existing, false];
            }

            // Create new confirmation request
            $confirmationToken = bin2hex(random_bytes(32));

            $confirmation = TransactionConfirmation::create([
                'transaction_id' => $lockedTransaction->id,
                'user_id' => $userId,
                'status' => TransactionConfirmationStatus::Pending->value,
                'confirmation_token' => $confirmationToken,
                'expires_at' => now()->addMinutes(30),
            ]);

            $this->auditService->logWithSeveritySealed('confirmation_requested', [
                'user_id' => $userId,
                'entity_type' => 'Transaction',
                'entity_id' => $lockedTransaction->id,
                'new_values' => [
                    'confirmation_id' => $confirmation->id,
                    'amount_myr' => $lockedTransaction->amount_myr,
                ],
            ], 'INFO');

            return [$confirmation, true];
        });

        if ($created) {
            $this->notifyManager($confirmation);
        }

        return $confirmation;
    }

    /**
     * Confirm or reject a transaction confirmation.
     *
     * @param  array  $validated  Must contain 'confirmation_action' => 'confirm'|'reject' and optional 'notes'
     */
    public function confirm(TransactionConfirmation $confirmation, array $validated, int $userId): ConfirmationResult
    {
        if ($confirmation->isExpired()) {
            $confirmation->markExpired();

            return new ConfirmationResult(false, 'Confirmation has expired. Please request a new confirmation.');
        }

        $action = $validated['confirmation_action'];
        $notes = $validated['notes'] ?? null;

        if (! in_array($action, ['confirm', 'reject'], true)) {
            throw new \InvalidArgumentException(
                "Invalid confirmation_action '{$action}'. Must be 'confirm' or 'reject'."
            );
        }

        try {
            return DB::transaction(function () use ($confirmation, $userId, $notes, $action) {
                // Lock the parent transaction row FIRST, mirroring
                // requestConfirmation()'s lock order (Transaction ->
                // TransactionConfirmation) so concurrent confirm/reject/request
                // flows can never deadlock waiting on each other's locks.
                Transaction::where('id', $confirmation->transaction_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Re-read under a pessimistic lock and re-verify the status so
                // two concurrent actions cannot both mutate the same row.
                $lockedConfirmation = TransactionConfirmation::where('id', $confirmation->id)
                    ->lockForUpdate()
                    ->first();

                // Expired rows must deterministically transition Pending ->
                // Expired here. isPending() treats expired rows as non-pending,
                // so expiry has to be tested BEFORE the generic bail below,
                // otherwise an expired Pending row would linger unprocessed.
                if ($lockedConfirmation && $lockedConfirmation->isExpired()) {
                    $lockedConfirmation->markExpired();

                    return new ConfirmationResult(false, 'Confirmation has expired. Please request a new confirmation.');
                }

                if (! $lockedConfirmation || ! $lockedConfirmation->isPending()) {
                    return new ConfirmationResult(false, 'Confirmation has already been processed or is no longer pending.');
                }

                if ($action === 'confirm') {
                    return $this->handleConfirm($lockedConfirmation, $userId, $notes);
                }

                return $this->handleReject($lockedConfirmation, $userId, $notes);
            });
        } catch (\Exception $e) {
            Log::error('Transaction confirmation failed', [
                'confirmation_id' => $confirmation->id,
                'transaction_id' => $confirmation->transaction_id,
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle confirmation action.
     */
    protected function handleConfirm(TransactionConfirmation $confirmation, int $userId, ?string $notes): ConfirmationResult
    {
        $confirmation->markConfirmed($userId, $notes);

        // Refresh transaction for any downstream listeners (if needed)
        $confirmation->transaction->refresh();

        $this->auditService->logWithSeveritySealed('transaction_confirmed', [
            'user_id' => $userId,
            'entity_type' => 'Transaction',
            'entity_id' => $confirmation->transaction_id,
            'new_values' => [
                'confirmation_id' => $confirmation->id,
                'confirmed_by' => $userId,
            ],
        ], 'INFO');

        return new ConfirmationResult(true, 'Transaction confirmed and pending final approval.');
    }

    /**
     * Handle rejection action.
     */
    protected function handleReject(TransactionConfirmation $confirmation, int $userId, ?string $notes): ConfirmationResult
    {
        // Lock the parent transaction row: the state machine requires it, and
        // it prevents a concurrent approval/completion from interleaving with
        // the rejection below.
        $transaction = Transaction::where('id', $confirmation->transaction_id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($transaction->status === TransactionStatus::Completed) {
            // Stock/till effects were already booked when the transaction was
            // approved - cancelling here would strand them. Reject only the
            // confirmation record and leave the transaction untouched. The row
            // is deleted below, so seal an audit entry first to preserve the
            // rejection trail.
            $this->auditService->logWithSeveritySealed('confirmation_rejected_completed_tx', [
                'user_id' => $userId,
                'entity_type' => 'Transaction',
                'entity_id' => $confirmation->transaction_id,
                'new_values' => [
                    'confirmation_id' => $confirmation->id,
                    'rejected_by' => $userId,
                    'reason' => $notes ?? 'No reason provided',
                ],
            ], 'WARNING');

            $this->rejectConfirmation($confirmation, $userId, $notes);

            return new ConfirmationResult(true, 'Confirmation rejected. The transaction was already completed and remains unchanged.');
        }

        $stateMachine = new TransactionStateMachine($transaction);
        $reason = 'Rejected during confirmation: '.($notes ?? 'No reason provided');

        if (! $stateMachine->transitionTo(TransactionStatus::Cancelled, [
            'user_id' => $userId,
            'reason' => $reason,
        ])) {
            throw new InvalidStateException(
                "Cannot reject transaction #{$transaction->id}: status '{$transaction->status->value}' does not allow cancellation."
            );
        }

        // Rejecting the confirmation is a full cancellation of the booking:
        // release any pending stock reservation so the currency returns to
        // available stock immediately rather than waiting for the expiry
        // sweep — the same compensating leg TransactionCancellationService
        // runs. This path is hit only by large transactions, i.e. the
        // biggest Sell reservations.
        $this->stockReleaseService->releaseReservation($transaction);

        $this->rejectConfirmation($confirmation, $userId, $notes);

        $this->auditService->logWithSeveritySealed('transaction_cancelled_via_confirmation', [
            'user_id' => $userId,
            'entity_type' => 'Transaction',
            'entity_id' => $confirmation->transaction_id,
            'new_values' => [
                'confirmation_id' => $confirmation->id,
                'rejected_by' => $userId,
                'reason' => $notes ?? 'No reason provided',
            ],
        ], 'WARNING');

        return new ConfirmationResult(true, 'Transaction has been rejected.');
    }

    /**
     * Mark the confirmation rejected and delete it so a future request can
     * create a new one. The unique index on transaction_id only protects
     * non-deleted rows.
     */
    protected function rejectConfirmation(TransactionConfirmation $confirmation, int $userId, ?string $notes): void
    {
        $confirmation->markRejected($userId, $notes);
        $confirmation->delete();
    }

    /**
     * Notify the transaction's branch manager that a confirmation is pending.
     * Dispatches a notification to managers of the branch.
     */
    public function notifyManager(TransactionConfirmation $confirmation): void
    {
        $transaction = $confirmation->transaction;
        if (! $transaction) {
            return;
        }

        $branchId = $transaction->branch_id;
        $managers = User::where('branch_id', $branchId)
            ->whereIn('role', ['manager', 'admin'])
            ->get();

        foreach ($managers as $manager) {
            try {
                $manager->notify(new ConfirmationRequiredNotification($confirmation));
            } catch (\Throwable $e) {
                Log::warning('Failed to notify manager of confirmation', [
                    'manager_id' => $manager->id,
                    'confirmation_id' => $confirmation->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->notifyComplianceOfLargeTransaction($confirmation, $transaction);
    }

    /**
     * Large-value transactions entering the confirmation flow are escalated to
     * compliance officers for oversight (BNM large-transaction monitoring).
     */
    protected function notifyComplianceOfLargeTransaction(TransactionConfirmation $confirmation, Transaction $transaction): void
    {
        try {
            $this->alertTriageService->notifyAvailableOfficers(
                new LargeTransactionNotification($transaction, $confirmation),
                ActorContext::capture()->userId
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to resolve compliance officers for large transaction', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Expire stale pending confirmations older than the given hours.
     * Returns the number of expired confirmations.
     */
    public function expireStale(int $hours = 24): int
    {
        // Single UPDATE: markExpired() only flips status, so per-row model
        // updates would just be N identical writes. Sweep by expires_at too —
        // confirmations lapse at expires_at (30 min), so a created_at-only
        // cutoff would leave effectively-expired rows marked Pending for the
        // full backstop window.
        return TransactionConfirmation::where('status', TransactionConfirmationStatus::Pending->value)
            ->where(function ($q) use ($hours) {
                $q->where('created_at', '<=', now()->subHours($hours))
                    ->orWhere('expires_at', '<=', now());
            })
            ->update(['status' => TransactionConfirmationStatus::Expired->value]);
    }
}
