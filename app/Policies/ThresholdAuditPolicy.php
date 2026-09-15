<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ThresholdAudit;
use App\Models\User;

class ThresholdAuditPolicy
{
    /**
     * Determine whether the user can view any models.
     * Requires the manage_thresholds permission — same gate as the admin
     * thresholds page, which is the only UI that lists audit history.
     */
    public function viewAny(User $user): bool
    {
        return $user->role->canPerform(Permission::ManageThresholds);
    }

    /**
     * Determine whether the user can view the model.
     * Same role requirements as viewAny.
     */
    public function view(User $user, ThresholdAudit $thresholdAudit): bool
    {
        return $user->role->canPerform(Permission::ManageThresholds);
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
