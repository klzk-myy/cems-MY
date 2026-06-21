<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\JournalEntry;
use App\Models\User;

class JournalEntryPolicy
{
    /**
     * Determine whether the user can view any journal entries.
     * Managers and admins can view journal entries.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Manager, UserRole::Admin]);
    }

    /**
     * Determine whether the user can view the journal entry.
     * Managers and admins can view journal entries.
     */
    public function view(User $user, JournalEntry $journalEntry): bool
    {
        return in_array($user->role, [UserRole::Manager, UserRole::Admin]);
    }

    /**
     * Determine whether the user can create journal entries.
     * Managers and admins can create journal entries.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Manager, UserRole::Admin]);
    }

    /**
     * Determine whether the user can update the journal entry.
     * Only managers and admins can update journal entries.
     */
    public function update(User $user, JournalEntry $journalEntry): bool
    {
        return in_array($user->role, [UserRole::Manager, UserRole::Admin]);
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
