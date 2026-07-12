<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AuthorizesCounter;
use App\Http\Controllers\Api\V1\Concerns\AuthorizesManager;
use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Counter\ApproveAndOpenRequest;
use App\Http\Requests\Api\V1\Counter\InitiateOpeningRequest;
use App\Models\User;
use App\Services\Branch\CounterOpeningWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
    use AuthorizesManager;

    public function __construct(
        protected CounterOpeningWorkflowService $workflowService,
    ) {}

    /**
     * Get pending opening requests for a branch.
     * Manager/Admin only.
     */
    public function pendingRequests(): JsonResponse
    {
        $user = Auth::user();

        if (! $user->branch) {
            return $this->errorResponse('User has no assigned branch', [], 400);
        }

        $pending = $this->workflowService->getPendingRequestsForBranch($user->branch);

        return $this->successResponse($pending);
    }

    /**
     * Initiate opening request - teller requests float allocation.
     * POST /api/v1/counters/{counter}/opening-request
     */
    public function initiateOpeningRequest(InitiateOpeningRequest $request, int $counterId): JsonResponse
    {
        $user = Auth::user();

        $counter = $this->authorizeCounter($counterId);
        if ($counter instanceof JsonResponse) {
            return $counter;
        }

        $validated = $request->validated();

        try {
            $allocations = $this->workflowService->initiateOpeningRequest(
                $user,
                $counter,
                $validated['requested_floats']
            );

            return $this->successResponse($allocations, 'Opening request initiated, awaiting manager approval');
        } catch (\Exception $e) {
            Log::error('Failed to initiate opening request', ['error' => $e->getMessage(), 'user_id' => auth()->id()]);

            return $this->errorResponse('Operation failed. Please contact support.', [], 400);
        }
    }

    /**
     * Approve and open counter - manager approves allocation and opens counter.
     * POST /api/v1/counters/{counter}/approve-and-open
     */
    public function approveAndOpen(ApproveAndOpenRequest $request, int $counterId): JsonResponse
    {
        $user = Auth::user();

        if ($response = $this->requireManagerOrAdminResponse('Only managers and admins can approve and open counters')) {
            return $response;
        }

        $counter = $this->authorizeCounter($counterId);
        if ($counter instanceof JsonResponse) {
            return $counter;
        }

        $validated = $request->validated();

        $teller = User::findOrFail($validated['teller_id']);

        // Verify teller belongs to same branch
        if ($teller->branch_id !== $counter->branch_id) {
            return $this->errorResponse('Teller does not belong to this branch', [], 400);
        }

        try {
            $session = $this->workflowService->approveAndOpen(
                $user,
                $counter,
                $teller,
                $validated['approved_floats'],
                $validated['daily_limits'] ?? []
            );

            return $this->successResponse($session, 'Counter opened successfully');
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to open counter. Please contact support.', $e);
        }
    }
}
