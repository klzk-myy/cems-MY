<?php

namespace App\Services\Transaction;

use App\Exceptions\Domain\InvalidCurrencyException;
use App\Exceptions\Domain\InvalidIpAddressException;
use App\Exceptions\Domain\PepApprovalRequiredException;
use App\Exceptions\Domain\TillBalanceMissingException;
use App\Exceptions\Domain\TransactionValidationException;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\TillBalance;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Branch\TellerAllocationService;
use App\Services\Branch\TillBalanceManager;
use App\Services\Compliance\ComplianceService;
use App\Services\Compliance\HistoricalRiskAnalysisService;
use App\Services\Compliance\PepApprovalService;
use App\Services\DTOs\PreValidationResult;
use App\Services\DTOs\SanctionCheckResult;
use App\Services\Screening\CustomerScreeningService;
use App\Services\Security\IpValidationService;
use App\Services\ThresholdService;
use App\Support\ActorContext;

class TransactionValidationService
{
    public function __construct(
        protected ComplianceService $complianceService,
        protected ThresholdService $thresholdService,
        protected TellerAllocationService $tellerAllocationService,
        protected PepApprovalService $pepApprovalService,
        protected CustomerScreeningService $screeningService,
        protected HistoricalRiskAnalysisService $historicalRiskAnalysisService,
        protected AuditService $auditService,
        protected TransactionHoldService $holdService,
        protected TillBalanceManager $tillBalanceManager,
        protected IpValidationService $ipValidationService,
    ) {}

    public function validateCurrency(string $currencyCode): void
    {
        $currency = Currency::where('code', $currencyCode)
            ->where('is_active', true)
            ->first();

        if (! $currency) {
            throw new InvalidCurrencyException($currencyCode);
        }
    }

    public function validateTillBalance(string $tillId, string $currencyCode, ?User $user = null): TillBalance
    {
        $counter = Counter::findByCodeOrId($tillId);

        if (! $counter) {
            throw new TillBalanceMissingException($currencyCode, $tillId);
        }

        // Mirror the ValidTill rule's cross-branch guard: a user may only book
        // against tills of their own branch. Report a foreign-branch till as
        // missing instead of leaking its existence. Callers without an
        // authenticated request (queue jobs, CSV import) must pass the actor
        // explicitly — with no user at all there is nothing to scope by.
        $user ??= ActorContext::capture()->user;

        if ($user !== null && $user->branch_id !== null
            && (int) $counter->branch_id !== (int) $user->branch_id) {
            throw new TillBalanceMissingException($currencyCode, $tillId);
        }

        $tillBalance = $this->tillBalanceManager->currentBalance($counter, $currencyCode, true);

        if (! $tillBalance) {
            throw new TillBalanceMissingException($currencyCode, $tillId);
        }

        return $tillBalance;
    }

    public function validateIpAddress(?string $ipAddress): void
    {
        if ($ipAddress && ! $this->ipValidationService->isValidIp($ipAddress)) {
            throw new InvalidIpAddressException($ipAddress);
        }
    }

    public function validatePepRequirements(Customer $customer, array $data): void
    {
        if ($this->pepApprovalService->requiresHeadOfficeApproval($customer)) {
            if (! $this->pepApprovalService->hasApprovedApproval($customer)) {
                $pendingApproval = $this->pepApprovalService->requestApproval(
                    $customer,
                    $data['type'] ?? 'transaction'
                );

                throw new PepApprovalRequiredException(
                    "Senior Management approval required for PEP customer. Approval ID: {$pendingApproval->id}"
                );
            }
        }

        if ($customer->pep_status) {
            if (empty($data['source_of_funds'])) {
                throw new TransactionValidationException(field: 'source_of_funds', message: 'Source of funds is required for PEP customers.');
            }
            if (empty($data['source_of_wealth'])) {
                throw new TransactionValidationException(field: 'source_of_wealth', message: 'Source of wealth is required for PEP customers per pd-00.md 14C.13.1(c).');
            }
        }
    }

    /**
     * Run complete pre-transaction validation before creation.
     *
     * Consolidates:
     * - Sanctions screening (blocking)
     * - CDD level determination
     * - Historical risk analysis (for returning customers)
     * - Hold status determination
     *
     * @param  string  $amountMyr  Transaction amount in MYR (as string for precision)
     */
    public function preValidate(Customer $customer, string $amountMyr, string $currencyCode): PreValidationResult
    {
        $result = new PreValidationResult;

        // 1. Sanctions screening (blocking)
        $sanctionResult = $this->checkSanctions($customer);
        if ($sanctionResult->isBlocked()) {
            // Audit the blocked attempt BEFORE returning — a block that leaves
            // no audit record is invisible to compliance review.
            $this->auditService->logPreTransactionEvent(
                'pre_validation_blocked',
                $customer->id,
                ['new_values' => [
                    'customer_id' => $customer->id,
                    'amount_myr' => $amountMyr,
                    'currency_code' => $currencyCode,
                    'block_reason' => 'sanctions',
                    'message' => $sanctionResult->getMessage(),
                ]],
                'CRITICAL'
            );

            $result->addBlock('sanctions', $sanctionResult->getMessage());

            return $result;
        }

        // 2. CDD level determination
        $cddLevel = $this->complianceService->determineCDDLevel($amountMyr, $customer);
        $result->setCDDLevel($cddLevel);

        // 3. Historical risk analysis (for returning customers)
        if ($this->isReturningCustomer($customer)) {
            $riskResult = $this->historicalRiskAnalysisService->analyze($customer, $amountMyr);
            $result->setRiskFlags($riskResult->getFlags());
        }

        // Non-blocking sanctions flag (fuzzy/adverse-media match): carry it as
        // a critical risk flag so the hold decision escalates the transaction
        // for review instead of the match disappearing silently.
        if ($sanctionResult->getMessage() !== null) {
            $result->addRiskFlag([
                'type' => 'sanctions_flag',
                'severity' => 'critical',
                'description' => $sanctionResult->getMessage(),
                'metric' => $sanctionResult->confidenceScore,
                'matched_entity' => $sanctionResult->matchedEntity,
            ]);
        }

        // 4. Determine hold status
        $holdRequired = $this->holdService->requiresHold(
            $cddLevel,
            $result->getRiskFlags()
        );
        $result->setHoldRequired($holdRequired);

        $this->auditService->logPreTransactionEvent(
            'pre_validation_completed',
            $customer->id,
            ['new_values' => [
                'customer_id' => $customer->id,
                'amount_myr' => $amountMyr,
                'cdd_level' => $cddLevel->value,
                'hold_required' => $holdRequired,
                'risk_flags' => $result->getRiskFlags(),
            ]],
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
            $matchedEntity = $response->matches->first()?->entityName;
            $message = $matchedEntity
                ? "Sanctions match found: {$matchedEntity} (confidence: {$matchScore}%)"
                : "Sanctions match found (confidence: {$matchScore}%)";

            return SanctionCheckResult::blocked($message, $matchScore, $matchedEntity ?? 'Unknown');
        }

        if ($response->action === 'flag') {
            $matchScore = $response->confidenceScore;
            $matchedEntity = $response->matches->first()?->entityName;
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
        return $customer->transactions()->exists();
    }
}
