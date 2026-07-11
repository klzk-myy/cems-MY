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
        $highPriorityFlags = array_filter(
            $amlResult['flags'],
            fn ($flag) => $flag->flag_type->isHighPriority()
        );

        if (! empty($highPriorityFlags)) {
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

        try {
            $result = DB::transaction(function () use ($transaction, $approverId, $amlResult, $ipAddress) {
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

                $customer = Customer::find($lockedTransaction->customer_id);
                if (! $customer) {
                    throw new \RuntimeException('Customer has been deleted. Cannot approve transaction for non-existent customer.');
                }

                $counter = Counter::where('code', $lockedTransaction->till_id)
                    ->orWhere('id', $lockedTransaction->till_id)
                    ->first();

                $tillBalance = $counter
                    ? $this->tillBalanceManager->currentBalance($counter, $lockedTransaction->currency_code)
                    : null;

                if (! $tillBalance) {
                    throw new \RuntimeException('Till has been closed. Cannot approve transaction for closed till.');
                }

                if ($lockedTransaction->type === TransactionType::Sell) {
                    $position = $this->positionService->getPositionWithLock(
                        $lockedTransaction->currency_code,
                        (string) $lockedTransaction->branch_id
                    );

                    if (! $position) {
                        throw new \RuntimeException('Currency position has been deleted. Cannot approve Sell transaction without position.');
                    }
                }

                $history = $lockedTransaction->transition_history ?? [];
                $nowIso = now()->toIso8601String();

                $history[] = [
                    'from' => $lockedTransaction->status->value,
                    'to' => TransactionStatus::Completed->value,
                    'reason' => 'Transaction approved and completed by manager',
                    'user_id' => $approverId,
                    'timestamp' => $nowIso,
                ];

                $lockedTransaction->status = TransactionStatus::Completed;
                $lockedTransaction->approved_by = $approverId;
                $lockedTransaction->approved_at = $nowIso;
                $lockedTransaction->transition_history = $history;
                $lockedTransaction->version = $lockedTransaction->version + 1;
                $lockedTransaction->save();
                $lockedTransaction->refresh();

                if ($lockedTransaction->type === TransactionType::Sell) {
                    $available = $this->positionService->getAvailableBalance(
                        $lockedTransaction->currency_code,
                        (string) $lockedTransaction->branch_id
                    );

                    if (bccomp($available, (string) $lockedTransaction->amount_foreign, 4) < 0) {
                        throw new InsufficientStockException(
                            $lockedTransaction->currency_code,
                            (string) $lockedTransaction->amount_foreign,
                            $available
                        );
                    }

                    $reservation = $this->positionService->consumeStockReservation($lockedTransaction->id);

                    if (! $reservation) {
                        throw new StockReservationExpiredException($lockedTransaction->id);
                    }
                }

                $this->positionService->updatePosition(
                    $lockedTransaction->currency_code,
                    (string) $lockedTransaction->amount_foreign,
                    (string) $lockedTransaction->rate,
                    $lockedTransaction->type->value,
                    $lockedTransaction->branch_id ?? 'HQ'
                );

                $this->updateTillBalance(
                    $tillBalance,
                    $lockedTransaction->type->value,
                    (string) $lockedTransaction->amount_local,
                    (string) $lockedTransaction->amount_foreign
                );

                $this->updateTellerAllocation($lockedTransaction);

                $approver = User::find($approverId);

                if ($lockedTransaction->cdd_level === CddLevel::Enhanced) {
                    $this->transactionAccountingService->createDeferredAccountingEntries($lockedTransaction->id);
                } else {
                    $this->createAccountingEntries($lockedTransaction, $ipAddress, $approver);
                }

                $this->auditTrailHelper->recordTransaction($lockedTransaction->id, 'transaction_approved', [
                    'old' => [
                        'status' => TransactionStatus::PendingApproval->value,
                        'approved_by' => null,
                    ],
                    'new' => [
                        'status' => TransactionStatus::Completed->value,
                        'approved_by' => $approverId,
                        'approved_at' => $lockedTransaction->approved_at->toIso8601String(),
                        'aml_flags_checked' => $amlResult['flags_created'] ?? 0,
                    ],
                ], $approver, 'INFO', $ipAddress);

                Event::dispatch(new TransactionApproved($lockedTransaction, $approverId));

                DB::afterCommit(fn () => $this->cacheTagsService->invalidate('dashboard'));

                return new ApprovalResult(
                    success: true,
                    message: 'Transaction approved and completed successfully.',
                    transaction: $lockedTransaction->fresh()
                );
            });

            return $result;
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
