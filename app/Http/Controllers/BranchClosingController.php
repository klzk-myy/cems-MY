<?php

namespace App\Http\Controllers;

use App\Exceptions\Domain\BranchClosingChecklistIncompleteException;
use App\Http\Requests\FinalizeBranchClosingRequest;
use App\Http\Requests\InitiateBranchClosingRequest;
use App\Http\Requests\SettleBranchClosingRequest;
use App\Models\Branch;
use App\Services\Branch\BranchClosingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BranchClosingController extends Controller
{
    public function __construct(
        protected BranchClosingService $branchClosingService,
    ) {}

    /**
     * Sidebar entry point (route 'closing.show'). The closing workflow is
     * per-branch, so resolve the manager's own branch — falling back to the
     * first active trading branch for HQ-scoped users — and forward to the
     * real page.
     */
    public function index(): RedirectResponse
    {
        $branch = auth()->user()?->branch;

        if ($branch === null || ! $branch->canTrade() || ! $branch->is_active) {
            $branch = Branch::where('is_active', true)
                ->where('type', '!=', Branch::TYPE_HEAD_OFFICE)
                ->orderBy('id')
                ->first();
        }

        abort_if($branch === null, 404);

        return redirect()->route('branches.closing.show', $branch);
    }

    public function show(Branch $branch): View
    {
        $workflow = $this->branchClosingService->getActiveWorkflow($branch);
        $checklist = $workflow ? $this->branchClosingService->getChecklist($workflow) : null;
        $canFinalize = $workflow ? $this->branchClosingService->canFinalize($workflow) : false;

        return view('branch.closing.show', compact('branch', 'workflow', 'checklist', 'canFinalize'));
    }

    public function initiate(InitiateBranchClosingRequest $request, Branch $branch): RedirectResponse
    {
        $existingWorkflow = $this->branchClosingService->getActiveWorkflow($branch);

        if ($existingWorkflow) {
            return redirect()->back()->with('error', 'An active closure workflow already exists for this branch.');
        }

        $workflow = $this->branchClosingService->initiateClosure($branch, auth()->user());

        return redirect()->route('branches.closing.show', $branch)
            ->with('success', 'Branch closure workflow initiated.');
    }

    public function settle(SettleBranchClosingRequest $request, Branch $branch): RedirectResponse
    {
        $workflow = $this->branchClosingService->getActiveWorkflow($branch);

        if (! $workflow) {
            return redirect()->back()->with('error', 'No active closure workflow found for this branch.');
        }

        $this->branchClosingService->settle($workflow, auth()->user());

        return redirect()->route('branches.closing.show', $branch)
            ->with('success', 'Branch settlement completed. Cash and allocations returned to pool.');
    }

    public function finalize(FinalizeBranchClosingRequest $request, Branch $branch): RedirectResponse
    {
        $workflow = $this->branchClosingService->getActiveWorkflow($branch);

        if (! $workflow) {
            return redirect()->back()->with('error', 'No active closure workflow found for this branch.');
        }

        try {
            $this->branchClosingService->finalize($workflow, auth()->user());

            return redirect()->route('branches.closing.show', $branch)
                ->with('success', 'Branch closure finalized successfully.');
        } catch (BranchClosingChecklistIncompleteException $e) {
            return redirect()->back()->with('error', 'Cannot finalize branch closure: incomplete checklist items must be resolved first.');
        }
    }
}
