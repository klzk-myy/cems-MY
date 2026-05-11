<?php

namespace App\Services;

use App\Enums\CddLevel;
use App\Enums\PepType;
use App\Enums\RiskRating;
use App\Models\Customer;

/**
 * CDD Level Determination Service
 *
 * Extracts Customer Due Diligence (CDD) level determination logic from ComplianceService.
 * Handles the CDD level calculation based on transaction amount and customer risk factors
 * per BNM regulations (pd-00.md 14C.12).
 *
 * CDD Levels:
 * - Simplified: < RM 3,000
 * - Specific: RM 3,000 - 10,000
 * - Standard: >= RM 10,000
 * - Enhanced: PEP, Sanction match, or High risk (risk-based, not amount-based)
 */
class CddLevelDeterminationService
{
    /**
     * Math service for precise financial calculations.
     */
    protected MathService $mathService;

    /**
     * Threshold service for dynamic threshold values.
     */
    protected ThresholdService $thresholdService;

    /**
     * Callable for checking sanction match status.
     * Should accept a Customer and return a bool.
     *
     * @var callable|null
     */
    protected $sanctionCheck;

    /**
     * Last CDD triggers captured for audit trail.
     *
     * @var array<string>
     */
    protected array $lastCddTriggers = [];

    /**
     * Create a new CddLevelDeterminationService instance.
     *
     * @param  MathService  $mathService  Service for high-precision calculations
     * @param  ThresholdService  $thresholdService  Service for dynamic thresholds
     * @param  callable|null  $sanctionCheck  Optional callable that accepts Customer and returns bool
     */
    public function __construct(
        MathService $mathService,
        ThresholdService $thresholdService,
        ?callable $sanctionCheck = null
    ) {
        $this->mathService = $mathService;
        $this->thresholdService = $thresholdService;
        $this->sanctionCheck = $sanctionCheck;
    }

    /**
     * Determine CDD level per pd-00.md 14C.12 for MSB:
     * - Simplified: < RM 3,000
     * - Specific: RM 3,000 - 10,000
     * - Standard: >= RM 10,000
     * - Enhanced: PEP, Sanction match, or High risk (risk-based, not amount-based)
     *
     * PEP handling per pd-00.md 15.2 and 15.3:
     * - Foreign PEPs (15.2) always require Enhanced CDD
     * - Domestic PEPs (15.3) require Enhanced CDD only if higher risk
     *
     * SECURITY NOTE: This method always uses the customer's actual record values
     * for PEP status and sanctions screening. No override parameters are allowed
     * to prevent bypassing Enhanced CDD requirements.
     *
     * @param  string  $amount  Transaction amount in MYR (as string for precision)
     * @param  Customer  $customer  The customer initiating the transaction
     * @param  string|null  $pepType  Optional PEP type to distinguish foreign vs domestic PEPs
     * @return CddLevel The determined CDD level (Simplified, Specific, Standard, or Enhanced)
     */
    public function determineCDDLevel(string $amount, Customer $customer, ?string $pepType = null): CddLevel
    {
        // Always use customer record - no overrides allowed for security
        $pepStatus = $customer->pep_status ?? false;
        $sanctionStatus = $this->checkSanctionMatch($customer);

        // Track Enhanced CDD triggers for audit trail
        $triggers = [];

        // PEP handling per pd-00.md 15.2 and 15.3
        // Foreign PEPs (15.2) require Enhanced CDD always
        if ($pepType === PepType::Foreign->value) {
            $triggers[] = 'Foreign PEP';

            $this->lastCddTriggers = $triggers;

            return CddLevel::Enhanced;
        }

        // Domestic PEPs (15.3) - risk-based enhanced CDD
        if ($pepType === PepType::Domestic->value && $this->isHigherRisk($customer)) {
            $triggers[] = 'Domestic PEP (higher risk)';

            $this->lastCddTriggers = $triggers;

            return CddLevel::Enhanced;
        }

        // Other PEP status (family member, close associate, etc.) - risk-based
        if ($pepStatus && $pepType !== null && $this->isHigherRisk($customer)) {
            $triggers[] = 'PEP associate (higher risk)';

            $this->lastCddTriggers = $triggers;

            return CddLevel::Enhanced;
        }

        // Enhanced Due Diligence triggers (risk-based per pd-00.md 14C.13)
        // Enhanced CDD is based on customer risk factors, not transaction amount.
        // Transaction amount determines Standard/Specific/Simplified, not Enhanced.
        if ($pepStatus && $pepType === null) {
            // Legacy PEP status without type distinction - treat as higher risk
            $triggers[] = 'PEP customer';
        }
        if ($sanctionStatus) {
            $triggers[] = 'Sanctions match';
        }
        if ($customer->risk_rating === RiskRating::High) {
            $triggers[] = 'High risk customer';
        }

        // Store triggers if Enhanced
        if (! empty($triggers)) {
            $this->lastCddTriggers = $triggers;

            return CddLevel::Enhanced;
        }

        // Standard CDD: >= RM 10,000 per pd-00.md 14C.12.2
        if ($this->mathService->compare($amount, $this->thresholdService->getStandardCddThreshold()) >= 0) {
            return CddLevel::Standard;
        }

        // Specific CDD: >= RM 3,000 per pd-00.md 14C.12.1
        if ($this->mathService->compare($amount, $this->thresholdService->getSpecificCddThreshold()) >= 0) {
            return CddLevel::Specific;
        }

        return CddLevel::Simplified;
    }

    /**
     * Check if customer is higher risk (for Domestic PEP assessment per pd-00.md 15.3).
     *
     * A domestic PEP requires Enhanced CDD only if they are assessed as higher risk.
     * This considers risk rating, sanctions match, and other risk factors.
     */
    protected function isHigherRisk(Customer $customer): bool
    {
        // High risk rating is automatically higher risk
        if ($customer->risk_rating === RiskRating::High) {
            return true;
        }

        // Sanction match indicates higher risk
        if ($this->checkSanctionMatch($customer)) {
            return true;
        }

        // Medium risk with PEP association could be higher risk
        // This could be enhanced with additional risk factors per pd-00.md 15.3.4
        if ($customer->risk_rating === RiskRating::Medium) {
            return true;
        }

        return false;
    }

    /**
     * Check if customer matches any sanctions list entries.
     *
     * @param  Customer  $customer  The customer to screen against sanctions lists
     * @return bool True if customer matches any sanctions entry, false otherwise
     */
    protected function checkSanctionMatch(Customer $customer): bool
    {
        if ($this->sanctionCheck !== null) {
            return ($this->sanctionCheck)($customer);
        }

        return false;
    }

    /**
     * Get the triggers that determined the Enhanced CDD level.
     * Must be called immediately after determineCDDLevel when Enhanced is returned.
     *
     * @return array<string> List of trigger reasons
     */
    public function getLastCddTriggers(): array
    {
        return $this->lastCddTriggers;
    }
}
