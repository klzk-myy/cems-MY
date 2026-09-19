<?php

namespace App\Services\Transaction\Checks;

use App\Enums\ComplianceFlagType;
use App\Models\Transaction;
use App\Services\Compliance\ComplianceService;

/**
 * 24h cumulative velocity threshold.
 */
class VelocityCheck implements TransactionCheck
{
    public function __construct(protected ComplianceService $complianceService) {}

    public function check(Transaction $transaction): array
    {
        $velocityCheck = $this->complianceService->checkVelocity(
            $transaction->customer_id,
            $transaction->amount_myr
        );

        if (! $velocityCheck['threshold_exceeded']) {
            return [];
        }

        $transactionCount = Transaction::where('customer_id', $transaction->customer_id)
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        return [new FlagDescriptor(
            type: ComplianceFlagType::Velocity,
            reason: "24h velocity exceeded: RM {$velocityCheck['with_new_transaction']}",
            auditEvent: 'aml_velocity_alert_triggered',
            auditPayload: [
                'customer_id' => $transaction->customer_id,
                'velocity_amount' => $velocityCheck['with_new_transaction'],
                'transaction_count' => $transactionCount,
            ],
        )];
    }
}
