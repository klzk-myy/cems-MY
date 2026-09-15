<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Concerns\AuthorizesBranchResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TellerAllocation\ApproveAllocationRequest;
use App\Http\Requests\Api\V1\TellerAllocation\ModifyAllocationRequest;
use App\Http\Requests\Api\V1\TellerAllocation\MyActiveAllocationRequest;
use App\Http\Requests\Api\V1\TellerAllocation\RejectAllocationRequest;
use App\Http\Requests\Api\V1\TellerAllocation\RequestAllocationRequest;
use App\Models\Branch;
use App\Models\Counter;
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
    use AuthorizesBranchResource;

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

        $branch = $user->branch;

        if (! $branch instanceof Branch) {
            return $this->errorResponse('User has no assigned branch', [], 400);
        }

        $pending = $this->allocationService->getPendingAllocationsForBranch($branch);

        return $this->successResponse($pending);
    }

    /**
     * Get active allocations for the authenticated user's branch.
     * Manager/Admin only.
     */
    public function activeForBranch(): JsonResponse
    {
        $user = Auth::user();

        $branch = $user->branch;

        if (! $branch instanceof Branch) {
            return $this->errorResponse('User has no assigned branch', [], 400);
        }

        $active = $this->allocationService->getActiveAllocationsForBranch($branch);

        return $this->successResponse($active);
    }

    /**
     * Get a specific allocation.
     */
    public function show(int $allocationId): JsonResponse
    {
        // The model's $with already eager-loads user, branch, counter, approver.
        $allocation = TellerAllocation::find($allocationId);

        if (! $allocation) {
            return $this->notFoundResponse('Allocation not found');
        }

        $authorization = $this->authorizeBranchResource($allocation, 'access', 'Unauthorized access to this allocation');
        if ($authorization instanceof JsonResponse) {
            return $authorization;
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

        $authorization = $this->authorizeBranchResource($allocation, 'manage', 'Unauthorized access to this allocation');
        if ($authorization instanceof JsonResponse) {
            return $authorization;
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

        $data = array_key_exists('data', $result) ? $result['data'] : $result;

        return $this->successResponse($data, $result['message'] ?? 'Active allocation retrieved');
    }

    /**
     * Teller-initiated stock request. Creates a PENDING allocation that the
     * branch manager approves via POST /allocations/{id}/approve.
     */
    public function requestStock(RequestAllocationRequest $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user->role->canPerform(Permission::RequestStock)) {
            return $this->errorResponse('Only users with the Request Stock Allocations permission can request stock allocations', [], 403);
        }

        $validated = $request->validated();
        $counter = isset($validated['counter_id']) ? Counter::query()->find((int) $validated['counter_id']) : null;

        try {
            $allocation = $this->allocationService->requestAllocation(
                $user,
                $user,
                $validated['currency_code'],
                $validated['requested_amount'],
                null,
                $counter
            );
        } catch (\Exception $e) {
            Log::error('Failed to request allocation', ['error' => $e->getMessage(), 'user_id' => $user->id]);

            return $this->errorResponse($e->getMessage(), [], 400);
        }

        return $this->successResponse($allocation, 'Allocation request created', 201);
    }

    /**
     * Teller accepts an approved assignment, activating the allocation.
     * Owner only.
     */
    public function accept(int $allocationId): JsonResponse
    {
        $allocation = TellerAllocation::find($allocationId);

        if (! $allocation) {
            return $this->notFoundResponse('Allocation not found');
        }

        if ($allocation->user_id !== Auth::id()) {
            return $this->errorResponse('Only the assigned teller can accept this allocation', [], 403);
        }

        if (! $allocation->isApproved()) {
            return $this->errorResponse('Allocation is not in approved status', [], 400);
        }

        $this->allocationService->activateAllocation($allocation);

        return $this->successResponse($allocation->refresh(), 'Allocation activated');
    }

    /**
     * Teller returns an active allocation to the branch pool. Owner only —
     * managers use POST /allocations/{id}/return-to-pool.
     */
    public function requestReturn(int $allocationId): JsonResponse
    {
        $allocation = TellerAllocation::find($allocationId);

        if (! $allocation) {
            return $this->notFoundResponse('Allocation not found');
        }

        if ($allocation->user_id !== Auth::id()) {
            return $this->errorResponse('Only the assigned teller can return this allocation', [], 403);
        }

        if (! $allocation->isActive()) {
            return $this->errorResponse('Allocation is not active', [], 400);
        }

        $this->allocationService->returnToPool($allocation);

        return $this->successResponse($allocation->refresh(), 'Allocation returned to pool');
    }
}
