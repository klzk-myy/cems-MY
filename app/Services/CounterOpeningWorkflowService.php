<?php

namespace App\Services;

use App\Enums\TellerAllocationStatus;
use App\Exceptions\Domain\InsufficientPoolBalanceException;
use App\Exceptions\Domain\PendingAllocationNotFoundException;
use App\Exceptions\Domain\TellerBranchRequiredException;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\CounterSession;
use App\Models\TellerAllocation;
use App\Models\User;

class CounterOpeningWorkflowService
{
    public function __construct(
        protected BranchPoolService $branchPoolService,
        protected TellerAllocationService $tellerAllocationService,
        protected CounterService $counterService,
    ) {}

    public function initiateOpeningRequest(User $teller, Counter $counter, array $requestedAmounts): array
    {
        $branch = $teller->branch;

        if (! $branch) {
            throw new TellerBranchRequiredException;
        }

        $requests = [];
        foreach ($requestedAmounts as $currency => $amount) {
            $pool = $this->branchPoolService->getOrCreateForBranch($branch, $currency);

            if (! $pool->hasAvailable($amount)) {
                throw new InsufficientPoolBalanceException($currency, $pool->available_balance, $amount);
            }

            $allocation = $this->tellerAllocationService->requestAllocation(
                $teller,
                $teller,
                $currency,
                $amount,
                null,
                $counter
            );

            $requests[] = $allocation;
        }

        return $requests;
    }

    public function approveAndOpen(User $manager, Counter $counter, User $teller, array $approvedAmounts, array $dailyLimits = []): CounterSession
    {
        $today = now()->toDateString();

        $tellerAllocations = [];
        foreach ($approvedAmounts as $currency => $amount) {
            $allocation = TellerAllocation::where('user_id', $teller->id)
                ->where('currency_code', $currency)
                ->whereDate('session_date', $today)
                ->where('status', TellerAllocationStatus::PENDING->value)
                ->first();

            if (! $allocation) {
                throw new PendingAllocationNotFoundException($currency);
            }

            $dailyLimit = $dailyLimits[$currency] ?? null;
            $this->tellerAllocationService->approveAllocation($allocation, $manager, $amount, $dailyLimit);
            $this->tellerAllocationService->activateAllocation($allocation);

            $tellerAllocations[] = $allocation;
        }

        $openingFloats = [];
        foreach ($tellerAllocations as $allocation) {
            $openingFloats[] = [
                'currency_id' => $allocation->currency_code,
                'amount' => $allocation->current_balance,
            ];
        }

        $session = $this->counterService->openSession($counter, $teller, $openingFloats);

        foreach ($tellerAllocations as $allocation) {
            $allocation->update(['counter_id' => $counter->id]);
        }

        return $session;
    }

    public function getPendingRequestsForBranch(Branch $branch): array
    {
        $pending = $this->tellerAllocationService->getPendingAllocationsForBranch($branch);

        return $pending->groupBy('user_id')->toArray();
    }
}
