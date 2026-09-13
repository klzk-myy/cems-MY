<?php

namespace App\Enums;

use App\Models\Branch;
use App\Models\User;
use App\Services\System\PermissionService;

/**
 * User Role Enum
 *
 * Represents the different roles a user can have in the system
 * with their associated permissions.
 *
 * Capability checks (can*() methods) have two layers: the static ceiling
 * declared here, and the admin-managed role_permissions matrix as a
 * restrictive overlay — it can revoke a built-in capability but can never
 * grant one the role does not statically hold. Admin is exempt from the
 * matrix: it operates it, and BNM requires an always-capable principal
 * officer.
 */
enum UserRole: string
{
    case Teller = 'teller';
    case Manager = 'manager';
    case ComplianceOfficer = 'compliance_officer';
    case Accountant = 'accountant';
    case Admin = 'admin';

    /**
     * Check if the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }

    /**
     * Check if the user is an accountant or admin.
     * Accountants handle company-wide accounting; admins inherit.
     */
    public function isAccountant(): bool
    {
        return in_array($this, [self::Accountant, self::Admin], true);
    }

    /**
     * Check if the user is a manager or admin.
     */
    public function isManager(): bool
    {
        return in_array($this, [self::Manager, self::Admin], true);
    }

    /**
     * Check if the user is a compliance officer or admin.
     */
    public function isComplianceOfficer(): bool
    {
        return in_array($this, [self::ComplianceOfficer, self::Admin], true);
    }

    /**
     * Check if the user is a teller.
     */
    public function isTeller(): bool
    {
        return $this === self::Teller;
    }

    /**
     * Check if the user can approve mid-tier transactions (RM10k–50k).
     * All approvals require compliance officer or admin.
     */
    public function canApproveTransactions(): bool
    {
        return $this->canPerform(Permission::ApproveTransactions);
    }

    /**
     * Check if the user can approve large transactions.
     * All approvals require compliance officer or admin.
     */
    public function canApproveLargeTransactions(): bool
    {
        return $this->canPerform(Permission::ApproveTransactions);
    }

    /**
     * Check if the user can access compliance features.
     */
    public function canAccessCompliance(): bool
    {
        return $this->canPerform(Permission::AccessCompliance);
    }

    /**
     * Check if the user can access accounting features.
     * Managers see their own branch; accountants and admin see company-wide.
     */
    public function canAccessAccounting(): bool
    {
        return $this->canPerform(Permission::AccessAccounting);
    }

    /**
     * Check if the user can create transactions.
     * Only tellers create transactions.
     */
    public function canCreateTransaction(): bool
    {
        return $this->canPerform(Permission::CreateTransactions);
    }

    /**
     * Check if the user has full system access.
     * Only Admin has full system access.
     */
    public function canAccessAll(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Check if the user can manage users.
     * Admins manage all users; managers manage own branch users.
     */
    public function canManageUsers(): bool
    {
        return $this->canPerform(Permission::ManageUsers);
    }

    /**
     * Check if the user can manage system settings.
     * Admins manage all settings; managers manage own branch settings.
     */
    public function canManageSettings(): bool
    {
        return $this->canPerform(Permission::ManageSettings);
    }

    /**
     * Check if the user can approve counter handovers.
     */
    public function canApproveHandover(): bool
    {
        return $this->isManager();
    }

    /**
     * Check if the user can transfer teller stock/cash within their own branch.
     * Managers and admins can perform within-branch transfers.
     */
    public function canTransferTellerStock(): bool
    {
        return $this->canPerform(Permission::TransferTellerStock);
    }

    /**
     * Check if the user can cancel any transaction.
     * Managers and compliance officers can approve cancellations.
     */
    public function canCancelAnyTransaction(): bool
    {
        return $this->canPerform(Permission::ApproveCancellations);
    }

    /**
     * Check if the user can view reports.
     */
    public function canViewReports(): bool
    {
        return $this->canPerform(Permission::ViewReports);
    }

    /**
     * Check if the user can perform revaluation.
     */
    public function canPerformRevaluation(): bool
    {
        return $this->isManager() || $this === self::Accountant;
    }

    /**
     * Check if the user can reverse a completed transaction.
     * Reversals are compliance-only.
     */
    public function canReverseTransaction(): bool
    {
        return $this->canPerform(Permission::ReverseTransactions);
    }

    /**
     * Get a human-readable label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Teller => 'Teller',
            self::Manager => 'Manager',
            self::ComplianceOfficer => 'Compliance Officer',
            self::Accountant => 'Accountant',
            self::Admin => 'Administrator',
        };
    }

    /**
     * Get a description of the role's permissions.
     */
    public function description(): string
    {
        return match ($this) {
            self::Teller => 'Can create transactions',
            self::Manager => 'Can approve transactions and manage counters',
            self::ComplianceOfficer => 'Can review flagged transactions and compliance reports',
            self::Accountant => 'Company-wide accounting, journals, and financial reports',
            self::Admin => 'Full system access',
        };
    }

    /**
     * Get all roles that can be assigned by this role.
     * Revoking the assign_roles matrix permission empties the set — the
     * role can then neither assign nor manage accounts.
     *
     * @return list<self>
     */
    public function assignableRoles(): array
    {
        if (! $this->matrixAllows(Permission::AssignRoles)) {
            return [];
        }

        return match ($this) {
            self::Admin => [self::Teller, self::Manager, self::ComplianceOfficer, self::Accountant, self::Admin],
            self::Manager => [self::Teller],
            default => [],
        };
    }

    /**
     * Get the rate override limit percentage for this role.
     *
     * Per BNM compliance requirements:
     * - Teller: ±0.5% from base rate
     * - Manager: ±2.0% from base rate
     * - Admin (Principal Officer): Unlimited
     *
     * @return float|null null means unlimited
     */
    public function rateOverrideLimit(): ?float
    {
        return match ($this) {
            self::Teller => 0.5,
            self::Manager => 2.0,
            self::ComplianceOfficer => null,
            self::Accountant => null,
            self::Admin => null, // Unlimited
        };
    }

    /**
     * Check if this role can apply a rate override without approval.
     *
     * @param  float  $overridePercentage  The percentage deviation from base rate
     */
    public function canOverrideRate(float $overridePercentage): bool
    {
        $limit = $this->rateOverrideLimit();

        // null means unlimited
        if ($limit === null) {
            return true;
        }

        return abs($overridePercentage) <= $limit;
    }

    /**
     * Check if this role requires rate override approval.
     *
     * @param  float  $overridePercentage  The percentage deviation from base rate
     */
    public function requiresRateOverrideApproval(float $overridePercentage): bool
    {
        return ! $this->canOverrideRate($overridePercentage);
    }

    /**
     * Check if the user can manage all branches.
     * Admin and Accountant roles have cross-branch access — accountants
     * handle company-wide accounting and financial reporting.
     */
    public function canManageAllBranches(): bool
    {
        return $this->canPerform(Permission::ManageAllBranches);
    }

    /**
     * The role's built-in capability ceiling for a dynamic permission.
     * The role_permissions matrix can only narrow this set — it can never
     * grant a permission the role does not statically hold.
     */
    public function staticallyGrants(Permission $permission): bool
    {
        return match ($permission) {
            Permission::CreateTransactions => $this === self::Teller,
            Permission::ApproveTransactions => $this->isComplianceOfficer(),
            Permission::ApproveCancellations => $this->isManager() || $this->isComplianceOfficer(),
            Permission::ReverseTransactions => $this->isComplianceOfficer(),
            Permission::AccessCompliance => $this->isComplianceOfficer(),
            Permission::AccessAccounting => $this->isManager() || $this === self::Accountant,
            Permission::ManageUsers => $this->isManager(),
            Permission::ManageSettings => $this->isManager(),
            Permission::ViewReports => in_array($this, [self::Manager, self::ComplianceOfficer, self::Accountant, self::Admin], true),
            Permission::ManageAllBranches => in_array($this, [self::Admin, self::Accountant], true),
            Permission::TransferTellerStock => $this->isManager(),
            Permission::AssignRoles => in_array($this, [self::Manager, self::Admin], true),
        };
    }

    /**
     * Whether the admin-managed role_permissions matrix grants this
     * permission to the role. Admin is exempt — it operates the matrix and
     * BNM requires an always-capable principal officer.
     */
    private function matrixAllows(Permission $permission): bool
    {
        if ($this === self::Admin) {
            return true;
        }

        return app(PermissionService::class)->can($this, $permission);
    }

    /**
     * Effective permission check: the static ceiling AND the dynamic
     * role_permissions matrix must both grant the permission.
     */
    public function canPerform(Permission $permission): bool
    {
        return $this->staticallyGrants($permission) && $this->matrixAllows($permission);
    }

    /**
     * Check if the user can access a specific branch.
     * Admin can access any branch; others can only access their own branch.
     */
    public function canAccessBranch(Branch $branch, User $user): bool
    {
        if ($this->canManageAllBranches()) {
            return true;
        }

        return $user->branch_id === $branch->id;
    }
}
