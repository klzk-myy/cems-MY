<?php

namespace App\Config;

use App\Enums\UserRole;

/**
 * Navigation configuration for CEMS-MY
 *
 * Organized by function and features for BNM compliance workflow
 * Groups: Main, Operations, Counter Management, Stock Management, Compliance & AML, Accounting, Reports, System
 */
class Navigation
{
    /**
     * Get the complete navigation structure
     * All groups are at the same level for a flat menu structure
     */
    public static function get(): array
    {
        return [
            // ============================================================
            // MAIN
            // ============================================================
            'main' => [
                'label' => 'Main',
                'items' => [
                    [
                        'label' => 'Dashboard',
                        'route' => 'dashboard',
                        'icon' => 'dashboard',
                        'uri' => '/dashboard',
                    ],
                ],
            ],

            // ============================================================
            // OPERATIONS - Daily operational tasks
            // ============================================================
            'operations' => [
                'label' => 'Operations',
                'items' => [
                    [
                        'label' => 'Transactions',
                        'route' => 'transactions.index',
                        'icon' => 'exchange',
                        'uri' => '/transactions',
                    ],
                    [
                        'label' => 'Customers',
                        'route' => 'customers.index',
                        'icon' => 'users',
                        'uri' => '/customers',
                    ],
                ],
            ],

            // ============================================================
            // COUNTER MANAGEMENT - Till/counter operations
            // ============================================================
            'counter_management' => [
                'label' => 'Counter Management',
                'items' => [
                    [
                        'label' => 'Counters',
                        'route' => 'counters.index',
                        'icon' => 'counter',
                        'uri' => '/counters',
                    ],
                    [
                        'label' => 'Branches',
                        'route' => 'branches.index',
                        'icon' => 'branch',
                        'uri' => '/branches',
                        'role' => UserRole::Admin,
                    ],
                ],
            ],

            // ============================================================
            // STOCK MANAGEMENT - Currency inventory operations
            // ============================================================
            'stock_management' => [
                'label' => 'Stock Management',
                'items' => [
                    [
                        'label' => 'Stock & Cash',
                        'route' => 'stock-cash.index',
                        'icon' => 'cash',
                        'uri' => '/stock-cash',
                    ],
                    [
                        'label' => 'Stock Transfers',
                        'route' => 'stock-transfers.index',
                        'icon' => 'arrows-right-left',
                        'uri' => '/stock-transfers',
                    ],
                ],
            ],

            // ============================================================
            // COMPLIANCE & AML - BNM regulatory compliance
            // ============================================================
            'compliance' => [
                'label' => 'Compliance & AML',
                'items' => [
                    [
                        'label' => 'Compliance',
                        'route' => 'compliance',
                        'icon' => 'shield',
                        'uri' => '/compliance',
                    ],
                    [
                        'label' => 'Compliance Workspace',
                        'route' => 'compliance.workspace',
                        'icon' => 'workspace',
                        'uri' => '/compliance/workspace',
                    ],
                    [
                        'label' => 'Alert Triage',
                        'route' => 'compliance.alerts.index',
                        'icon' => 'alert',
                        'uri' => '/compliance/alerts',
                    ],
                    [
                        'label' => 'Cases',
                        'route' => 'compliance.cases.index',
                        'icon' => 'folder',
                        'uri' => '/compliance/cases',
                    ],
                    [
                        'label' => 'Flagged Transactions',
                        'route' => 'compliance.flagged',
                        'icon' => 'flag',
                        'uri' => '/compliance/flagged',
                    ],
                    [
                        'label' => 'EDD Records',
                        'route' => 'compliance.edd.index',
                        'icon' => 'document',
                        'uri' => '/compliance/edd',
                    ],
                    [
                        'label' => 'EDD Templates',
                        'route' => 'compliance.edd-templates.index',
                        'icon' => 'template',
                        'uri' => '/compliance/edd-templates',
                    ],
                    [
                        'label' => 'AML Rules',
                        'route' => 'compliance.rules.index',
                        'icon' => 'rule',
                        'uri' => '/compliance/rules',
                    ],
                    [
                        'label' => 'Risk Dashboard',
                        'route' => 'compliance.risk-dashboard.index',
                        'icon' => 'trending',
                        'uri' => '/compliance/risk-dashboard',
                    ],
                    [
                        'label' => 'STR Studio',
                        'route' => 'compliance.str-studio.index',
                        'icon' => 'document-text',
                        'uri' => '/compliance/str-studio',
                    ],
                    [
                        'label' => 'Compliance Reporting',
                        'route' => 'compliance.reporting.index',
                        'icon' => 'chart',
                        'uri' => '/compliance/reporting',
                    ],
                    [
                        'label' => 'STR Reports',
                        'route' => 'str.index',
                        'icon' => 'exclamation',
                        'uri' => '/str',
                    ],
                ],
            ],

            // ============================================================
            // ACCOUNTING - Double-entry accounting
            // ============================================================
            'accounting' => [
                'label' => 'Accounting',
                'items' => [
                    [
                        'label' => 'Accounting',
                        'route' => 'accounting.index',
                        'icon' => 'calculator',
                        'uri' => '/accounting',
                    ],
                    [
                        'label' => 'Journal Entries',
                        'route' => 'accounting.journal',
                        'icon' => 'journal',
                        'uri' => '/accounting/journal',
                    ],
                    [
                        'label' => 'Ledger',
                        'route' => 'accounting.ledger',
                        'icon' => 'ledger',
                        'uri' => '/accounting/ledger',
                    ],
                    [
                        'label' => 'Trial Balance',
                        'route' => 'accounting.trial-balance',
                        'icon' => 'balance',
                        'uri' => '/accounting/trial-balance',
                    ],
                    [
                        'label' => 'Profit & Loss',
                        'route' => 'accounting.profit-loss',
                        'icon' => 'chart',
                        'uri' => '/accounting/profit-loss',
                    ],
                    [
                        'label' => 'Balance Sheet',
                        'route' => 'accounting.balance-sheet',
                        'icon' => 'statement',
                        'uri' => '/accounting/balance-sheet',
                    ],
                    [
                        'label' => 'Cash Flow',
                        'route' => 'accounting.cash-flow',
                        'icon' => 'cashflow',
                        'uri' => '/accounting/cash-flow',
                    ],
                    [
                        'label' => 'Financial Ratios',
                        'route' => 'accounting.ratios',
                        'icon' => 'ratio',
                        'uri' => '/accounting/ratios',
                    ],
                    [
                        'label' => 'Revaluation',
                        'route' => 'accounting.revaluation',
                        'icon' => 'currency',
                        'uri' => '/accounting/revaluation',
                    ],
                    [
                        'label' => 'Reconciliation',
                        'route' => 'accounting.reconciliation',
                        'icon' => 'bank',
                        'uri' => '/accounting/reconciliation',
                    ],
                    [
                        'label' => 'Budget',
                        'route' => 'accounting.budget',
                        'icon' => 'budget',
                        'uri' => '/accounting/budget',
                    ],
                    [
                        'label' => 'Periods',
                        'route' => 'accounting.periods',
                        'icon' => 'calendar',
                        'uri' => '/accounting/periods',
                    ],
                    [
                        'label' => 'Fiscal Years',
                        'route' => 'accounting.fiscal-years',
                        'icon' => 'calendar-alt',
                        'uri' => '/accounting/fiscal-years',
                    ],
                ],
            ],

            // ============================================================
            // REPORTS - BNM compliance reporting
            // ============================================================
            'reports' => [
                'label' => 'Reports',
                'items' => [
                    [
                        'label' => 'Reports',
                        'route' => 'reports.index',
                        'icon' => 'report',
                        'uri' => '/reports',
                    ],
                    [
                        'label' => 'MSB2 Report',
                        'route' => 'reports.msb2',
                        'icon' => 'daily',
                        'uri' => '/reports/msb2',
                    ],
                    [
                        'label' => 'LCTR',
                        'route' => 'reports.lctr',
                        'icon' => 'large-cash',
                        'uri' => '/reports/lctr',
                    ],
                    [
                        'label' => 'LMCA',
                        'route' => 'reports.lmca',
                        'icon' => 'monthly',
                        'uri' => '/reports/lmca',
                    ],
                    [
                        'label' => 'Quarterly LVR',
                        'route' => 'reports.quarterly-lvr',
                        'icon' => 'quarterly',
                        'uri' => '/reports/quarterly-lvr',
                    ],
                    [
                        'label' => 'Position Limits',
                        'route' => 'reports.position-limit',
                        'icon' => 'limit',
                        'uri' => '/reports/position-limit',
                    ],
                    [
                        'label' => 'Report History',
                        'route' => 'reports.history',
                        'icon' => 'history',
                        'uri' => '/reports/history',
                    ],
                ],
            ],

            // ============================================================
            // SYSTEM - Administrative tasks
            // ============================================================
            'system' => [
                'label' => 'System',
                'items' => [
                    [
                        'label' => 'Tasks',
                        'route' => 'tasks.index',
                        'icon' => 'task',
                        'uri' => '/tasks',
                    ],
                    [
                        'label' => 'Transaction Imports',
                        'route' => 'transactions.batch-upload',
                        'icon' => 'arrow-up-tray',
                        'uri' => '/transactions/batch-upload',
                    ],
                    [
                        'label' => 'Audit Log',
                        'route' => 'audit.index',
                        'icon' => 'audit',
                        'uri' => '/audit',
                    ],
                    [
                        'label' => 'Test Results',
                        'route' => 'test-results.index',
                        'icon' => 'test',
                        'uri' => '/test-results',
                    ],
                    [
                        'label' => 'Users',
                        'route' => 'users.index',
                        'icon' => 'user',
                        'uri' => '/users',
                    ],
                    [
                        'label' => 'Data Breach Alerts',
                        'route' => 'data-breach-alerts.index',
                        'icon' => 'shield-exclamation',
                        'uri' => '/data-breach-alerts',
                    ],
                ],
            ],
        ];
    }

    /**
     * Get navigation items with role-based filtering
     */
    public static function getForRole(?UserRole $role): array
    {
        $navigation = self::get();

        if (! $role) {
            // Return only main for unauthenticated
            return [
                'main' => $navigation['main'],
            ];
        }

        // Role-based filtering can be added here if needed
        // For now, all items are shown but may be filtered by route middleware
        return $navigation;
    }

    /**
     * Get top-level groups only (for compact navigation)
     */
    public static function getTopLevel(): array
    {
        $navigation = self::get();
        $topLevel = [];

        foreach ($navigation as $key => $group) {
            $topLevelItems = array_filter($group['items'], fn ($item) => ! isset($item['parent']));
            $topLevel[$key] = [
                'label' => $group['label'],
                'items' => array_values($topLevelItems),
            ];
        }

        return $topLevel;
    }
}
