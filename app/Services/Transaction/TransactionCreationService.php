<?php

namespace App\Services\Transaction;

use App\Enums\CddLevel;
use App\Enums\RateSide;
use App\Enums\StockReservationStatus;
use App\Enums\TransactionConfirmationStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Events\TransactionCreated;
use App\Exceptions\Domain\BusinessDateFrozenException;
use App\Exceptions\Domain\CustomerBlockedException;
use App\Exceptions\Domain\DuplicateTransactionException;
use App\Exceptions\Domain\InsufficientStockException;
use App\Exceptions\Domain\KycExpiredException;
use App\Exceptions\Domain\PermissionDeniedException;
use App\Exceptions\Domain\PositionLimitExceededException;
use App\Exceptions\Domain\TransactionBlockedException;
use App\Exceptions\Domain\TransactionValidationException;
use App\Models\Branch;
use App\Models\BranchClosureWorkflow;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\Customer;
use App\Models\StockReservation;
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
use App\Services\DTOs\PreValidationResult;
use App\Services\System\CacheInvalidationService;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use App\Services\Traits\AccountingEntriesTrait;
use App\Services\Traits\ExchangeCalculatorTrait;
use App\Services\Traits\TillBalanceTrait;
use App\Services\Transaction\DTOs\TransactionCreationContext;
use App\Support\ActorContext;
use App\ValueObjects\QuoteConvention;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class TransactionCreationService
{
    use AccountingEntriesTrait, ExchangeCalculatorTrait, TillBalanceTrait;

    public function __construct(
        protected TransactionIdempotencyService $idempotencyService,
        protected CurrencyPositionService $positionService,
        protected TransactionAccountingService $transactionAccountingService,
        protected AuditTrailHelper $auditTrailHelper,
        protected TillBalanceManager $tillBalanceManager,
        protected CacheInvalidationService $cacheInvalidationService,
        protected TransactionValidationService $validationService,
        protected MathService $mathService,
        protected ThresholdService $thresholdService,
        protected TellerAllocationService $tellerAllocationService,
        protected TransactionErrorHandler $errorHandler,
        protected TransactionRecoveryService $recoveryService,
        protected KycDocumentExpiryService $kycDocumentExpiryService,
        protected RateManagementService $rateManagementService,
        protected InitialStatusResolver $statusResolver,
        protected ExchangeCalculator $exchangeCalculator,
        protected AlertTriageService $alertTriageService,
    ) {}

    protected function auditTrailHelper(): AuditTrailHelper
    {
        return $this->auditTrailHelper;
    }

    protected function transactionAccountingService(): TransactionAccountingService
    {
        return $this->transactionAccountingService;
    }

    public function prepareAndCreate(array $data, ?int $userId = null, ?string $ipAddress = null): Transaction
    {
        $userId ??= ActorContext::capture()->userId;
        $user = User::findOrFail($userId);
        $ipAddress ??= ActorContext::capture()->ipAddress;

        return $this->create($this->buildCreationContext($user, $data), $user->id, $ipAddress);
    }

    /**
     * Assemble the full creation context for one booking — eligibility gate,
     * exchange calculation, compliance gates, teller allocation and initial
     * status. This is the single orchestration every creation path must use
     * (web form, API; the wizard passes its session CDD floor via $cddFloor)
     * so a gate added here can never be bypassed by a hand-rolled caller.
     *
     * @param  array<string, mixed>  $data  Unit-quoted rate payload.
     * @param  CddLevel|null  $cddFloor  Never downgrade due diligence below this tier (e.g. the tier documents were collected under mid-wizard).
     * @param  bool  $withAllocation  CSV imports settle straight to the booked
     *                                till and never consume a teller allocation — pass false there.
     */
    public function buildCreationContext(User $user, array $data, ?CddLevel $cddFloor = null, ?string $ipAddress = null, bool $withAllocation = true): TransactionCreationContext
    {
        [$tillBalance, $customer] = $this->assertBookingEligibility($user, $data, $ipAddress);

        $exchangeResult = $this->resolveExchangeCalculator()->calculate(
            TransactionType::from((string) $data['type']),
            (string) $data['currency_code'],
            (string) $data['quantity'],
            (string) $data['rate'],
            $user->branch_id,
            QuoteConvention::forCode((string) $data['currency_code'])
        );
        $amountMyr = $exchangeResult['amount_myr'];

        // Submitted rates are unit-quoted (per currencies.rate_unit foreign
        // units); transactions store the normalized per-unit rate so the
        // quantity × rate = amount_myr ledger invariant stays exact. The
        // submitted payload stays untouched — the normalized rate travels
        // on the context instead of mutating $data in place.
        $normalizedRate = $exchangeResult['rate'];

        $validationResult = $this->runComplianceGates($customer, $data, $amountMyr);

        // Never downgrade due diligence: the effective CDD level is the
        // higher of the fresh assessment and any caller-supplied floor.
        $freshCddLevel = $validationResult->getCDDLevel();
        $cddLevel = $cddFloor !== null
            && array_search($cddFloor, CddLevel::cases(), true) > array_search($freshCddLevel, CddLevel::cases(), true)
            ? $cddFloor
            : $freshCddLevel;

        $allocation = $withAllocation
            ? $this->tellerAllocationService->resolveForTransaction(
                $user,
                [
                    'type' => (string) $data['type'],
                    'currency_code' => (string) $data['currency_code'],
                ],
                $amountMyr
            )
            : null;
        $initialStatus = $this->statusResolver->resolve(
            $amountMyr,
            $validationResult->isHoldRequired(),
            $customer->risk_rating
        );

        return new TransactionCreationContext(
            data: $data,
            customer: $customer,
            tillBalance: $tillBalance,
            cddLevel: $cddLevel,
            holdRequired: $validationResult->isHoldRequired(),
            status: $initialStatus->status,
            amountMyr: $amountMyr,
            user: $user,
            allocation: $allocation,
            // hold_reason doubles as the compliance-clear gate in
            // TransactionApprovalService — only genuine compliance holds may
            // populate it; threshold/risk-driven approvals stay approvable.
            holdReason: $validationResult->isHoldRequired() ? $initialStatus->holdReason : null,
            normalizedRate: $normalizedRate,
        );
    }

    /**
     * Shared booking gate — the single eligibility check every creation path
     * (web form, wizard, API, CSV import) must pass before a transaction
     * record exists. Import calls this per row with the importing user so the
     * CSV path can never bypass customer-state, KYC, PEP-adjacent branch, or
     * rate-tolerance rules.
     *
     * @param  array<string, mixed>  $data  Row/request payload (unit-quoted rate).
     * @return array{0: ?TillBalance, 1: Customer} locked till row (null when booking drawer-less) and validated customer
     */
    public function assertBookingEligibility(User $user, array $data, ?string $ipAddress = null): array
    {
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

        // The drawer is optional: bookings without a till keep custody at the
        // teller allocation and skip till balances entirely. A supplied till
        // still goes through the full open-balance check.
        $tillBalance = filled($data['till_id'] ?? null)
            ? $this->validationService->validateTillBalance($data['till_id'], $data['currency_code'], $user)
            : null;
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

        // Rate tolerance guard: the submitted rate must sit within the
        // configured deviation of the current market rate. Scoped to the
        // user's branch; branch-less users (admin/HQ importers) scope to the
        // till's branch when one was booked. Skipped when no market rate is
        // configured.
        $rateCheck = $this->rateManagementService->validateTransactionRate(
            (string) $data['rate'],
            (string) $data['currency_code'],
            RateSide::fromTransactionType(TransactionType::from((string) $data['type'])),
            $user->branch_id ?? $tillBalance?->branch_id,
            $user->role
        );

        if (! ($rateCheck['valid'] ?? true)) {
            throw new TransactionValidationException(
                field: 'rate',
                message: $rateCheck['reason'] ?? 'Rate deviation exceeds the maximum allowed'
            );
        }

        return [$tillBalance, $customer];
    }

    /**
     * PEP requirements + sanctions/CDD/risk/hold pre-validation — the second
     * half of the booking gate, run after the amount is converted to MYR.
     *
     * @param  array<string, mixed>  $data
     */
    public function runComplianceGates(Customer $customer, array $data, string $amountMyr): PreValidationResult
    {
        $this->validationService->validatePepRequirements($customer, $data);

        $validationResult = $this->validationService->preValidate($customer, $amountMyr, $data['currency_code']);

        if ($validationResult->isBlocked()) {
            throw new TransactionBlockedException($validationResult->getBlocks()[0]['message']);
        }

        return $validationResult;
    }

    public function create(TransactionCreationContext $context, ?int $userId = null, ?string $ipAddress = null): Transaction
    {
        $data = $context->data;
        $user = $context->user;
        $userId ??= $user->id;
        $ipAddress ??= ActorContext::capture()->ipAddress;

        $this->assertCustomerCddRequirements($context->customer, $context->cddLevel, $data);

        // The booking branch scopes the day-close freeze, position lock, and
        // stock checks: the booked till's branch when a drawer is used, else
        // the acting user's branch (drawer-less custody ends at the
        // allocation).
        $txnBranchId = (int) ($context->tillBalance->branch_id ?? $context->user->branch_id ?? $data['branch_id'] ?? 0);

        // Phase 1: validate, persist the transaction record and commit it. The
        // record must survive booking failures so the transaction can be marked
        // Failed and retried (or parked in the DLQ) instead of disappearing.
        $transaction = DB::transaction(function () use ($context, $data, $userId, $txnBranchId) {
            // A finalized day close freezes the branch's books for that
            // business date — no new transactions can be booked on it.
            // Checked inside the transaction under the workflow-row lock so
            // a racing finalize serializes against the booking.
            $today = now()->toDateString();

            if (BranchClosureWorkflow::freezesDateForUpdate($txnBranchId, $today)) {
                $branchCode = Branch::whereKey($txnBranchId)->value('code') ?? (string) $txnBranchId;

                throw new BusinessDateFrozenException($branchCode, $today);
            }

            // Acquire position lock FIRST for both Buy and Sell to prevent race conditions
            // This ensures stock check, idempotency check, and transaction creation happen atomically
            $lockedPosition = $this->acquirePositionLock($data, $txnBranchId);

            // BNM position limit: reject Buys that would push the branch
            // position above the configured ceiling (checked under the lock;
            // Sells reduce the position so cannot breach a maximum).
            $this->assertPositionLimit($lockedPosition, $data);

            $this->ensureStockForSell($data, $txnBranchId, $lockedPosition);

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

            try {
                // Savepoint: a concurrent request bearing the same idempotency
                // key loses the insert on the global unique index — rolling
                // back to the savepoint keeps the outer transaction usable on
                // drivers that poison it on constraint errors (PostgreSQL).
                $transaction = DB::transaction(fn () => $this->createTransactionRecord($data, $context));
            } catch (QueryException $e) {
                $replayed = $this->resolveIdempotentReplay($e, $data, $userId);

                if ($replayed !== null) {
                    return $replayed;
                }

                throw $e;
            }

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
                DB::transaction(function () use ($transaction, $context, $ipAddress, $txnBranchId) {
                    $lockedPosition = $this->acquirePositionLock($context->data, $txnBranchId);
                    $this->ensureStockForSell($context->data, $txnBranchId, $lockedPosition);
                    $this->applyCompletedSideEffects($transaction, $context, $ipAddress, $txnBranchId);
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
    /**
     * Enforce BNM field requirements for the CDD tier the transaction amount
     * falls into (pd-00.md 14C.12/14C.10/14C.13). name/id_type/id_number/
     * date_of_birth/nationality are schema-required, so only the nullable
     * profile fields are checked here:
     *
     * - Simplified (14A.10.3): residential/mailing address
     * - Specific (14A.11.1, RM3,000–10,000): address + purpose of transaction
     *   (purpose is already a required transaction field)
     * - Standard (14A.9.1, >= RM10,000): + contact number, occupation type,
     *   employer name / nature of business
     * - Enhanced (14A.12.1, risk-based): Standard set + source of wealth or
     *   source of funds; PEPs must provide BOTH, so source_of_wealth is
     *   enforced for PEP customers (source_of_funds is already required)
     *
     * Identity fields (name, ID, DOB, nationality) are required at
     * registration for every tier and enforced by the form request.
     *
     * Runs for every booking path (web, wizard, API, import) after the CDD
     * level is determined, before any transaction record exists.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertCustomerCddRequirements(Customer $customer, CddLevel $cddLevel, array $data): void
    {
        $required = match ($cddLevel) {
            CddLevel::Simplified, CddLevel::Specific => ['address'],
            CddLevel::Standard, CddLevel::Enhanced => ['address', 'phone', 'occupation', 'employer_name'],
        };

        $messages = [];
        foreach ($required as $field) {
            // address/phone are stored encrypted; raw-attribute presence is
            // enough to prove the data was captured at registration.
            if (empty($customer->getRawOriginal($field))) {
                $messages[$field] = "Customer {$field} is required for {$cddLevel->value} CDD.";
            }
        }

        // 14C.13.1(c): PEPs must provide BOTH source of funds (already a
        // required field) and source of wealth; for other Enhanced triggers
        // either source satisfies the requirement.
        if ($cddLevel === CddLevel::Enhanced && $customer->pep_status && blank($data['source_of_wealth'] ?? null)) {
            $messages['source_of_wealth'] = 'Source of wealth is required for PEP customers.';
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }

    private function escalateLargeTransactionToCompliance(Transaction $transaction): void
    {
        try {
            if ($transaction->status !== TransactionStatus::PendingApproval) {
                return;
            }

            if ($this->mathService->compare($transaction->amount_myr, $this->thresholdService->getLargeTransactionThreshold()) < 0) {
                return;
            }

            $confirmation = TransactionConfirmation::create([
                'transaction_id' => $transaction->id,
                'user_id' => ActorContext::capture()->userId,
                'status' => TransactionConfirmationStatus::Pending->value,
                'expires_at' => now()->addMinutes((int) config('transactions.confirmation_ttl_minutes', 30)),
            ]);

            $this->alertTriageService->notifyAvailableOfficers(
                new LargeTransactionNotification($transaction, $confirmation),
                ActorContext::capture()->userId
            );
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

    private function ensureStockForSell(array $data, int $branchId, ?CurrencyPosition $lockedPosition = null): void
    {
        if ($data['type'] !== TransactionType::Sell->value) {
            return;
        }

        // Use the already-locked position if provided, otherwise fall back to getAvailableBalance
        if ($lockedPosition) {
            $quantity = $lockedPosition->quantity ?? '0';

            // Reservations protect the branch-level position — sum all pending
            // reservations for the branch (matching getAvailableBalance()).
            $reserved = StockReservation::where('currency_code', $data['currency_code'])
                ->where('branch_id', (string) $branchId)
                ->where('status', StockReservationStatus::Pending->value)
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->sum('quantity');

            $availableBalance = $this->mathService->subtract($quantity, (string) $reserved);
        } else {
            // Always scope to the canonical transaction branch — a
            // caller-supplied branch_id must never steer the stock check
            // away from the branch the booking will post against.
            $availableBalance = $this->positionService->getAvailableBalance(
                $data['currency_code'],
                (string) $branchId
            );
        }

        if (bccomp($availableBalance, $data['quantity'], 4) < 0) {
            throw new InsufficientStockException(
                $data['currency_code'],
                $data['quantity'],
                $availableBalance
            );
        }
    }

    private function acquirePositionLock(array $data, int $branchId): ?CurrencyPosition
    {
        // Lock position for both Buy and Sell to prevent race conditions
        // on stock availability checks and position updates
        return $this->positionService->getPositionWithLock(
            $data['currency_code'],
            (string) $branchId
        );
    }

    /**
     * BNM position ceiling: a Buy that would take the branch position above
     * thresholds.position_limits.<currency> is rejected under the lock.
     */
    private function assertPositionLimit(?CurrencyPosition $position, array $data): void
    {
        if ($position === null) {
            return;
        }

        if (($data['type'] ?? null) !== TransactionType::Buy->value) {
            return; // Sells only reduce the position.
        }

        $limit = $this->thresholdService->getPositionLimit((string) $data['currency_code']);

        if ($limit === null || ! is_numeric($limit)) {
            return;
        }

        // CurrencyPosition carries the foreign-currency quantity; total_quantity
        // belongs to TillBalance, not positions.
        $projected = $this->mathService->add(
            (string) ($position->quantity ?? '0'),
            (string) $data['quantity']
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
                    'amount_myr' => $transaction->amount_myr,
                    'quantity' => $transaction->quantity,
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
            // Drawer-less bookings scope to the acting user's branch.
            'branch_id' => $context->tillBalance->branch_id ?? $context->user->branch_id,
            // Store the booked till balance's code — callers may submit an
            // id or code, but the transaction always records the code. Null
            // when no drawer was used.
            'till_id' => $context->tillBalance?->till_id,
            // counter_id is the relational FK — always derived from the
            // booked till so it can never diverge from till_id regardless
            // of what a caller submitted (web, API, wizard, import).
            'counter_id' => $context->tillBalance !== null
                ? Counter::findByCodeOrId($context->tillBalance->till_id)?->id
                : null,
            'type' => $data['type'],
            'currency_code' => $data['currency_code'],
            'quantity' => $data['quantity'],
            'amount_myr' => $context->amountMyr,
            'rate' => $context->normalizedRate ?? $data['rate'],
            'purpose' => $data['purpose'],
            'source_of_funds' => $data['source_of_funds'],
            'source_of_wealth' => $data['source_of_wealth'] ?? null,
        ]);

        $transaction->cdd_level = $context->cddLevel;
        $transaction->idempotency_key = $data['idempotency_key'] ?? null;
        $transaction->teller_allocation_id = $context->allocation?->id;
        $transaction->status = $context->status;
        $transaction->hold_reason = $context->holdReason;
        $transaction->approved_by = null;
        $transaction->version = 0;
        $transaction->save();

        return $transaction->refresh();
    }

    /**
     * Translate a lost idempotency-key insert race into a replay of the
     * winning transaction. Returns null (rethrow) when the failure is not a
     * unique violation or no keyed row materialized.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveIdempotentReplay(QueryException $e, array $data, int $userId): ?Transaction
    {
        if (empty($data['idempotency_key'])) {
            return null;
        }

        $sqlState = (string) $e->getCode();
        if ($sqlState !== '23000' && $sqlState !== '23505') {
            return null;
        }

        return $this->idempotencyService->findDuplicate($data['idempotency_key'], $userId, $data);
    }

    private function applyCompletedSideEffects(Transaction $transaction, TransactionCreationContext $context, ?string $ipAddress, int $txnBranchId): void
    {
        $data = $context->data;

        $this->positionService->updatePosition(
            $data['currency_code'],
            $data['quantity'],
            $context->normalizedRate ?? $data['rate'],
            $data['type'],
            (string) $txnBranchId,
            $transaction
        );

        // Drawer-less bookings carry no till row — custody stays at the
        // teller allocation, which is applied below.
        if ($context->tillBalance !== null) {
            $this->tillBalanceManager->applyTransaction(
                $context->tillBalance,
                TransactionType::from($data['type']),
                $context->amountMyr,
                $data['quantity']
            );
        }

        $this->tellerAllocationService->applyTransactionAllocation($transaction, $context->allocation);

        $this->createAccountingEntries($transaction, $ipAddress, $context->user);
    }
}
