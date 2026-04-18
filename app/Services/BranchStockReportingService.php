<?php

namespace App\Services;

use App\Enums\TellerAllocationStatus;
use App\Models\Branch;
use App\Models\BranchPool;
use App\Models\CounterSession;
use App\Models\TellerAllocation;

class BranchStockReportingService
{
    protected MathService $mathService;

    public function __construct()
    {
        $this->mathService = new MathService;
    }

    public function getBranchPoolSummary(Branch $branch): array
    {
        $pools = BranchPool::where('branch_id', $branch->id)->get();

        $poolData = $pools->map(function (BranchPool $pool) {
            $total = $this->mathService->add($pool->available_balance, $pool->allocated_balance);

            return [
                'currency' => $pool->currency_code,
                'available' => $pool->available_balance,
                'allocated' => $pool->allocated_balance,
                'total' => $total,
            ];
        })->values()->toArray();

        $totalMyriad = array_reduce($poolData, function (string $carry, array $pool) {
            return $this->mathService->add($carry, $pool['total']);
        }, '0.0000');

        return [
            'branch' => $branch,
            'pools' => $poolData,
            'total_myriad' => $totalMyriad,
        ];
    }

    public function getTellerAllocationsSummary(Branch $branch): array
    {
        $allocations = TellerAllocation::where('branch_id', $branch->id)->get();

        $pendingCount = $allocations->filter(fn ($a) => $a->status === TellerAllocationStatus::PENDING)->count();
        $activeCount = $allocations->filter(fn ($a) => $a->status === TellerAllocationStatus::ACTIVE)->count();
        $returnedCount = $allocations->filter(fn ($a) => in_array($a->status, [
            TellerAllocationStatus::RETURNED,
            TellerAllocationStatus::AUTO_RETURNED,
        ]))->count();

        $totalAllocated = $allocations->reduce(function (string $carry, TellerAllocation $allocation) {
            return $this->mathService->add($carry, $allocation->allocated_amount ?? '0.0000');
        }, '0.0000');

        $totalOutstanding = $allocations
            ->filter(fn ($a) => $a->status === TellerAllocationStatus::ACTIVE)
            ->reduce(function (string $carry, TellerAllocation $allocation) {
                return $this->mathService->add($carry, $allocation->current_balance ?? '0.0000');
            }, '0.0000');

        return [
            'pending_count' => $pendingCount,
            'active_count' => $activeCount,
            'returned_count' => $returnedCount,
            'total_allocated' => $totalAllocated,
            'total_outstanding' => $totalOutstanding,
        ];
    }

    public function getEodReport(Branch $branch, string $date): array
    {
        $poolSummary = $this->getBranchPoolSummary($branch);
        $allocationSummary = $this->getTellerAllocationsSummary($branch);

        $counterIds = $branch->counters()->pluck('id');

        $sessions = CounterSession::whereIn('counter_id', $counterIds)
            ->where('session_date', $date)
            ->with(['counter', 'user'])
            ->get()
            ->toArray();

        return [
            'branch' => $branch,
            'date' => $date,
            'pool_summary' => $poolSummary,
            'allocation_summary' => $allocationSummary,
            'sessions' => $sessions,
        ];
    }
}
