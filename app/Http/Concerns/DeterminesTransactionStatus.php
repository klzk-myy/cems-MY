<?php

namespace App\Http\Concerns;

use App\Enums\RiskRating;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Exceptions\Domain\AllocationValidationException;
use App\Models\TellerAllocation;
use App\Models\User;

/**
 * Helpers for deciding teller allocation and initial transaction status
 * when assembling a TransactionCreationContext.
 *
 * Host classes must provide constructor-injected properties:
 *   - `protected MathService $mathService`
 *   - `protected TellerAllocationService $tellerAllocationService`
 *   - `protected ThresholdService $thresholdService`
 */
trait DeterminesTransactionStatus
{
    /**
     * Determine the teller allocation to attach to a new transaction.
     *
     * @param  User  $user  The authenticated user creating the transaction.
     * @param  array{type: string, currency_code: string}  $data  Validated transaction data.
     * @param  string  $amountLocal  Local currency amount as a numeric string.
     * @return TellerAllocation|null The active teller allocation, or null for non-tellers.
     *
     * @throws AllocationValidationException When the active allocation cannot cover the transaction.
     */
    private function determineTellerAllocation(User $user, array $data, string $amountLocal): ?TellerAllocation
    {
        if (! $user->isTeller()) {
            return null;
        }

        if ($data['type'] === TransactionType::Buy->value) {
            $result = $this->tellerAllocationService->validateTransaction($user, $data['currency_code'], $amountLocal, true);

            if (! $result->valid) {
                throw new AllocationValidationException($result->reason);
            }

            /** @var TellerAllocation|null $allocation */
            $allocation = $result->allocation;

            return $allocation;
        }

        return $this->tellerAllocationService->getActiveAllocation($user, $data['currency_code']);
    }

    /**
     * Decide whether a transaction should start as Completed or PendingApproval.
     * Only a small transaction by a Low-risk customer auto-completes; anything
     * at/above the auto-approve threshold or any elevated risk needs approval.
     *
     * @param  string  $amountLocal  Local currency amount as a numeric string.
     * @param  bool  $holdRequired  Whether a compliance hold is required.
     * @param  RiskRating|null  $riskRating  Customer risk rating; null fails closed to approval.
     */
    private function determineInitialStatus(string $amountLocal, bool $holdRequired, ?RiskRating $riskRating): TransactionStatus
    {
        if ($holdRequired
            || $riskRating !== RiskRating::Low
            || $this->mathService->compare($amountLocal, $this->thresholdService->getAutoApproveThreshold()) >= 0) {
            return TransactionStatus::PendingApproval;
        }

        return TransactionStatus::Completed;
    }
}
