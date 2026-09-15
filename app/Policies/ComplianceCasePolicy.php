<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Compliance\ComplianceCase;
use App\Models\Customer;
use App\Models\User;

class ComplianceCasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canPerform(Permission::AccessCompliance);
    }

    public function view(User $user, ComplianceCase $case): bool
    {
        if ($user->isAdmin() || $user->role === UserRole::ComplianceOfficer) {
            return true;
        }

        // Other roles granted access_compliance see cases for customers
        // with transactions in their own branch (manager-scoped view).
        return $user->role->canPerform(Permission::AccessCompliance)
            && $user->branch_id !== null
            && Customer::where('id', $case->customer_id)
                ->whereHas('transactions', fn ($t) => $t->where('branch_id', $user->branch_id))
                ->exists();
    }

    public function create(User $user): bool
    {
        return $user->role->canPerform(Permission::AccessCompliance);
    }

    public function update(User $user, ComplianceCase $case): bool
    {
        return $user->role->canPerform(Permission::AccessCompliance);
    }

    public function addNote(User $user, ComplianceCase $case): bool
    {
        return $user->role->canPerform(Permission::AccessCompliance);
    }
}
