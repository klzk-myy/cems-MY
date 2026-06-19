<?php

namespace App\Http\Concerns;

use App\Enums\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

trait BranchScoped
{
    protected function authorizeBranchAccess(int $branchId): ?JsonResponse
    {
        $user = Auth::user();

        if ($user->role !== UserRole::Admin && (int) $branchId !== $user->branch_id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to access this branch.',
            ], 403);
        }

        return null;
    }
}
