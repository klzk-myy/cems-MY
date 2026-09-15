<?php

namespace App\Enums;

/**
 * Permission Enum
 *
 * Defines all granular permissions that can be toggled per role via the
 * admin role-permission management UI. These keys map to the role-permission
 * matrix and are stored in the role_permissions table.
 *
 * Route middleware accepts permission keys directly: `role:manage_counters`
 * resolves through UserRole::matchesRoleAlias() to a matrix check, so a
 * grant in the admin UI both unlocks the routes and shows the sidebar link.
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
    case ManageTransactions = 'manage_transactions';
    case ManageDlq = 'manage_dlq';
    case RequestCancellation = 'request_cancellation';
    case AccessRates = 'access_rates';
    case ValidateRates = 'validate_rates';
    case OperateCounters = 'operate_counters';
    case ManageCounters = 'manage_counters';
    case ManageStock = 'manage_stock';
    case RequestStock = 'request_stock';
    case ManageAllocations = 'manage_allocations';
    case ManageStockTransfers = 'manage_stock_transfers';
    case ManageEod = 'manage_eod';
    case ViewEodReconciliation = 'view_eod_reconciliation';
    case ViewRiskDashboard = 'view_risk_dashboard';
    case ManageRiskScreening = 'manage_risk_screening';
    case ManageSanctions = 'manage_sanctions';
    case ManageAccounting = 'manage_accounting';
    case PostExpenses = 'post_expenses';
    case PostJournalEntries = 'post_journal_entries';
    case ManageCustomers = 'manage_customers';
    case AccessBranches = 'access_branches';
    case ManageBranches = 'manage_branches';
    case ManageBranchClosing = 'manage_branch_closing';
    case ManageRolePermissions = 'manage_role_permissions';
    case ManageThresholds = 'manage_thresholds';
    case AccessPerformance = 'access_performance';
    case ManageReportSchedules = 'manage_report_schedules';
    case ManageCurrencies = 'manage_currencies';
    case ManageSystemAlerts = 'manage_system_alerts';
    case ManageSystem = 'manage_system';
    case ViewTestResults = 'view_test_results';

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
            self::ManageTransactions => 'Manage Transaction Batches',
            self::ManageDlq => 'Manage Dead Letter Queue',
            self::RequestCancellation => 'Request Cancellations',
            self::AccessRates => 'Manage Exchange Rates',
            self::ValidateRates => 'Validate Rates',
            self::OperateCounters => 'Operate Counters',
            self::ManageCounters => 'Manage Counters',
            self::ManageStock => 'Manage Stock & Cash',
            self::RequestStock => 'Request Stock Allocations',
            self::ManageAllocations => 'Manage Teller Allocations',
            self::ManageStockTransfers => 'Manage Stock Transfers',
            self::ManageEod => 'Manage End of Day',
            self::ViewEodReconciliation => 'View EOD Reconciliation',
            self::ViewRiskDashboard => 'View Risk Dashboard',
            self::ManageRiskScreening => 'Manage Risk Screening',
            self::ManageSanctions => 'Manage Sanctions Lists',
            self::ManageAccounting => 'Manage Accounting Periods',
            self::PostExpenses => 'Post Branch Expenses',
            self::PostJournalEntries => 'Post Journal Entries',
            self::ManageCustomers => 'Manage Customers',
            self::AccessBranches => 'Access Branches',
            self::ManageBranches => 'Manage Branches',
            self::ManageBranchClosing => 'Manage Branch Closing',
            self::ManageRolePermissions => 'Manage Role Permissions',
            self::ManageThresholds => 'Manage Thresholds',
            self::AccessPerformance => 'Access Performance Dashboard',
            self::ManageReportSchedules => 'Manage Report Schedules',
            self::ManageCurrencies => 'Manage Currencies',
            self::ManageSystemAlerts => 'Manage System Alerts',
            self::ManageSystem => 'Manage System',
            self::ViewTestResults => 'View Test Results',
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
            self::ManageTransactions => 'Bulk upload, import, and export transactions',
            self::ManageDlq => 'View, retry, and purge failed transaction jobs',
            self::RequestCancellation => 'Request cancellation of transactions',
            self::AccessRates => 'View and manage daily exchange rates',
            self::ValidateRates => 'Validate a transaction rate against configured limits (API)',
            self::OperateCounters => 'Open counters, view status/history, and hand over custody',
            self::ManageCounters => 'Create counters, close counters, and acknowledge emergency closures',
            self::ManageStock => 'Open/close tills, fund branch pools, and view stock positions',
            self::RequestStock => 'Request, accept, and return own teller stock allocations',
            self::ManageAllocations => 'Approve, reject, and modify teller stock allocations',
            self::ManageStockTransfers => 'Create, dispatch, receive, and complete inter-branch stock transfers',
            self::ManageEod => 'View the branch EOD dashboard and close the day',
            self::ViewEodReconciliation => 'View daily and per-counter reconciliation reports (API)',
            self::ViewRiskDashboard => 'View the customer risk dashboard and trends',
            self::ManageRiskScreening => 'Trigger portfolio-wide customer risk rescreening',
            self::ManageSanctions => 'Import and maintain sanctions list entries (API)',
            self::ManageAccounting => 'Run month-end close and manage accounting periods (API)',
            self::PostExpenses => 'Create and post petty-cash/branch expenses',
            self::PostJournalEntries => 'Create and post manual journal entries',
            self::ManageCustomers => 'Close customer accounts and perform customer maintenance',
            self::AccessBranches => 'View and edit own branch details',
            self::ManageBranches => 'Create, update, and deactivate branches',
            self::ManageBranchClosing => 'Run the branch closing workflow',
            self::ManageRolePermissions => 'Edit the role-permission matrix',
            self::ManageThresholds => 'View and override compliance/operational threshold values',
            self::AccessPerformance => 'View the performance monitoring dashboard',
            self::ManageReportSchedules => 'Schedule automated report generation',
            self::ManageCurrencies => 'Create, edit, and disable traded currencies',
            self::ManageSystemAlerts => 'View and acknowledge operational system alerts',
            self::ManageSystem => 'System health, setup reset, and infrastructure endpoints',
            self::ViewTestResults => 'View the test-results dashboard',
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
            self::TransferTellerStock,
            self::ManageTransactions,
            self::ManageDlq,
            self::RequestCancellation => 'Transactions',
            self::AccessRates,
            self::ValidateRates => 'Rates',
            self::OperateCounters,
            self::ManageCounters => 'Counters',
            self::ManageStock,
            self::RequestStock,
            self::ManageAllocations,
            self::ManageStockTransfers,
            self::ManageEod,
            self::ViewEodReconciliation => 'Stock & Cash',
            self::AccessCompliance,
            self::ViewRiskDashboard,
            self::ManageRiskScreening,
            self::ManageSanctions => 'Compliance',
            self::AccessAccounting,
            self::ManageAccounting,
            self::PostExpenses,
            self::PostJournalEntries => 'Accounting',
            self::ManageUsers,
            self::ManageSettings,
            self::ManageAllBranches,
            self::AssignRoles,
            self::ManageCustomers,
            self::AccessBranches,
            self::ManageBranches,
            self::ManageBranchClosing,
            self::ManageRolePermissions,
            self::ManageThresholds => 'Administration',
            self::ViewReports,
            self::AccessPerformance,
            self::ManageReportSchedules => 'Reports',
            self::ManageCurrencies,
            self::ManageSystemAlerts,
            self::ManageSystem,
            self::ViewTestResults => 'System',
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
     * Used by the seeder to populate the role_permissions table and by the
     * admin UI's "Default" action to restore the built-in access model.
     *
     * Defaults mirror the access the route map granted before the matrix
     * became authoritative, so applying defaults changes nothing.
     *
     * @return array<string, list<string>> role => [permission keys]
     */
    public static function defaultMatrix(): array
    {
        return [
            UserRole::Teller->value => [
                self::CreateTransactions->value,
                self::RequestCancellation->value,
                self::OperateCounters->value,
                self::RequestStock->value,
                self::ValidateRates->value,
            ],
            UserRole::Manager->value => [
                self::AccessAccounting->value,
                self::ApproveCancellations->value,
                self::ManageUsers->value,
                self::ManageSettings->value,
                self::ViewReports->value,
                self::TransferTellerStock->value,
                self::AssignRoles->value,
                self::AccessPerformance->value,
                self::AccessRates->value,
                self::ValidateRates->value,
                self::ManageTransactions->value,
                self::RequestCancellation->value,
                self::ManageCustomers->value,
                self::OperateCounters->value,
                self::ManageCounters->value,
                self::ManageStock->value,
                self::ManageAllocations->value,
                self::ManageStockTransfers->value,
                self::ManageEod->value,
                self::ViewEodReconciliation->value,
                self::ViewRiskDashboard->value,
                self::AccessBranches->value,
                self::ManageBranchClosing->value,
                self::ManageAccounting->value,
                self::PostExpenses->value,
                self::PostJournalEntries->value,
            ],
            UserRole::ComplianceOfficer->value => [
                self::ApproveTransactions->value,
                self::ApproveCancellations->value,
                self::ReverseTransactions->value,
                self::AccessCompliance->value,
                self::ViewReports->value,
                self::RequestCancellation->value,
                self::ViewEodReconciliation->value,
            ],
            UserRole::Accountant->value => [
                self::AccessAccounting->value,
                self::ViewReports->value,
                self::ManageAllBranches->value,
                self::PostJournalEntries->value,
            ],
            UserRole::Admin->value => array_column(self::cases(), 'value'),
        ];
    }
}
