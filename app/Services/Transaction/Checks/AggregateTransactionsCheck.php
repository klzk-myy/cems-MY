<?php

namespace App\Services\Transaction\Checks;

use App\Enums\ComplianceFlagType;
use App\Models\Transaction;
use App\Services\Compliance\ComplianceService;

/**
 * Related transactions exceeding the aggregate threshold.
 */
class AggregateTransactionsCheck implements TransactionCheck
{
    public function __construct(protected ComplianceService $complianceService) {}

    public function check(Transaction $transaction): array
    {
        $aggregateCheck = $this->complianceService->checkAggregateTransactions(
            $transaction->customer_id,
            $transaction->amount_myr
        );

        if (! $aggregateCheck['has_aggregate_concern']) {
            return [];
        }

        return [new FlagDescriptor(
            type: ComplianceFlagType::LargeAmount,
            reason: "Aggregate concern: RM {$aggregateCheck['total_aggregate']} across {$aggregateCheck['transaction_count']} transactions in 24h",
        )];
    }
}
