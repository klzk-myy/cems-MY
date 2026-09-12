<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\CustomerDocument;
use App\Models\User;

class CustomerDocumentPolicy
{
    /**
     * Determine whether the user can verify a customer document.
     */
    public function verify(User $user, CustomerDocument $document): bool
    {
        return $this->review($user, $document);
    }

    /**
     * Determine whether the user can reject a customer document.
     */
    public function reject(User $user, CustomerDocument $document): bool
    {
        return $this->review($user, $document);
    }

    /**
     * Determine whether the user can download a customer document.
     */
    public function download(User $user, CustomerDocument $document): bool
    {
        return $this->review($user, $document);
    }

    /**
     * Review actions are restricted to compliance officers and admins
     * (mirroring the role:compliance,admin route middleware) with the
     * same branch isolation rule as CustomerPolicy::view.
     */
    protected function review(User $user, CustomerDocument $document): bool
    {
        if (! in_array($user->role, [UserRole::ComplianceOfficer, UserRole::Admin], true)) {
            return false;
        }

        if ($user->role === UserRole::Admin) {
            return true;
        }

        // Customers are company-wide: a compliance officer may review any
        // customer's documents.
        return true;
    }
}
