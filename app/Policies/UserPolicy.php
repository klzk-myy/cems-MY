<?php

namespace App\Policies;

use App\Enums\Permission;
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
        return $user->role->canPerform(Permission::ManageUsers);
    }

    /**
     * Determine whether the user can view the user.
     * Users can view themselves; managers can view users in their branch;
     * admins can view anyone.
     */
    public function view(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return true;
        }

        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $user->role->canPerform(Permission::ManageUsers)
            && $user->branch_id !== null
            && $user->branch_id === $model->branch_id;
    }

    /**
     * Determine whether the user can create users.
     * Admins can create users anywhere. Managers can create users
     * in their own branch. A role whose assign_roles permission has been
     * revoked in the role_permissions matrix has an empty assignable set
     * and can create nobody.
     */
    public function create(User $user): bool
    {
        return $user->role->assignableRoles() !== []
            && $user->role->canPerform(Permission::ManageUsers)
            && ($user->role === UserRole::Admin || $user->branch_id !== null);
    }

    /**
     * Determine whether the user can update the user.
     * Users can update themselves (role self-changes are rejected by
     * validation/service layers); otherwise the actor must be able to
     * manage the target — admins anyone, managers only users whose role
     * they could assign (tellers) within their own branch.
     */
    public function update(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return true;
        }

        return $this->canManage($user, $model);
    }

    /**
     * Determine whether the user can delete the user.
     * Admins can delete anyone but themselves; managers can delete users
     * in their branch whose role falls within their assignable set.
     */
    public function delete(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return false;
        }

        return $this->canManage($user, $model);
    }

    /**
     * Determine whether the user can reset the target's password.
     * Self-reset is allowed; otherwise the actor must manage the target —
     * a manager cannot reset a same-branch admin's password.
     */
    public function resetPassword(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return true;
        }

        return $this->canManage($user, $model);
    }

    /**
     * Whether the actor may administer the target account: admins manage
     * everyone; other roles only manage users in their own branch whose
     * current role is within their assignable set (per
     * UserRole::assignableRoles()). Roles with an empty assignable set
     * (teller, compliance officer, accountant) can manage nobody.
     */
    private function canManage(User $user, User $model): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $user->branch_id !== null
            && $user->branch_id === $model->branch_id
            && in_array($model->role, $user->role->assignableRoles(), true);
    }
}
