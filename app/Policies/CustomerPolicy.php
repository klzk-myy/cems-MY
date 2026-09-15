<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    /**
     * Determine whether the user can view any customers.
     * Customers are company-wide entities with no branch ownership: every
     * authenticated role may view them regardless of branch assignment.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the customer.
     * Customers are company-wide (same gate as viewAny).
     */
    public function view(User $user, Customer $customer): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can create customers.
     * Tellers register customers as part of transaction creation; managers
     * via the manage_customers permission. Both are matrix grants, so an
     * admin can extend registration to any role.
     */
    public function create(User $user): bool
    {
        return $user->role->canPerform(Permission::CreateTransactions)
            || $user->role->canPerform(Permission::ManageCustomers);
    }

    /**
     * Determine whether the user can update the customer.
     * Requires the manage_customers matrix permission (managers by
     * default; admins always).
     */
    public function update(User $user, Customer $customer): bool
    {
        return $user->role->canPerform(Permission::ManageCustomers);
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
     * Customers are company-wide: any authenticated role can record notes
     * on any customer (same gate as viewAny).
     */
    public function createNote(User $user, Customer $customer): bool
    {
        return $this->viewAny($user);
    }
}
