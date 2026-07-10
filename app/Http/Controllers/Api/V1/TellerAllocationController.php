<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TellerAllocation\ApproveAllocationRequest;
use App\Http\Requests\Api\V1\TellerAllocation\ModifyAllocationRequest;
use App\Http\Requests\Api\V1\TellerAllocation\MyActiveAllocationRequest;
use App\Http\Requests\Api\V1\TellerAllocation\RejectAllocationRequest;
use App\Models\TellerAllocation;
use App\Services\Branch\TellerAllocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * TellerAllocationController API v1
 *
 * Handles teller allocation requests and approvals.
 * Part of the daily branch opening workflow.
 */
class TellerAllocationController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TellerAllocationService $allocationService
    ) {}

    /**
     * Get pending allocations for the authenticated user's branch.
     * Manager/Admin only.
     */
    public function pendingForBranch(): JsonResponse
    {
        $user = Auth::user();

        if (! $user->branch) {
            return $this->errorResponse('User has no assigned branch', [], 400);
        }

        $pending = $this->allocationService->getPendingAllocationsForBranch($user->branch);

        return $this->successResponse($pending);
    }

    /**
     * Get active allocations for the authenticated user's branch.
     * Manager/Admin only.
     */
    public function activeForBranch(): JsonResponse
    {
        $user = Auth::user();

        if (! $user->branch) {
            return $this->errorResponse('User has no assigned branch', [], 400);
        }

        $active = $this->allocationService->getActiveAllocationsForBranch($user->branch);

        return $this->successResponse($active);
    }

    /**
     * Get a specific allocation.
     */
    public function show(int $allocationId): JsonResponse
    {
        $allocation = TellerAllocation::with(['user', 'branch', 'counter'])->find($allocationId);

        if (! $allocation) {
            return $this->notFoundResponse('Allocation not found');
        }

        return $this->successResponse($allocation);
    }

    /**
     * Approve a pending allocation.
     * Manager/Admin only.
     */
    public function approve(ApproveAllocationRequest $request, int $allocationId): JsonResponse
    {
        $user = Auth::user();

        if (! $this->allocationService->canManageAllocations($user)) {
            return $this->errorResponse('Only managers and admins can approve allocations', [], 403);
        }

        $validated = $request->validated();

        $allocation = TellerAllocation::find($allocationId);

        if (! $allocation) {
            return $this->notFoundResponse('Allocation not found');
        }

        if (! $allocation->isPending()) {
            return $this->errorResponse('Allocation is not in pending status', [], 400);
        }

        try {
            $allocation = $this->allocationService->approveAllocation(
                $allocation,
                $user,
                $validated['approved_amount'],
                $validated['daily_limit_myr'] ?? null
            );

            return $this->successResponse($allocation, 'Allocation approved successfully');
        } catch (\Exception $e) {
            Log::error('Failed to approve allocation', ['error' => $e->getMessage(), 'user_id' => auth()->id()]);

            return $this->errorResponse('Operation failed. Please contact support.', [], 400);
        }
    }

    /**
     * Reject a pending allocation.
     * Manager/Admin only.
     */
    public function reject(RejectAllocationRequest $request, int $allocationId): JsonResponse
    {
        $user = Auth::user();

        if (! $this->allocationService->canManageAllocations($user)) {
            return $this->errorResponse('Only managers and admins can reject allocations', [], 403);
        }

        $allocation = TellerAllocation::find($allocationId);

        if (! $allocation) {
            return $this->notFoundResponse('Allocation not found');
        }

        if (! $allocation->isPending()) {
            return $this->errorResponse('Allocation is not in pending status', [], 400);
        }

        $validated = $request->validated();

        try {
            $allocation = $this->allocationService->rejectAllocation(
                $allocation,
                $user,
                $validated['rejection_reason'] ?? null
            );

            return $this->successResponse($allocation, 'Allocation rejected');
        } catch (\Exception $e) {
            Log::error('Failed to reject allocation', ['error' => $e->getMessage(), 'user_id' => auth()->id()]);

            return $this->errorResponse('Operation failed. Please contact support.', [], 400);
        }
    }

    /**
     * Modify an active allocation (increase/decrease).
     * Manager/Admin only.
     */
    public function modify(ModifyAllocationRequest $request, int $allocationId): JsonResponse
    {
        $user = Auth::user();

        if (! $this->allocationService->canManageAllocations($user)) {
            return $this->errorResponse('Only managers and admins can modify allocations', [], 403);
        }

        $validated = $request->validated();

        $allocation = TellerAllocation::find($allocationId);

        if (! $allocation) {
            return $this->notFoundResponse('Allocation not found');
        }

        if (! $allocation->isActive()) {
            return $this->errorResponse('Only active allocations can be modified', [], 400);
        }

        try {
            $allocation = $this->allocationService->modifyAllocation(
                $allocation,
                $user,
                $validated['new_amount'],
                $validated['is_increase']
            );

            return $this->successResponse($allocation, 'Allocation modified successfully');
        } catch (\Exception $e) {
            Log::error('Failed to modify allocation', ['error' => $e->getMessage(), 'user_id' => auth()->id()]);

            return $this->errorResponse('Operation failed. Please contact support.', [], 400);
        }
    }

    /**
     * Return allocation to pool (end of day).
     * Manager/Admin only.
     */
    public function returnToPool(int $allocationId): JsonResponse
    {
        $user = Auth::user();

        if (! $this->allocationService->canManageAllocations($user)) {
            return $this->errorResponse('Only managers and admins can return allocations to pool', [], 403);
        }

        $allocation = TellerAllocation::find($allocationId);

        if (! $allocation) {
            return $this->notFoundResponse('Allocation not found');
        }

        try {
            $this->allocationService->returnToPool($allocation);

            return $this->successResponse($allocation, 'Allocation returned to pool');
        } catch (\Exception $e) {
            Log::error('Failed to return allocation to pool', ['error' => $e->getMessage(), 'user_id' => auth()->id()]);

            return $this->errorResponse('Operation failed. Please contact support.', [], 400);
        }
    }

    /**
     * Get active allocation for authenticated teller.
     */
    public function myActiveAllocation(MyActiveAllocationRequest $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validated();

        $result = $this->allocationService->getActiveAllocationForTeller($user, $validated['currency_code']);

        // Service returns a pre-shaped envelope (success/data/message); keep passthrough to preserve consumer contract.
        return response()->json($result);
    }
}
