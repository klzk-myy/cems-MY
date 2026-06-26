<?php

namespace App\Services\Branch;

use App\Enums\TellerAllocationStatus;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\TellerAllocation;
use App\Models\User;
use App\Services\Contracts\TellerAllocationServiceInterface;
use App\Services\DTOs\AllocationValidationResult;
use App\Services\System\MathService;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TellerAllocationService implements TellerAllocationServiceInterface
{
    public function __construct(
        protected BranchPoolService $branchPoolService,
        protected MathService $mathService,
    ) {}

    public function requestAllocation(User $teller, User $approver, string $currencyCode, string $requestedAmount, ?string $dailyLimitMyr = null, ?Counter $counter = null): TellerAllocation
    {
        $branch = $teller->branch;

        if (! $branch) {
            throw new Exception('Teller must be assigned to a branch');
        }

        $pool = $this->branchPoolService->getOrCreateForBranch($branch, $currencyCode);

        if (! $pool->hasAvailable($requestedAmount)) {
            throw new Exception('Insufficient available balance in branch pool');
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
        return DB::transaction(function () use ($allocation, $approver, $approvedAmount, $dailyLimitMyr) {
            $locked = TellerAllocation::where('id', $allocation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isPending()) {
                throw new \RuntimeException('Allocation is not pending approval');
            }

            if (! $this->branchPoolService->allocateToTeller($locked->branch, $locked->currency_code, $approvedAmount)) {
                throw new Exception('Failed to allocate from branch pool');
            }

            $locked->approve($approver, $approvedAmount, $dailyLimitMyr);

            return $locked;
        });
    }

    public function activateAllocation(TellerAllocation $allocation): TellerAllocation
    {
        if (! $allocation->isApproved()) {
            throw new Exception('Can only activate approved allocation');
        }

        $allocation->activate();

        return $allocation;
    }

    public function modifyAllocation(TellerAllocation $allocation, User $modifier, string $newAmount, bool $isIncrease): TellerAllocation
    {
        return DB::transaction(function () use ($allocation, $newAmount, $isIncrease) {
            $locked = TellerAllocation::where('id', $allocation->id)
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                throw new \RuntimeException('Allocation no longer exists.');
            }

            $branch = $locked->branch;

            if ($isIncrease) {
                if (! $this->branchPoolService->allocateToTeller($branch, $locked->currency_code, $newAmount)) {
                    throw new Exception('Failed to allocate additional amount from branch pool');
                }
                $locked->current_balance = $this->mathService->add($locked->current_balance, $newAmount);
                $locked->allocated_amount = $this->mathService->add($locked->allocated_amount, $newAmount);
            } else {
                $availableToReturn = $this->mathService->subtract($locked->allocated_amount, $locked->current_balance);
                $returnAmount = $this->mathService->compare($newAmount, $availableToReturn) < 0 ? $newAmount : $availableToReturn;

                if ($this->mathService->compare($returnAmount, '0') > 0) {
                    $this->branchPoolService->deallocateFromTeller($branch, $locked->currency_code, $returnAmount);
                }

                $locked->allocated_amount = $this->mathService->subtract($locked->allocated_amount, $newAmount);
                $locked->current_balance = $this->mathService->subtract($locked->current_balance, $this->mathService->subtract($newAmount, $returnAmount));
            }

            $locked->save();

            return $locked;
        });
    }

    public function rejectAllocation(TellerAllocation $allocation, User $rejector, ?string $reason = null): TellerAllocation
    {
        if (! $allocation->isPending()) {
            throw new Exception('Can only reject pending allocations');
        }

        return DB::transaction(function () use ($allocation, $rejector, $reason) {
            // Deallocate any allocated amount back to pool
            if ($this->mathService->compare($allocation->allocated_amount, '0') > 0) {
                $this->branchPoolService->deallocateFromTeller(
                    $allocation->branch,
                    $allocation->currency_code,
                    $allocation->allocated_amount
                );
            }

            $allocation->reject($rejector, $reason);

            return $allocation;
        });
    }

    public function returnToPool(TellerAllocation $allocation): TellerAllocation
    {
        return DB::transaction(function () use ($allocation) {
            $locked = TellerAllocation::where('id', $allocation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status->value !== TellerAllocationStatus::ACTIVE->value) {
                throw new \RuntimeException('Allocation is not active');
            }

            $returnAmount = $locked->current_balance;

            if ($this->mathService->compare($returnAmount, '0') > 0) {
                $this->branchPoolService->deallocateFromTeller($locked->branch, $locked->currency_code, $returnAmount);
            }

            $locked->returnToPool();

            return $locked;
        });
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

    public function validateTransaction(User $teller, string $currencyCode, string $amountMyr, bool $isBuy): AllocationValidationResult
    {
        $allocation = $this->getActiveAllocation($teller, $currencyCode);

        if (! $allocation) {
            return new AllocationValidationResult(valid: false, reason: 'No active allocation for this currency');
        }

        if ($isBuy && ! $allocation->hasAvailable($amountMyr)) {
            return new AllocationValidationResult(valid: false, reason: 'Insufficient allocation balance');
        }

        if (! $isBuy && ! $allocation->hasAvailable($amountMyr)) {
            return new AllocationValidationResult(valid: false, reason: "No {$allocation->currency_code} balance available to sell");
        }

        if (! $allocation->hasDailyLimitRemaining($amountMyr)) {
            return new AllocationValidationResult(valid: false, reason: 'Daily limit exceeded');
        }

        return new AllocationValidationResult(valid: true, allocation: $allocation);
    }

    /**
     * Check if user has permission to approve/reject allocations.
     */
    public function canManageAllocations(User $user): bool
    {
        return $user->role->isManager() || $user->role->isAdmin();
    }

    /**
     * Get active allocation for a teller with currency validation.
     *
     * @return array Result with allocation or error
     */
    public function getActiveAllocationForTeller(User $teller, string $currencyCode): array
    {
        $allocation = $this->getActiveAllocation($teller, $currencyCode);

        if (! $allocation) {
            return [
                'success' => true,
                'data' => null,
                'message' => 'No active allocation found',
            ];
        }

        return [
            'success' => true,
            'data' => $allocation,
            'message' => null,
        ];
    }
}
