<?php

namespace App\Http\Concerns;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\User;
use App\Services\Branch\TellerAllocationService;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use Illuminate\Database\Eloquent\Model;

/**
 * Helpers for deciding teller allocation and initial transaction status
 * when assembling a TransactionCreationContext.
 */
trait DeterminesTransactionStatus
{
    /**
     * Determine the teller allocation to attach to a new transaction.
     *
     * @param  User  $user  The authenticated user creating the transaction.
     * @param  array{type: string, currency_code: string}  $data  Validated transaction data.
     * @param  string  $amountLocal  Local currency amount as a numeric string.
     * @return Model|null The active teller allocation, or null for non-tellers.
     */
    private function determineTellerAllocation(User $user, array $data, string $amountLocal): ?Model
    {
        if (! $user->isTeller()) {
            return null;
        }

        $service = app(TellerAllocationService::class);

        if ($data['type'] === TransactionType::Buy->value) {
            $result = $service->validateTransaction($user, $data['currency_code'], $amountLocal, true);

            if (! $result->valid) {
                throw new \InvalidArgumentException($result->reason);
            }

            return $result->allocation;
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
        $mathService = app(MathService::class);
        $thresholdService = app(ThresholdService::class);

        if ($holdRequired || $mathService->compare($amountLocal, $thresholdService->getAutoApproveThreshold()) >= 0) {
            return TransactionStatus::PendingApproval;
        }

        return TransactionStatus::Completed;
    }
}
