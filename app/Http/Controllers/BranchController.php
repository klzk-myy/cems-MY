<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Models\Branch;
use App\Services\Branch\BranchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * BranchController (web)
 *
 * Admin branch management UI wrapping the same BranchService used by
 * Api/V1/BranchController — no business logic is duplicated here. All
 * mutations go through BranchService::createBranch/updateBranch/
 * deactivateBranch so web and API paths produce identical outcomes and
 * audit trails.
 */
class BranchController extends Controller
{
    public function __construct(
        protected BranchService $branchService,
    ) {}

    /**
     * List all branches with status badges and attached resource counts.
     */
    public function index(): View
    {
        $this->requireAdmin();

        $branches = Branch::query()
            ->withCount(['users', 'counters', 'tillBalances'])
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString();

        return view('system.branches.index', compact('branches'));
    }

    /**
     * Show the create-branch form.
     */
    public function create(): View
    {
        $this->requireAdmin();

        return view('system.branches.create', [
            'branchTypes' => $this->branchService->getBranchTypes(),
            'parentBranches' => $this->branchService->getParentBranches(),
        ]);
    }

    /**
     * Create a branch via BranchService (same path as API V1 store).
     */
    public function store(StoreBranchRequest $request): RedirectResponse
    {
        $this->requireAdmin();

        $branch = $this->branchService->createBranch(
            $request->validated(),
            (int) auth()->id(),
            (string) $request->ip()
        );

        return redirect()->route('branches.index')
            ->with('success', "Branch {$branch->code} created successfully.");
    }

    /**
     * Show the edit form for a branch.
     */
    public function edit(Branch $branch): View
    {
        $this->requireAdmin();

        return view('system.branches.edit', [
            'branch' => $branch,
            'branchTypes' => $this->branchService->getBranchTypes(),
            'parentBranches' => $this->branchService->getParentBranches($branch->id),
        ]);
    }

    /**
     * Update a branch via BranchService (same path as API V1 update).
     */
    public function update(UpdateBranchRequest $request, Branch $branch): RedirectResponse
    {
        $this->requireAdmin();

        $branch = $this->branchService->updateBranch(
            $branch,
            $request->validated(),
            (int) auth()->id(),
            (string) $request->ip()
        );

        return redirect()->route('branches.index')
            ->with('success', "Branch {$branch->code} updated successfully.");
    }

    /**
     * Deactivate a branch via BranchService (same path as API V1 destroy).
     */
    public function deactivate(Request $request, Branch $branch): RedirectResponse
    {
        $this->requireAdmin();

        try {
            $this->branchService->deactivateBranch(
                $branch,
                (int) auth()->id(),
                (string) $request->ip()
            );
        } catch (\RuntimeException $e) {
            return redirect()->route('branches.index')->with('error', $e->getMessage());
        }

        return redirect()->route('branches.index')
            ->with('success', "Branch {$branch->code} deactivated.");
    }
}
