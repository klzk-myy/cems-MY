<?php

namespace App\Services\Transaction;

use App\Enums\TransactionConfirmationStatus;
use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Models\TransactionConfirmation;
use App\Models\User;
use App\Notifications\ConfirmationRequiredNotification;
use App\Notifications\LargeTransactionNotification;
use App\Services\AuditService;
use App\Services\Compliance\AlertTriageService;
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
        protected AlertTriageService $alertTriageService
    ) {}

    /**
     * Determine if a transaction requires manager confirmation.
     *
     * Confirmation is required when the local amount is greater than or equal
     * to the configured structured-transaction threshold.
     */
    public function requiresConfirmation(Transaction $transaction): bool
    {
        $threshold = $this->thresholdService->getStrThreshold();

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
     * @return array{success: bool, message: string}
     */
    public function confirm(TransactionConfirmation $confirmation, array $validated, int $userId): array
    {
        if ($confirmation->isExpired()) {
            $confirmation->markExpired();

            return [
                'success' => false,
                'message' => 'Confirmation has expired. Please request a new confirmation.',
            ];
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

                    return [
                        'success' => false,
                        'message' => 'Confirmation has expired. Please request a new confirmation.',
                    ];
                }

                if (! $lockedConfirmation || ! $lockedConfirmation->isPending()) {
                    return [
                        'success' => false,
                        'message' => 'Confirmation has already been processed or is no longer pending.',
                    ];
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
    /**
     * @return array{success: bool, message: string}
     */
    protected function handleConfirm(TransactionConfirmation $confirmation, int $userId, ?string $notes): array
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

        return [
            'success' => true,
            'message' => 'Transaction confirmed and pending final approval.',
        ];
    }

    /**
     * Handle rejection action.
     */
    /**
     * @return array{success: bool, message: string}
     */
    protected function handleReject(TransactionConfirmation $confirmation, int $userId, ?string $notes): array
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

            return [
                'success' => true,
                'message' => 'Confirmation rejected. The transaction was already completed and remains unchanged.',
            ];
        }

        $stateMachine = new TransactionStateMachine($transaction);
        $reason = 'Rejected during confirmation: '.($notes ?? 'No reason provided');

        if (! $stateMachine->transitionTo(TransactionStatus::Cancelled, [
            'user_id' => $userId,
            'reason' => $reason,
        ])) {
            throw new \RuntimeException(
                "Cannot reject transaction #{$transaction->id}: status '{$transaction->status->value}' does not allow cancellation."
            );
        }

        $this->rejectConfirmation($confirmation, $userId, $notes);

        $this->auditService->logWithSeveritySealed('transaction_rejected', [
            'user_id' => $userId,
            'entity_type' => 'Transaction',
            'entity_id' => $confirmation->transaction_id,
            'new_values' => [
                'confirmation_id' => $confirmation->id,
                'rejected_by' => $userId,
                'reason' => $notes ?? 'No reason provided',
            ],
        ], 'WARNING');

        return [
            'success' => true,
            'message' => 'Transaction has been rejected.',
        ];
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
        $cutoff = now()->subHours($hours);

        $stale = TransactionConfirmation::where('status', TransactionConfirmationStatus::Pending->value)
            ->where('created_at', '<=', $cutoff)
            ->get();

        $count = 0;
        foreach ($stale as $confirmation) {
            $confirmation->update(['status' => TransactionConfirmationStatus::Expired->value]);
            $count++;
        }

        return $count;
    }
}
