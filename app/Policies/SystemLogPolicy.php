<?php

namespace App\Policies;

use App\Models\SystemLog;
use App\Models\User;

class SystemLogPolicy
{
    /**
     * Determine whether the user can view any models.
     * Compliance officers, admins, and auditors can view audit logs.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'compliance', 'auditor']) ||
               $user->can('access_compliance_dashboard');
    }

    /**
     * Determine whether the user can view the model.
     * Same role requirements as viewAny.
     */
    public function view(User $user, SystemLog $systemLog): bool
    {
        return $user->hasRole(['admin', 'compliance', 'auditor']) ||
               $user->can('access_compliance_dashboard');
    }

    /**
     * Determine whether the user can create models.
     * Only the AuditService should create logs (via internal calls).
     * Direct user creation is not allowed.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     * Audit logs are immutable - no updates allowed.
     * Only the SealAuditHashJob can set hashes during initial sealing.
     */
    public function update(User $user, SystemLog $systemLog): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     * Audit logs cannot be deleted - required for compliance.
     * Only automated retention policies can archive old logs.
     */
    public function delete(User $user, SystemLog $systemLog): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, SystemLog $systemLog): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SystemLog $systemLog): bool
    {
        return false;
    }
}
