<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AuthorizesManager;
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
    use AuthorizesManager;

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
     * Handle an allocation action with common guard scaffolding.
     */
    private function handleAllocationAction(
        int $allocationId,
        string $actionName,
        callable $operation,
        ?string $statusCheck = null
    ): JsonResponse {
        $user = Auth::user();

        if (! $this->allocationService->canManageAllocations($user)) {
            return $this->errorResponse("Only managers and admins can {$actionName} allocations", [], 403);
        }

        $allocation = TellerAllocation::find($allocationId);

        if (! $allocation) {
            return $this->notFoundResponse('Allocation not found');
        }

        if ($statusCheck && ! $allocation->{$statusCheck}()) {
            return $this->errorResponse('Allocation is not in the required status', [], 400);
        }

        try {
            return $this->successResponse($operation($allocation, $user));
        } catch (\Exception $e) {
            Log::error("Failed to {$actionName} allocation", ['error' => $e->getMessage(), 'user_id' => auth()->id()]);

            return $this->errorResponse('Operation failed. Please contact support.', [], 400);
        }
    }

    /**
     * Approve a pending allocation.
     * Manager/Admin only.
     */
    public function approve(ApproveAllocationRequest $request, int $allocationId): JsonResponse
    {
        return $this->handleAllocationAction(
            allocationId: $allocationId,
            actionName: 'approve',
            statusCheck: 'isPending',
            operation: fn ($allocation, $user) => $this->allocationService->approveAllocation(
                $allocation,
                $user,
                $request->validated()['approved_amount'],
                $request->validated()['daily_limit_myr'] ?? null
            )
        );
    }

    /**
     * Reject a pending allocation.
     * Manager/Admin only.
     */
    public function reject(RejectAllocationRequest $request, int $allocationId): JsonResponse
    {
        return $this->handleAllocationAction(
            allocationId: $allocationId,
            actionName: 'reject',
            statusCheck: 'isPending',
            operation: fn ($allocation, $user) => $this->allocationService->rejectAllocation(
                $allocation,
                $user,
                $request->validated()['rejection_reason'] ?? null
            )
        );
    }

    /**
     * Modify an active allocation (increase/decrease).
     * Manager/Admin only.
     */
    public function modify(ModifyAllocationRequest $request, int $allocationId): JsonResponse
    {
        return $this->handleAllocationAction(
            allocationId: $allocationId,
            actionName: 'modify',
            statusCheck: 'isActive',
            operation: fn ($allocation, $user) => $this->allocationService->modifyAllocation(
                $allocation,
                $user,
                $request->validated()['new_amount'],
                $request->validated()['is_increase']
            )
        );
    }

    /**
     * Return allocation to pool (end of day).
     * Manager/Admin only.
     */
    public function returnToPool(int $allocationId): JsonResponse
    {
        return $this->handleAllocationAction(
            allocationId: $allocationId,
            actionName: 'return to pool',
            operation: function ($allocation, $user) {
                $this->allocationService->returnToPool($allocation);

                return $allocation;
            }
        );
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
