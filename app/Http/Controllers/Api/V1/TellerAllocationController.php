<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ApproveAllocationRequest;
use App\Http\Requests\Api\V1\ModifyAllocationRequest;
use App\Http\Requests\Api\V1\MyActiveAllocationRequest;
use App\Http\Requests\Api\V1\RejectAllocationRequest;
use App\Models\TellerAllocation;
use App\Models\User;
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
    protected TellerAllocationService $allocationService;

    public function __construct(TellerAllocationService $allocationService)
    {
        $this->allocationService = $allocationService;
    }

    /**
     * Get pending allocations for the authenticated user's branch.
     * Manager/Admin only.
     */
    public function pendingForBranch(): JsonResponse
    {
        $user = Auth::user();

        if (! $user->branch) {
            return response()->json([
                'success' => false,
                'message' => 'User has no assigned branch',
            ], 400);
        }

        $pending = $this->allocationService->getPendingAllocationsForBranch($user->branch);

        return response()->json([
            'success' => true,
            'data' => $pending,
        ]);
    }

    /**
     * Get active allocations for the authenticated user's branch.
     * Manager/Admin only.
     */
    public function activeForBranch(): JsonResponse
    {
        $user = Auth::user();

        if (! $user->branch) {
            return response()->json([
                'success' => false,
                'message' => 'User has no assigned branch',
            ], 400);
        }

        $active = $this->allocationService->getActiveAllocationsForBranch($user->branch);

        return response()->json([
            'success' => true,
            'data' => $active,
        ]);
    }

    /**
     * Get a specific allocation.
     */
    public function show(int $allocationId): JsonResponse
    {
        $allocation = TellerAllocation::with(['user', 'branch', 'counter'])->find($allocationId);

        if (! $allocation) {
            return response()->json([
                'success' => false,
                'message' => 'Allocation not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $allocation,
        ]);
    }

    /**
     * Approve a pending allocation.
     * Manager/Admin only.
     */
    public function approve(ApproveAllocationRequest $request, int $allocationId): JsonResponse
    {
        $user = Auth::user();

        if (! $this->allocationService->canManageAllocations($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Only managers and admins can approve allocations',
            ], 403);
        }

        $validated = $request->validated();

        $allocation = TellerAllocation::find($allocationId);

        if (! $allocation) {
            return response()->json([
                'success' => false,
                'message' => 'Allocation not found',
            ], 404);
        }

        if (! $allocation->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Allocation is not in pending status',
            ], 400);
        }

        try {
            $allocation = $this->allocationService->approveAllocation(
                $allocation,
                $user,
                $validated['approved_amount'],
                $validated['daily_limit_myr'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Allocation approved successfully',
                'data' => $allocation,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to approve allocation', ['error' => $e->getMessage(), 'user_id' => auth()->id()]);

            return response()->json([
                'success' => false,
                'message' => 'Operation failed. Please contact support.',
            ], 400);
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
            return response()->json([
                'success' => false,
                'message' => 'Only managers and admins can reject allocations',
            ], 403);
        }

        $allocation = TellerAllocation::find($allocationId);

        if (! $allocation) {
            return response()->json([
                'success' => false,
                'message' => 'Allocation not found',
            ], 404);
        }

        if (! $allocation->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Allocation is not in pending status',
            ], 400);
        }

        $validated = $request->validated();

        try {
            $allocation = $this->allocationService->rejectAllocation(
                $allocation,
                $user,
                $validated['rejection_reason'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Allocation rejected',
                'data' => $allocation,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to reject allocation', ['error' => $e->getMessage(), 'user_id' => auth()->id()]);

            return response()->json([
                'success' => false,
                'message' => 'Operation failed. Please contact support.',
            ], 400);
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
            return response()->json([
                'success' => false,
                'message' => 'Only managers and admins can modify allocations',
            ], 403);
        }

        $validated = $request->validated();

        $allocation = TellerAllocation::find($allocationId);

        if (! $allocation) {
            return response()->json([
                'success' => false,
                'message' => 'Allocation not found',
            ], 404);
        }

        if (! $allocation->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Only active allocations can be modified',
            ], 400);
        }

        try {
            $allocation = $this->allocationService->modifyAllocation(
                $allocation,
                $user,
                $validated['new_amount'],
                $validated['is_increase']
            );

            return response()->json([
                'success' => true,
                'message' => 'Allocation modified successfully',
                'data' => $allocation,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to modify allocation', ['error' => $e->getMessage(), 'user_id' => auth()->id()]);

            return response()->json([
                'success' => false,
                'message' => 'Operation failed. Please contact support.',
            ], 400);
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
            return response()->json([
                'success' => false,
                'message' => 'Only managers and admins can return allocations to pool',
            ], 403);
        }

        $allocation = TellerAllocation::find($allocationId);

        if (! $allocation) {
            return response()->json([
                'success' => false,
                'message' => 'Allocation not found',
            ], 404);
        }

        try {
            $this->allocationService->returnToPool($allocation);

            return response()->json([
                'success' => true,
                'message' => 'Allocation returned to pool',
                'data' => $allocation,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to return allocation to pool', ['error' => $e->getMessage(), 'user_id' => auth()->id()]);

            return response()->json([
                'success' => false,
                'message' => 'Operation failed. Please contact support.',
            ], 400);
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

        return response()->json($result);
    }
}
