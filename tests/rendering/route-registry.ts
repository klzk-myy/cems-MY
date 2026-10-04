// Route registry for the rendering crawl audit.
//
// Every GET web route (public + auth-gated) is listed here. The crawl spec
// (`crawl-audit.spec.ts`) walks this list, resolves any {id} placeholders at
// runtime, and reports rendering health per page.
//
// Dynamic routes (`dynamic: true`) are resolved by visiting `indexPath` and
// scraping the first N IDs from links that match the pattern. If the index is
// empty, the dynamic route is skipped (reported as "no data").

export interface RouteEntry {
  /** URL path, e.g. "/customers/{id}" */
  path: string;
  /** Route name from `routes/web.php`, e.g. "customers.show" */
  name: string;
  /** Whether the path contains an {id} placeholder to resolve at runtime */
  dynamic: boolean;
  /** For dynamic routes: the index page to scrape IDs from */
  indexPath?: string;
  /** For dynamic routes: regex to match an ID in an href */
  idMatch?: RegExp;
  /** Grouping label for the report */
  category: string;
  /** Route returns a non-HTML response (download) — skip render check */
  nonHtml?: boolean;
  /** Route is expected to error in the current app state (e.g. gated by middleware) */
  expectError?: boolean;
}

export const routes: RouteEntry[] = [
  // ────────────────────────────────────────────────────────────
  // Public (no auth)
  // ────────────────────────────────────────────────────────────
  { path: '/', name: 'home', dynamic: false, category: 'Public' },
  { path: '/up', name: 'up', dynamic: false, category: 'Public' },
  { path: '/login', name: 'login', dynamic: false, category: 'Public' },
  { path: '/forgot-password', name: 'password.request', dynamic: false, category: 'Public' },
  { path: '/verify/transaction/PROBE001', name: 'verification.transaction', dynamic: false, category: 'Public' },

  // ────────────────────────────────────────────────────────────
  // Core / Profile / Dashboard (auth-gated)
  // ────────────────────────────────────────────────────────────
  { path: '/dashboard', name: 'dashboard', dynamic: false, category: 'Core' },
  { path: '/password/change', name: 'password.change', dynamic: false, category: 'Core' },
  { path: '/confirm-password', name: 'password.confirm', dynamic: false, category: 'Core' },
  { path: '/performance', name: 'performance', dynamic: false, category: 'Core' },

  // ────────────────────────────────────────────────────────────
  // MFA
  // ────────────────────────────────────────────────────────────
  { path: '/mfa/setup', name: 'mfa.setup', dynamic: false, category: 'MFA' },
  { path: '/mfa/verify', name: 'mfa.verify', dynamic: false, category: 'MFA' },
  { path: '/mfa/recovery', name: 'mfa.recovery', dynamic: false, category: 'MFA' },
  { path: '/mfa/recovery-codes', name: 'mfa.recovery-codes', dynamic: false, category: 'MFA' },
  { path: '/mfa/trusted-devices', name: 'mfa.trusted-devices', dynamic: false, category: 'MFA' },

  // ────────────────────────────────────────────────────────────
  // Notifications
  // ────────────────────────────────────────────────────────────
  { path: '/notifications/preferences', name: 'notifications.preferences', dynamic: false, category: 'Notifications' },

  // ────────────────────────────────────────────────────────────
  // Rates
  // ────────────────────────────────────────────────────────────
  { path: '/rates', name: 'rates.index', dynamic: false, category: 'Rates' },
  { path: '/rates/units', name: 'rates.units', dynamic: false, category: 'Rates' },

  // ────────────────────────────────────────────────────────────
  // Customers
  // ────────────────────────────────────────────────────────────
  { path: '/customers', name: 'customers.index', dynamic: false, category: 'Customers' },
  { path: '/customers/create', name: 'customers.create', dynamic: false, category: 'Customers' },
  { path: '/customers/search', name: 'customers.search', dynamic: false, category: 'Customers' },
  { path: '/customers/{id}', name: 'customers.show', dynamic: true, indexPath: '/customers', idMatch: /\/customers\/(\d+)$/, category: 'Customers' },
  { path: '/customers/{id}/edit', name: 'customers.edit', dynamic: true, indexPath: '/customers', idMatch: /\/customers\/(\d+)\/edit$/, category: 'Customers' },

  // ────────────────────────────────────────────────────────────
  // Transactions
  // ────────────────────────────────────────────────────────────
  { path: '/transactions', name: 'transactions.index', dynamic: false, category: 'Transactions' },
  { path: '/transactions/wizard', name: 'transactions.wizard', dynamic: false, category: 'Transactions' },
  { path: '/transactions/create', name: 'transactions.create', dynamic: false, category: 'Transactions' },
  { path: '/transactions/batch-upload', name: 'transactions.batch-upload', dynamic: false, category: 'Transactions' },
  { path: '/transactions/template', name: 'transactions.batch-upload.template', dynamic: false, category: 'Transactions', nonHtml: true },
  { path: '/transactions/export', name: 'transactions.export.form', dynamic: false, category: 'Transactions' },
  { path: '/transactions/dlq', name: 'transactions.dlq', dynamic: false, category: 'Transactions' },
  { path: '/transactions/{id}', name: 'transactions.show', dynamic: true, indexPath: '/transactions', idMatch: /\/transactions\/([A-Z0-9-]+)$/, category: 'Transactions' },
  { path: '/transactions/{id}/receipt', name: 'transactions.receipt', dynamic: true, indexPath: '/transactions', idMatch: /\/transactions\/([A-Z0-9-]+)\/receipt$/, category: 'Transactions' },
  { path: '/transactions/{id}/print', name: 'transactions.print', dynamic: true, indexPath: '/transactions', idMatch: /\/transactions\/([A-Z0-9-]+)\/print$/, category: 'Transactions' },
  { path: '/transactions/{id}/cancel', name: 'transactions.cancel', dynamic: true, indexPath: '/transactions', idMatch: /\/transactions\/([A-Z0-9-]+)\/cancel$/, category: 'Transactions' },
  { path: '/transactions/{id}/confirm', name: 'transactions.confirm.show', dynamic: true, indexPath: '/transactions', idMatch: /\/transactions\/([A-Z0-9-]+)\/confirm$/, category: 'Transactions' },
  { path: '/transactions/{id}/approve-cancellation', name: 'transactions.approve-cancellation', dynamic: true, indexPath: '/transactions', idMatch: /\/transactions\/([A-Z0-9-]+)\/approve-cancellation$/, category: 'Transactions' },
  { path: '/transactions/{id}/reject-cancellation', name: 'transactions.reject-cancellation', dynamic: true, indexPath: '/transactions', idMatch: /\/transactions\/([A-Z0-9-]+)\/reject-cancellation$/, category: 'Transactions' },
  { path: '/transactions/{id}/reverse', name: 'transactions.reverse', dynamic: true, indexPath: '/transactions', idMatch: /\/transactions\/([A-Z0-9-]+)\/reverse$/, category: 'Transactions' },

  // ────────────────────────────────────────────────────────────
  // Stock & Cash
  // ────────────────────────────────────────────────────────────
  { path: '/stock-cash', name: 'stock-cash.index', dynamic: false, category: 'Stock & Cash' },
  { path: '/stock-cash/till-report', name: 'stock-cash.till-report', dynamic: false, category: 'Stock & Cash' },
  { path: '/stock-cash/reconciliation', name: 'stock-cash.reconciliation', dynamic: false, category: 'Stock & Cash' },

  // ────────────────────────────────────────────────────────────
  // Teller Self-Service
  // ────────────────────────────────────────────────────────────
  { path: '/my-stock', name: 'my-stock.index', dynamic: false, category: 'Teller' },
  { path: '/my-allocations', name: 'my-allocations.index', dynamic: false, category: 'Teller' },
  { path: '/my-allocations/request', name: 'my-allocations.request', dynamic: false, category: 'Teller' },

  // ────────────────────────────────────────────────────────────
  // Allocations
  // ────────────────────────────────────────────────────────────
  { path: '/allocations', name: 'allocations.index', dynamic: false, category: 'Allocations' },
  { path: '/allocations/create', name: 'allocations.create', dynamic: false, category: 'Allocations' },
  { path: '/allocations/{id}', name: 'allocations.show', dynamic: true, indexPath: '/allocations', idMatch: /\/allocations\/(\d+)$/, category: 'Allocations' },

  // ────────────────────────────────────────────────────────────
  // Branch Pools
  // ────────────────────────────────────────────────────────────
  { path: '/branch-pools', name: 'branch-pools.index', dynamic: false, category: 'Branch Pools' },
  { path: '/branch-pools/{id}', name: 'branch-pools.show', dynamic: true, indexPath: '/branch-pools', idMatch: /\/branch-pools\/(\d+)$/, category: 'Branch Pools' },

  // ────────────────────────────────────────────────────────────
  // EOD
  // ────────────────────────────────────────────────────────────
  { path: '/eod', name: 'eod.dashboard', dynamic: false, category: 'EOD' },

  // ────────────────────────────────────────────────────────────
  // Stock Transfers
  // ────────────────────────────────────────────────────────────
  { path: '/stock-transfers', name: 'stock-transfers.index', dynamic: false, category: 'Stock Transfers' },
  { path: '/stock-transfers/create', name: 'stock-transfers.create', dynamic: false, category: 'Stock Transfers' },
  { path: '/stock-transfers/{id}', name: 'stock-transfers.show', dynamic: true, indexPath: '/stock-transfers', idMatch: /\/stock-transfers\/(\d+)$/, category: 'Stock Transfers' },

  // ────────────────────────────────────────────────────────────
  // Compliance
  // ────────────────────────────────────────────────────────────
  { path: '/compliance', name: 'compliance', dynamic: false, category: 'Compliance' },
  { path: '/compliance/flagged', name: 'compliance.flagged', dynamic: false, category: 'Compliance' },
  { path: '/compliance/unified', name: 'compliance.unified.index', dynamic: false, category: 'Compliance' },
  { path: '/compliance/alerts', name: 'compliance.alerts.index', dynamic: false, category: 'Compliance' },
  { path: '/compliance/alerts/{id}', name: 'compliance.alerts.show', dynamic: true, indexPath: '/compliance/alerts', idMatch: /\/compliance\/alerts\/(\d+)$/, category: 'Compliance' },
  { path: '/compliance/cases', name: 'compliance.cases.index', dynamic: false, category: 'Compliance' },
  { path: '/compliance/cases/{id}', name: 'compliance.cases.show', dynamic: true, indexPath: '/compliance/cases', idMatch: /\/compliance\/cases\/(\d+)$/, category: 'Compliance' },
  { path: '/compliance/sanctions', name: 'compliance.sanctions.index', dynamic: false, category: 'Compliance' },
  { path: '/compliance/sanctions/entries', name: 'compliance.sanctions.entries.index', dynamic: false, category: 'Compliance' },
  { path: '/compliance/sanctions/entries/create', name: 'compliance.sanctions.entries.create', dynamic: false, category: 'Compliance' },
  { path: '/compliance/sanctions/entries/{id}', name: 'compliance.sanctions.entries.show', dynamic: true, indexPath: '/compliance/sanctions/entries', idMatch: /\/compliance\/sanctions\/entries\/(\d+)$/, category: 'Compliance' },
  { path: '/compliance/sanctions/entries/{id}/edit', name: 'compliance.sanctions.entries.edit', dynamic: true, indexPath: '/compliance/sanctions/entries', idMatch: /\/compliance\/sanctions\/entries\/(\d+)\/edit$/, category: 'Compliance' },
  { path: '/compliance/sanctions/import-logs', name: 'compliance.sanctions.import-logs', dynamic: false, category: 'Compliance' },
  { path: '/compliance/screening/{customerId}', name: 'compliance.screening.show', dynamic: true, indexPath: '/customers', idMatch: /\/customers\/(\d+)$/, category: 'Compliance' },
  { path: '/compliance/screening/{customerId}/history', name: 'compliance.screening.history', dynamic: true, indexPath: '/customers', idMatch: /\/customers\/(\d+)$/, category: 'Compliance' },
  { path: '/compliance/screening-matches', name: 'compliance.screening.matches.index', dynamic: false, category: 'Compliance' },
  { path: '/compliance/screening-matches/{id}', name: 'compliance.screening.matches.show', dynamic: true, indexPath: '/compliance/screening-matches', idMatch: /\/compliance\/screening-matches\/(\d+)$/, category: 'Compliance' },
  { path: '/compliance/findings', name: 'compliance.findings.index', dynamic: false, category: 'Compliance' },
  { path: '/compliance/findings/{id}', name: 'compliance.findings.show', dynamic: true, indexPath: '/compliance/findings', idMatch: /\/compliance\/findings\/(\d+)$/, category: 'Compliance' },
  { path: '/str', name: 'compliance.str.index', dynamic: false, category: 'Compliance' },
  { path: '/str/{id}', name: 'compliance.str.show', dynamic: true, indexPath: '/str', idMatch: /\/str\/(\d+)$/, category: 'Compliance' },
  { path: '/str/export', name: 'compliance.str.export', dynamic: false, category: 'Compliance' },
  { path: '/compliance/edd-review', name: 'compliance.edd-reviews.index', dynamic: false, category: 'Compliance' },
  { path: '/compliance/pep-approvals', name: 'compliance.pep-approvals.index', dynamic: false, category: 'Compliance' },
  { path: '/compliance/risk-dashboard', name: 'compliance.risk-dashboard.index', dynamic: false, category: 'Compliance' },
  { path: '/compliance/risk-dashboard/trends', name: 'compliance.risk-dashboard.trends', dynamic: false, category: 'Compliance' },

  // ────────────────────────────────────────────────────────────
  // Accounting
  // ────────────────────────────────────────────────────────────
  { path: '/accounting', name: 'accounting.index', dynamic: false, category: 'Accounting' },
  { path: '/accounting/journal', name: 'accounting.journal', dynamic: false, category: 'Accounting' },
  { path: '/accounting/journal/create', name: 'accounting.journal.create', dynamic: false, category: 'Accounting' },
  { path: '/accounting/journal/{id}', name: 'accounting.journal.show', dynamic: true, indexPath: '/accounting/journal', idMatch: /\/accounting\/journal\/(\d+)$/, category: 'Accounting' },
  { path: '/accounting/expenses', name: 'accounting.expenses.index', dynamic: false, category: 'Accounting' },
  { path: '/accounting/expenses/create', name: 'accounting.expenses.create', dynamic: false, category: 'Accounting' },
  { path: '/accounting/ledger', name: 'accounting.ledger', dynamic: false, category: 'Accounting' },
  { path: '/accounting/ledger/{id}', name: 'accounting.ledger.account', dynamic: true, indexPath: '/accounting/ledger', idMatch: /\/accounting\/ledger\/(\w+)$/, category: 'Accounting' },
  { path: '/accounting/trial-balance', name: 'accounting.trial-balance', dynamic: false, category: 'Accounting' },
  { path: '/accounting/profit-loss', name: 'accounting.profit-loss', dynamic: false, category: 'Accounting' },
  { path: '/accounting/balance-sheet', name: 'accounting.balance-sheet', dynamic: false, category: 'Accounting' },
  { path: '/accounting/cash-flow', name: 'accounting.cash-flow', dynamic: false, category: 'Accounting' },
  { path: '/accounting/ratios', name: 'accounting.ratios', dynamic: false, category: 'Accounting' },
  { path: '/accounting/periods', name: 'accounting.periods', dynamic: false, category: 'Accounting' },
  { path: '/accounting/fiscal-years', name: 'accounting.fiscal-years', dynamic: false, category: 'Accounting' },
  { path: '/accounting/revaluation', name: 'accounting.revaluation', dynamic: false, category: 'Accounting' },
  { path: '/accounting/revaluation/history', name: 'accounting.revaluation.history', dynamic: false, category: 'Accounting' },
  { path: '/accounting/reconciliation', name: 'accounting.reconciliation', dynamic: false, category: 'Accounting' },
  { path: '/accounting/reconciliation/report', name: 'accounting.reconciliation.report', dynamic: false, category: 'Accounting' },
  { path: '/accounting/budget', name: 'accounting.budget', dynamic: false, category: 'Accounting' },
  { path: '/accounting/chart-of-accounts', name: 'accounting.chart-of-accounts.index', dynamic: false, category: 'Accounting' },

  // ────────────────────────────────────────────────────────────
  // Reports
  // ────────────────────────────────────────────────────────────
  { path: '/reports', name: 'reports.index', dynamic: false, category: 'Reports' },
  { path: '/reports/msb2', name: 'reports.msb2', dynamic: false, category: 'Reports' },
  { path: '/reports/lmca', name: 'reports.lmca', dynamic: false, category: 'Reports' },
  { path: '/reports/quarterly-lvr', name: 'reports.quarterly-lvr', dynamic: false, category: 'Reports' },
  { path: '/reports/position-limit', name: 'reports.position-limit', dynamic: false, category: 'Reports' },
  { path: '/reports/monthly-trends', name: 'reports.monthly-trends', dynamic: false, category: 'Reports' },
  { path: '/reports/profitability', name: 'reports.profitability', dynamic: false, category: 'Reports' },
  { path: '/reports/customer-analysis', name: 'reports.customer-analysis', dynamic: false, category: 'Reports' },
  { path: '/reports/compliance-summary', name: 'reports.compliance-summary', dynamic: false, category: 'Reports' },
  { path: '/reports/schedules', name: 'reports.schedules.index', dynamic: false, category: 'Reports' },
  { path: '/reports/schedules/create', name: 'reports.schedules.create', dynamic: false, category: 'Reports' },

  // ────────────────────────────────────────────────────────────
  // Users
  // ────────────────────────────────────────────────────────────
  { path: '/users', name: 'users.index', dynamic: false, category: 'Users' },
  { path: '/users/create', name: 'users.create', dynamic: false, category: 'Users' },
  { path: '/users/{id}', name: 'users.show', dynamic: true, indexPath: '/users', idMatch: /\/users\/(\d+)$/, category: 'Users' },
  { path: '/users/{id}/edit', name: 'users.edit', dynamic: true, indexPath: '/users', idMatch: /\/users\/(\d+)\/edit$/, category: 'Users' },

  // ────────────────────────────────────────────────────────────
  // Admin
  // ────────────────────────────────────────────────────────────
  { path: '/admin/role-permissions', name: 'admin.role-permissions.index', dynamic: false, category: 'Admin' },
  { path: '/admin/sanctions', name: 'admin.sanctions.index', dynamic: false, category: 'Admin' },
  { path: '/admin/branch-scope', name: 'admin.branch-scope.index', dynamic: false, category: 'Admin' },
  { path: '/admin/thresholds', name: 'admin.thresholds.index', dynamic: false, category: 'Admin' },
  { path: '/admin/audit-logs', name: 'admin.audit-logs.index', dynamic: false, category: 'Admin' },
  { path: '/admin/audit-logs/{id}', name: 'admin.audit-logs.show', dynamic: true, indexPath: '/admin/audit-logs', idMatch: /\/admin\/audit-logs\/(\d+)$/, category: 'Admin' },
  { path: '/health', name: 'health', dynamic: false, category: 'Admin' },

  // ────────────────────────────────────────────────────────────
  // Branches
  // ────────────────────────────────────────────────────────────
  { path: '/branches', name: 'branches.index', dynamic: false, category: 'Branches' },
  { path: '/branches/create', name: 'branches.create', dynamic: false, category: 'Branches' },
  { path: '/branches/{id}/edit', name: 'branches.edit', dynamic: true, indexPath: '/branches', idMatch: /\/branches\/(\d+)\/edit$/, category: 'Branches' },

  // ────────────────────────────────────────────────────────────
  // Closing
  // ────────────────────────────────────────────────────────────
  { path: '/closing', name: 'closing.show', dynamic: false, category: 'Closing' },

  // ────────────────────────────────────────────────────────────
  // System
  // ────────────────────────────────────────────────────────────
  { path: '/system/currencies', name: 'system.currencies.index', dynamic: false, category: 'System' },
  { path: '/system/currencies/create', name: 'system.currencies.create', dynamic: false, category: 'System' },
  { path: '/system/alerts', name: 'system.alerts.index', dynamic: false, category: 'System' },

  // ────────────────────────────────────────────────────────────
  // Test Results
  // ────────────────────────────────────────────────────────────
  { path: '/test-results', name: 'test-results.index', dynamic: false, category: 'Test Results' },
  { path: '/test-results/statistics', name: 'test-results.statistics', dynamic: false, category: 'Test Results' },
  { path: '/test-results/status', name: 'test-results.status', dynamic: false, category: 'Test Results' },

  // ────────────────────────────────────────────────────────────
  // Setup (redirects if already set up — handled gracefully)
  // ────────────────────────────────────────────────────────────
  { path: '/setup', name: 'setup.index', dynamic: false, category: 'Setup', expectError: true },
];
