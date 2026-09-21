<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Exceptions\Domain\DomainException;
use App\Http\Controllers\Api\V1\Concerns\AuthorizesCounter;
use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Concerns\RequiresPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Counter\ApproveAndOpenRequest;
use App\Http\Requests\Api\V1\Counter\InitiateOpeningRequest;
use App\Http\Resources\Api\V1\CounterSessionResource;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\User;
use App\Services\Branch\CounterOpeningWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * CounterOpeningController API v1
 *
 * Handles the counter opening workflow:
 * 1. Initiate opening request (teller requests float)
 * 2. Approve and open (manager approves and opens counter)
 */
class CounterOpeningController extends Controller
{
    use ApiResponse;
    use AuthorizesCounter;
    use RequiresPermission;

    public function __construct(
        protected CounterOpeningWorkflowService $workflowService,
    ) {}

    /**
     * Get pending opening requests for a branch.
     * Manager/Admin only.
     */
    public function pendingRequests(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $branch = $user->branch;

        if (! $branch instanceof Branch) {
            return $this->errorResponse('User has no assigned branch', [], 422);
        }

        $this->requirePermission(Permission::ManageCounters, 'Only users with the Manage Counters permission can view pending opening requests');

        $pending = $this->workflowService->getPendingRequestsForBranch($branch);

        return $this->successResponse($pending);
    }

    /**
     * Initiate opening request - teller requests float allocation.
     * POST /api/v1/counters/{counter}/opening-request
     */
    public function initiateOpeningRequest(InitiateOpeningRequest $request, int $counterId): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $counter = $this->authorizeCounter($counterId);
        if (! $counter instanceof Counter) {
            return $this->notFoundResponse('Counter not found');
        }

        $validated = $request->validated();

        try {
            $allocations = $this->workflowService->initiateOpeningRequest(
                $user,
                $counter,
                $validated['requested_floats']
            );

            return $this->successResponse($allocations, 'Opening request initiated, awaiting manager approval');
        } catch (DomainException $e) {
            return $this->domainErrorResponse($e);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to initiate opening request. Please contact support.', $e);
        }
    }

    /**
     * Approve and open counter - manager approves allocation and opens counter.
     * POST /api/v1/counters/{counter}/approve-and-open
     */
    public function approveAndOpen(ApproveAndOpenRequest $request, int $counterId): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $this->requirePermission(Permission::ManageCounters, 'Only users with the Manage Counters permission can approve and open counters');

        $counter = $this->authorizeCounter($counterId);
        if (! $counter instanceof Counter) {
            return $this->notFoundResponse('Counter not found');
        }

        $validated = $request->validated();

        /** @var User $teller */
        $teller = User::findOrFail($validated['teller_id']);

        // Verify teller belongs to same branch
        if ($teller->branch_id !== $counter->branch_id) {
            return $this->errorResponse('Teller does not belong to this branch', [], 422);
        }

        try {
            $session = $this->workflowService->approveAndOpen(
                $user,
                $counter,
                $teller,
                $validated['approved_floats'],
                $validated['daily_limits'] ?? []
            );

            return $this->successResponse(new CounterSessionResource($session), 'Counter opened successfully');
        } catch (DomainException $e) {
            return $this->domainErrorResponse($e);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to open counter. Please contact support.', $e);
        }
    }
}
