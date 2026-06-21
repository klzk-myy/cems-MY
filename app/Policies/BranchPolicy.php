<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    /**
     * Determine whether the user can view any branches.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the branch.
     */
    public function view(User $user, Branch $branch): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create branches.
     * Only admins can create branches.
     */
    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    /**
     * Determine whether the user can update the branch.
     * Only admins can update branches.
     */
    public function update(User $user, Branch $branch): bool
    {
        return $user->role === UserRole::Admin;
    }

    /**
     * Determine whether the user can delete the branch.
     * Only admins can delete branches.
     */
    public function delete(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }
}
