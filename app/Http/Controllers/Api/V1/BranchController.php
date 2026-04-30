<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Models\Branch;
use App\Services\BranchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * BranchController API v1
 *
 * Handles branch management operations via API.
 * Admin-only for index, store, update, destroy.
 * show, counters, users accessible to admin OR user's own branch.
 */
class BranchController extends Controller
{
    public function __construct(
        protected BranchService $branchService,
    ) {}

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
    public function store(StoreBranchRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $branch = $this->branchService->createBranch($validated, Auth::id(), $request->ip());

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
    public function update(UpdateBranchRequest $request, int $id): JsonResponse
    {
        $branch = Branch::findOrFail($id);

        $validated = $request->validated();

        $branch = $this->branchService->updateBranch($branch, $validated, Auth::id(), $request->ip());

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

        try {
            $this->branchService->deactivateBranch($branch, Auth::id(), $request->ip());

            return response()->json([
                'success' => true,
                'message' => 'Branch deactivated successfully',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get counters for a branch.
     * Accessible to admin OR user whose branch_id matches.
     */
    public function counters(Request $request, int $id): JsonResponse
    {
        $branch = Branch::findOrFail($id);
        $user = Auth::user();

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
