<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\JournalEntry;
use App\Models\User;

class JournalEntryPolicy
{
    /**
     * Determine whether the user can view any journal entries.
     * Managers (own branch), accountants and admins (company-wide).
     */
    public function viewAny(User $user): bool
    {
        return $user->role->canAccessAccounting();
    }

    /**
     * Determine whether the user can view the journal entry.
     * Non-admins are restricted to entries scoped to their own branch.
     */
    public function view(User $user, JournalEntry $journalEntry): bool
    {
        if (! $user->role->canAccessAccounting()) {
            return false;
        }

        if ($user->role->canManageAllBranches()) {
            return true;
        }

        return $journalEntry->branch_id === null
            || $journalEntry->branch_id === $user->branch_id;
    }

    /**
     * Determine whether the user can create journal entries.
     * Branch managers post branch journals; admins and accountants post
     * company-wide. Entries post directly — no approval step.
     */
    public function create(User $user): bool
    {
        return $user->role->canPerform(Permission::PostJournalEntries);
    }

    /**
     * Determine whether the user can update the journal entry.
     * Requires the manage_accounting permission (managers by default;
     * admins always).
     */
    public function update(User $user, JournalEntry $journalEntry): bool
    {
        return $user->role->canPerform(Permission::ManageAccounting);
    }

    /**
     * Determine whether the user can reverse the journal entry.
     * Requires the manage_accounting permission (managers by default;
     * admins always).
     */
    public function reverse(User $user, JournalEntry $journalEntry): bool
    {
        return $user->role->canPerform(Permission::ManageAccounting);
    }

    /**
     * Determine whether the user can delete the journal entry.
     * Only admins can delete journal entries.
     */
    public function delete(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }
}
