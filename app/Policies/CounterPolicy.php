<?php

namespace App\Policies;

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
        return true;
    }

    /**
     * Determine whether the user can view the counter.
     */
    public function view(User $user, Counter $counter): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create counters.
     * Managers and admins can create counters.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Manager, UserRole::Admin]);
    }

    /**
     * Determine whether the user can update the counter.
     * Managers and admins can update counters.
     */
    public function update(User $user, Counter $counter): bool
    {
        return in_array($user->role, [UserRole::Manager, UserRole::Admin]);
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
