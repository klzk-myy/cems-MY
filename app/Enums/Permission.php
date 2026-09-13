<?php

namespace App\Enums;

/**
 * Permission Enum
 *
 * Defines all granular permissions that can be toggled per role via the
 * admin role-permission management UI. These keys map to the role-permission
 * matrix and are stored in the role_permissions table.
 */
enum Permission: string
{
    case CreateTransactions = 'create_transactions';
    case ApproveTransactions = 'approve_transactions';
    case ApproveCancellations = 'approve_cancellations';
    case ReverseTransactions = 'reverse_transactions';
    case AccessCompliance = 'access_compliance';
    case AccessAccounting = 'access_accounting';
    case ManageUsers = 'manage_users';
    case ManageSettings = 'manage_settings';
    case ViewReports = 'view_reports';
    case ManageAllBranches = 'manage_all_branches';
    case TransferTellerStock = 'transfer_teller_stock';
    case AssignRoles = 'assign_roles';

    /**
     * Get a human-readable label for the permission.
     */
    public function label(): string
    {
        return match ($this) {
            self::CreateTransactions => 'Create Transactions',
            self::ApproveTransactions => 'Approve Transactions',
            self::ApproveCancellations => 'Approve Cancellations',
            self::ReverseTransactions => 'Reverse Completed Transactions',
            self::AccessCompliance => 'Access Compliance',
            self::AccessAccounting => 'Access Accounting',
            self::ManageUsers => 'Manage Users',
            self::ManageSettings => 'Manage Settings',
            self::ViewReports => 'View Reports',
            self::ManageAllBranches => 'Manage All Branches',
            self::TransferTellerStock => 'Transfer Teller Stock (Within Branch)',
            self::AssignRoles => 'Assign Roles',
        };
    }

    /**
     * Get a description of the permission.
     */
    public function description(): string
    {
        return match ($this) {
            self::CreateTransactions => 'Create new currency exchange transactions',
            self::ApproveTransactions => 'Approve pending transactions (all amounts)',
            self::ApproveCancellations => 'Approve transaction cancellation requests',
            self::ReverseTransactions => 'Reverse completed transactions (compliance action)',
            self::AccessCompliance => 'Access compliance workflows, alerts, and AML features',
            self::AccessAccounting => 'Access accounting module: journals, ledgers, financial reports',
            self::ManageUsers => 'Create, edit, and manage user accounts (scoped to own branch for managers)',
            self::ManageSettings => 'Manage branch settings (scoped to own branch for managers)',
            self::ViewReports => 'View operational and regulatory reports',
            self::ManageAllBranches => 'Manage all branches (cross-branch access)',
            self::TransferTellerStock => 'Transfer stock and cash between tellers within own branch',
            self::AssignRoles => 'Assign roles to other users',
        };
    }

    /**
     * Get the category/group for this permission (for UI grouping).
     */
    public function category(): string
    {
        return match ($this) {
            self::CreateTransactions,
            self::ApproveTransactions,
            self::ApproveCancellations,
            self::ReverseTransactions,
            self::TransferTellerStock => 'Transactions',
            self::AccessCompliance => 'Compliance',
            self::AccessAccounting => 'Accounting',
            self::ManageUsers,
            self::ManageSettings,
            self::ManageAllBranches,
            self::AssignRoles => 'Administration',
            self::ViewReports => 'Reports',
        };
    }

    /**
     * Get all permissions grouped by category.
     *
     * @return array<string, list<self>>
     */
    public static function groupedByCategory(): array
    {
        $grouped = [];
        foreach (self::cases() as $permission) {
            $grouped[$permission->category()][] = $permission;
        }

        return $grouped;
    }

    /**
     * Default permission matrix: which roles have which permissions by default.
     * Used by the seeder to populate the role_permissions table.
     *
     * @return array<string, list<string>> role => [permission keys]
     */
    public static function defaultMatrix(): array
    {
        return [
            UserRole::Teller->value => [
                self::CreateTransactions->value,
            ],
            UserRole::Manager->value => [
                self::AccessAccounting->value,
                self::ApproveCancellations->value,
                self::ManageUsers->value,
                self::ManageSettings->value,
                self::ViewReports->value,
                self::TransferTellerStock->value,
                self::AssignRoles->value,
            ],
            UserRole::ComplianceOfficer->value => [
                self::ApproveTransactions->value,
                self::ApproveCancellations->value,
                self::ReverseTransactions->value,
                self::AccessCompliance->value,
                self::ViewReports->value,
            ],
            UserRole::Accountant->value => [
                self::AccessAccounting->value,
                self::ViewReports->value,
                self::ManageAllBranches->value,
            ],
            UserRole::Admin->value => array_column(self::cases(), 'value'),
        ];
    }
}
