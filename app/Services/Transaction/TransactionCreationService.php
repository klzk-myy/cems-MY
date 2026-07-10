<?php

namespace App\Services\Transaction;

use App\Enums\CddLevel;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Events\TransactionCreated;
use App\Exceptions\Domain\DuplicateTransactionException;
use App\Exceptions\Domain\InsufficientStockException;
use App\Exceptions\Domain\TillBalanceMissingException;
use App\Models\Counter;
use App\Models\TellerAllocation;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\CurrencyPositionService;
use App\Services\Accounting\TransactionAccountingService;
use App\Services\Audit\AuditTrailHelper;
use App\Services\Branch\TillBalanceManager;
use App\Services\Contracts\TransactionCreationServiceInterface;
use App\Services\Contracts\TransactionIdempotencyServiceInterface;
use App\Services\System\CacheTagsService;
use App\Services\Transaction\DTOs\TransactionCreationContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class TransactionCreationService implements TransactionCreationServiceInterface
{
    public function __construct(
        protected TransactionIdempotencyServiceInterface $idempotencyService,
        protected CurrencyPositionService $positionService,
        protected TransactionAccountingService $transactionAccountingService,
        protected AuditTrailHelper $auditTrailHelper,
        protected TillBalanceManager $tillBalanceManager,
        protected CacheTagsService $cacheTagsService,
    ) {}

    public function create(TransactionCreationContext $context, ?int $userId = null, ?string $ipAddress = null): Transaction
    {
        $data = $context->data;
        $user = $context->user;
        $userId ??= $user->id;
        $ipAddress ??= request()?->ip();

        return DB::transaction(function () use ($context, $data, $user, $userId, $ipAddress) {
            $existingByIdempotencyKey = $this->idempotencyService->findDuplicate(
                $data['idempotency_key'] ?? null,
                $userId,
                $data
            );

            if ($existingByIdempotencyKey) {
                return $existingByIdempotencyKey;
            }

            $recentDuplicate = $this->idempotencyService->checkRecentDuplicate($userId, $data, 30);
            if ($recentDuplicate) {
                throw new DuplicateTransactionException;
            }

            $this->ensureStockForSell($data, $context->tillBalance);
            $this->lockPositionForBuy($data, $context->tillBalance);

            $transaction = $this->createTransactionRecord($data, $context);

            if ($transaction->status === TransactionStatus::PendingApproval
                && $data['type'] === TransactionType::Sell->value) {
                $this->positionService->reserveStock($transaction);
            }

            if ($transaction->status === TransactionStatus::Completed) {
                $this->applyCompletedSideEffects($transaction, $context, $ipAddress);
            }

            $this->auditTrailHelper->recordTransaction(
                $transaction->id,
                'transaction_created',
                [
                    'new' => [
                        'customer_id' => $transaction->customer_id,
                        'type' => $transaction->type,
                        'amount_local' => $transaction->amount_local,
                        'amount_foreign' => $transaction->amount_foreign,
                        'currency' => $transaction->currency_code,
                        'rate' => $transaction->rate,
                        'status' => $transaction->status->value,
                        'cdd_level' => $transaction->cdd_level->value,
                        'branch_id' => $transaction->branch_id,
                        'till_id' => $transaction->till_id,
                    ],
                ],
                $user,
                'INFO',
                $ipAddress
            );

            DB::afterCommit(function () use ($transaction) {
                Event::dispatch(new TransactionCreated($transaction));
                $this->cacheTagsService->invalidate('dashboard');
            });

            return $transaction;
        });
    }

    private function ensureStockForSell(array $data, TillBalance $tillBalance): void
    {
        if ($data['type'] !== TransactionType::Sell->value) {
            return;
        }

        $availableBalance = $this->positionService->getAvailableBalance(
            $data['currency_code'],
            (string) $tillBalance->branch_id
        );

        if (bccomp($availableBalance, $data['amount_foreign'], 4) < 0) {
            throw new InsufficientStockException(
                $data['currency_code'],
                $data['amount_foreign'],
                $availableBalance
            );
        }
    }

    private function lockPositionForBuy(array $data, TillBalance $tillBalance): void
    {
        if ($data['type'] !== TransactionType::Buy->value) {
            return;
        }

        $this->positionService->getPositionWithLock(
            $data['currency_code'],
            (string) $tillBalance->branch_id
        );
    }

    private function createTransactionRecord(array $data, TransactionCreationContext $context): Transaction
    {
        $transaction = Transaction::create([
            'customer_id' => $context->customer->id,
            'user_id' => $context->user->id,
            'branch_id' => $context->tillBalance->branch_id,
            'till_id' => $data['till_id'],
            'type' => $data['type'],
            'currency_code' => $data['currency_code'],
            'amount_foreign' => $data['amount_foreign'],
            'amount_local' => $context->amountLocal,
            'rate' => $data['rate'],
            'purpose' => $data['purpose'],
            'source_of_funds' => $data['source_of_funds'],
            'source_of_wealth' => $data['source_of_wealth'] ?? null,
            'cdd_level' => $context->cddLevel,
            'idempotency_key' => $data['idempotency_key'] ?? null,
        ]);

        $transaction->status = $context->status;
        $transaction->hold_reason = $context->holdReason;
        $transaction->approved_by = null;
        $transaction->version = 0;
        $transaction->save();

        return $transaction->refresh();
    }

    private function applyCompletedSideEffects(Transaction $transaction, TransactionCreationContext $context, ?string $ipAddress): void
    {
        $data = $context->data;

        $this->positionService->updatePosition(
            $data['currency_code'],
            $data['amount_foreign'],
            $data['rate'],
            $data['type'],
            (string) $context->tillBalance->branch_id
        );

        $this->updateTillBalance($context->tillBalance, $data['type'], $context->amountLocal, $data['amount_foreign']);

        $this->updateTellerAllocation($context->allocation, $data['type'], $data['amount_foreign'], $context->amountLocal);

        $this->createAccountingEntries($transaction, $ipAddress, $context->user);
    }

    private function updateTillBalance(TillBalance $tillBalance, string $type, string $amountLocal, string $amountForeign): void
    {
        $counter = Counter::where('code', $tillBalance->till_id)
            ->orWhere('id', $tillBalance->till_id)
            ->first();

        if (! $counter) {
            throw new TillBalanceMissingException($tillBalance->currency_code, $tillBalance->till_id);
        }

        $lockedForeign = $this->tillBalanceManager->currentBalance($counter, $tillBalance->currency_code, true);
        if (! $lockedForeign) {
            throw new TillBalanceMissingException($tillBalance->currency_code, $tillBalance->till_id);
        }

        $myrBalance = $this->tillBalanceManager->currentBalance($counter, 'MYR', true);
        if (! $myrBalance) {
            throw new TillBalanceMissingException('MYR', $tillBalance->till_id);
        }

        if ($type === TransactionType::Buy->value) {
            $this->tillBalanceManager->adjustBalance($lockedForeign, 'buy_total_foreign', $amountForeign, 'add', false);
            $this->tillBalanceManager->adjustBalance($lockedForeign, 'foreign_total', $amountForeign, 'add', false);
        } else {
            $this->tillBalanceManager->adjustBalance($lockedForeign, 'sell_total_foreign', $amountForeign, 'add', false);
            $this->tillBalanceManager->adjustBalance($lockedForeign, 'foreign_total', $amountForeign, 'subtract', false);
        }

        $myrOperation = $type === TransactionType::Buy->value ? 'subtract' : 'add';
        $this->tillBalanceManager->adjustBalance($myrBalance, 'transaction_total', $amountLocal, $myrOperation, false);
    }

    private function updateTellerAllocation(?Model $allocation, string $type, string $amountForeign, string $amountLocal): void
    {
        if (! $allocation) {
            return;
        }

        $lockedAllocation = TellerAllocation::where('id', $allocation->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($type === TransactionType::Buy->value) {
            $lockedAllocation->add($amountForeign);
        } else {
            $lockedAllocation->deduct($amountForeign);
        }

        $lockedAllocation->addDailyUsed($amountLocal);
    }

    private function createAccountingEntries(Transaction $transaction, ?string $ipAddress, ?Model $user = null): void
    {
        if ($transaction->cdd_level === CddLevel::Enhanced
            && $transaction->status !== TransactionStatus::Completed) {
            Log::info('Deferring journal entry creation for Enhanced CDD transaction', [
                'transaction_id' => $transaction->id,
                'status' => $transaction->status->value,
                'cdd_level' => $transaction->cdd_level->value,
            ]);

            $this->auditTrailHelper->recordTransaction($transaction->id, 'journal_entries_deferred', [
                'cdd_level' => $transaction->cdd_level->value,
                'status' => $transaction->status->value,
                'reason' => 'Enhanced CDD requires approval before bookkeeping',
            ], $user instanceof User ? $user : null, 'INFO', $ipAddress);

            return;
        }

        $this->transactionAccountingService->createImmediateAccountingEntries($transaction);
    }
}
