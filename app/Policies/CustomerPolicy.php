<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    /**
     * Determine whether the user can view any customers.
     * Users can view customers if they are assigned to a branch (or are admin).
     */
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin || $user->branch_id !== null;
    }

    /**
     * Determine whether the user can view the customer.
     * Customers are company-wide: any branch-assigned staff member may view
     * any customer (same gate as viewAny).
     */
    public function view(User $user, Customer $customer): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can create customers.
     * Tellers, managers, and admins can create customers.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Teller, UserRole::Manager, UserRole::Admin]);
    }

    /**
     * Determine whether the user can update the customer.
     * Customers are company-wide: managers and admins can update any customer.
     */
    public function update(User $user, Customer $customer): bool
    {
        return $user->role === UserRole::Admin || $user->role === UserRole::Manager;
    }

    /**
     * Determine whether the user can delete the customer.
     * Only admins can delete customers.
     */
    public function delete(User $user, Customer $customer): bool
    {
        if ($user->role !== UserRole::Admin) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can add a note to the customer.
     * Customers are company-wide: any branch-assigned staff member can record
     * notes on any customer.
     */
    public function createNote(User $user, Customer $customer): bool
    {
        return $this->viewAny($user);
    }
}
