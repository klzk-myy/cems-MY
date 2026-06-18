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
use App\Models\CurrencyPosition;
use App\Models\Customer;
use App\Models\TillBalance;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\CurrencyPositionService;
use App\Services\Accounting\TransactionAccountingService;
use App\Services\AuditService;
use App\Services\Branch\TellerAllocationService;
use App\Services\CacheTagsService;
use App\Services\Compliance\ComplianceService;
use App\Services\Compliance\HistoricalRiskAnalysisService;
use App\Services\Compliance\PepApprovalService;
use App\Services\Contracts\TransactionServiceInterface;
use App\Services\CustomerScreeningService;
use App\Services\MathService;
use App\Services\PreValidationResult;
use App\Services\SanctionCheckResult;
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
        protected TransactionValidationService $validationService,
    ) {}

    /**
     * Run complete pre-transaction validation before creation.
     *
     * This consolidates validation logic previously in TransactionPreValidationService:
     * - Sanctions screening (blocking)
     * - CDD level determination
     * - Historical risk analysis (for returning customers)
     * - Hold status determination
     *
     * @param  Customer  $customer  Customer for validation
     * @param  string  $amount  Transaction amount (in MYR)
     * @param  string  $currencyCode  Currency code
     */
    public function preValidate(Customer $customer, string $amount, string $currencyCode): PreValidationResult
    {
        $result = new PreValidationResult;

        // 1. Sanctions screening (blocking)
        $sanctionResult = $this->checkSanctions($customer);
        if ($sanctionResult->isBlocked()) {
            $result->addBlock('sanctions', $sanctionResult->getMessage());

            return $result;
        }

        // 2. CDD level determination
        $cddLevel = $this->complianceService->determineCDDLevel($amount, $customer);
        $result->setCDDLevel($cddLevel);

        // 3. Historical risk analysis (for returning customers)
        if ($this->isReturningCustomer($customer)) {
            $riskResult = $this->historicalRiskAnalysisService->analyze($customer, $amount);
            $result->setRiskFlags($riskResult->getFlags());
        }

        // 4. Determine hold status
        $holdRequired = $this->determineHoldRequired($result);
        $result->setHoldRequired($holdRequired);

        $this->auditService->logWithSeverity(
            'pre_validation_completed',
            [
                'entity_type' => 'PreTransaction',
                'entity_id' => $customer->id,
                'new_values' => [
                    'customer_id' => $customer->id,
                    'amount' => $amount,
                    'cdd_level' => $cddLevel->value,
                    'hold_required' => $holdRequired,
                    'risk_flags' => $result->getRiskFlags(),
                ],
            ],
            'INFO'
        );

        return $result;
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
     */
    private function determineHoldRequired(PreValidationResult $result): bool
    {
        // Hold if Enhanced CDD
        if ($result->getCDDLevel() === CddLevel::Enhanced) {
            return true;
        }

        // Hold if any critical risk flags
        foreach ($result->getRiskFlags() as $flag) {
            if ($flag['severity'] === 'critical') {
                return true;
            }
        }

        return false;
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

                if (! $validationResult['valid']) {
                    throw new AllocationValidationException($validationResult['reason']);
                }

                $allocationForUpdate = $validationResult['allocation'];
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

        if ($holdCheck['requires_hold'] || $this->mathService->compare($amountLocal, $this->thresholdService->getAutoApproveThreshold()) >= 0) {
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
            if ($holdCheck['requires_hold']) {
                $holdReason = implode(', ', $holdCheck['reasons']);
            }
        }

        $transaction = DB::transaction(function () use ($data, $userId, $tillBalance, $amountForeign, $rate, $amountLocal, $cddLevel, $status, $holdReason, $approvedBy, &$allocationForUpdate) {
            // For Sell transactions, acquire position lock FIRST to prevent race conditions
            // where two concurrent transactions could both pass the duplicate check
            // before either acquires the lock
            if ($data['type'] === TransactionType::Sell->value) {
                $this->positionService->getPositionWithLock($data['currency_code'], $data['till_id']);

                // Verify sufficient stock for Sell transactions IMMEDIATELY after acquiring lock
                // This prevents race conditions where another transaction could modify the position
                // Use getAvailableBalance which accounts for pending reservations
                $availableBalance = $this->positionService->getAvailableBalance(
                    $data['currency_code'],
                    $data['till_id']
                );
                if ($this->mathService->compare($availableBalance, $amountForeign) < 0) {
                    throw new InsufficientStockException(
                        $data['currency_code'],
                        $amountForeign,
                        $availableBalance
                    );
                }
            } elseif ($data['type'] === TransactionType::Buy->value) {
                // For Buy transactions, acquire position lock to prevent race conditions
                // where concurrent transactions could cause inconsistent position updates.
                // Unlike Sell, Buy does not require stock validation (we are acquiring currency).
                $this->positionService->getPositionWithLock($data['currency_code'], $data['till_id']);
            }

            // Check for duplicate transaction via idempotency key (inside transaction to prevent race)
            // Check this FIRST, before recent duplicate window, as idempotency is the strongest guarantee
            if (! empty($data['idempotency_key'])) {
                $existingByKey = Transaction::where('idempotency_key', $data['idempotency_key'])->first();
                if ($existingByKey) {
                    return $existingByKey;
                }
            }

            // Check for recent similar transaction (potential double-submit)
            // Moved inside DB transaction to ensure check and insert are atomic
            $recentWindow = now()->subSeconds(30);
            $recentAmount = Transaction::where('user_id', $userId)
                ->where('created_at', '>=', $recentWindow)
                ->where('amount_foreign', $data['amount_foreign'])
                ->where('currency_code', $data['currency_code'])
                ->where('type', $data['type'])
                ->first();

            if ($recentAmount) {
                $this->auditService->logWithSeverity(
                    'potential_duplicate_detected',
                    [
                        'user_id' => $userId,
                        'entity_type' => 'Transaction',
                        'entity_id' => $recentAmount->id,
                        'description' => "Similar transaction {$recentAmount->id} found within 30 seconds",
                    ],
                    'WARNING'
                );

                throw new DuplicateTransactionException;
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
                'status' => $status,
                'hold_reason' => $holdReason,
                'approved_by' => $approvedBy,
                'cdd_level' => $cddLevel,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'version' => 0,
            ]);

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
                    $data['till_id']
                );
                $this->updateTillBalance($tillBalance, $data['type'], $amountLocal, $amountForeign);

                // Update teller allocation if this was a teller transaction
                if ($allocationForUpdate) {
                    $isBuy = ($data['type'] === TransactionType::Buy->value);
                    // Buy: money changer buys foreign currency FROM customer → allocation increases
                    // Sell: money changer sells foreign currency TO customer → allocation decreases
                    if ($isBuy) {
                        $allocationForUpdate->add($amountForeign);
                    } else {
                        $allocationForUpdate->deduct($amountForeign);
                    }
                    $allocationForUpdate->addDailyUsed($amountLocal);
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
     * Verify till is still open for operations.
     *
     * @param  TillBalance  $tillBalance  The till balance to verify
     *
     * @throws \InvalidArgumentException If till is closed
     */
    protected function verifyTillIsOpen(TillBalance $tillBalance): void
    {
        if ($tillBalance->closed_at !== null) {
            throw new \InvalidArgumentException('Till is closed. Cannot perform operations on closed till.');
        }
    }

    /**
     * Update till balance after transaction.
     * Updates both foreign currency and MYR (local currency) balances.
     * Uses lockForUpdate to prevent race conditions on concurrent transactions.
     */
    protected function updateTillBalance(TillBalance $tillBalance, string $type, string $amountLocal, string $amountForeign): void
    {
        $this->verifyTillIsOpen($tillBalance);

        // Lock the foreign currency balance
        $lockedForeign = TillBalance::where('id', $tillBalance->id)
            ->lockForUpdate()
            ->first();

        // Lock the MYR balance (always present for active till)
        $myrBalance = TillBalance::where('till_id', $lockedForeign->till_id)
            ->where('currency_code', 'MYR')
            ->whereDate('date', today())
            ->whereNull('closed_at')
            ->lockForUpdate()
            ->first();

        if (! $myrBalance) {
            throw new TillBalanceMissingException('MYR', $lockedForeign->till_id);
        }

        // Update foreign currency balance using separate buy/sell tracking
        // Buy: increase buy_total_foreign (we are buying foreign currency from customer, stock increases)
        // Sell: increase sell_total_foreign (we are selling foreign currency to customer, stock decreases)
        $buyTotal = $lockedForeign->buy_total_foreign ?? '0';
        $sellTotal = $lockedForeign->sell_total_foreign ?? '0';
        $foreignTotal = $lockedForeign->foreign_total ?? '0';

        if ($type === TransactionType::Buy->value) {
            $newBuyTotal = $this->mathService->add($buyTotal, $amountForeign);
            $newForeignTotal = $this->mathService->add($foreignTotal, $amountForeign);
            $lockedForeign->update([
                'buy_total_foreign' => $newBuyTotal,
                'foreign_total' => $newForeignTotal,
            ]);
        } else {
            $newSellTotal = $this->mathService->add($sellTotal, $amountForeign);
            $newForeignTotal = $this->mathService->subtract($foreignTotal, $amountForeign);
            $lockedForeign->update([
                'sell_total_foreign' => $newSellTotal,
                'foreign_total' => $newForeignTotal,
            ]);
        }

        // Update MYR balance - always add (cash in on Sell, cash out on Buy is recorded separately)
        $myrTotal = $myrBalance->transaction_total ?? '0';
        $newMyrTotal = $this->mathService->add($myrTotal, $amountLocal);

        $myrBalance->update(['transaction_total' => $newMyrTotal]);
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
            return DB::transaction(function () use ($transaction, $approverId, $amlResult) {
                // Optimistic locking with pessimistic lock to prevent race conditions
                $lockedTransaction = Transaction::where('id', $transaction->id)
                    ->where('status', TransactionStatus::PendingApproval)
                    ->where('version', $transaction->version)
                    ->lockForUpdate()
                    ->first();

                if (! $lockedTransaction) {
                    throw new \RuntimeException(
                        'Transaction was already processed or modified by another user.'
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
                $tillBalance = TillBalance::where('till_id', $lockedTransaction->till_id)
                    ->where('currency_code', $lockedTransaction->currency_code)
                    ->whereDate('date', today())
                    ->whereNull('closed_at')
                    ->first();

                if (! $tillBalance) {
                    throw new \RuntimeException(
                        'Till has been closed. Cannot approve transaction for closed till.'
                    );
                }

                // EDGE CASE VALIDATION: Verify position still exists (for Sell transactions)
                // Position could have been deleted between transaction creation and approval
                if ($lockedTransaction->type->isSell()) {
                    $position = CurrencyPosition::where('currency_code', $lockedTransaction->currency_code)
                        ->where('till_id', $lockedTransaction->till_id)
                        ->first();

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
                $lockedTransaction->update([
                    'status' => TransactionStatus::Completed,
                    'approved_by' => $approverId,
                    'approved_at' => $nowIso,
                    'transition_history' => $history,
                    'version' => $lockedTransaction->version + 1,
                ]);

                // Refresh the model to get updated version
                $lockedTransaction->refresh();

                // Get the till balance for today
                $tillBalance = TillBalance::where('till_id', $lockedTransaction->till_id ?? 'MAIN')
                    ->where('currency_code', $lockedTransaction->currency_code)
                    ->whereDate('date', today())
                    ->whereNull('closed_at')
                    ->lockForUpdate()
                    ->first();

                if (! $tillBalance) {
                    throw new \RuntimeException(
                        'Till balance not found for today. Cannot complete transaction.'
                    );
                }

                // Check available balance BEFORE consuming reservation (Sell only)
                // Buy transactions ADD foreign currency to the position — no existing stock required
                if ($lockedTransaction->type === TransactionType::Sell) {
                    $available = $this->positionService->getAvailableBalance(
                        $lockedTransaction->currency_code,
                        (string) $lockedTransaction->till_id
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
                    $lockedTransaction->till_id ?? 'MAIN'
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
                Event::dispatch(new TransactionApproved($lockedTransaction));

                return [
                    'success' => true,
                    'message' => 'Transaction approved and completed successfully.',
                    'transaction' => $lockedTransaction->fresh(),
                ];
            });

            $this->cacheTagsService->invalidate('dashboard');

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
        // Must be completed
        if (! $transaction->status->isCompleted()) {
            return false;
        }

        // Cannot be already cancelled
        if ($transaction->cancelled_at !== null) {
            return false;
        }

        // Must be within configured cancellation window
        $cancellationWindowHours = config('cems.transaction_cancellation_window_hours', 24);
        if ($transaction->created_at->diffInHours(now()) >= $cancellationWindowHours) {
            return false;
        }

        // Cannot be a refund
        if ($transaction->is_refund) {
            return false;
        }

        return true;
    }

    /**
     * Determine if a transaction has been cancelled.
     *
     * Checks if the cancelled_at timestamp is set.
     *
     * @param  Transaction  $transaction  Transaction to check
     * @return bool True if the transaction has been cancelled
     */
    public function isCancelled(Transaction $transaction): bool
    {
        return $transaction->cancelled_at !== null;
    }
}
