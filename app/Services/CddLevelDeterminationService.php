<?php

namespace App\Services;

use App\Enums\CddLevel;
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
     * SECURITY NOTE: This method always uses the customer's actual record values
     * for PEP status and sanctions screening. No override parameters are allowed
     * to prevent bypassing Enhanced CDD requirements.
     *
     * @param  string  $amount  Transaction amount in MYR (as string for precision)
     * @param  Customer  $customer  The customer initiating the transaction
     * @return CddLevel The determined CDD level (Simplified, Specific, Standard, or Enhanced)
     */
    public function determineCDDLevel(string $amount, Customer $customer): CddLevel
    {
        // Always use customer record - no overrides allowed for security
        $pepStatus = $customer->pep_status ?? false;
        $sanctionStatus = $this->checkSanctionMatch($customer);

        // Track Enhanced CDD triggers for audit trail
        $triggers = [];

        // Enhanced Due Diligence triggers (risk-based per pd-00.md 14C.13)
        // Large transactions also trigger Enhanced CDD per regulatory requirements
        if ($pepStatus) {
            $triggers[] = 'PEP customer';
        }
        if ($sanctionStatus) {
            $triggers[] = 'Sanctions match';
        }
        if ($customer->risk_rating === 'High') {
            $triggers[] = 'High risk customer';
        }
        if ($this->mathService->compare($amount, $this->thresholdService->getLargeTransactionThreshold()) >= 0) {
            $triggers[] = 'Large amount >= RM '.$this->thresholdService->getLargeTransactionThreshold();
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
