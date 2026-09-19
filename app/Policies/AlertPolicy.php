<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Alert;
use App\Models\User;

class AlertPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canPerform(Permission::AccessCompliance);
    }

    public function view(User $user, Alert $alert): bool
    {
        return $user->role->canPerform(Permission::AccessCompliance);
    }

    /**
     * @param  Alert|null  $alert  Null when authorizing the class itself (bulk/auto-assign).
     */
    public function assign(User $user, ?Alert $alert = null): bool
    {
        return $user->role->canPerform(Permission::AccessCompliance);
    }

    /**
     * @param  Alert|null  $alert  Null when authorizing the class itself (bulk resolve).
     */
    public function updateStatus(User $user, ?Alert $alert = null): bool
    {
        return $user->role->canPerform(Permission::AccessCompliance);
    }
}
