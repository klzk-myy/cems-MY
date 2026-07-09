<?php

namespace App\Services\Transaction;

use App\Exceptions\Domain\InvalidCurrencyException;
use App\Exceptions\Domain\InvalidIpAddressException;
use App\Exceptions\Domain\PepApprovalRequiredException;
use App\Exceptions\Domain\TillBalanceMissingException;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\TillBalance;
use App\Services\AuditService;
use App\Services\Branch\TellerAllocationService;
use App\Services\Compliance\ComplianceService;
use App\Services\Compliance\HistoricalRiskAnalysisService;
use App\Services\Compliance\PepApprovalService;
use App\Services\Contracts\TransactionHoldServiceInterface;
use App\Services\Contracts\TransactionValidationInterface;
use App\Services\CustomerScreeningService;
use App\Services\DTOs\PreValidationResult;
use App\Services\DTOs\SanctionCheckResult;
use App\Services\ThresholdService;

class TransactionValidationService implements TransactionValidationInterface
{
    public function __construct(
        protected ComplianceService $complianceService,
        protected ThresholdService $thresholdService,
        protected TellerAllocationService $tellerAllocationService,
        protected PepApprovalService $pepApprovalService,
        protected CustomerScreeningService $screeningService,
        protected HistoricalRiskAnalysisService $historicalRiskAnalysisService,
        protected AuditService $auditService,
        protected TransactionHoldServiceInterface $holdService,
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

    public function validateTillBalance(string $tillId, string $currencyCode): TillBalance
    {
        $tillBalance = TillBalance::where('till_id', $tillId)
            ->where('currency_code', $currencyCode)
            ->whereDate('date', today())
            ->whereNull('closed_at')
            ->first();

        if (! $tillBalance) {
            throw new TillBalanceMissingException($currencyCode, $tillId);
        }

        return $tillBalance;
    }

    public function validateIpAddress(?string $ipAddress): void
    {
        if ($ipAddress && ! filter_var($ipAddress, FILTER_VALIDATE_IP)) {
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
                throw new \InvalidArgumentException('Source of funds is required for PEP customers.');
            }
            if (empty($data['source_of_wealth'])) {
                throw new \InvalidArgumentException('Source of wealth is required for PEP customers per pd-00.md 14C.13.1(c).');
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
     * @param  string  $amount  Transaction amount in MYR (as string for precision)
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
        $holdRequired = $this->holdService->requiresHold(
            $cddLevel,
            $customer,
            $result->getRiskFlags()
        );
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
}
