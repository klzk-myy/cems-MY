<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Service for branch scope filtering based on user access.
 *
 * Provides methods to determine which branches a user can access
 * and to scope queries accordingly.
 */
class BranchScopeService
{
    /**
     * Returns branch IDs the user can access.
     * Returns null for Admin (means "all branches").
     *
     * @return array<int>|null
     */
    public function getAccessibleBranchIds(User $user): ?array
    {
        // Admin can access all branches
        if ($user->role->isAdmin()) {
            return null;
        }

        // User with a branch_id can only access that branch
        if ($user->branch_id !== null) {
            return [$user->branch_id];
        }

        // User without branch_id has no access
        return [];
    }

    /**
     * Scope a query to the user's accessible branches.
     * Admin gets no filter (sees all).
     */
    public function scopeToUserBranch(Builder $query, User $user): Builder
    {
        // Admin sees all branches
        if ($user->role->isAdmin()) {
            return $query;
        }

        // Non-admin with branch_id should only see their branch
        if ($user->branch_id !== null) {
            return $query->where('id', $user->branch_id);
        }

        // User without branch_id sees nothing (empty result)
        return $query->where('id', null);
    }

    /**
     * Check if a user can access a specific branch.
     */
    public function canAccessBranch(User $user, Branch $branch): bool
    {
        // Admin can access any branch
        if ($user->role->isAdmin()) {
            return true;
        }

        // User can only access their own branch
        return $user->branch_id === $branch->id;
    }
}
