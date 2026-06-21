<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    /**
     * Determine whether the user can view any customers.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the customer.
     */
    public function view(User $user, Customer $customer): bool
    {
        return true;
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
     * Managers and admins can update customers.
     */
    public function update(User $user, Customer $customer): bool
    {
        return in_array($user->role, [UserRole::Manager, UserRole::Admin]);
    }

    /**
     * Determine whether the user can delete the customer.
     * Only admins can delete customers.
     */
    public function delete(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }
}
