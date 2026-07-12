<?php

namespace App\Services\Transaction;

use App\Enums\CddLevel;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Events\TransactionCreated;
use App\Exceptions\Domain\AllocationValidationException;
use App\Exceptions\Domain\DuplicateTransactionException;
use App\Exceptions\Domain\InsufficientStockException;
use App\Exceptions\Domain\TransactionBlockedException;
use App\Models\Customer;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\CurrencyPositionService;
use App\Services\Accounting\TransactionAccountingService;
use App\Services\Audit\AuditTrailHelper;
use App\Services\Branch\TellerAllocationService;
use App\Services\Branch\TillBalanceManager;
use App\Services\Contracts\TransactionCreationServiceInterface;
use App\Services\Contracts\TransactionIdempotencyServiceInterface;
use App\Services\Contracts\TransactionValidationInterface;
use App\Services\System\CacheTagsService;
use App\Services\System\MathService;
use App\Services\ThresholdService;
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
        protected TransactionValidationInterface $validationService,
        protected MathService $mathService,
        protected ThresholdService $thresholdService,
        protected TellerAllocationService $tellerAllocationService,
    ) {}

    public function prepareAndCreate(array $data, ?int $userId = null, ?string $ipAddress = null): Transaction
    {
        $userId ??= auth()->id();
        $user = User::findOrFail($userId);
        $ipAddress ??= request()?->ip();

        $this->validationService->validateCurrency($data['currency_code']);
        $this->validationService->validateIpAddress($ipAddress);

        $tillBalance = $this->validationService->validateTillBalance($data['till_id'], $data['currency_code']);
        $customer = Customer::findOrFail($data['customer_id']);

        $amountLocal = $this->mathService->multiply(
            (string) $data['amount_foreign'],
            (string) $data['rate']
        );

        $this->validationService->validatePepRequirements($customer, $data);

        $validationResult = $this->validationService->preValidate($customer, $amountLocal, $data['currency_code']);

        if ($validationResult->isBlocked()) {
            throw new TransactionBlockedException($validationResult->getBlocks()[0]['message']);
        }

        $allocation = $this->determineTellerAllocation($user, $data, $amountLocal);
        $status = $this->determineInitialStatus($amountLocal, $validationResult->isHoldRequired());

        $context = new TransactionCreationContext(
            data: $data,
            customer: $customer,
            tillBalance: $tillBalance,
            cddLevel: $validationResult->getCDDLevel(),
            holdRequired: $validationResult->isHoldRequired(),
            status: $status,
            amountLocal: $amountLocal,
            user: $user,
            allocation: $allocation,
            holdReason: $validationResult->isHoldRequired() ? 'Compliance hold' : null,
        );

        return $this->create($context, $user->id, $ipAddress);
    }

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
            $this->acquirePositionLock($data, $context->tillBalance);

            $transaction = $this->createTransactionRecord($data, $context);

            $this->reserveStockIfPending($transaction, $data);

            if ($transaction->status === TransactionStatus::Completed) {
                $this->applyCompletedSideEffects($transaction, $context, $ipAddress);
            }

            $this->recordCreationAudit($transaction, $user, $ipAddress);
            $this->dispatchCreationEvent($transaction);

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

    private function acquirePositionLock(array $data, TillBalance $tillBalance): void
    {
        if ($data['type'] !== TransactionType::Buy->value) {
            return;
        }

        $this->positionService->getPositionWithLock(
            $data['currency_code'],
            (string) $tillBalance->branch_id
        );
    }

    private function reserveStockIfPending(Transaction $transaction, array $data): void
    {
        if ($transaction->status !== TransactionStatus::PendingApproval) {
            return;
        }

        if ($data['type'] !== TransactionType::Sell->value) {
            return;
        }

        $this->positionService->reserveStock($transaction);
    }

    private function recordCreationAudit(Transaction $transaction, User $user, ?string $ipAddress): void
    {
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
    }

    private function dispatchCreationEvent(Transaction $transaction): void
    {
        DB::afterCommit(function () use ($transaction) {
            Event::dispatch(new TransactionCreated($transaction));
            $this->cacheTagsService->invalidate('dashboard');
        });
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

        $this->tellerAllocationService->applyTransactionAllocation($transaction, $context->allocation);

        $this->createAccountingEntries($transaction, $ipAddress, $context->user);
    }

    /**
     * Determine the teller allocation to attach to a new transaction.
     *
     * @param  User  $user  The authenticated user creating the transaction.
     * @param  array{type: string, currency_code: string}  $data  Validated transaction data.
     * @param  string  $amountLocal  Local currency amount as a numeric string.
     * @return TellerAllocation|null The active teller allocation, or null for non-tellers.
     *
     * @throws AllocationValidationException When the active allocation cannot cover the transaction.
     */
    private function determineTellerAllocation(User $user, array $data, string $amountLocal): ?Model
    {
        if (! $user->isTeller()) {
            return null;
        }

        if ($data['type'] === TransactionType::Buy->value) {
            $result = $this->tellerAllocationService->validateTransaction($user, $data['currency_code'], $amountLocal, true);

            if (! $result->valid) {
                throw new AllocationValidationException($result->reason);
            }

            /** @var TellerAllocation|null $allocation */
            $allocation = $result->allocation;

            return $allocation;
        }

        return $this->tellerAllocationService->getActiveAllocation($user, $data['currency_code']);
    }

    /**
     * Decide whether a transaction should start as Completed or PendingApproval.
     *
     * @param  string  $amountLocal  Local currency amount as a numeric string.
     * @param  bool  $holdRequired  Whether a compliance hold is required.
     */
    private function determineInitialStatus(string $amountLocal, bool $holdRequired): TransactionStatus
    {
        if ($holdRequired || $this->mathService->compare($amountLocal, $this->thresholdService->getAutoApproveThreshold()) >= 0) {
            return TransactionStatus::PendingApproval;
        }

        return TransactionStatus::Completed;
    }

    private function updateTillBalance(TillBalance $tillBalance, string $type, string $amountLocal, string $amountForeign): void
    {
        $this->tillBalanceManager->applyTransaction(
            $tillBalance,
            TransactionType::from($type),
            $amountLocal,
            $amountForeign
        );
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
