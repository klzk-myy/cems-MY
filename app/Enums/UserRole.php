<?php

namespace App\Enums;

use App\Models\Branch;
use App\Models\User;
use App\Services\System\PermissionService;
use App\Services\ThresholdService;
use App\Support\ThresholdDefaults;

/**
 * User Role Enum
 *
 * Represents the different roles a user can have in the system
 * with their associated permissions.
 *
 * Capability checks (can*() methods) are driven by the admin-managed
 * role_permissions matrix, seeded from the built-in defaults declared in
 * Permission::defaultMatrix(). An administrator can grant any permission
 * to any role, or revoke a built-in capability.
 * Admin is exempt from the matrix: it operates it, and BNM requires an
 * always-capable principal officer.
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
        return $this->canPerform(Permission::ManageCounters);
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
     * Running revaluation is gated behind manage_accounting (DESIGN.md).
     */
    public function canPerformRevaluation(): bool
    {
        return $this->canPerform(Permission::ManageAccounting);
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
            // Managers — and any other role granted assign_roles in the
            // matrix — may assign the floor-level teller role only.
            default => [self::Teller],
        };
    }

    /**
     * Get the rate override limit percentage for this role.
     *
     * Per BNM compliance requirements the limits live in the threshold
     * system (thresholds.rates.override_limit_*), so an admin can tune or
     * override them via the thresholds page with a full audit trail:
     * - Teller: ±0.5% from base rate
     * - Manager: ±2.0% from base rate
     * - Admin (Principal Officer): Unlimited
     *
     * @return float|null null means unlimited
     */
    public function rateOverrideLimit(): ?float
    {
        // Enums cannot use constructor DI; resolved intentionally.
        return match ($this) {
            self::Teller => (float) app(ThresholdService::class)
                ->get('rates', 'override_limit_teller', ThresholdDefaults::FALLBACK_RATE_OVERRIDE_LIMIT_TELLER),
            self::Manager => (float) app(ThresholdService::class)
                ->get('rates', 'override_limit_manager', ThresholdDefaults::FALLBACK_RATE_OVERRIDE_LIMIT_MANAGER),
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
     * Branch operating roles — every screen and posting for a teller or
     * manager is branch-scoped, so the account is meaningless without a
     * home branch. Office roles (admin, accountant, compliance officer)
     * legitimately operate without a branch assignment.
     */
    public function requiresBranch(): bool
    {
        return match ($this) {
            self::Teller, self::Manager => true,
            self::ComplianceOfficer, self::Accountant, self::Admin => false,
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

        // Enums cannot use constructor DI; resolved intentionally.
        return app(PermissionService::class)->can($this, $permission);
    }

    /**
     * Effective permission check: the admin-managed role_permissions
     * matrix must grant the permission. The matrix is seeded from the
     * role's built-in defaults but may be widened or narrowed by an admin.
     */
    public function canPerform(Permission $permission): bool
    {
        return $this->matrixAllows($permission);
    }

    /**
     * Whether this role satisfies a `role:` middleware argument. Identity
     * aliases match the role itself (including admin's manager/accountant
     * inheritance); the legacy module aliases and any Permission key are
     * effective-permission checks against the role_permissions matrix —
     * `role:manage_counters` is equivalent to `canPerform(ManageCounters)`.
     * Unknown strings throw so typos in route files surface loudly instead
     * of failing closed.
     */
    public function matchesRoleAlias(string $alias): bool
    {
        return match ($alias) {
            'admin' => $this->isAdmin(),
            'manager' => $this->isManager(),
            'compliance' => $this->canAccessCompliance(),
            'accountant' => $this->isAccountant() && $this->canAccessAccounting(),
            'accounting' => $this->canAccessAccounting(),
            'users' => $this->canManageUsers(),
            'teller' => $this->isTeller(),
            default => $this->matchesPermissionKey($alias),
        };
    }

    /**
     * Resolve a `role:` argument that is a Permission key through the
     * role_permissions matrix.
     */
    private function matchesPermissionKey(string $key): bool
    {
        $permission = Permission::tryFrom($key);

        if ($permission === null) {
            throw new \InvalidArgumentException(
                "Unknown role [{$key}] in role middleware"
            );
        }

        return $this->canPerform($permission);
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
