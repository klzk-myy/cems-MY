<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Counter;
use App\Models\User;

class CounterPolicy
{
    /**
     * Determine whether the user can view any counters.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->branch_id !== null;
    }

    /**
     * Determine whether the user can view the counter.
     */
    public function view(User $user, Counter $counter): bool
    {
        return $user->isAdmin() || $user->branch_id === $counter->branch_id;
    }

    /**
     * Determine whether the user can create counters.
     * Requires the manage_counters matrix permission; non-admin holders
     * must be attached to a branch (the target branch cannot be validated
     * here because no Counter instance exists yet — see view()/update()
     * for the per-counter branch match).
     */
    public function create(User $user): bool
    {
        return $user->role->canPerform(Permission::ManageCounters)
            && ($user->isAdmin() || $user->branch_id !== null);
    }

    /**
     * Determine whether the user can update the counter.
     * Requires the manage_counters matrix permission; non-admin holders
     * are restricted to counters belonging to their own branch.
     */
    public function update(User $user, Counter $counter): bool
    {
        return $user->role->canPerform(Permission::ManageCounters)
            && ($user->isAdmin() || ($user->branch_id !== null && $user->branch_id === $counter->branch_id));
    }

    /**
     * Determine whether the user can delete the counter.
     * Only admins can delete counters.
     */
    public function delete(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }
}
