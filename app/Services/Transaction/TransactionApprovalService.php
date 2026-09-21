<?php

namespace App\Services\Transaction;

use App\Enums\ApprovalStatus;
use App\Enums\CddLevel;
use App\Enums\ComplianceFlagType;
use App\Enums\FlagStatus;
use App\Enums\StockReservationStatus;
use App\Enums\TransactionConfirmationStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Events\TransactionApproved;
use App\Exceptions\Domain\InsufficientStockException;
use App\Exceptions\Domain\SelfApprovalException;
use App\Exceptions\Domain\StockReservationExpiredException;
use App\Exceptions\Domain\TransactionApprovalException;
use App\Exceptions\Domain\TransactionConfirmationRequiredException;
use App\Exceptions\Domain\TransactionCreationException;
use App\Exceptions\Domain\TransactionValidationException;
use App\Models\Compliance\FlaggedTransaction;
use App\Models\Counter;
use App\Models\Customer;
use App\Models\StockReservation;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Models\TransactionConfirmation;
use App\Models\User;
use App\Notifications\TransactionOutcomeNotification;
use App\Services\Accounting\CurrencyPositionService;
use App\Services\Accounting\TransactionAccountingService;
use App\Services\Audit\AuditTrailHelper;
use App\Services\AuditService;
use App\Services\Branch\TellerAllocationService;
use App\Services\Branch\TillBalanceManager;
use App\Services\Compliance\AmlRuleEvaluator;
use App\Services\DTOs\ApprovalResult;
use App\Services\System\CacheInvalidationService;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use App\Services\Traits\AccountingEntriesTrait;
use App\Services\Traits\TillBalanceTrait;
use App\Support\ActorContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class TransactionApprovalService
{
    use AccountingEntriesTrait, TillBalanceTrait;

    public function __construct(
        protected TransactionMonitoringService $monitoringService,
        protected CurrencyPositionService $positionService,
        protected TransactionAccountingService $transactionAccountingService,
        protected AuditTrailHelper $auditTrailHelper,
        protected TillBalanceManager $tillBalanceManager,
        protected CacheInvalidationService $cacheInvalidationService,
        protected AuditService $auditService,
        protected TellerAllocationService $tellerAllocationService,
        protected MathService $mathService,
        protected TransactionConfirmationService $confirmationService,
        protected ThresholdService $thresholdService,
        protected AmlRuleEvaluator $amlRuleEvaluator,
    ) {}

    public function validateApprovalEligibility(Transaction $transaction, int $approverId): void
    {
        if (! $transaction->status->isPending()) {
            throw new TransactionValidationException(
                message: 'Transaction is not pending approval. Current status: '.$transaction->status->label()
            );
        }

        if ($transaction->user_id === $approverId) {
            throw new SelfApprovalException;
        }

        if ($transaction->hold_reason !== null && $transaction->compliance_cleared_at === null) {
            throw new TransactionValidationException(
                message: 'Transaction is under compliance hold and must be cleared by a compliance officer before approval.'
            );
        }
    }

    /**
     * Enforce the approval tier: all transaction approvals require a
     * compliance officer (or admin) holding the approve_transactions
     * permission — an admin matrix revocation closes this path too.
     */
    private function validateApproverTier(Transaction $transaction, int $approverId): void
    {
        $approver = User::findOrFail($approverId);

        if (! $approver->role->canApproveTransactions()) {
            throw new TransactionValidationException(
                message: 'All transaction approvals require compliance officer approval.'
            );
        }
    }

    /**
     * Record compliance clearance of a held transaction.
     *
     * A transaction carrying a hold_reason cannot be approved until a
     * compliance officer clears it. Clearing does not approve — the normal
     * approval path (compliance/admin via approve_transactions) still
     * applies afterwards.
     */
    public function clearHold(Transaction $transaction, int $clearerId): void
    {
        // Re-read under a row lock: the caller's model is a stale snapshot.
        // Two concurrent clears must not both pass the cleared_at check and
        // write duplicate clearance audit records.
        DB::transaction(function () use ($transaction, $clearerId) {
            $locked = Transaction::where('id', $transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->hold_reason === null) {
                throw new TransactionValidationException(
                    message: 'Transaction is not under compliance hold.'
                );
            }

            if ($locked->compliance_cleared_at !== null) {
                throw new TransactionValidationException(
                    message: 'Compliance hold has already been cleared.'
                );
            }

            if (! $locked->status->isPending()) {
                throw new TransactionValidationException(
                    message: 'Transaction is not pending approval. Current status: '.$locked->status->label()
                );
            }

            $locked->compliance_cleared_by = $clearerId;
            $locked->compliance_cleared_at = now();
            $locked->save();

            $this->auditService->logComplianceDecision('compliance_hold_cleared', $locked->id, [
                'cleared_by' => $clearerId,
                'hold_reason' => $locked->hold_reason,
                'amount_myr' => (string) $locked->amount_myr,
                'customer_id' => $locked->customer_id,
            ]);
        });
    }

    /**
     * Reject a pending transaction and notify the originating teller.
     *
     * Centralizes validation, the PendingApproval -> Rejected transition and
     * the teller outcome notification so both the web and API controllers
     * share one path. The notification is queued (ShouldQueue) and failures
     * are logged without ever failing the rejection itself.
     */
    public function reject(Transaction $transaction, int $rejectorId, string $reason): bool
    {
        // Re-read under a row lock: the caller's model is a stale snapshot.
        // Without the re-read a reject racing an approval overwrites the
        // committed Completed status with Rejected while the booked stock,
        // till and journal side effects stay in place.
        $locked = DB::transaction(function () use ($transaction, $rejectorId, $reason) {
            $locked = Transaction::where('id', $transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->validateApprovalEligibility($locked, $rejectorId);

            // Same tier rule as approve(): rejection is an approval decision,
            // so it requires the approve_transactions permission even when the
            // caller bypasses the HTTP policy layer.
            $this->validateApproverTier($locked, $rejectorId);

            if (! (new TransactionStateMachine($locked, $this->auditService))->reject($reason)) {
                return null;
            }

            // Release the reservation a pending Sell was holding. Without
            // this the stock stays unavailable until the 24h expiry sweep.
            // Safe on transactions with no reservation (no-op).
            $this->positionService->releaseStockReservation($locked->id);

            return $locked;
        });

        if ($locked === null) {
            return false;
        }

        $rejector = User::find($rejectorId);
        $teller = $locked->user()->first();

        if ($teller && $teller->id !== $rejectorId) {
            try {
                $teller->notify(new TransactionOutcomeNotification(
                    $locked->fresh() ?? $locked,
                    ApprovalStatus::Rejected,
                    ($rejector !== null && $rejector->username !== null) ? $rejector->username : 'Unknown',
                    $reason
                ));
            } catch (\Throwable $e) {
                Log::warning('Failed to send transaction rejection notification', [
                    'transaction_id' => $locked->id,
                    'teller_id' => $teller->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return true;
    }

    public function approve(Transaction $transaction, int $approverId, ?string $ipAddress = null): ApprovalResult
    {
        $ipAddress ??= ActorContext::capture()->ipAddress;

        // Self-guard so direct service callers (not just ApproveTransactionAction)
        // cannot approve non-pending, self-created, or compliance-held transactions.
        $this->validateApprovalEligibility($transaction, $approverId);

        $this->validateApproverTier($transaction, $approverId);

        // Segregation-of-duties gate: large transactions that enter the manager
        // confirmation flow may only be approved once a TransactionConfirmation
        // with status Confirmed exists. Without this gate the confirmation step
        // could be bypassed by approving directly from the approval queue.
        $this->enforceConfirmationGate($transaction);

        $amlResult = $this->monitoringService->monitorTransaction($transaction);
        $blockResult = $this->handleAmlBlocks($transaction, $amlResult, $approverId, $ipAddress);

        if ($blockResult) {
            return $blockResult;
        }

        try {
            $this->amlRuleEvaluator
                ->evaluateActiveRules($transaction, $transaction->customer);
        } catch (\Throwable $e) {
            Log::error('AML rule engine skipped', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            return $this->processApproval($transaction, $approverId, $amlResult, $ipAddress);
        } catch (InsufficientStockException $e) {
            return new ApprovalResult(success: false, message: 'Insufficient stock: '.$e->getMessage());
        } catch (StockReservationExpiredException $e) {
            return new ApprovalResult(success: false, message: 'Stock reservation expired: '.$e->getMessage());
        } catch (\RuntimeException $e) {
            return new ApprovalResult(success: false, message: $e->getMessage());
        } catch (\Exception $e) {
            return new ApprovalResult(success: false, message: 'Transaction approval failed: '.$e->getMessage());
        }
    }

    /**
     * Throw unless a Confirmed confirmation exists for transactions that
     * require manager confirmation. Only PendingApproval transactions are
     * gated: refunds and other statuses follow their own approval flows.
     */
    private function enforceConfirmationGate(Transaction $transaction): void
    {
        if ($transaction->status !== TransactionStatus::PendingApproval) {
            return;
        }

        if (! $this->confirmationService->requiresConfirmation($transaction)) {
            return;
        }

        $confirmed = TransactionConfirmation::where('transaction_id', $transaction->id)
            ->where('status', TransactionConfirmationStatus::Confirmed->value)
            ->exists();

        if (! $confirmed) {
            throw new TransactionConfirmationRequiredException($transaction->id);
        }
    }

    /**
     * @param  array{flags: array<int, FlaggedTransaction>}  $amlResult
     */
    private function handleAmlBlocks(Transaction $transaction, array $amlResult, int $approverId, ?string $ipAddress): ?ApprovalResult
    {
        // Flags already dispositioned by compliance (resolved/rejected) are
        // honored — re-running monitoring must not resurrect a reviewed
        // finding and deadlock approval while the pattern window is live.
        $blocking = collect($amlResult['flags'])
            ->filter(fn ($flag) => $flag->flag_type->isHighPriority()
                && ! ($flag->status?->isTerminal() ?? false));

        // Flags persisted outside this monitoring run — e.g. sanction hits
        // written by screening jobs — block just the same. The check registry
        // never re-emits them, so they would otherwise be invisible here.
        $persisted = FlaggedTransaction::where('transaction_id', $transaction->id)
            ->whereIn('flag_type', ComplianceFlagType::highPriorityValues())
            ->whereNotIn('status', FlagStatus::terminalValues())
            ->get();

        $highPriorityFlags = $blocking->merge($persisted)->unique('id')->all();

        if (empty($highPriorityFlags)) {
            return null;
        }

        $flagTypes = implode(', ', array_map(
            fn ($f) => $f->flag_type->label(),
            $highPriorityFlags
        ));

        $this->auditTrailHelper->recordTransaction(
            $transaction->id,
            'transaction_approval_blocked',
            [
                'new' => [
                    'reason' => 'High-priority AML flags',
                    'flags' => $flagTypes,
                ],
            ],
            User::find($approverId),
            'WARNING',
            $ipAddress
        );

        return new ApprovalResult(
            success: false,
            message: "Approval blocked: High-priority AML flags generated ({$flagTypes}). Transaction remains pending for compliance review."
        );
    }

    private function processApproval(Transaction $transaction, int $approverId, array $amlResult, ?string $ipAddress): ApprovalResult
    {
        return DB::transaction(function () use ($transaction, $approverId, $amlResult, $ipAddress) {
            $lockedTransaction = $this->acquireLockAndCheckVersion($transaction);
            $tillBalance = $this->verifyPreApprovalState($lockedTransaction);
            $requiresProcessing = $this->recordStatusTransition($lockedTransaction, $approverId);

            if (! $requiresProcessing) {
                // Standard transaction - execute side effects and complete
                $this->executeSideEffects($lockedTransaction, $tillBalance, $approverId, $amlResult, $ipAddress);
                $this->postApprovalCleanup($lockedTransaction, $approverId);
            }
            // For refunds: side effects will be executed in separate completion step

            $message = $requiresProcessing
                ? 'Transaction approved. Refund requires compliance review before processing.'
                : 'Transaction approved and completed successfully.';

            return new ApprovalResult(
                success: true,
                message: $message,
                transaction: $lockedTransaction->fresh(),
                requiresProcessing: $requiresProcessing
            );
        });
    }

    private function acquireLockAndCheckVersion(Transaction $transaction): Transaction
    {
        $lockedTransaction = Transaction::where('id', $transaction->id)
            ->where('status', TransactionStatus::PendingApproval->value)
            ->lockForUpdate()
            ->first();

        if (! $lockedTransaction) {
            throw new TransactionApprovalException(transactionId: $transaction->id, message: 'Transaction was already processed or modified by another user.');
        }

        if ((int) $lockedTransaction->version !== (int) $transaction->version) {
            throw new TransactionApprovalException(
                transactionId: $transaction->id,
                message: 'Transaction was modified by another user since you loaded it. Please refresh the record and try again.'
            );
        }

        return $lockedTransaction;
    }

    private function verifyPreApprovalState(Transaction $transaction): ?TillBalance
    {
        $customer = Customer::find($transaction->customer_id);
        if (! $customer) {
            throw new TransactionApprovalException(transactionId: $transaction->id, message: 'Customer has been deleted. Cannot approve transaction for non-existent customer.');
        }

        // Drawer-less transactions carry no till — there is nothing to check
        // beyond customer existence and (for Sells) position availability.
        $counter = filled($transaction->till_id)
            ? Counter::findByCodeOrId($transaction->till_id)
            : null;

        $tillBalance = $counter
            ? $this->tillBalanceManager->currentBalance($counter, $transaction->currency_code)
            : null;

        if ($counter !== null && ! $tillBalance) {
            throw new TransactionApprovalException(transactionId: $transaction->id, message: 'Till has been closed. Cannot approve transaction for closed till.');
        }

        if ($transaction->type === TransactionType::Sell) {
            $position = $this->positionService->getPositionWithLock(
                $transaction->currency_code,
                (string) $transaction->branch_id
            );

            if (! $position) {
                throw new TransactionApprovalException(transactionId: $transaction->id, message: 'Currency position has been deleted. Cannot approve Sell transaction without position.');
            }
        }

        return $tillBalance;
    }

    private function recordStatusTransition(Transaction $transaction, int $approverId): bool
    {
        $stateMachine = new TransactionStateMachine($transaction, $this->auditService);

        // For refunds, require full approval flow: PendingApproval -> Approved
        // This ensures compliance review for high-risk reversal transactions
        // Returns true if transaction needs further processing (Approved but not Completed)
        if ($transaction->is_refund) {
            $stateMachine->approve(); // PendingApproval -> Approved

            $transaction->approved_by = $approverId;
            $transaction->approved_at = now();
            $transaction->save();
            $transaction->refresh();

            return true; // Requires further processing
        }

        // Standard flow for non-refunds: direct to Completed (manager approval)
        $approver = User::findOrFail($approverId);
        $stateMachine->approveAndComplete('Transaction approved and completed by manager', $approver);

        // approveAndComplete doesn't set approved_by/approved_at for Completed status
        // Set them manually after the transition
        $transaction->approved_by = $approverId;
        $transaction->approved_at = now();
        $transaction->save();
        $transaction->refresh();

        return false; // Fully completed
    }

    private function executeSideEffects(
        Transaction $transaction,
        ?TillBalance $tillBalance,
        int $approverId,
        array $amlResult,
        ?string $ipAddress,
        string $auditAction = 'transaction_approved',
        string $auditOldStatus = TransactionStatus::PendingApproval->value
    ): void {
        // Side effects are skipped when the transaction was already booked —
        // either because it completed at creation (journals post immediately
        // for non-Enhanced CDD) or because a previous approval attempt got this
        // far. Re-applying would double-count stock, till and journal entries.
        $approver = User::find($approverId);

        if ($transaction->journal_entry_id === null) {
            $this->consumeSellStockIfNeeded($transaction);

            $this->positionService->updatePosition(
                $transaction->currency_code,
                (string) $transaction->quantity,
                (string) $transaction->rate,
                $transaction->type->value,
                $transaction->branch_id !== null ? (string) $transaction->branch_id : null,
                $transaction
            );

            if ($tillBalance !== null) {
                $this->tillBalanceManager->applyTransaction(
                    $tillBalance,
                    $transaction->type,
                    (string) $transaction->amount_myr,
                    (string) $transaction->quantity
                );
            }

            $this->updateTellerAllocation($transaction);

            if ($transaction->cdd_level === CddLevel::Enhanced) {
                $this->transactionAccountingService->createDeferredAccountingEntries($transaction->id);
            } else {
                $this->createAccountingEntries($transaction, $ipAddress, $approver);
            }
        }

        $this->recordApprovalAudit($transaction, $approverId, $amlResult, $approver, $ipAddress, $auditAction, $auditOldStatus);
    }

    private function consumeSellStockIfNeeded(Transaction $transaction): void
    {
        if ($transaction->type !== TransactionType::Sell) {
            return;
        }

        // Compare against the locked position's raw quantity, not
        // getAvailableBalance(): this transaction's own pending reservation
        // already earmarks the stock, so subtracting reservations would
        // double-count it and falsely fail a full-stock sell.
        $position = $this->positionService->getPositionWithLock(
            $transaction->currency_code,
            (string) $transaction->branch_id
        );

        $available = $position === null ? '0' : (string) $position->quantity;

        if ($this->mathService->compare($available, (string) $transaction->quantity) < 0) {
            throw new InsufficientStockException(
                $transaction->currency_code,
                (string) $transaction->quantity,
                $available
            );
        }

        $reservation = $this->positionService->consumeStockReservation($transaction->id);

        if (! $reservation) {
            // Transactions created directly as Completed (no approval flow) never
            // had a reservation: the stock was never reserved, and a booking
            // failure rolled back the position update, so re-execution must not
            // fail on a reservation that was never made.
            $wentThroughApproval = collect($transaction->transition_history ?? [])
                ->contains(fn ($step) => ($step['from'] ?? null) === TransactionStatus::PendingApproval->value);

            if (! $wentThroughApproval) {
                return;
            }

            // Approval-flow re-execution: the reservation may have been consumed
            // by the original (partially successful) attempt, in which case the
            // stock was already deducted from the position and re-consumption
            // must not fail the retry.
            $alreadyConsumed = StockReservation::where('transaction_id', $transaction->id)
                ->where('status', StockReservationStatus::Consumed->value)
                ->exists();

            if (! $alreadyConsumed) {
                throw new StockReservationExpiredException($transaction->id);
            }
        }
    }

    private function recordApprovalAudit(
        Transaction $transaction,
        int $approverId,
        array $amlResult,
        ?User $approver,
        ?string $ipAddress,
        string $action = 'transaction_approved',
        string $oldStatus = TransactionStatus::PendingApproval->value
    ): void {
        $this->auditTrailHelper->recordTransactionSealed($transaction->id, $action, [
            'old' => [
                'status' => $oldStatus,
                'approved_by' => null,
            ],
            'new' => [
                'status' => TransactionStatus::Completed->value,
                // Mirror the persisted field, not the actor: system
                // re-execution leaves approved_by null, and the audit must
                // not attribute an approval that never happened.
                'approved_by' => $transaction->approved_by,
                'approved_at' => $transaction->approved_at?->toIso8601String(),
                'reexecuted_at' => $transaction->reexecuted_at?->toIso8601String(),
                'aml_flags_checked' => $amlResult['flags_created'] ?? 0,
            ],
        ], $approver, 'CRITICAL', $ipAddress);
    }

    private function postApprovalCleanup(Transaction $transaction, int $approverId): void
    {
        Event::dispatch(new TransactionApproved($transaction, $approverId));

        DB::afterCommit(fn () => $this->cacheInvalidationService->invalidate('dashboard'));
    }

    private function updateTellerAllocation(Transaction $transaction): void
    {
        $this->tellerAllocationService->applyTransactionAllocation($transaction);
    }

    /**
     * Re-execute a failed transaction and book it to Completed.
     *
     * Automated recovery path used by ProcessTransactionRetry. Re-runs the
     * standard execution side effects (position, till, teller allocation,
     * accounting) under a row lock inside a database transaction so a failed
     * attempt rolls back atomically and can never double-book.
     *
     * @param  Transaction  $transaction  The failed transaction to re-execute
     * @param  string|null  $ipAddress  IP address for audit
     */
    public function reprocessFailed(Transaction $transaction, ?string $ipAddress = null): ApprovalResult
    {
        if (! $transaction->status->isFailed()) {
            return new ApprovalResult(
                success: false,
                message: 'Transaction is not in Failed status. Current status: '.$transaction->status->label()
            );
        }

        if ($transaction->is_dlq) {
            return new ApprovalResult(
                success: false,
                message: 'Transaction is in the dead letter queue. Use retryFromDLQ to recover it before reprocessing.'
            );
        }

        $ipAddress ??= ActorContext::capture()->ipAddress;

        try {
            return DB::transaction(function () use ($transaction, $ipAddress) {
                $lockedTransaction = Transaction::where('id', $transaction->id)
                    ->where('status', TransactionStatus::Failed->value)
                    ->lockForUpdate()
                    ->first();

                if (! $lockedTransaction) {
                    throw new TransactionApprovalException(transactionId: $transaction->id, message: 'Transaction was already processed or modified by another process.');
                }

                $tillBalance = $this->verifyPreApprovalState($lockedTransaction);

                $stateMachine = new TransactionStateMachine($lockedTransaction, $this->auditService);
                if (! $stateMachine->reprocess()) {
                    throw new TransactionCreationException('Failed to transition transaction to Completed during reprocessing.');
                }

                // The re-execution is performed by the automated recovery
                // flow — it is not an approval. approved_by/approved_at stay
                // null so "who approved" queries never attribute a system
                // retry to the original teller; the re-execution is recorded
                // on reexecuted_by (null = system) + reexecuted_at, and the
                // transaction_reexecuted audit action makes it unambiguous.
                $actorId = ActorContext::capture()->userId ?? (int) $lockedTransaction->user_id;
                $lockedTransaction->approved_by = null;
                $lockedTransaction->approved_at = null;
                $lockedTransaction->reexecuted_by = ActorContext::capture()->userId;
                $lockedTransaction->reexecuted_at = now();
                $lockedTransaction->save();

                $this->executeSideEffects(
                    $lockedTransaction,
                    $tillBalance,
                    $actorId,
                    [],
                    $ipAddress,
                    'transaction_reexecuted',
                    TransactionStatus::Failed->value
                );
                $this->postApprovalCleanup($lockedTransaction, $actorId);

                return new ApprovalResult(
                    success: true,
                    message: 'Transaction re-executed and completed successfully.',
                    transaction: $lockedTransaction->fresh(),
                    requiresProcessing: false,
                );
            });
        } catch (InsufficientStockException $e) {
            return new ApprovalResult(success: false, message: 'Insufficient stock: '.$e->getMessage());
        } catch (StockReservationExpiredException $e) {
            return new ApprovalResult(success: false, message: 'Stock reservation expired: '.$e->getMessage());
        } catch (\RuntimeException $e) {
            return new ApprovalResult(success: false, message: $e->getMessage());
        } catch (\Exception $e) {
            return new ApprovalResult(success: false, message: 'Transaction reprocessing failed: '.$e->getMessage());
        }
    }

    /**
     * Complete a refund transaction that has been approved (Approved -> Processing -> Completed).
     * This is a separate step from initial approval for compliance oversight.
     *
     * @param  Transaction  $transaction  The refund transaction in Approved status
     * @param  int  $approverId  The user completing the refund
     * @param  ?string  $ipAddress  IP address for audit
     */
    public function completeRefund(Transaction $transaction, int $approverId, ?string $ipAddress = null): ApprovalResult
    {
        $ipAddress ??= ActorContext::capture()->ipAddress;

        if (! $transaction->is_refund) {
            return new ApprovalResult(
                success: false,
                message: 'Only refund transactions can be completed via this method.'
            );
        }

        if (! $transaction->status->isApproved()) {
            return new ApprovalResult(
                success: false,
                message: 'Refund must be in Approved status to complete. Current: '.$transaction->status->label()
            );
        }

        // Completing a refund releases funds — the same compliance/admin
        // tier that approved it must complete it.
        $this->validateApproverTier($transaction, $approverId);

        return DB::transaction(function () use ($transaction, $approverId, $ipAddress) {
            $lockedTransaction = Transaction::where('id', $transaction->id)
                ->where('status', TransactionStatus::Approved->value)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $lockedTransaction->version !== (int) $transaction->version) {
                throw new TransactionApprovalException(transactionId: $transaction->id, message: 'Transaction was modified by another user. Please refresh and try again.');
            }

            $stateMachine = new TransactionStateMachine($lockedTransaction, $this->auditService);

            // Approved -> Processing -> Completed
            $stateMachine->startProcessing();
            $stateMachine->complete();

            // The reversal already restored position, till, journal and
            // teller-allocation state on the ORIGINAL transaction inside
            // TransactionReversalService::reverse(). The refund record is a
            // compliance-gated acknowledgement of the physical cash return —
            // routing it through executeSideEffects would book every
            // financial leg a second time.
            $this->recordApprovalAudit(
                $lockedTransaction,
                $approverId,
                [],
                User::find($approverId),
                $ipAddress,
                'refund_completed',
                TransactionStatus::Approved->value
            );
            $this->postApprovalCleanup($lockedTransaction, $approverId);

            return new ApprovalResult(
                success: true,
                message: 'Refund completed successfully.',
                transaction: $lockedTransaction->fresh(),
                requiresProcessing: false
            );
        });
    }
}
