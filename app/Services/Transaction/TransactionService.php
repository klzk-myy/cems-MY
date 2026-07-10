<?php

namespace App\Services\Transaction;

use App\Enums\CddLevel;
use App\Enums\RiskRating;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Events\TransactionApproved;
use App\Exceptions\Domain\AllocationValidationException;
use App\Exceptions\Domain\InsufficientStockException;
use App\Exceptions\Domain\StockReservationExpiredException;
use App\Exceptions\Domain\TillBalanceMissingException;
use App\Http\Traits\ValidatorMethods;
use App\Models\Counter;
use App\Models\Customer;
use App\Models\TellerAllocation;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\CurrencyPositionLockService;
use App\Services\Accounting\CurrencyPositionService;
use App\Services\Accounting\TransactionAccountingService;
use App\Services\Audit\AuditTrailHelper;
use App\Services\AuditService;
use App\Services\Branch\TellerAllocationService;
use App\Services\Branch\TillBalanceManager;
use App\Services\Compliance\ComplianceService;
use App\Services\Compliance\HistoricalRiskAnalysisService;
use App\Services\Compliance\PepApprovalService;
use App\Services\Contracts\TransactionCreationServiceInterface;
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
use App\Services\Transaction\DTOs\TransactionCreationContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

class TransactionService implements TransactionServiceInterface
{
    use ValidatorMethods;

    public function __construct(
        protected MathService $mathService,
        protected ComplianceService $complianceService,
        protected CurrencyPositionService $positionService,
        protected AccountingService $accountingService,
        protected AuditService $auditService,
        protected AuditTrailHelper $auditTrailHelper,
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
        protected TransactionCreationServiceInterface $creationService,
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
        $user = User::findOrFail($userId);

        $this->validationService->validateIpAddress($ipAddress);

        $tillBalance = $this->validationService->validateTillBalance($data['till_id'], $data['currency_code']);

        $customer = Customer::findOrFail($data['customer_id']);
        $amountForeign = (string) $data['amount_foreign'];
        $rate = (string) $data['rate'];
        $amountLocal = $this->mathService->multiply($amountForeign, $rate);

        $this->validationService->validatePepRequirements($customer, $data);

        $allocationForUpdate = $this->determineTellerAllocation($user, $data, $amountLocal);

        $cddLevel = $this->complianceService->determineCDDLevel($amountLocal, $customer);
        $holdCheck = $this->complianceService->requiresHold($amountLocal, $customer);

        $cddTriggers = $this->buildCDDTriggers($cddLevel, $customer, $amountLocal);
        $this->logCDDDecision($userId, $customer, $cddLevel, $cddTriggers, $amountLocal);

        [$status, $holdReason] = $this->determineInitialStatus($amountLocal, $holdCheck);

        $context = new TransactionCreationContext(
            data: $data,
            customer: $customer,
            tillBalance: $tillBalance,
            cddLevel: $cddLevel,
            holdRequired: $holdCheck->requiresHold,
            status: $status,
            amountLocal: $amountLocal,
            user: $user,
            allocation: $allocationForUpdate,
            holdReason: $holdReason,
        );

        return $this->creationService->create($context, $userId, $ipAddress);
    }

    private function determineTellerAllocation(User $user, array $data, string $amountLocal): ?Model
    {
        if (! $user->isTeller()) {
            return null;
        }

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

            return $validationResult->allocation;
        }

        return $this->tellerAllocationService->getActiveAllocation($user, $data['currency_code']);
    }

    private function buildCDDTriggers(CddLevel $cddLevel, Customer $customer, string $amountLocal): array
    {
        $triggers = [];

        if ($cddLevel === CddLevel::Enhanced) {
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
        } elseif ($cddLevel === CddLevel::Standard) {
            $triggers[] = 'Standard amount >= RM '.$this->thresholdService->getStandardCddThreshold();
        }

        return $triggers;
    }

    private function logCDDDecision(int $userId, Customer $customer, CddLevel $cddLevel, array $cddTriggers, string $amountLocal): void
    {
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
    }

    private function determineInitialStatus(string $amountLocal, $holdCheck): array
    {
        $status = TransactionStatus::Completed;
        $holdReason = null;

        if ($holdCheck->requiresHold
            || $this->mathService->compare($amountLocal, $this->thresholdService->getAutoApproveThreshold()) >= 0) {
            $status = TransactionStatus::PendingApproval;
            if ($holdCheck->requiresHold) {
                $holdReason = implode(', ', $holdCheck->reasons);
            }
        }

        return [$status, $holdReason];
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
            $result = DB::transaction(function () use ($transaction, $approverId, $amlResult, $ipAddress) {
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

                $manager = app(TillBalanceManager::class);

                $lockedForeign = $manager->currentBalance($counter, $lockedTransaction->currency_code, true);
                if (! $lockedForeign) {
                    throw new TillBalanceMissingException($lockedTransaction->currency_code, $lockedTransaction->till_id);
                }

                $myrBalance = $manager->currentBalance($counter, 'MYR', true);
                if (! $myrBalance) {
                    throw new TillBalanceMissingException('MYR', $lockedTransaction->till_id);
                }

                if ($lockedTransaction->type->value === TransactionType::Buy->value) {
                    $manager->adjustBalance($lockedForeign, 'buy_total_foreign', (string) $lockedTransaction->amount_foreign, 'add', false);
                    $manager->adjustBalance($lockedForeign, 'foreign_total', (string) $lockedTransaction->amount_foreign, 'add', false);
                } else {
                    $manager->adjustBalance($lockedForeign, 'sell_total_foreign', (string) $lockedTransaction->amount_foreign, 'add', false);
                    $manager->adjustBalance($lockedForeign, 'foreign_total', (string) $lockedTransaction->amount_foreign, 'subtract', false);
                }

                $myrOperation = $lockedTransaction->type->value === TransactionType::Buy->value ? 'subtract' : 'add';
                $manager->adjustBalance($myrBalance, 'transaction_total', (string) $lockedTransaction->amount_local, $myrOperation, false);

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
                $approver = User::find($approverId);

                if ($lockedTransaction->cdd_level === CddLevel::Enhanced) {
                    $this->createDeferredAccountingEntries($lockedTransaction->id);
                } else {
                    $this->createImmediateAccountingEntries($lockedTransaction);
                }

                // Audit logging for the approval action
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
