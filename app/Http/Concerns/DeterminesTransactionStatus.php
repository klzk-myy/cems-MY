<?php

namespace App\Http\Concerns;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Exceptions\Domain\AllocationValidationException;
use App\Models\TellerAllocation;
use App\Models\User;
use App\Services\Branch\TellerAllocationService;
use App\Services\System\MathService;
use App\Services\ThresholdService;

/**
 * Helpers for deciding teller allocation and initial transaction status
 * when assembling a TransactionCreationContext.
 *
 * Host classes must provide a constructor-injected `MathService` property
 * named `$mathService` (e.g. `protected MathService $mathService`).
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

        $service = app(TellerAllocationService::class);

        if ($data['type'] === TransactionType::Buy->value) {
            $result = $service->validateTransaction($user, $data['currency_code'], $amountLocal, true);

            if (! $result->valid) {
                throw new AllocationValidationException($result->reason);
            }

            /** @var TellerAllocation|null $allocation */
            $allocation = $result->allocation;

            return $allocation;
        }

        return $service->getActiveAllocation($user, $data['currency_code']);
    }

    /**
     * Decide whether a transaction should start as Completed or PendingApproval.
     *
     * @param  string  $amountLocal  Local currency amount as a numeric string.
     * @param  bool  $holdRequired  Whether a compliance hold is required.
     */
    private function determineInitialStatus(string $amountLocal, bool $holdRequired): TransactionStatus
    {
        $thresholdService = app(ThresholdService::class);

        if ($holdRequired || $this->mathService->compare($amountLocal, $thresholdService->getAutoApproveThreshold()) >= 0) {
            return TransactionStatus::PendingApproval;
        }

        return TransactionStatus::Completed;
    }
}
