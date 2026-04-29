<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\BranchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * BranchController
 *
 * Handles branch management operations including creation, updates, and deactivation.
 * All methods require admin authentication.
 */
class BranchController extends Controller
{
    public function __construct(
        protected BranchService $branchService,
    ) {}

    /**
     * Display a paginated listing of all branches.
     *
     * @return View
     */
    public function index()
    {
        $branches = Branch::orderBy('code')->paginate(20);

        return view('branches.index', compact('branches'));
    }

    /**
     * Show the form for creating a new branch.
     *
     * @return View
     */
    public function create()
    {
        $branchTypes = $this->branchService->getBranchTypes();
        $parentBranches = $this->branchService->getParentBranches();

        return view('branches.create', compact('branchTypes', 'parentBranches'));
    }

    /**
     * Store a newly created branch in the database.
     *
     * @return RedirectResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:branches,code',
            'name' => 'required|string|max:255',
            'type' => 'required|in:head_office,branch,sub_branch',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:100',
            'is_active' => 'boolean',
            'is_main' => 'boolean',
            'parent_id' => 'nullable|exists:branches,id',
        ]);

        $branch = $this->branchService->createBranch($validated, auth()->id(), $request->ip());

        return redirect()->route('branches.index')
            ->with('success', "Branch {$branch->code} - {$branch->name} created successfully!");
    }

    /**
     * Display the specified branch with summary statistics.
     *
     * @return View
     */
    public function show(Branch $branch)
    {
        $stats = $this->branchService->getBranchStats($branch);
        $childBranches = $branch->children()->get();

        return view('branches.show', compact('branch', 'stats', 'childBranches'));
    }

    /**
     * Show the form for editing the specified branch.
     *
     * @return View
     */
    public function edit(Branch $branch)
    {
        $branchTypes = $this->branchService->getBranchTypes();
        $parentBranches = $this->branchService->getParentBranches($branch->id);

        return view('branches.edit', compact('branch', 'branchTypes', 'parentBranches'));
    }

    /**
     * Update the specified branch in the database.
     *
     * @return RedirectResponse
     */
    public function update(Request $request, Branch $branch)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('branches')->ignore($branch->id)],
            'name' => 'required|string|max:255',
            'type' => 'required|in:head_office,branch,sub_branch',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:100',
            'is_active' => 'boolean',
            'is_main' => 'boolean',
            'parent_id' => 'nullable|exists:branches,id',
        ]);

        $branch = $this->branchService->updateBranch($branch, $validated, auth()->id(), $request->ip());

        return redirect()->route('branches.show', $branch)
            ->with('success', "Branch {$branch->code} - {$branch->name} updated successfully!");
    }

    /**
     * Deactivate the specified branch.
     *
     * @return RedirectResponse
     */
    public function destroy(Request $request, Branch $branch)
    {
        try {
            $this->branchService->deactivateBranch($branch, auth()->id(), $request->ip());

            return redirect()->route('branches.index')
                ->with('success', "Branch {$branch->code} - {$branch->name} has been deactivated!");
        } catch (\RuntimeException $e) {
            return redirect()->route('branches.index')
                ->with('error', $e->getMessage());
        }
    }
}
