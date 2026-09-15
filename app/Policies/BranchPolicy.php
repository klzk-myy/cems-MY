<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    /**
     * Determine whether the user can view any branches.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->branch_id !== null;
    }

    /**
     * Determine whether the user can view the branch.
     */
    public function view(User $user, Branch $branch): bool
    {
        return $user->isAdmin() || $user->branch_id === $branch->id;
    }

    /**
     * Determine whether the user can create branches.
     * Requires the manage_branches matrix permission (admins by default).
     */
    public function create(User $user): bool
    {
        return $user->role->canPerform(Permission::ManageBranches);
    }

    /**
     * Determine whether the user can update the branch.
     * Requires the access_branches matrix permission (managers by
     * default); non-admin holders may only update their own branch.
     */
    public function update(User $user, Branch $branch): bool
    {
        return $user->role->canPerform(Permission::AccessBranches)
            && ($user->isAdmin() || $user->branch_id === $branch->id);
    }

    /**
     * Determine whether the user can delete the branch.
     * Requires the manage_branches matrix permission (admins by default).
     */
    public function delete(User $user): bool
    {
        return $user->role->canPerform(Permission::ManageBranches);
    }
}
