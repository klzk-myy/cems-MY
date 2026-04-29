<?php

namespace App\Services;

use App\Enums\TellerAllocationStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Exceptions\Domain\InsufficientPoolBalanceException;
use App\Exceptions\Domain\InvalidAllocationStateException;
use App\Exceptions\Domain\PoolAllocationException;
use App\Exceptions\Domain\TellerBranchRequiredException;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\TellerAllocation;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Collection;

class TellerAllocationService
{
    public function __construct(
        protected BranchPoolService $branchPoolService,
        protected MathService $mathService,
    ) {}

    public function requestAllocation(User $teller, User $approver, string $currencyCode, string $requestedAmount, ?string $dailyLimitMyr = null, ?Counter $counter = null): TellerAllocation
    {
        $branch = $teller->branch;

        if (! $branch) {
            throw new TellerBranchRequiredException;
        }

        $pool = $this->branchPoolService->getOrCreateForBranch($branch, $currencyCode);

        if (! $pool->hasAvailable($requestedAmount)) {
            throw new InsufficientPoolBalanceException('branch_pool', '0', 'requested');
        }

        $allocationData = [
            'user_id' => $teller->id,
            'branch_id' => $branch->id,
            'counter_id' => $counter?->id,
            'currency_code' => $currencyCode,
            'requested_amount' => $requestedAmount,
            'allocated_amount' => $requestedAmount,
            'current_balance' => 0,
            'daily_used_myr' => 0,
            'status' => TellerAllocationStatus::PENDING->value,
            'session_date' => now()->toDateString(),
        ];

        if ($dailyLimitMyr !== null) {
            $allocationData['daily_limit_myr'] = $dailyLimitMyr;
        }

        $allocation = TellerAllocation::create($allocationData);

        return $allocation;
    }

    public function approveAllocation(TellerAllocation $allocation, User $approver, string $approvedAmount, ?string $dailyLimitMyr = null): TellerAllocation
    {
        $branch = $allocation->branch;

        if (! $this->branchPoolService->allocateToTeller($branch, $allocation->currency_code, $approvedAmount)) {
            throw new PoolAllocationException;
        }

        $allocation->approve($approver, $approvedAmount, $dailyLimitMyr);

        return $allocation;
    }

    public function activateAllocation(TellerAllocation $allocation): TellerAllocation
    {
        if (! $allocation->isApproved()) {
            throw new InvalidAllocationStateException;
        }

        $allocation->activate();

        return $allocation;
    }

    public function modifyAllocation(TellerAllocation $allocation, User $modifier, string $newAmount, bool $isIncrease): TellerAllocation
    {
        $branch = $allocation->branch;

        if ($isIncrease) {
            if (! $this->branchPoolService->allocateToTeller($branch, $allocation->currency_code, $newAmount)) {
                throw new PoolAllocationException('Failed to allocate additional amount from branch pool');
            }
            $allocation->current_balance = $this->mathService->add($allocation->current_balance, $newAmount);
            $allocation->allocated_amount = $this->mathService->add($allocation->allocated_amount, $newAmount);
        } else {
            $pendingUsage = $this->calculatePendingTransactionUsage($allocation);
            $newAllocation = $this->mathService->subtract($allocation->allocated_amount, $newAmount);

            if ($this->mathService->compare($newAllocation, $pendingUsage) < 0) {
                throw new PoolAllocationException(
                    "Cannot reduce allocation to {$newAllocation}: {$pendingUsage} MYR required for pending transactions"
                );
            }

            $availableToReturn = $this->mathService->subtract($allocation->allocated_amount, $allocation->current_balance);
            $returnAmount = $this->mathService->compare($newAmount, $availableToReturn) < 0 ? $newAmount : $availableToReturn;

            if ($this->mathService->compare($returnAmount, '0') > 0) {
                $this->branchPoolService->deallocateFromTeller($branch, $allocation->currency_code, $returnAmount);
            }

            $allocation->allocated_amount = $this->mathService->subtract($allocation->allocated_amount, $newAmount);
            $allocation->current_balance = $this->mathService->subtract($allocation->current_balance, $this->mathService->subtract($newAmount, $returnAmount));
        }

        $allocation->save();

        return $allocation;
    }

    private function calculatePendingTransactionUsage(TellerAllocation $allocation): string
    {
        $pendingTransactions = Transaction::where('user_id', $allocation->user_id)
            ->where('currency_code', $allocation->currency_code)
            ->where('status', '!=', TransactionStatus::Completed->value)
            ->where('status', '!=', TransactionStatus::Cancelled->value)
            ->where('status', '!=', TransactionStatus::Failed->value)
            ->whereDate('created_at', now()->toDateString())
            ->get();

        $pendingTotal = '0';
        foreach ($pendingTransactions as $tx) {
            if ($tx->type === TransactionType::Buy) {
                $pendingTotal = $this->mathService->add($pendingTotal, $tx->amount_local);
            }
        }

        return $pendingTotal;
    }

    public function rejectAllocation(TellerAllocation $allocation, User $rejector, ?string $reason = null): TellerAllocation
    {
        $pool = $this->branchPoolService->getOrCreateForBranch($allocation->branch, $allocation->currency_code);
        $pool->releaseFunds($allocation->allocated_amount);

        $allocation->update([
            'status' => TellerAllocationStatus::REJECTED,
            'rejected_at' => now(),
            'rejected_by' => $rejector->id,
            'rejection_reason' => $reason,
        ]);

        return $allocation;
    }

    public function returnToPool(TellerAllocation $allocation): TellerAllocation
    {
        $branch = $allocation->branch;

        $returnAmount = $allocation->current_balance;

        if ($this->mathService->compare($returnAmount, '0') > 0) {
            $this->branchPoolService->deallocateFromTeller($branch, $allocation->currency_code, $returnAmount);
        }

        $allocation->returnToPool();

        return $allocation;
    }

    public function forceReturnAllOpen(): int
    {
        $openAllocations = TellerAllocation::where('status', TellerAllocationStatus::ACTIVE->value)
            ->whereDate('session_date', '<', now()->toDateString())
            ->get();

        foreach ($openAllocations as $allocation) {
            $this->returnToPool($allocation);
            $allocation->forceReturn();
        }

        return $openAllocations->count();
    }

    public function getActiveAllocation(User $teller, string $currencyCode): ?TellerAllocation
    {
        return TellerAllocation::where('user_id', $teller->id)
            ->where('currency_code', $currencyCode)
            ->where('status', TellerAllocationStatus::ACTIVE->value)
            ->whereDate('session_date', now()->toDateString())
            ->first();
    }

    public function getPendingAllocationsForBranch(Branch $branch): Collection
    {
        return TellerAllocation::where('branch_id', $branch->id)
            ->where('status', TellerAllocationStatus::PENDING->value)
            ->whereDate('session_date', now()->toDateString())
            ->with('user')
            ->get();
    }

    public function getActiveAllocationsForBranch(Branch $branch): Collection
    {
        return TellerAllocation::where('branch_id', $branch->id)
            ->where('status', TellerAllocationStatus::ACTIVE->value)
            ->whereDate('session_date', now()->toDateString())
            ->with('user')
            ->get();
    }

    public function transferToTeller(TellerAllocation $allocation, User $toTeller): TellerAllocation
    {
        $allocation->update([
            'user_id' => $toTeller->id,
        ]);

        return $allocation;
    }

    public function validateTransaction(User $teller, string $currencyCode, string $amountMyr, bool $isBuy): array
    {
        $allocation = $this->getActiveAllocation($teller, $currencyCode);

        if (! $allocation) {
            return ['valid' => false, 'reason' => 'No active allocation for this currency'];
        }

        if ($isBuy) {
            if (! $allocation->hasAvailable($amountMyr)) {
                return ['valid' => false, 'reason' => 'Insufficient allocation balance'];
            }
        } else {
            // For Sell, verify teller actually has currency allocated to sell
            if ($this->mathService->compare($allocation->current_balance, '0') <= 0) {
                return ['valid' => false, 'reason' => "No {$currencyCode} balance available to sell"];
            }
        }

        if (! $allocation->hasDailyLimitRemaining($amountMyr)) {
            return ['valid' => false, 'reason' => 'Daily limit exceeded'];
        }

        return ['valid' => true, 'allocation' => $allocation];
    }
}
