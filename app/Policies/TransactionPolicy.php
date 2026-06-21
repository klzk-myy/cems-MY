<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    /**
     * Determine whether the user can view any transactions.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the transaction.
     */
    public function view(User $user, Transaction $transaction): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create transactions.
     * Tellers, managers, and admins can create transactions.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Teller, UserRole::Manager, UserRole::Admin]);
    }

    /**
     * Determine whether the user can update the transaction.
     * Admins can update any transaction; others can only update their own.
     */
    public function update(User $user, Transaction $transaction): bool
    {
        return $user->role === UserRole::Admin || $transaction->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the transaction.
     * Only admins can delete transactions.
     */
    public function delete(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }
}
