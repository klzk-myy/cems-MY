<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Domain\BranchClosingChecklistIncompleteException;
use App\Http\Concerns\BranchScoped;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BranchClosingRequest;
use App\Models\Branch;
use App\Services\Branch\BranchClosingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class BranchClosingController extends Controller
{
    use BranchScoped;

    public function __construct(
        protected BranchClosingService $branchClosingService,
    ) {}

    public function initiate(BranchClosingRequest $request, int $branchId): JsonResponse
    {
        $branch = Branch::findOrFail($branchId);

        if ($unauthorized = $this->authorizeBranchAccess($branchId)) {
            return $unauthorized;
        }

        $user = Auth::user();

        $existingWorkflow = $this->branchClosingService->getActiveWorkflow($branch);
        if ($existingWorkflow) {
            return response()->json([
                'success' => false,
                'message' => 'An active closure workflow already exists for this branch',
                'data' => $existingWorkflow,
            ], 400);
        }

        $workflow = $this->branchClosingService->initiateClosure($branch, $user);

        return response()->json([
            'success' => true,
            'message' => 'Branch closure workflow initiated',
            'data' => $workflow,
        ], 201);
    }

    public function checklist(BranchClosingRequest $request, int $branchId): JsonResponse
    {
        $branch = Branch::findOrFail($branchId);

        if ($unauthorized = $this->authorizeBranchAccess($branchId)) {
            return $unauthorized;
        }

        $workflow = $this->branchClosingService->getActiveWorkflow($branch);

        if (! $workflow) {
            return response()->json([
                'success' => false,
                'message' => 'No active closure workflow found for this branch',
            ], 404);
        }

        $checklist = $this->branchClosingService->getChecklist($workflow);

        return response()->json([
            'success' => true,
            'data' => [
                'workflow' => $workflow,
                'checklist' => $checklist,
                'can_finalize' => $this->branchClosingService->canFinalize($workflow),
            ],
        ]);
    }

    public function finalize(BranchClosingRequest $request, int $branchId): JsonResponse
    {
        $branch = Branch::findOrFail($branchId);

        if ($unauthorized = $this->authorizeBranchAccess($branchId)) {
            return $unauthorized;
        }

        $workflow = $this->branchClosingService->getActiveWorkflow($branch);

        if (! $workflow) {
            return response()->json([
                'success' => false,
                'message' => 'No active closure workflow found for this branch',
            ], 404);
        }

        $user = Auth::user();

        try {
            $this->branchClosingService->finalize($workflow, $user);

            return response()->json([
                'success' => true,
                'message' => 'Branch closure finalized successfully',
                'data' => $workflow->fresh(),
            ]);
        } catch (BranchClosingChecklistIncompleteException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function show(BranchClosingRequest $request, int $branchId): JsonResponse
    {
        return $this->checklist($request, $branchId);
    }
}
