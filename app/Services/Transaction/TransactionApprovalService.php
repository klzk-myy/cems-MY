<?php

namespace App\Services\Transaction;

use App\Enums\CddLevel;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Events\TransactionApproved;
use App\Exceptions\Domain\InsufficientStockException;
use App\Exceptions\Domain\SelfApprovalException;
use App\Exceptions\Domain\StockReservationExpiredException;
use App\Models\Counter;
use App\Models\Customer;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\CurrencyPositionService;
use App\Services\Accounting\TransactionAccountingService;
use App\Services\Audit\AuditTrailHelper;
use App\Services\Branch\TellerAllocationService;
use App\Services\Branch\TillBalanceManager;
use App\Services\Contracts\TransactionApprovalServiceInterface;
use App\Services\DTOs\ApprovalResult;
use App\Services\System\CacheTagsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

class TransactionApprovalService implements TransactionApprovalServiceInterface
{
    public function __construct(
        protected TransactionMonitoringService $monitoringService,
        protected CurrencyPositionService $positionService,
        protected TransactionAccountingService $transactionAccountingService,
        protected AuditTrailHelper $auditTrailHelper,
        protected TillBalanceManager $tillBalanceManager,
        protected CacheTagsService $cacheTagsService,
    ) {}

    public function validateApprovalEligibility(Transaction $transaction, int $approverId): void
    {
        if (! $transaction->status->isPending()) {
            throw new \InvalidArgumentException(
                'Transaction is not pending approval. Current status: '.$transaction->status->label()
            );
        }

        if ($transaction->user_id === $approverId) {
            throw new SelfApprovalException;
        }
    }

    public function approve(Transaction $transaction, int $approverId, ?string $ipAddress = null): ApprovalResult
    {
        $ipAddress ??= request()?->ip();

        $amlResult = $this->monitoringService->monitorTransaction($transaction);
        $blockResult = $this->handleAmlBlocks($transaction, $amlResult, $approverId, $ipAddress);

        if ($blockResult) {
            return $blockResult;
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

    private function handleAmlBlocks(Transaction $transaction, array $amlResult, int $approverId, ?string $ipAddress): ?ApprovalResult
    {
        $highPriorityFlags = array_filter(
            $amlResult['flags'],
            fn ($flag) => $flag->flag_type->isHighPriority()
        );

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
            $this->recordStatusTransition($lockedTransaction, $approverId);
            $this->executeSideEffects($lockedTransaction, $tillBalance, $approverId, $amlResult, $ipAddress);
            $this->postApprovalCleanup($lockedTransaction, $approverId);

            return new ApprovalResult(
                success: true,
                message: 'Transaction approved and completed successfully.',
                transaction: $lockedTransaction->fresh()
            );
        });
    }

    private function acquireLockAndCheckVersion(Transaction $transaction): Transaction
    {
        $lockedTransaction = Transaction::where('id', $transaction->id)
            ->where('status', TransactionStatus::PendingApproval)
            ->lockForUpdate()
            ->first();

        if (! $lockedTransaction) {
            throw new \RuntimeException('Transaction was already processed or modified by another user.');
        }

        if ((int) $lockedTransaction->version !== (int) $transaction->version) {
            throw new \RuntimeException(
                'Transaction was modified by another user since you loaded it. Please refresh the record and try again.'
            );
        }

        return $lockedTransaction;
    }

    private function verifyPreApprovalState(Transaction $transaction): TillBalance
    {
        $customer = Customer::find($transaction->customer_id);
        if (! $customer) {
            throw new \RuntimeException('Customer has been deleted. Cannot approve transaction for non-existent customer.');
        }

        $counter = Counter::where('code', $transaction->till_id)
            ->orWhere('id', $transaction->till_id)
            ->first();

        $tillBalance = $counter
            ? $this->tillBalanceManager->currentBalance($counter, $transaction->currency_code)
            : null;

        if (! $tillBalance) {
            throw new \RuntimeException('Till has been closed. Cannot approve transaction for closed till.');
        }

        if ($transaction->type === TransactionType::Sell) {
            $position = $this->positionService->getPositionWithLock(
                $transaction->currency_code,
                (string) $transaction->branch_id
            );

            if (! $position) {
                throw new \RuntimeException('Currency position has been deleted. Cannot approve Sell transaction without position.');
            }
        }

        return $tillBalance;
    }

    private function recordStatusTransition(Transaction $transaction, int $approverId): void
    {
        $history = $transaction->transition_history ?? [];
        $nowIso = now()->toIso8601String();

        $history[] = [
            'from' => $transaction->status->value,
            'to' => TransactionStatus::Completed->value,
            'reason' => 'Transaction approved and completed by manager',
            'user_id' => $approverId,
            'timestamp' => $nowIso,
        ];

        $transaction->status = TransactionStatus::Completed;
        $transaction->approved_by = $approverId;
        $transaction->approved_at = $nowIso;
        $transaction->transition_history = $history;
        $transaction->version = $transaction->version + 1;
        $transaction->save();
        $transaction->refresh();
    }

    private function executeSideEffects(Transaction $transaction, TillBalance $tillBalance, int $approverId, array $amlResult, ?string $ipAddress): void
    {
        $this->consumeSellStockIfNeeded($transaction);

        $this->positionService->updatePosition(
            $transaction->currency_code,
            (string) $transaction->amount_foreign,
            (string) $transaction->rate,
            $transaction->type->value,
            $transaction->branch_id ?? 'HQ'
        );

        $this->updateTillBalance(
            $tillBalance,
            $transaction->type->value,
            (string) $transaction->amount_local,
            (string) $transaction->amount_foreign
        );

        $this->updateTellerAllocation($transaction);

        $approver = User::find($approverId);

        if ($transaction->cdd_level === CddLevel::Enhanced) {
            $this->transactionAccountingService->createDeferredAccountingEntries($transaction->id);
        } else {
            $this->createAccountingEntries($transaction, $ipAddress, $approver);
        }

        $this->recordApprovalAudit($transaction, $approverId, $amlResult, $approver, $ipAddress);
    }

    private function consumeSellStockIfNeeded(Transaction $transaction): void
    {
        if ($transaction->type !== TransactionType::Sell) {
            return;
        }

        $available = $this->positionService->getAvailableBalance(
            $transaction->currency_code,
            (string) $transaction->branch_id
        );

        if (bccomp($available, (string) $transaction->amount_foreign, 4) < 0) {
            throw new InsufficientStockException(
                $transaction->currency_code,
                (string) $transaction->amount_foreign,
                $available
            );
        }

        $reservation = $this->positionService->consumeStockReservation($transaction->id);

        if (! $reservation) {
            throw new StockReservationExpiredException($transaction->id);
        }
    }

    private function recordApprovalAudit(Transaction $transaction, int $approverId, array $amlResult, ?User $approver, ?string $ipAddress): void
    {
        $this->auditTrailHelper->recordTransaction($transaction->id, 'transaction_approved', [
            'old' => [
                'status' => TransactionStatus::PendingApproval->value,
                'approved_by' => null,
            ],
            'new' => [
                'status' => TransactionStatus::Completed->value,
                'approved_by' => $approverId,
                'approved_at' => $transaction->approved_at->toIso8601String(),
                'aml_flags_checked' => $amlResult['flags_created'] ?? 0,
            ],
        ], $approver, 'INFO', $ipAddress);
    }

    private function postApprovalCleanup(Transaction $transaction, int $approverId): void
    {
        Event::dispatch(new TransactionApproved($transaction, $approverId));

        DB::afterCommit(fn () => $this->cacheTagsService->invalidate('dashboard'));
    }

    private function updateTillBalance($tillBalance, string $type, string $amountLocal, string $amountForeign): void
    {
        $this->tillBalanceManager->applyTransaction(
            $tillBalance,
            TransactionType::from($type),
            $amountLocal,
            $amountForeign
        );
    }

    private function updateTellerAllocation(Transaction $transaction): void
    {
        app(TellerAllocationService::class)->applyTransactionAllocation($transaction);
    }

    private function createAccountingEntries(Transaction $transaction, ?string $ipAddress, ?User $user): void
    {
        if ($transaction->cdd_level === CddLevel::Enhanced
            && $transaction->status !== TransactionStatus::Completed) {
            return;
        }

        $this->transactionAccountingService->createImmediateAccountingEntries($transaction);
    }
}
