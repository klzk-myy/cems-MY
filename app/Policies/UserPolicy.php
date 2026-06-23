<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any users.
     * Managers and admins can view any users.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Manager, UserRole::Admin]);
    }

    /**
     * Determine whether the user can view the user.
     * Users can view themselves; managers and admins can view anyone.
     */
    public function view(User $user, User $model): bool
    {
        return $user->id === $model->id || in_array($user->role, [UserRole::Manager, UserRole::Admin]);
    }

    /**
     * Determine whether the user can create users.
     * Only admins can create users.
     */
    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    /**
     * Determine whether the user can update the user.
     * Users can update themselves; admins can update anyone.
     */
    public function update(User $user, User $model): bool
    {
        return $user->id === $model->id || $user->role === UserRole::Admin;
    }

    /**
     * Determine whether the user can delete the user.
     * Only admins can delete users.
     */
    public function delete(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }
}
