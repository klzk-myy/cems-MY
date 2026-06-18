<?php

namespace App\Services\Contracts;

use App\Models\Branch;
use App\Models\Counter;
use App\Models\TellerAllocation;
use App\Models\User;
use Illuminate\Support\Collection;

interface TellerAllocationServiceInterface
{
    public function requestAllocation(User $teller, User $approver, string $currencyCode, string $requestedAmount, ?string $dailyLimitMyr = null, ?Counter $counter = null): TellerAllocation;

    public function approveAllocation(TellerAllocation $allocation, User $approver, string $approvedAmount, ?string $dailyLimitMyr = null): TellerAllocation;

    public function activateAllocation(TellerAllocation $allocation): TellerAllocation;

    public function modifyAllocation(TellerAllocation $allocation, User $modifier, string $newAmount, bool $isIncrease): TellerAllocation;

    public function rejectAllocation(TellerAllocation $allocation, User $rejector, ?string $reason = null): TellerAllocation;

    public function returnToPool(TellerAllocation $allocation): TellerAllocation;

    public function forceReturnAllOpen(): int;

    public function getActiveAllocation(User $teller, string $currencyCode): ?TellerAllocation;

    public function getPendingAllocationsForBranch(Branch $branch): Collection;

    public function getActiveAllocationsForBranch(Branch $branch): Collection;

    public function transferToTeller(TellerAllocation $allocation, User $toTeller): TellerAllocation;

    public function validateTransaction(User $teller, string $currencyCode, string $amountMyr, bool $isBuy): array;

    public function canManageAllocations(User $user): bool;

    public function getActiveAllocationForTeller(User $teller, string $currencyCode): array;
}
