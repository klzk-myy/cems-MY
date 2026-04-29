<?php

namespace App\Http\Controllers;

use App\Exceptions\Domain\BranchClosingChecklistIncompleteException;
use App\Models\Branch;
use App\Services\BranchClosingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BranchClosingController extends Controller
{
    public function __construct(
        protected BranchClosingService $branchClosingService,
    ) {}

    public function show(Branch $branch): View
    {
        $workflow = $this->branchClosingService->getActiveWorkflow($branch);
        $checklist = $workflow ? $this->branchClosingService->getChecklist($workflow) : null;
        $canFinalize = $workflow ? $this->branchClosingService->canFinalize($workflow) : false;

        return view('branch-closing.show', compact('branch', 'workflow', 'checklist', 'canFinalize'));
    }

    public function initiate(Request $request, Branch $branch): RedirectResponse
    {
        $existingWorkflow = $this->branchClosingService->getActiveWorkflow($branch);

        if ($existingWorkflow) {
            return redirect()->back()->with('error', 'An active closure workflow already exists for this branch.');
        }

        $workflow = $this->branchClosingService->initiateClosure($branch, auth()->user());

        return redirect()->route('branch-closing.show', $branch)
            ->with('success', 'Branch closure workflow initiated.');
    }

    public function finalize(Request $request, Branch $branch): RedirectResponse
    {
        $workflow = $this->branchClosingService->getActiveWorkflow($branch);

        if (! $workflow) {
            return redirect()->back()->with('error', 'No active closure workflow found for this branch.');
        }

        try {
            $this->branchClosingService->finalize($workflow, auth()->user());

            return redirect()->route('branches.show', $branch)
                ->with('success', 'Branch closure finalized successfully.');
        } catch (BranchClosingChecklistIncompleteException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
