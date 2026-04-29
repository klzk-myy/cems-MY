<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TellerAllocation;
use App\Models\User;
use App\Services\TellerAllocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
    public function approve(Request $request, int $allocationId): JsonResponse
    {
        $user = Auth::user();

        if (! $user->role->isManager() && ! $user->role->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only managers and admins can approve allocations',
            ], 403);
        }

        $validated = $request->validate([
            'approved_amount' => 'required|numeric|min:0.0001',
            'daily_limit_myr' => 'nullable|numeric|min:0',
        ]);

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
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Reject a pending allocation.
     * Manager/Admin only.
     */
    public function reject(Request $request, int $allocationId): JsonResponse
    {
        $user = Auth::user();

        if (! $user->role->isManager() && ! $user->role->isAdmin()) {
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

        $validated = $request->validate([
            'rejection_reason' => 'nullable|string|max:500',
        ]);

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
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Modify an active allocation (increase/decrease).
     * Manager/Admin only.
     */
    public function modify(Request $request, int $allocationId): JsonResponse
    {
        $user = Auth::user();

        if (! $user->role->isManager() && ! $user->role->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only managers and admins can modify allocations',
            ], 403);
        }

        $validated = $request->validate([
            'new_amount' => 'required|numeric|min:0.0001',
            'is_increase' => 'required|boolean',
        ]);

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
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
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

        if (! $user->role->isManager() && ! $user->role->isAdmin()) {
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
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get active allocation for authenticated teller.
     */
    public function myActiveAllocation(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'currency_code' => 'required|string|size:3',
        ]);

        $allocation = $this->allocationService->getActiveAllocation($user, $validated['currency_code']);

        if (! $allocation) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'No active allocation found',
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $allocation,
        ]);
    }
}
