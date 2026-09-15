<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\StrReport;
use App\Models\User;

/**
 * StrReportPolicy
 *
 * STR records are regulatory filings: only Compliance Officers and Admins
 * may view or act on them. Laravel auto-discovers this policy for
 * App\Models\StrReport by convention (no AuthServiceProvider entry needed).
 */
class StrReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canPerform(Permission::AccessCompliance);
    }

    public function view(User $user, StrReport $strReport): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, StrReport $strReport): bool
    {
        return $this->viewAny($user);
    }
}
