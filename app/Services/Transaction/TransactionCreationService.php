<?php

namespace App\Services\Transaction;

use App\Enums\CddLevel;
use App\Enums\RiskRating;
use App\Enums\StockReservationStatus;
use App\Enums\TransactionConfirmationStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Events\TransactionCreated;
use App\Exceptions\Domain\AllocationValidationException;
use App\Exceptions\Domain\CustomerBlockedException;
use App\Exceptions\Domain\DuplicateTransactionException;
use App\Exceptions\Domain\InsufficientStockException;
use App\Exceptions\Domain\KycExpiredException;
use App\Exceptions\Domain\PermissionDeniedException;
use App\Exceptions\Domain\PositionLimitExceededException;
use App\Exceptions\Domain\TransactionBlockedException;
use App\Exceptions\Domain\TransactionValidationException;
use App\Models\Branch;
use App\Models\CurrencyPosition;
use App\Models\Customer;
use App\Models\StockReservation;
use App\Models\TellerAllocation;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Models\TransactionConfirmation;
use App\Models\User;
use App\Notifications\LargeTransactionNotification;
use App\Services\Accounting\CurrencyPositionService;
use App\Services\Accounting\TransactionAccountingService;
use App\Services\Audit\AuditTrailHelper;
use App\Services\Branch\TellerAllocationService;
use App\Services\Branch\TillBalanceManager;
use App\Services\Compliance\AlertTriageService;
use App\Services\Compliance\KycDocumentExpiryService;
use App\Services\Contracts\RateManagementServiceInterface;
use App\Services\Contracts\TransactionCreationServiceInterface;
use App\Services\Contracts\TransactionIdempotencyServiceInterface;
use App\Services\Contracts\TransactionValidationInterface;
use App\Services\System\CacheInvalidationService;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use App\Services\Traits\AccountingEntriesTrait;
use App\Services\Traits\ExchangeCalculatorTrait;
use App\Services\Traits\TillBalanceTrait;
use App\Services\Transaction\DTOs\TransactionCreationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Throwable;

class TransactionCreationService implements TransactionCreationServiceInterface
{
    use AccountingEntriesTrait, ExchangeCalculatorTrait, TillBalanceTrait;

    public function __construct(
        protected TransactionIdempotencyServiceInterface $idempotencyService,
        protected CurrencyPositionService $positionService,
        protected TransactionAccountingService $transactionAccountingService,
        protected AuditTrailHelper $auditTrailHelper,
        protected TillBalanceManager $tillBalanceManager,
        protected CacheInvalidationService $cacheInvalidationService,
        protected TransactionValidationInterface $validationService,
        protected MathService $mathService,
        protected ThresholdService $thresholdService,
        protected TellerAllocationService $tellerAllocationService,
        protected TransactionErrorHandler $errorHandler,
        protected TransactionRecoveryService $recoveryService,
        protected KycDocumentExpiryService $kycDocumentExpiryService,
        protected RateManagementServiceInterface $rateManagementService,
        protected ?ExchangeCalculator $exchangeCalculator = null,
    ) {}

    public function prepareAndCreate(array $data, ?int $userId = null, ?string $ipAddress = null): Transaction
    {
        $userId ??= auth()->id();
        $user = User::findOrFail($userId);
        $ipAddress ??= request()?->ip();

        $this->validationService->validateCurrency($data['currency_code']);
        $this->validationService->validateIpAddress($ipAddress);

        // Head-office branches are non-trading: they manage an MYR expense
        // float only and must never book exchange transactions.
        $branch = $user->branch;
        if ($branch instanceof Branch && ! $branch->canTrade()) {
            throw new TransactionValidationException(
                field: 'branch_id',
                message: 'Head office branches cannot process transactions'
            );
        }

        $tillBalance = $this->validationService->validateTillBalance($data['till_id'], $data['currency_code']);
        /** @var Customer $customer */
        $customer = Customer::findOrFail($data['customer_id']);

        // Frozen, blocked, or deactivated (closed / sanction-hit) customers
        // cannot book new transactions (BNM freeze-order enforcement).
        if ($customer->transactions_blocked || $customer->is_frozen || ! $customer->is_active) {
            throw new CustomerBlockedException(
                (int) $customer->id,
                (string) ($customer->freeze_reason ?? $customer->closure_reason ?? 'account blocked from transactions')
            );
        }

        // KYC document lifecycle enforcement (BNM): customers whose identity
        // documents have all expired past the grace period cannot book new
        // transactions. Customers without documents are unaffected.
        if ($this->kycDocumentExpiryService->hasAllIdentityDocumentsExpired($customer)) {
            throw new KycExpiredException((int) $customer->id);
        }

        // Branch isolation: fail closed when the user's branch has no
        // relationship with this customer (same rule as CustomerPolicy::view).
        $this->ensureCustomerIsWithinUserBranch($customer, $user);

        // Rate tolerance guard: the teller-entered rate must sit within the
        // configured deviation of the current market rate, mirroring the
        // bulk-import check so manual bookings cannot bypass it. Skipped
        // when no market rate is configured for the currency.
        $rateCheck = $this->rateManagementService->validateTransactionRate(
            (string) $data['rate'],
            (string) $data['currency_code'],
            strtolower((string) $data['type']),
            $user->branch_id
        );

        if (! ($rateCheck['valid'] ?? true)) {
            throw new TransactionValidationException(
                field: 'rate',
                message: $rateCheck['reason'] ?? 'Rate deviation exceeds the maximum allowed'
            );
        }

        $amountLocal = $this->resolveExchangeCalculator()->calculate(
            TransactionType::from((string) $data['type']),
            (string) $data['currency_code'],
            (string) $data['amount_foreign'],
            (string) $data['rate'],
        )['amount_local'];

        $this->validationService->validatePepRequirements($customer, $data);

        $validationResult = $this->validationService->preValidate($customer, $amountLocal, $data['currency_code']);

        if ($validationResult->isBlocked()) {
            throw new TransactionBlockedException($validationResult->getBlocks()[0]['message']);
        }

        $allocation = $this->determineTellerAllocation(
            $user,
            [
                'type' => (string) $data['type'],
                'currency_code' => (string) $data['currency_code'],
            ],
            $amountLocal
        );
        $status = $this->determineInitialStatus($amountLocal, $validationResult->isHoldRequired(), $customer->risk_rating);

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

        // Phase 1: validate, persist the transaction record and commit it. The
        // record must survive booking failures so the transaction can be marked
        // Failed and retried (or parked in the DLQ) instead of disappearing.
        $transaction = DB::transaction(function () use ($context, $data, $userId) {
            // Acquire position lock FIRST for both Buy and Sell to prevent race conditions
            // This ensures stock check, idempotency check, and transaction creation happen atomically
            $lockedPosition = $this->acquirePositionLock($data, $context->tillBalance);

            // BNM position limit: reject Buys that would push the branch
            // position above the configured ceiling (checked under the lock;
            // Sells reduce the position so cannot breach a maximum).
            $this->assertPositionLimit($lockedPosition, $data);

            $this->ensureStockForSell($data, $context->tillBalance, $lockedPosition);

            $existingByIdempotencyKey = $this->idempotencyService->findDuplicate(
                $data['idempotency_key'] ?? null,
                $userId,
                $data
            );

            if ($existingByIdempotencyKey) {
                return $existingByIdempotencyKey;
            }

            // The 30-second heuristic window only guards interactive teller
            // double-clicks. Import rows carry deterministic idempotency keys
            // and are already deduplicated by findDuplicate above, so two
            // legitimately identical rows in one file must not be rejected.
            if (empty($data['idempotency_key'])) {
                $recentDuplicate = $this->idempotencyService->checkRecentDuplicate($userId, $data, 30);
                if ($recentDuplicate) {
                    throw new DuplicateTransactionException;
                }
            }

            $transaction = $this->createTransactionRecord($data, $context);

            $this->reserveStockIfPending($transaction, $data);

            return $transaction;
        });

        // Phase 2: book the side effects for transactions that complete
        // immediately. A booking failure (position/till/allocation/accounting)
        // marks the transaction Failed with an error record and dispatches the
        // retry job; the exception is rethrown so the caller still reports the
        // failure to the operator, but the record now exists for recovery.
        if ($transaction->status === TransactionStatus::Completed) {
            try {
                // Phase 2 re-acquires the position lock and re-checks stock under
                // it: the phase-1 lock was released at commit, so without this a
                // concurrent Sell could pass the phase-1 availability check and
                // both transactions oversell against the same committed balance.
                DB::transaction(function () use ($transaction, $context, $ipAddress) {
                    $lockedPosition = $this->acquirePositionLock($context->data, $context->tillBalance);
                    $this->ensureStockForSell($context->data, $context->tillBalance, $lockedPosition);
                    $this->applyCompletedSideEffects($transaction, $context, $ipAddress);
                });
            } catch (Throwable $e) {
                $this->recordBookingFailure($transaction, $e, $context, $user, $ipAddress);

                throw $e;
            }
        }

        // The record is persisted either way, so the creation audit applies to
        // Failed transactions too. But a Failed transaction must not trigger the
        // TransactionCreated event: the listener runs AML monitoring and risk
        // scoring on a transaction that was never booked. The booking failure
        // path always rethrows, so reaching this point means the booking
        // succeeded and the event is always safe to dispatch.
        $this->recordCreationAudit($transaction, $user, $ipAddress);
        $this->dispatchCreationEvent($transaction);

        $this->escalateLargeTransactionToCompliance($transaction);

        return $transaction;
    }

    /**
     * BNM large-transaction oversight: bookings at or above the configured
     * cdd.large_transaction threshold are escalated to compliance officers so
     * the approval queue is not the only control watching high-value deals.
     *
     * The escalation targets PendingApproval deals - the transactions actually
     * awaiting manager confirmation. Already-Completed bookings were approved
     * through the normal flow and must not receive retroactive confirmation
     * emails; Failed bookings were never booked.
     */
    private function escalateLargeTransactionToCompliance(Transaction $transaction): void
    {
        try {
            if ($transaction->status !== TransactionStatus::PendingApproval) {
                return;
            }

            if ($this->mathService->compare($transaction->amount_local, $this->thresholdService->getLargeTransactionThreshold()) < 0) {
                return;
            }

            $confirmation = TransactionConfirmation::create([
                'transaction_id' => $transaction->id,
                'user_id' => auth()->id(),
                'status' => TransactionConfirmationStatus::Pending->value,
                'expires_at' => now()->addMinutes(30),
            ]);

            foreach (app(AlertTriageService::class)->getAvailableOfficers() as $officer) {
                if ($officer->id === auth()->id()) {
                    continue;
                }

                $officer->notify(new LargeTransactionNotification($transaction, $confirmation));
            }
        } catch (Throwable $e) {
            Log::warning('Failed to escalate large transaction to compliance', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Record a booking failure: persist the error, transition the transaction
     * to Failed, and dispatch the retry job through the recovery service so the
     * transaction is re-executed automatically instead of vanishing.
     */
    private function recordBookingFailure(
        Transaction $transaction,
        Throwable $e,
        TransactionCreationContext $context,
        User $user,
        ?string $ipAddress
    ): void {
        Log::error('Transaction booking failed; marking transaction failed', [
            'transaction_id' => $transaction->id,
            'exception' => $e->getMessage(),
        ]);

        try {
            $this->errorHandler->handleProcessingError(
                $transaction->refresh(),
                TransactionErrorHandler::ERROR_TYPE_ACCOUNTING,
                'Booking failed: '.$e->getMessage(),
                ['exception' => get_class($e), 'trace' => $e->getTraceAsString()]
            );

            $this->auditTrailHelper->recordTransaction($transaction->id, 'transaction_booking_failed', [
                'new' => [
                    'status' => TransactionStatus::Failed->value,
                    'error' => $e->getMessage(),
                    'customer_id' => $transaction->customer_id,
                    'branch_id' => $transaction->branch_id,
                ],
            ], $user, 'ERROR', $ipAddress);

            // Dispatch the retry after the failure audit is persisted so the
            // recovery job never observes an inconsistent audit trail.
            $this->recoveryService->attemptRecovery($transaction->refresh());
        } catch (Throwable $handlerError) {
            // Never mask the original booking failure with an error-handler failure.
            Log::error('Failed to record transaction booking error', [
                'transaction_id' => $transaction->id,
                'handler_error' => $handlerError->getMessage(),
            ]);
        }
    }

    /**
     * Create a transaction for import (no teller allocation, no request context).
     *
     * @param  array{type: string, currency_code: string, amount_foreign: string, rate: string, purpose: string, source_of_funds: string, source_of_wealth?: string, idempotency_key?: string, customer_id: int, till_id: string}  $data
     * @param  User  $user  The user performing the import
     */
    public function createForImport(
        array $data,
        Customer $customer,
        TillBalance $tillBalance,
        CddLevel $cddLevel,
        TransactionStatus $status,
        string $amountLocal,
        User $user,
        ?string $holdReason = null,
        ?string $ipAddress = null
    ): Transaction {
        $context = new TransactionCreationContext(
            data: $data,
            customer: $customer,
            tillBalance: $tillBalance,
            cddLevel: $cddLevel,
            holdRequired: $status === TransactionStatus::PendingApproval,
            status: $status,
            amountLocal: $amountLocal,
            user: $user,
            allocation: null, // No teller allocation for imports
            holdReason: $holdReason,
        );

        return $this->create($context, $user->id, $ipAddress);
    }

    /**
     * Gate transaction creation on branch assignment.
     *
     * Customers are company-wide, so the only requirement is that the user
     * belongs to a branch (or is admin). Users without a branch assignment
     * fail closed.
     *
     * @throws PermissionDeniedException When the user has no branch assignment.
     */
    private function ensureCustomerIsWithinUserBranch(Customer $customer, User $user): void
    {
        // Customers are company-wide: any branch-assigned staff member may
        // serve any customer. Users without a branch assignment fail closed.
        if ($user->role === UserRole::Admin) {
            return;
        }

        if ($user->branch_id === null) {
            throw new PermissionDeniedException('create transactions for this customer');
        }
    }

    private function ensureStockForSell(array $data, TillBalance $tillBalance, ?CurrencyPosition $lockedPosition = null): void
    {
        if ($data['type'] !== TransactionType::Sell->value) {
            return;
        }

        // Use the already-locked position if provided, otherwise fall back to getAvailableBalance
        if ($lockedPosition) {
            $quantity = $lockedPosition->quantity ?? '0';

            // Check reservations within the same till (reservations are scoped by till_id,
            // matching getAvailableBalance() which also filters reservations by till_id).
            $reserved = StockReservation::where('currency_code', $data['currency_code'])
                ->where('till_id', (string) $tillBalance->till_id)
                ->where('status', StockReservationStatus::Pending)
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->sum('amount_foreign');

            $availableBalance = $this->mathService->subtract($quantity, (string) $reserved);
        } else {
            $availableBalance = $this->positionService->getAvailableBalance(
                $data['currency_code'],
                isset($data['branch_id']) ? (string) $data['branch_id'] : (string) $tillBalance->branch_id,
                (string) $tillBalance->till_id
            );
        }

        if (bccomp($availableBalance, $data['amount_foreign'], 4) < 0) {
            throw new InsufficientStockException(
                $data['currency_code'],
                $data['amount_foreign'],
                $availableBalance
            );
        }
    }

    private function acquirePositionLock(array $data, TillBalance $tillBalance): ?CurrencyPosition
    {
        // Lock position for both Buy and Sell to prevent race conditions
        // on stock availability checks and position updates
        return $this->positionService->getPositionWithLock(
            $data['currency_code'],
            (string) $tillBalance->branch_id
        );
    }

    /**
     * BNM position ceiling: a Buy that would take the branch position above
     * config('cems.position_limits.<currency>') is rejected under the lock.
     */
    private function assertPositionLimit(?CurrencyPosition $position, array $data): void
    {
        if ($position === null) {
            return;
        }

        if (($data['type'] ?? null) !== TransactionType::Buy->value) {
            return; // Sells only reduce the position.
        }

        $limit = config('cems.position_limits.'.$data['currency_code']);

        if ($limit === null || ! is_numeric($limit)) {
            return;
        }

        // CurrencyPosition carries the foreign-currency quantity; foreign_total
        // belongs to TillBalance, not positions.
        $projected = $this->mathService->add(
            (string) ($position->quantity ?? '0'),
            (string) $data['amount_foreign']
        );

        if ($this->mathService->compare($projected, (string) $limit) > 0) {
            throw new PositionLimitExceededException(
                (string) $data['currency_code'],
                $projected,
                (string) $limit
            );
        }
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
            $this->cacheInvalidationService->invalidate('dashboard');
        });
    }

    private function createTransactionRecord(array $data, TransactionCreationContext $context): Transaction
    {
        $transaction = new Transaction([
            'customer_id' => $context->customer->id,
            'user_id' => $context->user->id,
            'branch_id' => $context->tillBalance->branch_id,
            'till_id' => $data['till_id'],
            'counter_id' => $data['counter_id'] ?? null,
            'type' => $data['type'],
            'currency_code' => $data['currency_code'],
            'amount_foreign' => $data['amount_foreign'],
            'amount_local' => $context->amountLocal,
            'rate' => $data['rate'],
            'purpose' => $data['purpose'],
            'source_of_funds' => $data['source_of_funds'],
            'source_of_wealth' => $data['source_of_wealth'] ?? null,
        ]);

        $transaction->cdd_level = $context->cddLevel;
        $transaction->idempotency_key = $data['idempotency_key'] ?? null;
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
            (string) $context->tillBalance->branch_id,
            $transaction
        );

        $this->tillBalanceManager->applyTransaction(
            $context->tillBalance,
            TransactionType::from($data['type']),
            $context->amountLocal,
            $data['amount_foreign']
        );

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
    private function determineTellerAllocation(User $user, array $data, string $amountLocal): ?TellerAllocation
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
     * A small transaction auto-completes unless the customer is High risk or a
     * compliance hold is required; anything at/above the auto-approve
     * threshold needs approval. Null risk fails closed to approval.
     *
     * @param  string  $amountLocal  Local currency amount as a numeric string.
     * @param  bool  $holdRequired  Whether a compliance hold is required.
     * @param  RiskRating|null  $riskRating  Customer risk rating; null fails closed to approval.
     */
    private function determineInitialStatus(string $amountLocal, bool $holdRequired, ?RiskRating $riskRating): TransactionStatus
    {
        if ($holdRequired
            || $riskRating === null
            || $riskRating === RiskRating::High
            || $this->mathService->compare($amountLocal, $this->thresholdService->getAutoApproveThreshold()) >= 0) {
            return TransactionStatus::PendingApproval;
        }

        return TransactionStatus::Completed;
    }
}
