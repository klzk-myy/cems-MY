<?php

namespace App\Policies;

use App\Models\ThresholdAudit;
use App\Models\User;

class ThresholdAuditPolicy
{
    /**
     * Determine whether the user can view any models.
     * Compliance officers, admins, and auditors can view threshold audits.
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
    public function view(User $user, ThresholdAudit $thresholdAudit): bool
    {
        return $user->hasRole(['admin', 'compliance', 'auditor']) ||
               $user->can('access_compliance_dashboard');
    }

    /**
     * Determine whether the user can create models.
     * Only the ThresholdService should create audits (via internal calls).
     * Direct user creation is not allowed.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     * Threshold audits are immutable - no updates allowed.
     */
    public function update(User $user, ThresholdAudit $thresholdAudit): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     * Threshold audits cannot be deleted - required for compliance.
     */
    public function delete(User $user, ThresholdAudit $thresholdAudit): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ThresholdAudit $thresholdAudit): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ThresholdAudit $thresholdAudit): bool
    {
        return false;
    }
}
