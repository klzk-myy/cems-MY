<?php

namespace App\Http\Controllers;

use App\Enums\TellerAllocationStatus;
use App\Models\TellerAllocation;
use App\Services\Branch\TellerAllocationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AllocationController extends Controller
{
    public function __construct(
        protected TellerAllocationService $allocationService,
    ) {}

    /**
     * List allocations visible to the authenticated manager/admin.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $status = $request->query('status', 'active');
        $branch = $user->branch;

        $query = TellerAllocation::with(['user', 'branch', 'approver', 'counter', 'currency'])
            ->when($branch, fn ($q) => $q->where('branch_id', $branch->id));

        match ($status) {
            'pending' => $query->where('status', TellerAllocationStatus::PENDING),
            'active' => $query->where('status', TellerAllocationStatus::ACTIVE),
            'completed' => $query->where('status', TellerAllocationStatus::CLOSED),
            'rejected' => $query->where('status', TellerAllocationStatus::REJECTED),
            default => $query,
        };

        $allocations = $query->latest()->paginate(25);

        return view('allocations.index', compact('allocations', 'status'));
    }

    /**
     * Show a single allocation.
     */
    public function show(TellerAllocation $allocation): View
    {
        $allocation->load(['user', 'branch', 'approver', 'counter', 'currency']);

        return view('allocations.show', compact('allocation'));
    }
}
