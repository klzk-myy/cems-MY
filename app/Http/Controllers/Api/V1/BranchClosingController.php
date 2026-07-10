<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Domain\BranchClosingChecklistIncompleteException;
use App\Http\Concerns\BranchScoped;
use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BranchClosingRequest;
use App\Models\Branch;
use App\Services\Branch\BranchClosingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class BranchClosingController extends Controller
{
    use ApiResponse;
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
            return $this->errorResponse('An active closure workflow already exists for this branch', [], 400, [
                'data' => $existingWorkflow,
            ]);
        }

        $workflow = $this->branchClosingService->initiateClosure($branch, $user);

        return $this->successResponse($workflow, 'Branch closure workflow initiated', 201);
    }

    public function checklist(BranchClosingRequest $request, int $branchId): JsonResponse
    {
        $branch = Branch::findOrFail($branchId);

        if ($unauthorized = $this->authorizeBranchAccess($branchId)) {
            return $unauthorized;
        }

        $workflow = $this->branchClosingService->getActiveWorkflow($branch);

        if (! $workflow) {
            return $this->notFoundResponse('No active closure workflow found for this branch');
        }

        $checklist = $this->branchClosingService->getChecklist($workflow);

        return $this->successResponse([
            'workflow' => $workflow,
            'checklist' => $checklist,
            'can_finalize' => $this->branchClosingService->canFinalize($workflow),
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
            return $this->notFoundResponse('No active closure workflow found for this branch');
        }

        $user = Auth::user();

        try {
            $this->branchClosingService->finalize($workflow, $user);

            return $this->successResponse($workflow->fresh(), 'Branch closure finalized successfully');
        } catch (BranchClosingChecklistIncompleteException $e) {
            return $this->errorResponse($e->getMessage(), [], 400);
        }
    }

    public function show(BranchClosingRequest $request, int $branchId): JsonResponse
    {
        return $this->checklist($request, $branchId);
    }
}
