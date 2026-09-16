<?php

namespace App\Services\Transaction\Checks;

use App\Enums\ComplianceFlagType;
use App\Models\Transaction;
use App\Services\Compliance\ComplianceService;
use App\Services\ThresholdService;

/**
 * Structuring detection — multiple small transactions under the CDD threshold.
 */
class StructuringCheck implements TransactionCheck
{
    public function __construct(
        protected ComplianceService $complianceService,
        protected ThresholdService $thresholdService,
    ) {}

    public function check(Transaction $transaction): array
    {
        if (! $this->complianceService->checkStructuring($transaction->customer_id)) {
            return [];
        }

        return [new FlagDescriptor(
            type: ComplianceFlagType::Structuring,
            reason: 'Potential structuring: 3+ transactions under RM '
                .number_format((float) $this->thresholdService->getStandardCddThreshold())
                .' within 1 hour',
            auditEvent: 'aml_structuring_detected',
            auditPayload: [
                'customer_id' => $transaction->customer_id,
                'pattern' => 'aggregate_transactions',
            ],
        )];
    }
}
