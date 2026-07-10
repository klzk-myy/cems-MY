<?php

namespace App\Services\Transaction;

use App\Enums\CddLevel;
use App\Enums\RiskRating;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Events\TransactionApproved;
use App\Events\TransactionCreated;
use App\Exceptions\Domain\AllocationValidationException;
use App\Exceptions\Domain\DuplicateTransactionException;
use App\Exceptions\Domain\InsufficientStockException;
use App\Exceptions\Domain\StockReservationExpiredException;
use App\Exceptions\Domain\TillBalanceMissingException;
use App\Http\Traits\ValidatorMethods;
use App\Models\Counter;
use App\Models\Customer;
use App\Models\TellerAllocation;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\CurrencyPositionLockService;
use App\Services\Accounting\CurrencyPositionService;
use App\Services\Accounting\TransactionAccountingService;
use App\Services\AuditService;
use App\Services\Branch\TellerAllocationService;
use App\Services\Branch\TillBalanceManager;
use App\Services\Compliance\ComplianceService;
use App\Services\Compliance\HistoricalRiskAnalysisService;
use App\Services\Compliance\PepApprovalService;
use App\Services\Contracts\TransactionHoldServiceInterface;
use App\Services\Contracts\TransactionIdempotencyServiceInterface;
use App\Services\Contracts\TransactionServiceInterface;
use App\Services\Contracts\TransactionStatusServiceInterface;
use App\Services\Contracts\TransactionValidationInterface;
use App\Services\CustomerScreeningService;
use App\Services\DTOs\PreValidationResult;
use App\Services\DTOs\SanctionCheckResult;
use App\Services\System\CacheTagsService;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class TransactionService implements TransactionServiceInterface
{
    use ValidatorMethods;

    public function __construct(
        protected MathService $mathService,
        protected ComplianceService $complianceService,
        protected CurrencyPositionService $positionService,
        protected AccountingService $accountingService,
        protected AuditService $auditService,
        protected TransactionMonitoringService $monitoringService,
        protected TellerAllocationService $tellerAllocationService,
        protected CustomerScreeningService $screeningService,
        protected HistoricalRiskAnalysisService $historicalRiskAnalysisService,
        protected ThresholdService $thresholdService,
        protected CacheTagsService $cacheTagsService,
        protected TransactionAccountingService $transactionAccountingService,
        protected PepApprovalService $pepApprovalService,
        protected TransactionValidationInterface $validationService,
        protected TransactionHoldServiceInterface $holdService,
        protected TransactionIdempotencyServiceInterface $idempotencyService,
        protected TransactionStatusServiceInterface $statusService,
        protected CurrencyPositionLockService $positionLockService,
    ) {}

    /**
     * Run complete pre-transaction validation before creation.
     * Delegates to TransactionValidationService.
     */
    public function preValidate(Customer $customer, string $amount, string $currencyCode): PreValidationResult
    {
        return $this->validationService->preValidate($customer, $amount, $currencyCode);
    }

    /**
     * Check sanctions status for a customer.
     */
    private function checkSanctions(Customer $customer): SanctionCheckResult
    {
        $response = $this->screeningService->screenCustomer($customer);

        if ($response->action === 'block') {
            $matchScore = $response->confidenceScore;
            $matchedEntity = $response->matches->first()?->entryName;
            $message = $matchedEntity
                ? "Sanctions match found: {$matchedEntity} (confidence: {$matchScore}%)"
                : "Sanctions match found (confidence: {$matchScore}%)";

            return SanctionCheckResult::blocked($message, $matchScore, $matchedEntity ?? 'Unknown');
        }

        if ($response->action === 'flag') {
            $matchScore = $response->confidenceScore;
            $matchedEntity = $response->matches->first()?->entryName;
            $message = $matchedEntity
                ? "Sanctions flag: {$matchedEntity} (confidence: {$matchScore}%)"
                : "Sanctions flag (confidence: {$matchScore}%)";

            return new SanctionCheckResult(false, $message, $matchScore, $matchedEntity);
        }

        return SanctionCheckResult::passed();
    }

    /**
     * Check if customer has existing transactions.
     */
    private function isReturningCustomer(Customer $customer): bool
    {
        return $customer->transactions()->count() > 0;
    }

    /**
     * Determine if transaction requires a hold based on CDD level and risk flags.
     * Delegates to TransactionHoldService.
     *
     * @param  Customer  $customer  The customer for hold determination
     * @param  PreValidationResult  $result  Pre-validation result with CDD level and risk flags
     */
    private function determineHoldRequired(Customer $customer, PreValidationResult $result): bool
    {
        return $this->holdService->requiresHold(
            $result->getCDDLevel(),
            $customer,
            $result->getRiskFlags()
        );
    }

    /**
     * Create a new transaction with full validation and compliance checks.
     *
     * @param  array  $data  Validated transaction data
     * @param  int|null  $userId  User creating the transaction (null for API context)
     * @param  string|null  $ipAddress  IP address for audit logging
     *
     * @throws \Exception If transaction creation fails
     */
    public function createTransaction(array $data, ?int $userId = null, ?string $ipAddress = null): Transaction
    {
        $this->validationService->validateCurrency($data['currency_code']);

        $userId = $userId ?? auth()->id();
        $ipAddress = $ipAddress ?? request()->ip();

        $this->validationService->validateIpAddress($ipAddress);

        $tillBalance = $this->validationService->validateTillBalance($data['till_id'], $data['currency_code']);

        // Get customer and calculate amounts
        $customer = Customer::findOrFail($data['customer_id']);
        $amountForeign = (string) $data['amount_foreign'];
        $rate = (string) $data['rate'];
        $amountLocal = $this->mathService->multiply($amountForeign, $rate);

        $this->validationService->validatePepRequirements($customer, $data);

        // Validate against teller allocation (only for tellers, not manager/admin overrides)
        // Only validate for Buy transactions (teller sells foreign currency and needs allocation)
        // For Sell transactions, no allocation check is needed upfront
        $user = User::findOrFail($userId);
        $allocationForUpdate = null;
        if ($user->isTeller()) {
            $isBuy = ($data['type'] === TransactionType::Buy->value);
            if ($isBuy) {
                $validationResult = $this->tellerAllocationService->validateTransaction(
                    $user,
                    $data['currency_code'],
                    $amountLocal,
                    $isBuy
                );

                if (! $validationResult->valid) {
                    throw new AllocationValidationException($validationResult->reason);
                }

                $allocationForUpdate = $validationResult->allocation;
            } else {
                // For Sell transactions, get allocation for update after transaction completes
                $allocationForUpdate = $this->tellerAllocationService->getActiveAllocation(
                    $user,
                    $data['currency_code']
                );
            }
        }

        // Determine CDD level
        $cddLevel = $this->complianceService->determineCDDLevel($amountLocal, $customer);
        $holdCheck = $this->complianceService->requiresHold($amountLocal, $customer);

        // Build CDD triggers from the CDD level determination (already done by ComplianceService)
        // Note: ComplianceService::determineCDDLevel() already checked sanctions, PEP status, and amounts
        $cddTriggers = [];
        if ($cddLevel === CddLevel::Enhanced) {
            $triggers = [];
            if ($customer->pep_status) {
                $triggers[] = 'PEP customer';
            }
            if ($customer->sanction_hit) {
                $triggers[] = 'Sanctions match';
            }
            if ($this->mathService->compare($amountLocal, $this->thresholdService->getLargeTransactionThreshold()) >= 0) {
                $triggers[] = 'Large amount >= RM '.$this->thresholdService->getLargeTransactionThreshold();
            }
            if ($customer->risk_rating === RiskRating::High) {
                $triggers[] = 'High risk customer';
            }
            $cddTriggers = $triggers;
        } elseif ($cddLevel === CddLevel::Standard) {
            $cddTriggers[] = 'Standard amount >= RM '.$this->thresholdService->getStandardCddThreshold();
        }

        $this->auditService->logWithSeverity(
            'cdd_decision',
            [
                'user_id' => $userId,
                'entity_type' => 'Transaction',
                'new_values' => [
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->full_name,
                    'cdd_level' => $cddLevel->value,
                    'triggers' => $cddTriggers,
                    'amount_local' => $amountLocal,
                ],
            ],
            'INFO'
        );

        // Determine initial status
        $status = TransactionStatus::Completed;
        $holdReason = null;
        $approvedBy = null;

        if ($holdCheck->requiresHold || $this->mathService->compare($amountLocal, $this->thresholdService->getAutoApproveThreshold()) >= 0) {
            // BNM AML/CFT COMPLIANCE REQUIREMENT:
            // Transactions >= RM 10,000 (auto_approve threshold) require manager approval, regardless of compliance hold status.
            // This is a BNM regulatory requirement to ensure proper oversight of larger transactions.
            //
            // RATIONALE:
            // - Transactions < RM 10,000: Can be auto-approved (Completed status)
            // - Transactions >= RM 10,000: Require manager approval (PendingApproval status)
            // - Transactions >= RM 50,000 OR high-risk: Additional compliance hold (PendingApproval status)
            //
            // This dual-layer approval ensures:
            // 1. Manager oversight for all transactions at or above auto_approve threshold
            // 2. Compliance officer review for high-risk or large transactions
            // 3. Segregation of duties between tellers, managers, and compliance officers
            //
            // NOTE: CDD levels (Simplified/Specific/Standard/Enhanced) are separate from approval requirements.
            // CDD determines documentation requirements; approval requirement is based on transaction amount.
            $status = TransactionStatus::PendingApproval;
            if ($holdCheck->requiresHold) {
                $holdReason = implode(', ', $holdCheck->reasons);
            }
        }

        $transaction = DB::transaction(function () use ($data, $userId, $tillBalance, $amountForeign, $rate, $amountLocal, $cddLevel, $status, $holdReason, $approvedBy, &$allocationForUpdate) {
            // STEP 1: Check for duplicate transaction via idempotency key FIRST
            // This is checked BEFORE acquiring the position lock to avoid needless lock
            // contention when a request is a known duplicate. If the idempotency key
            // already exists, return immediately without acquiring any locks.
            $existingByIdempotencyKey = $this->idempotencyService->findDuplicate(
                $data['idempotency_key'] ?? null,
                $userId,
                $data
            );
            if ($existingByIdempotencyKey) {
                return $existingByIdempotencyKey;
            }

            // STEP 2: Check for recent similar transaction (potential double-submit) BEFORE lock
            // Early detection allows returning without acquiring position lock
            $recentDuplicate = $this->idempotencyService->checkRecentDuplicate($userId, $data, 30);
            if ($recentDuplicate) {
                $this->auditService->logWithSeverity(
                    'potential_duplicate_detected',
                    [
                        'user_id' => $userId,
                        'entity_type' => 'Transaction',
                        'entity_id' => $recentDuplicate->id,
                        'description' => "Similar transaction {$recentDuplicate->id} found within 30 seconds",
                    ],
                    'WARNING'
                );

                throw new DuplicateTransactionException;
            }

            // STEP 3: Acquire position lock (only for actual new transactions)
            // For Sell transactions, getAvailableBalance() locks the row and returns
            // '0' when no position exists, so a zero-balance row is not created.
            // For Buy transactions, lock() is used because the position will be written.
            if ($data['type'] === TransactionType::Sell->value) {
                $availableBalance = $this->positionService->getAvailableBalance(
                    $data['currency_code'],
                    $tillBalance->branch_id
                );

                if ($this->mathService->compare($availableBalance, $amountForeign) < 0) {
                    throw new InsufficientStockException(
                        $data['currency_code'],
                        $amountForeign,
                        $availableBalance
                    );
                }
            } elseif ($data['type'] === TransactionType::Buy->value) {
                $this->positionLockService->lock($tillBalance->branch_id, $data['currency_code']);
            }

            $transaction = Transaction::create([
                'customer_id' => $data['customer_id'],
                'user_id' => $userId,
                'branch_id' => $tillBalance->branch_id,
                'till_id' => $data['till_id'],
                'type' => $data['type'],
                'currency_code' => $data['currency_code'],
                'amount_foreign' => $amountForeign,
                'amount_local' => $amountLocal,
                'rate' => $rate,
                'purpose' => $data['purpose'],
                'source_of_funds' => $data['source_of_funds'],
                'source_of_wealth' => $data['source_of_wealth'] ?? null,
                'cdd_level' => $cddLevel,
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);

            $transaction->status = $status;
            $transaction->hold_reason = $holdReason;
            $transaction->approved_by = $approvedBy;
            $transaction->version = 0;
            $transaction->save();

            // If transaction requires approval (>= RM 3,000 and no compliance hold),
            // reserve stock immediately so it cannot be oversold
            // Only reserve stock for Sell transactions (Buy transactions add stock, not consume it)
            if ($status === TransactionStatus::PendingApproval && $data['type'] === TransactionType::Sell->value) {
                $this->positionService->reserveStock($transaction);
            }

            // If completed, update positions, till balance, and create accounting entries
            if ($status === TransactionStatus::Completed) {
                $this->positionService->updatePosition(
                    $data['currency_code'],
                    $amountForeign,
                    $rate,
                    $data['type'],
                    $tillBalance->branch_id
                );
                $this->updateTillBalance($tillBalance, $data['type'], $amountLocal, $amountForeign);

                // Update teller allocation if this was a teller transaction
                if ($allocationForUpdate) {
                    $isBuy = ($data['type'] === TransactionType::Buy->value);
                    // Re-fetch allocation with lock to prevent concurrent modification
                    $lockedAllocation = TellerAllocation::where('id', $allocationForUpdate->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($isBuy) {
                        $lockedAllocation->add($amountForeign);
                    } else {
                        $lockedAllocation->deduct($amountForeign);
                    }
                    $lockedAllocation->addDailyUsed($amountLocal);
                }

                $this->createAccountingEntries($transaction);
            }

            $this->auditService->logWithSeverity(
                'transaction_created',
                [
                    'user_id' => $userId,
                    'entity_type' => 'Transaction',
                    'entity_id' => $transaction->id,
                    'new_values' => [
                        'type' => $transaction->type,
                        'amount_local' => $transaction->amount_local,
                        'amount_foreign' => $transaction->amount_foreign,
                        'currency' => $transaction->currency_code,
                        'status' => $transaction->status,
                        'cdd_level' => $cddLevel,
                    ],
                ],
                'INFO'
            );

            // Dispatch event for async processing
            DB::afterCommit(fn () => Event::dispatch(new TransactionCreated($transaction)));

            return $transaction;
        });

        return $transaction;
    }

    /**
     * Update till balance after transaction.
     * Updates both foreign currency and MYR (local currency) balances.
     * Uses lockForUpdate to prevent race conditions on concurrent transactions.
     */
    protected function updateTillBalance(TillBalance $tillBalance, string $type, string $amountLocal, string $amountForeign): void
    {
        $manager = app(TillBalanceManager::class);

        $counter = Counter::where('code', $tillBalance->till_id)
            ->orWhere('id', $tillBalance->till_id)
            ->first();

        if (! $counter) {
            throw new TillBalanceMissingException($tillBalance->currency_code, $tillBalance->till_id);
        }

        $lockedForeign = $manager->currentBalance($counter, $tillBalance->currency_code, true);

        if (! $lockedForeign) {
            throw new TillBalanceMissingException($tillBalance->currency_code, $tillBalance->till_id);
        }

        $myrBalance = $manager->currentBalance($counter, 'MYR', true);

        if (! $myrBalance) {
            throw new TillBalanceMissingException('MYR', $tillBalance->till_id);
        }

        // Update foreign currency balance using separate buy/sell tracking
        // Buy: increase buy_total_foreign (we are buying foreign currency from customer, stock increases)
        // Sell: increase sell_total_foreign (we are selling foreign currency to customer, stock decreases)
        if ($type === TransactionType::Buy->value) {
            $manager->adjustBalance($lockedForeign, 'buy_total_foreign', $amountForeign, 'add', false);
            $manager->adjustBalance($lockedForeign, 'foreign_total', $amountForeign, 'add', false);
        } else {
            $manager->adjustBalance($lockedForeign, 'sell_total_foreign', $amountForeign, 'add', false);
            $manager->adjustBalance($lockedForeign, 'foreign_total', $amountForeign, 'subtract', false);
        }

        // Update MYR balance - cash in on Sell, cash out on Buy
        $myrOperation = $type === TransactionType::Buy->value ? 'subtract' : 'add';
        $manager->adjustBalance($myrBalance, 'transaction_total', $amountLocal, $myrOperation, false);
    }

    /**
     * Create accounting journal entries for transaction.
     * For Enhanced CDD transactions, defers creation until approval.
     */
    protected function createAccountingEntries(Transaction $transaction): void
    {
        // Check if Enhanced CDD and not yet approved (status is PendingApproval)
        if ($transaction->cdd_level === CddLevel::Enhanced
            && $transaction->status !== TransactionStatus::Completed) {
            Log::info('Deferring journal entry creation for Enhanced CDD transaction', [
                'transaction_id' => $transaction->id,
                'status' => $transaction->status->value,
                'cdd_level' => $transaction->cdd_level->value,
            ]);

            $this->auditService->logTransaction('journal_entries_deferred', $transaction->id, [
                'cdd_level' => $transaction->cdd_level->value,
                'status' => $transaction->status->value,
                'reason' => 'Enhanced CDD requires approval before bookkeeping',
            ]);

            return;
        }

        // Create entries immediately for Simplified/Standard CDD or approved Enhanced CDD
        $this->createImmediateAccountingEntries($transaction);
    }

    /**
     * Create deferred journal entries for Enhanced CDD transactions.
     * Called when transaction is approved.
     */
    public function createDeferredAccountingEntries(int $transactionId): void
    {
        $this->transactionAccountingService->createDeferredAccountingEntries($transactionId);
    }

    /**
     * Create accounting journal entries immediately.
     */
    protected function createImmediateAccountingEntries(Transaction $transaction): void
    {
        $this->transactionAccountingService->createImmediateAccountingEntries($transaction);
    }

    /**
     * Approve a pending transaction and complete its side effects.
     *
     * This method handles the full approval workflow for transactions that were
     * created with 'Pending' status (typically >= RM 50,000).
     *
     * @param  Transaction  $transaction  The pending transaction to approve
     * @param  int  $approverId  The user ID of the manager/admin approving
     * @param  string|null  $ipAddress  IP address for audit logging
     * @return array{success: bool, message: string, transaction?: Transaction}
     *
     * @throws \InvalidArgumentException If transaction is not pending
     * @throws \RuntimeException If transaction was already processed
     */
    public function approveTransaction(Transaction $transaction, int $approverId, ?string $ipAddress = null): array
    {
        $ipAddress = $ipAddress ?? request()->ip();

        $this->validationService->validateIpAddress($ipAddress);

        // Validate transaction is in pending approval status
        if ($transaction->status !== TransactionStatus::PendingApproval) {
            throw new \InvalidArgumentException(
                'Transaction is not pending approval. Current status: '.$transaction->status->label()
            );
        }

        // Re-run compliance monitoring before approval
        // If high-priority AML flags are generated, approval is blocked
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

            $this->auditService->logWithSeverity(
                'transaction_approval_blocked',
                [
                    'user_id' => $approverId,
                    'entity_type' => 'Transaction',
                    'entity_id' => $transaction->id,
                    'new_values' => [
                        'reason' => 'High-priority AML flags',
                        'flags' => $flagTypes,
                    ],
                ],
                'WARNING'
            );

            return [
                'success' => false,
                'message' => "Approval blocked: High-priority AML flags generated ({$flagTypes}). Transaction remains pending for compliance review.",
            ];
        }

        try {
            $result = DB::transaction(function () use ($transaction, $approverId, $amlResult) {
                // Pessimistic lock to prevent concurrent approvals, with optimistic version check
                // lockForUpdate() prevents other transactions from modifying this row concurrently.
                // After acquiring the lock, we verify the version hasn't changed since the caller
                // loaded the model, providing a clear error if the data is stale.
                $lockedTransaction = Transaction::where('id', $transaction->id)
                    ->where('status', TransactionStatus::PendingApproval)
                    ->lockForUpdate()
                    ->first();

                if (! $lockedTransaction) {
                    throw new \RuntimeException(
                        'Transaction was already processed or modified by another user.'
                    );
                }

                // Verify version match after acquiring lock (optimistic concurrency guard)
                // This detects stale data: if the in-memory model has a different version
                // than the locked DB row, the caller was operating on outdated data.
                if ((int) $lockedTransaction->version !== (int) $transaction->version) {
                    throw new \RuntimeException(
                        'Transaction was modified by another user since you loaded it. '.
                        'Please refresh the record and try again.'
                    );
                }

                // EDGE CASE VALIDATION: Verify customer still exists
                // Customer could have been deleted between transaction creation and approval
                $customer = Customer::find($lockedTransaction->customer_id);
                if (! $customer) {
                    throw new \RuntimeException(
                        'Customer has been deleted. Cannot approve transaction for non-existent customer.'
                    );
                }

                // EDGE CASE VALIDATION: Verify till is still open
                // Till could have been closed between transaction creation and approval
                $manager = app(TillBalanceManager::class);
                $counter = Counter::where('code', $lockedTransaction->till_id)
                    ->orWhere('id', $lockedTransaction->till_id)
                    ->first();

                $tillBalance = $counter
                    ? $manager->currentBalance($counter, $lockedTransaction->currency_code)
                    : null;

                if (! $tillBalance) {
                    throw new \RuntimeException(
                        'Till has been closed. Cannot approve transaction for closed till.'
                    );
                }

                // EDGE CASE VALIDATION: Verify position still exists (for Sell transactions)
                // Position could have been deleted between transaction creation and approval
                if ($lockedTransaction->type->isSell()) {
                    $position = $this->positionLockService->findForUpdate(
                        (string) $lockedTransaction->branch_id,
                        $lockedTransaction->currency_code
                    );

                    if (! $position) {
                        throw new \RuntimeException(
                            'Currency position has been deleted. Cannot approve Sell transaction without position.'
                        );
                    }
                }

                // Build proper transition history: record direct PendingApproval -> Completed transition
                $history = $lockedTransaction->transition_history ?? [];
                $nowIso = now()->toIso8601String();

                // Determine "from" state based on original status
                $fromState = $lockedTransaction->status->value;

                // Record actual state transition: PendingApproval -> Completed
                $history[] = [
                    'from' => $fromState,
                    'to' => TransactionStatus::Completed->value,
                    'reason' => 'Transaction approved and completed by manager',
                    'user_id' => $approverId,
                    'timestamp' => $nowIso,
                ];

                // Perform the update with proper history and version increment
                $lockedTransaction->status = TransactionStatus::Completed;
                $lockedTransaction->approved_by = $approverId;
                $lockedTransaction->approved_at = $nowIso;
                $lockedTransaction->transition_history = $history;
                $lockedTransaction->version = $lockedTransaction->version + 1;
                $lockedTransaction->save();

                // Refresh the model to get updated version
                $lockedTransaction->refresh();

                // Check available balance BEFORE consuming reservation (Sell only)
                // Buy transactions ADD foreign currency to the position — no existing stock required
                if ($lockedTransaction->type === TransactionType::Sell) {
                    $available = $this->positionService->getAvailableBalance(
                        $lockedTransaction->currency_code,
                        (string) $lockedTransaction->branch_id
                    );

                    if ($this->mathService->compare($available, (string) $lockedTransaction->amount_foreign) < 0) {
                        throw new InsufficientStockException(
                            $lockedTransaction->currency_code,
                            (string) $lockedTransaction->amount_foreign,
                            $available
                        );
                    }

                    // Consume the stock reservation for Sell transactions only
                    $reservation = $this->positionService->consumeStockReservation($lockedTransaction->id);

                    if (! $reservation) {
                        throw new StockReservationExpiredException($lockedTransaction->id);
                    }
                }

                // Execute position and till balance updates
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

                // Update teller allocation if this was a teller transaction
                $user = User::find($lockedTransaction->user_id);
                if ($user && $user->isTeller()) {
                    $allocationForUpdate = $this->tellerAllocationService->getActiveAllocation(
                        $user,
                        $lockedTransaction->currency_code
                    );
                    if ($allocationForUpdate) {
                        $allocationForUpdate = TellerAllocation::where('id', $allocationForUpdate->id)
                            ->lockForUpdate()
                            ->firstOrFail();

                        if ($lockedTransaction->type->isBuy()) {
                            $allocationForUpdate->add((string) $lockedTransaction->amount_foreign);
                        } else {
                            $allocationForUpdate->deduct((string) $lockedTransaction->amount_foreign);
                        }
                        $allocationForUpdate->addDailyUsed((string) $lockedTransaction->amount_local);
                    }
                }

                // Create double-entry accounting journal entries
                // For Enhanced CDD transactions, use deferred entry creation (approval triggers it)
                if ($lockedTransaction->cdd_level === CddLevel::Enhanced) {
                    $this->createDeferredAccountingEntries($lockedTransaction->id);
                } else {
                    $this->createAccountingEntries($lockedTransaction);
                }

                // Audit logging for the approval action
                $this->auditService->logTransaction('transaction_approved', $lockedTransaction->id, [
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
                ]);

                // Dispatch event for async compliance processing
                Event::dispatch(new TransactionApproved($lockedTransaction, $approverId));

                // Invalidate dashboard cache only after the transaction commits successfully.
                // This keeps the invalidation logically tied to the approval while avoiding
                // a scenario where the cache is cleared but the DB transaction later rolls back.
                DB::afterCommit(function () {
                    $this->cacheTagsService->invalidate('dashboard');
                });

                return [
                    'success' => true,
                    'message' => 'Transaction approved and completed successfully.',
                    'transaction' => $lockedTransaction->fresh(),
                ];
            });

            return $result;
        } catch (InsufficientStockException $e) {
            return [
                'success' => false,
                'message' => 'Insufficient stock: '.$e->getMessage(),
            ];
        } catch (StockReservationExpiredException $e) {
            return [
                'success' => false,
                'message' => 'Stock reservation expired: '.$e->getMessage(),
            ];
        } catch (\RuntimeException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Transaction approval failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Determine if a transaction is refundable.
     *
     * A transaction is refundable if:
     * - Status is 'Completed'
     * - Not already cancelled
     * - Within 24 hours of creation (configurable)
     * - Not a refund transaction itself
     *
     * @param  Transaction  $transaction  Transaction to check
     * @return bool True if the transaction can be refunded
     */
    public function isRefundable(Transaction $transaction): bool
    {
        return $this->statusService->isRefundable($transaction);
    }

    /**
     * Determine if a transaction has been cancelled.
     * Delegates to TransactionStatusService.
     */
    public function isCancelled(Transaction $transaction): bool
    {
        return $this->statusService->isCancelled($transaction);
    }
}
