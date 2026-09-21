<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Exceptions\Domain\DomainException;
use App\Exceptions\Domain\PermissionDeniedException;
use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Concerns\AuthorizesBranchResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TellerAllocation\ApproveAllocationRequest;
use App\Http\Requests\Api\V1\TellerAllocation\ModifyAllocationRequest;
use App\Http\Requests\Api\V1\TellerAllocation\MyActiveAllocationRequest;
use App\Http\Requests\Api\V1\TellerAllocation\RejectAllocationRequest;
use App\Http\Requests\Api\V1\TellerAllocation\RequestAllocationRequest;
use App\Http\Resources\Api\V1\TellerAllocationResource;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\TellerAllocation;
use App\Services\Branch\TellerAllocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

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
            return $this->errorResponse('User has no assigned branch', [], 422);
        }

        $pending = $this->allocationService->getPendingAllocationsForBranch($branch);

        return $this->successResponse(TellerAllocationResource::collection($pending));
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
            return $this->errorResponse('User has no assigned branch', [], 422);
        }

        $active = $this->allocationService->getActiveAllocationsForBranch($branch);

        return $this->successResponse(TellerAllocationResource::collection($active));
    }

    /**
     * Get a specific allocation.
     */
    public function show(int $allocationId): JsonResponse
    {
        $allocation = TellerAllocation::with(TellerAllocation::API_RELATIONS)->find($allocationId);

        if (! $allocation) {
            return $this->notFoundResponse('Allocation not found');
        }

        $this->authorizeBranchResource($allocation, 'access', 'Unauthorized access to this allocation');

        return $this->successResponse(new TellerAllocationResource($allocation));
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
            throw new PermissionDeniedException("{$actionName} allocations");
        }

        $allocation = TellerAllocation::find($allocationId);

        if (! $allocation) {
            return $this->notFoundResponse('Allocation not found');
        }

        $this->authorizeBranchResource($allocation, 'manage', 'Unauthorized access to this allocation');

        if ($statusCheck && ! $allocation->{$statusCheck}()) {
            return $this->errorResponse('Allocation is not in the required status', [], 409);
        }

        try {
            $result = $operation($allocation, $user);

            if ($result instanceof TellerAllocation) {
                $result->loadMissing(TellerAllocation::API_RELATIONS);
            }

            return $this->successResponse($result instanceof TellerAllocation ? new TellerAllocationResource($result) : $result);
        } catch (DomainException $e) {
            return $this->domainErrorResponse($e);
        } catch (\Exception $e) {
            return $this->serverErrorResponse("Failed to {$actionName} allocation. Please contact support.", $e);
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
                $request->validated()['approved_quantity'],
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
                $request->validated()['new_quantity'],
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

        return $this->successResponse($data instanceof TellerAllocation ? new TellerAllocationResource($data) : $data, $result['message'] ?? 'Active allocation retrieved');
    }

    /**
     * Teller-initiated stock request. Creates a PENDING allocation that the
     * branch manager approves via POST /allocations/{id}/approve.
     */
    public function requestStock(RequestAllocationRequest $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user->role->canPerform(Permission::RequestStock)) {
            throw new PermissionDeniedException('request stock allocations');
        }

        $validated = $request->validated();
        $counter = isset($validated['counter_id']) ? Counter::query()->find((int) $validated['counter_id']) : null;

        try {
            $allocation = $this->allocationService->requestAllocation(
                $user,
                $user,
                $validated['currency_code'],
                $validated['requested_quantity'],
                null,
                $counter
            );
        } catch (DomainException $e) {
            return $this->domainErrorResponse($e);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to request allocation. Please contact support.', $e);
        }

        return $this->successResponse(new TellerAllocationResource($allocation->loadMissing(TellerAllocation::API_RELATIONS)), 'Allocation request created', 201);
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
            throw new PermissionDeniedException('Only the assigned teller can accept this allocation');
        }

        if (! $allocation->isApproved()) {
            return $this->errorResponse('Allocation is not in approved status', [], 409);
        }

        $this->allocationService->activateAllocation($allocation);

        return $this->successResponse(new TellerAllocationResource($allocation->refresh()->loadMissing(TellerAllocation::API_RELATIONS)), 'Allocation activated');
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
            throw new PermissionDeniedException('Only the assigned teller can return this allocation');
        }

        if (! $allocation->isActive()) {
            return $this->errorResponse('Allocation is not active', [], 409);
        }

        $this->allocationService->returnToPool($allocation);

        return $this->successResponse(new TellerAllocationResource($allocation->refresh()->loadMissing(TellerAllocation::API_RELATIONS)), 'Allocation returned to pool');
    }
}
