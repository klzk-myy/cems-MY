<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Compliance\FlaggedTransaction;
use App\Models\User;

class FlaggedTransactionPolicy
{
    /**
     * Determine whether the user can view the compliance dashboard.
     */
    public function viewAny(User $user): bool
    {
        return $user->role->canPerform(Permission::AccessCompliance);
    }

    /**
     * Determine whether the user can assign a flagged transaction.
     */
    public function assign(User $user, FlaggedTransaction $flaggedTransaction): bool
    {
        return $user->role->canPerform(Permission::AccessCompliance);
    }

    /**
     * Determine whether the user can resolve a flagged transaction.
     */
    public function resolve(User $user, FlaggedTransaction $flaggedTransaction): bool
    {
        return $user->role->canPerform(Permission::AccessCompliance);
    }
}
