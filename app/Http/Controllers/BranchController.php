<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\SystemLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * BranchController
 *
 * Handles branch management operations including creation, updates, and deactivation.
 * All methods require admin authentication.
 */
class BranchController extends Controller
{
    /**
     * Display a paginated listing of all branches.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $branches = Branch::orderBy('code')->paginate(20);

        return view('branches.index', compact('branches'));
    }

    /**
     * Show the form for creating a new branch.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $branchTypes = [
            'head_office' => 'Head Office',
            'branch' => 'Branch',
            'sub_branch' => 'Sub-Branch',
        ];

        $parentBranches = Branch::where('is_active', true)
            ->orderBy('code')
            ->get();

        return view('branches.create', compact('branchTypes', 'parentBranches'));
    }

    /**
     * Store a newly created branch in the database.
     *
     * @return \Illuminate\Http\RedirectResponse
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

        // Ensure only one main branch
        if (! empty($validated['is_main'])) {
            Branch::where('is_main', true)->update(['is_main' => false]);
        }

        $branch = Branch::create([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'type' => $validated['type'],
            'address' => $validated['address'] ?? null,
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
            'postal_code' => $validated['postal_code'] ?? null,
            'country' => $validated['country'] ?? 'Malaysia',
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'is_main' => $validated['is_main'] ?? false,
            'parent_id' => $validated['parent_id'] ?? null,
        ]);

        // Log branch creation
        SystemLog::create([
            'user_id' => auth()->id(),
            'action' => 'branch_created',
            'entity_type' => 'Branch',
            'entity_id' => $branch->id,
            'new_values' => [
                'code' => $branch->code,
                'name' => $branch->name,
                'type' => $branch->type,
            ],
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('branches.index')
            ->with('success', "Branch {$branch->code} - {$branch->name} created successfully!");
    }

    /**
     * Display the specified branch with summary statistics.
     *
     * @return \Illuminate\View\View
     */
    public function show(Branch $branch)
    {
        // Get summary statistics for the branch
        $stats = [
            'user_count' => $branch->users()->count(),
            'counter_count' => $branch->counters()->count(),
            'transaction_today' => $branch->transactions()
                ->whereDate('created_at', now()->toDateString())
                ->count(),
            'transaction_month' => $branch->transactions()
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
        ];

        // Get child branches if any
        $childBranches = $branch->children()->get();

        return view('branches.show', compact('branch', 'stats', 'childBranches'));
    }

    /**
     * Show the form for editing the specified branch.
     *
     * @return \Illuminate\View\View
     */
    public function edit(Branch $branch)
    {
        $branchTypes = [
            'head_office' => 'Head Office',
            'branch' => 'Branch',
            'sub_branch' => 'Sub-Branch',
        ];

        $parentBranches = Branch::where('is_active', true)
            ->where('id', '!=', $branch->id)
            ->orderBy('code')
            ->get();

        return view('branches.edit', compact('branch', 'branchTypes', 'parentBranches'));
    }

    /**
     * Update the specified branch in the database.
     *
     * @return \Illuminate\Http\RedirectResponse
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

        $oldValues = [
            'code' => $branch->code,
            'name' => $branch->name,
            'type' => $branch->type,
            'is_active' => $branch->is_active,
            'is_main' => $branch->is_main,
        ];

        // Ensure only one main branch
        if (! empty($validated['is_main']) && ! $branch->is_main) {
            Branch::where('is_main', true)->update(['is_main' => false]);
        }

        $branch->update([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'type' => $validated['type'],
            'address' => $validated['address'] ?? null,
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
            'postal_code' => $validated['postal_code'] ?? null,
            'country' => $validated['country'] ?? 'Malaysia',
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'is_main' => $validated['is_main'] ?? false,
            'parent_id' => $validated['parent_id'] ?? null,
        ]);

        // Log branch update
        SystemLog::create([
            'user_id' => auth()->id(),
            'action' => 'branch_updated',
            'entity_type' => 'Branch',
            'entity_id' => $branch->id,
            'old_values' => $oldValues,
            'new_values' => [
                'code' => $branch->code,
                'name' => $branch->name,
                'type' => $branch->type,
                'is_active' => $branch->is_active,
                'is_main' => $branch->is_main,
            ],
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('branches.show', $branch)
            ->with('success', "Branch {$branch->code} - {$branch->name} updated successfully!");
    }

    /**
     * Deactivate the specified branch.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request, Branch $branch)
    {
        // Prevent deactivating the main branch
        if ($branch->is_main) {
            return redirect()->route('branches.index')
                ->with('error', 'Cannot deactivate the main branch!');
        }

        // Prevent deactivating if it has active child branches
        if ($branch->children()->where('is_active', true)->exists()) {
            return redirect()->route('branches.index')
                ->with('error', 'Cannot deactivate branch with active child branches!');
        }

        $branchCode = $branch->code;
        $branchName = $branch->name;

        // Deactivate instead of delete to preserve audit trail
        $branch->update(['is_active' => false]);

        // Log branch deactivation
        SystemLog::create([
            'user_id' => auth()->id(),
            'action' => 'branch_deactivated',
            'entity_type' => 'Branch',
            'entity_id' => $branch->id,
            'old_values' => [
                'code' => $branchCode,
                'name' => $branchName,
                'is_active' => true,
            ],
            'new_values' => [
                'code' => $branchCode,
                'name' => $branchName,
                'is_active' => false,
            ],
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('branches.index')
            ->with('success', "Branch {$branchCode} - {$branchName} has been deactivated!");
    }
}
