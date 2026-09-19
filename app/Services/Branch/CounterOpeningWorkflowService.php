<?php

namespace App\Services\Branch;

use App\Enums\TellerAllocationStatus;
use App\Exceptions\Domain\InsufficientPoolBalanceException;
use App\Exceptions\Domain\PendingAllocationNotFoundException;
use App\Exceptions\Domain\TellerBranchRequiredException;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\CounterSession;
use App\Models\TellerAllocation;
use App\Models\User;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CounterOpeningWorkflowService
{
    public function __construct(
        protected BranchPoolService $branchPoolService,
        protected TellerAllocationService $tellerAllocationService,
        protected CounterService $counterService,
        protected AuditService $auditService,
    ) {}

    public function initiateOpeningRequest(User $teller, Counter $counter, array $requestedQuantities): array
    {
        $branch = $teller->branch;

        if (! $branch instanceof Branch) {
            throw new TellerBranchRequiredException;
        }

        $requests = DB::transaction(function () use ($branch, $teller, $requestedQuantities, $counter) {
            $requests = [];
            foreach ($requestedQuantities as $currency => $quantity) {
                $pool = $this->branchPoolService->getOrCreateForBranch($branch, $currency);

                if (! $pool->hasAvailable($quantity)) {
                    throw new InsufficientPoolBalanceException($currency, (string) $pool->available_balance, $quantity);
                }

                $allocation = $this->tellerAllocationService->requestAllocation(
                    $teller,
                    $teller,
                    $currency,
                    $quantity,
                    null,
                    $counter
                );

                $requests[] = $allocation;
            }

            return $requests;
        });

        return $requests;
    }

    public function approveAndOpen(User $manager, Counter $counter, User $teller, array $approvedQuantities, array $dailyLimits = []): CounterSession
    {
        return DB::transaction(function () use ($manager, $counter, $teller, $approvedQuantities, $dailyLimits) {
            $today = now()->toDateString();
            $currencyCodes = array_keys($approvedQuantities);

            $allocations = TellerAllocation::where('user_id', $teller->id)
                ->where('branch_id', $teller->branch_id)
                ->where('counter_id', $counter->id)
                ->whereIn('currency_code', $currencyCodes)
                ->where('status', TellerAllocationStatus::Pending->value)
                ->whereDate('session_date', '<=', Carbon::parse($today)->toDateString())
                ->orderByDesc('session_date')
                ->get()
                ->unique('currency_code')
                ->keyBy('currency_code');

            $tellerAllocations = [];
            foreach ($approvedQuantities as $currency => $quantity) {
                $allocation = $allocations->get($currency);
                if (! $allocation) {
                    throw new PendingAllocationNotFoundException($currency);
                }

                $dailyLimit = $dailyLimits[$currency] ?? null;
                $allocation = $this->tellerAllocationService->approveAllocation($allocation, $manager, $quantity, $dailyLimit);
                $this->tellerAllocationService->activateAllocation($allocation);

                $tellerAllocations[] = $allocation;
            }

            $openingFloats = [];
            foreach ($tellerAllocations as $allocation) {
                $openingFloats[] = [
                    'currency_id' => $allocation->currency_code,
                    'quantity' => $allocation->current_quantity,
                ];
            }

            $session = $this->counterService->openSession($counter, $teller, $openingFloats);

            foreach ($tellerAllocations as $allocation) {
                $allocation->update(['counter_id' => $counter->id]);
            }

            // Audit: Manager approval of teller allocation and counter session opening
            $this->auditService->log(
                'counter_allocation_approved',
                $manager->id,
                'CounterSession',
                $session->id,
                [],
                [
                    'teller_id' => $teller->id,
                    'counter_id' => $counter->id,
                    'approved_quantities' => $approvedQuantities,
                    'daily_limits' => $dailyLimits,
                    'action' => 'manager_approved_and_opened',
                ]
            );

            return $session;
        });
    }

    public function getPendingRequestsForBranch(Branch $branch): array
    {
        $pending = $this->tellerAllocationService->getPendingAllocationsForBranch($branch);

        return $pending->groupBy('user_id')->toArray();
    }
}
