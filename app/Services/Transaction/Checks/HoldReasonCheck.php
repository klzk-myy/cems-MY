<?php

namespace App\Services\Transaction\Checks;

use App\Enums\ComplianceFlagType;
use App\Models\Transaction;
use App\Services\Compliance\ComplianceService;

/**
 * Hold decision. A Completed transaction has already booked its journal,
 * position and till movements — reverting status to PendingApproval without
 * unwinding them corrupts the books and a later approval would double-apply
 * every effect. Keep the record Completed and flag it for compliance review
 * instead; voiding is a separate reversal workflow.
 */
class HoldReasonCheck implements TransactionCheck
{
    public function __construct(protected ComplianceService $complianceService) {}

    public function check(Transaction $transaction): array
    {
        $holdCheck = $this->complianceService->requiresHold(
            $transaction->amount_local,
            $transaction->customer
        );

        if (! $holdCheck->requiresHold
            || ! $transaction->status->isCompleted()
            || $transaction->approved_by !== null) {
            return [];
        }

        return array_map(
            fn (string $reason) => new FlagDescriptor(ComplianceFlagType::EddRequired, $reason),
            $holdCheck->reasons
        );
    }
}
