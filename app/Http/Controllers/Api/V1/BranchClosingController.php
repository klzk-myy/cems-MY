<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Domain\BranchClosingChecklistIncompleteException;
use App\Exceptions\Domain\BusinessDateFrozenException;
use App\Exceptions\Domain\InvalidStateException;
use App\Http\Controllers\Api\V1\Traits\ApiResponse;
use App\Http\Controllers\Concerns\AuthorizesBranchResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BranchClosingRequest;
use App\Http\Resources\Api\V1\BranchClosureResource;
use App\Models\Branch;
use App\Services\Branch\BranchClosingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class BranchClosingController extends Controller
{
    use ApiResponse;
    use AuthorizesBranchResource;

    public function __construct(
        protected BranchClosingService $branchClosingService,
    ) {}

    public function initiate(BranchClosingRequest $request, int $branchId): JsonResponse
    {
        $branch = Branch::findOrFail($branchId);

        $this->authorizeBranchAccess($branchId);

        $user = Auth::user();

        $existingWorkflow = $this->branchClosingService->getActiveWorkflow($branch);
        if ($existingWorkflow) {
            return $this->errorResponse('An active closure workflow already exists for this branch', [], 409, [
                'data' => $existingWorkflow,
            ]);
        }

        try {
            $workflow = $this->branchClosingService->initiateClosure($branch, $user);
        } catch (BusinessDateFrozenException $e) {
            return $this->domainErrorResponse($e, 'This business date is already finalized — reopen the day before starting a new closure.');
        }

        return $this->successResponse(new BranchClosureResource($workflow), 'Branch closure workflow initiated', 201);
    }

    public function checklist(BranchClosingRequest $request, int $branchId): JsonResponse
    {
        $branch = Branch::findOrFail($branchId);

        $this->authorizeBranchAccess($branchId);

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

    public function settle(BranchClosingRequest $request, int $branchId): JsonResponse
    {
        $branch = Branch::findOrFail($branchId);

        $this->authorizeBranchAccess($branchId);

        $workflow = $this->branchClosingService->getActiveWorkflow($branch);

        if (! $workflow) {
            return $this->notFoundResponse('No active closure workflow found for this branch');
        }

        $user = Auth::user();

        try {
            $this->branchClosingService->settle($workflow, $user);

            return $this->successResponse(new BranchClosureResource($workflow->fresh()), 'Branch settlement completed');
        } catch (BranchClosingChecklistIncompleteException $e) {
            return $this->domainErrorResponse($e, 'Cannot settle branch closure: counters must be closed first.');
        } catch (InvalidStateException $e) {
            return $this->domainErrorResponse($e);
        }
    }

    public function finalize(BranchClosingRequest $request, int $branchId): JsonResponse
    {
        $branch = Branch::findOrFail($branchId);

        $this->authorizeBranchAccess($branchId);

        $workflow = $this->branchClosingService->getActiveWorkflow($branch);

        if (! $workflow) {
            return $this->notFoundResponse('No active closure workflow found for this branch');
        }

        $user = Auth::user();

        try {
            $this->branchClosingService->finalize($workflow, $user);

            return $this->successResponse(new BranchClosureResource($workflow->fresh()), 'Branch closure finalized successfully');
        } catch (BranchClosingChecklistIncompleteException $e) {
            return $this->domainErrorResponse($e, 'Cannot finalize branch closure: incomplete checklist items must be resolved first.');
        } catch (InvalidStateException $e) {
            return $this->domainErrorResponse($e);
        }
    }

    public function show(BranchClosingRequest $request, int $branchId): JsonResponse
    {
        return $this->checklist($request, $branchId);
    }
}
