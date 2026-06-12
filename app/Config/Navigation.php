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
                        'icon' => 'home',
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
                        'icon' => 'arrows-right-left',
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
                        'icon' => 'ticket',
                        'uri' => '/counters',
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
                        'icon' => 'banknotes',
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
                        'icon' => 'shield-check',
                        'uri' => '/compliance',
                    ],
                    [
                        'label' => 'Compliance Workspace',
                        'route' => 'compliance.workspace',
                        'icon' => 'clipboard-document-list',
                        'uri' => '/compliance/workspace',
                    ],
                    [
                        'label' => 'Alert Triage',
                        'route' => 'compliance.alerts.index',
                        'icon' => 'exclamation-triangle',
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
                        'route' => 'compliance.findings.index',
                        'icon' => 'document-text',
                        'uri' => '/compliance/findings',
                    ],
                    [
                        'label' => 'Risk Dashboard',
                        'route' => 'compliance.risk-dashboard.index',
                        'icon' => 'chart-bar',
                        'uri' => '/compliance/risk-dashboard',
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
                        'icon' => 'book-open',
                        'uri' => '/accounting/journal',
                    ],
                    [
                        'label' => 'Ledger',
                        'route' => 'accounting.ledger',
                        'icon' => 'bookmark-square',
                        'uri' => '/accounting/ledger',
                    ],
                    [
                        'label' => 'Trial Balance',
                        'route' => 'accounting.trial-balance',
                        'icon' => 'scale',
                        'uri' => '/accounting/trial-balance',
                    ],
                    [
                        'label' => 'Profit & Loss',
                        'route' => 'accounting.profit-loss',
                        'icon' => 'chart-pie',
                        'uri' => '/accounting/profit-loss',
                    ],
                    [
                        'label' => 'Balance Sheet',
                        'route' => 'accounting.balance-sheet',
                        'icon' => 'document-text',
                        'uri' => '/accounting/balance-sheet',
                    ],
                    [
                        'label' => 'Cash Flow',
                        'route' => 'accounting.cash-flow',
                        'icon' => 'arrow-trending-up',
                        'uri' => '/accounting/cash-flow',
                    ],
                    [
                        'label' => 'Financial Ratios',
                        'route' => 'accounting.ratios',
                        'icon' => 'variable',
                        'uri' => '/accounting/ratios',
                    ],
                    [
                        'label' => 'Revaluation',
                        'route' => 'accounting.revaluation',
                        'icon' => 'currency-dollar',
                        'uri' => '/accounting/revaluation',
                    ],
                    [
                        'label' => 'Reconciliation',
                        'route' => 'accounting.reconciliation',
                        'icon' => 'building-office',
                        'uri' => '/accounting/reconciliation',
                    ],
                    [
                        'label' => 'Budget',
                        'route' => 'accounting.budget',
                        'icon' => 'currency-dollar',
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
                        'icon' => 'calendar-days',
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
                        'icon' => 'table-cells',
                        'uri' => '/reports',
                    ],
                    [
                        'label' => 'MSB2 Report',
                        'route' => 'reports.msb2',
                        'icon' => 'calendar',
                        'uri' => '/reports/msb2',
                    ],
                    [
                        'label' => 'LMCA',
                        'route' => 'reports.lmca',
                        'icon' => 'calendar',
                        'uri' => '/reports/lmca',
                    ],
                    [
                        'label' => 'Quarterly LVR',
                        'route' => 'reports.quarterly-lvr',
                        'icon' => 'calendar',
                        'uri' => '/reports/quarterly-lvr',
                    ],
                    [
                        'label' => 'Position Limits',
                        'route' => 'reports.position-limit',
                        'icon' => 'no-symbol',
                    ],
                    // [
                    //     'label' => 'Report History',
                    //     'route' => 'reports.history',
                    //     'icon' => 'history',
                    //     'uri' => '/reports/history',
                    // ],
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
                        'route' => 'dashboard',
                        'icon' => 'list-bullet',
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
                        'route' => 'test-results.index',
                        'icon' => 'magnifying-glass',
                        'uri' => '/test-results',
                    ],
                    [
                        'label' => 'Test Results',
                        'route' => 'test-results.index',
                        'icon' => 'beaker',
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
                        'route' => 'compliance.alerts.index',
                        'icon' => 'exclamation-circle',
                        'uri' => '/compliance/alerts',
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

        // Filter sections based on role permissions
        $filtered = [];

        foreach ($navigation as $section => $config) {
            if (self::sectionAllowed($section, $role)) {
                $filtered[$section] = $config;
            }
        }

        return $filtered;
    }

    /**
     * Check if navigation section is allowed for role
     */
    private static function sectionAllowed(string $section, UserRole $role): bool
    {
        return match ($section) {
            'main' => true,
            'operations', 'counter_management', 'stock_management' => $role->isManager(),
            'compliance' => $role->isComplianceOfficer(),
            'accounting', 'reports' => $role->isManager(),
            'system' => $role->isAdmin(),
            default => true,
        };
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
