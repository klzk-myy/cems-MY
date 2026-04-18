<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\SystemLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * BranchController API v1
 *
 * Handles branch management operations via API.
 * Admin-only for index, store, update, destroy.
 * show, counters, users accessible to admin OR user's own branch.
 */
class BranchController extends Controller
{
    /**
     * List all branches (Admin only).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 20);
        $branches = Branch::orderBy('code')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $branches->items(),
            'current_page' => $branches->currentPage(),
            'per_page' => $branches->perPage(),
            'total' => $branches->total(),
        ]);
    }

    /**
     * Create a new branch (Admin only).
     */
    public function store(Request $request): JsonResponse
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
            'user_id' => Auth::id(),
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

        return response()->json([
            'success' => true,
            'message' => 'Branch created successfully',
            'data' => $branch,
        ], 201);
    }

    /**
     * Display a specific branch.
     * Accessible to admin OR user whose branch_id matches.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $branch = Branch::findOrFail($id);
        $user = Auth::user();

        // Check authorization: admin OR user's own branch
        if (! $user->role->isAdmin() && $user->branch_id !== $id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this branch',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $branch,
        ]);
    }

    /**
     * Update a branch (Admin only).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $branch = Branch::findOrFail($id);

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
            'user_id' => Auth::id(),
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

        return response()->json([
            'success' => true,
            'message' => 'Branch updated successfully',
            'data' => $branch->fresh(),
        ]);
    }

    /**
     * Deactivate a branch (Admin only).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $branch = Branch::findOrFail($id);

        // Prevent deactivating the main branch
        if ($branch->is_main) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot deactivate the main branch',
            ], 400);
        }

        // Prevent deactivating if it has active child branches
        if ($branch->children()->where('is_active', true)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot deactivate branch with active child branches',
            ], 400);
        }

        $oldValues = [
            'code' => $branch->code,
            'name' => $branch->name,
            'is_active' => $branch->is_active,
        ];

        // Deactivate instead of delete to preserve audit trail
        $branch->update(['is_active' => false]);

        // Log branch deactivation
        SystemLog::create([
            'user_id' => Auth::id(),
            'action' => 'branch_deactivated',
            'entity_type' => 'Branch',
            'entity_id' => $branch->id,
            'old_values' => $oldValues,
            'new_values' => [
                'code' => $branch->code,
                'name' => $branch->name,
                'is_active' => false,
            ],
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Branch deactivated successfully',
        ]);
    }

    /**
     * Get counters for a branch.
     * Accessible to admin OR user whose branch_id matches.
     */
    public function counters(Request $request, int $id): JsonResponse
    {
        $branch = Branch::findOrFail($id);
        $user = Auth::user();

        // Check authorization: admin OR user's own branch
        if (! $user->role->isAdmin() && $user->branch_id !== $id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this branch',
            ], 403);
        }

        $counters = $branch->counters()->get(['id', 'code', 'name', 'status']);

        return response()->json([
            'success' => true,
            'data' => $counters,
        ]);
    }

    /**
     * Get users for a branch.
     * Accessible to admin OR user whose branch_id matches.
     */
    public function users(Request $request, int $id): JsonResponse
    {
        $branch = Branch::findOrFail($id);
        $user = Auth::user();

        // Check authorization: admin OR user's own branch
        if (! $user->role->isAdmin() && $user->branch_id !== $id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this branch',
            ], 403);
        }

        $users = $branch->users()->get(['id', 'username', 'email', 'role']);

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }
}
