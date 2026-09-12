<?php

namespace App\Enums;

use App\Models\Branch;
use App\Models\User;

/**
 * User Role Enum
 *
 * Represents the different roles a user can have in the system
 * with their associated permissions.
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
     * Manager or admin.
     */
    public function canApproveTransactions(): bool
    {
        return $this->isManager();
    }

    /**
     * Check if the user can approve large transactions.
     * Transactions >= RM 50,000 require compliance officer or admin approval.
     */
    public function canApproveLargeTransactions(): bool
    {
        return $this->isComplianceOfficer();
    }

    /**
     * Check if the user can access compliance features.
     */
    public function canAccessCompliance(): bool
    {
        return $this->isComplianceOfficer();
    }

    /**
     * Check if the user can access accounting features.
     * Managers see their own branch; accountants and admin see company-wide.
     */
    public function canAccessAccounting(): bool
    {
        return $this->isManager() || $this === self::Accountant;
    }

    /**
     * Check if the user can create transactions.
     * Only tellers create transactions.
     */
    public function canCreateTransaction(): bool
    {
        return $this === self::Teller;
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
     */
    public function canManageUsers(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Check if the user can manage system settings.
     */
    public function canManageSettings(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Check if the user can approve counter handovers.
     */
    public function canApproveHandover(): bool
    {
        return $this->isManager();
    }

    /**
     * Check if the user can cancel any transaction.
     * Managers and compliance officers can approve cancellations.
     */
    public function canCancelAnyTransaction(): bool
    {
        return $this->isManager() || $this->isComplianceOfficer();
    }

    /**
     * Check if the user can view reports.
     */
    public function canViewReports(): bool
    {
        return in_array($this, [self::Manager, self::ComplianceOfficer, self::Accountant, self::Admin], true);
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
        return $this->isComplianceOfficer();
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
     */
    public function assignableRoles(): array
    {
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
     * Only Admin role has cross-branch management privileges.
     */
    public function canManageAllBranches(): bool
    {
        return $this === self::Admin;
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
