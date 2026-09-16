<?php

namespace App\Services\Transaction\Checks;

use App\Enums\ComplianceFlagType;
use App\Models\Transaction;
use App\Services\Compliance\ComplianceService;

/**
 * Large transactions sitting on hold beyond the duration threshold.
 */
class DurationOnHoldCheck implements TransactionCheck
{
    public function __construct(protected ComplianceService $complianceService) {}

    public function check(Transaction $transaction): array
    {
        $durationCheck = $this->complianceService->checkTransactionDuration($transaction);

        if (! $durationCheck['has_duration_concern']) {
            return [];
        }

        return [new FlagDescriptor(
            type: ComplianceFlagType::EddRequired,
            reason: "Duration threshold exceeded: {$durationCheck['hours_on_hold']} hours on hold (threshold: {$durationCheck['threshold_hours']} hours) - {$durationCheck['severity']}",
        )];
    }
}
