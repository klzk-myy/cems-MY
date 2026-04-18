<?php

namespace App\Services;

use App\Enums\CddLevel;
use App\Models\Customer;

class TransactionPreValidationService
{
    public function __construct(
        protected UnifiedSanctionScreeningService $screeningService,
        protected ComplianceService $complianceService,
        protected HistoricalRiskAnalysisService $historicalRiskAnalysisService,
        protected AuditService $auditService
    ) {}

    /**
     * Run complete pre-transaction validation
     */
    public function validate(
        Customer $customer,
        string $amount,
        string $currencyCode
    ): PreValidationResult {
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

    private function isReturningCustomer(Customer $customer): bool
    {
        return $customer->transactions()->count() > 0;
    }

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
}
