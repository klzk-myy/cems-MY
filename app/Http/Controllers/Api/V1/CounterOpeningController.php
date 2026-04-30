<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Counter;
use App\Models\User;
use App\Services\BranchPoolService;
use App\Services\CounterOpeningWorkflowService;
use App\Services\CounterService;
use App\Services\TellerAllocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    public function __construct(
        protected CounterOpeningWorkflowService $workflowService,
        protected BranchPoolService $branchPoolService,
        protected TellerAllocationService $tellerAllocationService,
        protected CounterService $counterService,
    ) {}

    /**
     * Get pending opening requests for a branch.
     * Manager/Admin only.
     */
    public function pendingRequests(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (! $user->branch) {
            return response()->json([
                'success' => false,
                'message' => 'User has no assigned branch',
            ], 400);
        }

        $pending = $this->workflowService->getPendingRequestsForBranch($user->branch);

        return response()->json([
            'success' => true,
            'data' => $pending,
        ]);
    }

    /**
     * Initiate opening request - teller requests float allocation.
     * POST /api/v1/counters/{counter}/opening-request
     */
    public function initiateOpeningRequest(Request $request, int $counterId): JsonResponse
    {
        $user = Auth::user();

        $counter = Counter::find($counterId);
        if (! $counter) {
            return response()->json([
                'success' => false,
                'message' => 'Counter not found',
            ], 404);
        }

        // Verify teller belongs to same branch as counter
        if ($user->branch_id !== $counter->branch_id) {
            return response()->json([
                'success' => false,
                'message' => 'Counter does not belong to your branch',
            ], 403);
        }

        $validated = $request->validate([
            'requested_floats' => 'required|array',
            'requested_floats.*' => 'required|numeric|min:0.0001',
        ]);

        try {
            $allocations = $this->workflowService->initiateOpeningRequest(
                $user,
                $counter,
                $validated['requested_floats']
            );

            return response()->json([
                'success' => true,
                'message' => 'Opening request initiated, awaiting manager approval',
                'data' => $allocations,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Approve and open counter - manager approves allocation and opens counter.
     * POST /api/v1/counters/{counter}/approve-and-open
     */
    public function approveAndOpen(Request $request, int $counterId): JsonResponse
    {
        $user = Auth::user();

        // Only manager or admin can approve and open
        if (! $user->role->isManager() && ! $user->role->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only managers and admins can approve and open counters',
            ], 403);
        }

        $counter = Counter::find($counterId);
        if (! $counter) {
            return response()->json([
                'success' => false,
                'message' => 'Counter not found',
            ], 404);
        }

        $validated = $request->validate([
            'teller_id' => 'required|integer|exists:users,id',
            'approved_floats' => 'required|array',
            'approved_floats.*' => 'required|numeric|min:0',
            'daily_limits' => 'nullable|array',
            'daily_limits.*' => 'nullable|numeric|min:0',
        ]);

        $teller = User::find($validated['teller_id']);

        // Verify teller belongs to same branch
        if ($teller->branch_id !== $counter->branch_id) {
            return response()->json([
                'success' => false,
                'message' => 'Teller does not belong to this branch',
            ], 400);
        }

        try {
            $session = $this->workflowService->approveAndOpen(
                $user,
                $counter,
                $teller,
                $validated['approved_floats'],
                $validated['daily_limits'] ?? []
            );

            return response()->json([
                'success' => true,
                'message' => 'Counter opened successfully',
                'data' => $session,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
