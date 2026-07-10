<?php

namespace App\Services\Transaction;

use App\Enums\CddLevel;
use App\Enums\RiskRating;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Exceptions\Domain\AllocationValidationException;
use App\Http\Traits\ValidatorMethods;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\CurrencyPositionLockService;
use App\Services\Accounting\CurrencyPositionService;
use App\Services\Accounting\TransactionAccountingService;
use App\Services\Audit\AuditTrailHelper;
use App\Services\AuditService;
use App\Services\Branch\TellerAllocationService;
use App\Services\Compliance\ComplianceService;
use App\Services\Compliance\HistoricalRiskAnalysisService;
use App\Services\Compliance\PepApprovalService;
use App\Services\Contracts\TransactionApprovalServiceInterface;
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
        protected TransactionApprovalServiceInterface $approvalService,
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
     * Approve a pending transaction and complete its side effects.
     *
     * @param  Transaction  $transaction  The pending transaction to approve
     * @param  int  $approverId  The user ID of the manager/admin approving
     * @param  string|null  $ipAddress  IP address for audit logging
     * @return array{success: bool, message: string, transaction?: Transaction}
     */
    public function approveTransaction(Transaction $transaction, int $approverId, ?string $ipAddress = null): array
    {
        $this->validationService->validateIpAddress($ipAddress ?? request()->ip());

        $this->approvalService->validateApprovalEligibility($transaction, $approverId);

        $result = $this->approvalService->approve($transaction, $approverId, $ipAddress);

        return [
            'success' => $result->success,
            'message' => $result->message,
            'transaction' => $result->transaction,
        ];
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
