import { test, expect, Page } from '@playwright/test';

const BASE_URL = 'http://local.host';
const PASSWORD = 'Password123!';

const USERS: Record<string, string> = {
  admin: 'admin',
  teller: 'teller1',
  manager: 'manager1',
  compliance: 'compliance1',
  accountant: 'accountant1',
};

// [label, path] for every entry in $navItems in
// resources/views/components/navigation.blade.php — keep in sync with it.
const NAV: [string, string][] = [
  ['Dashboard', '/dashboard'],
  ['Transactions', '/transactions'],
  ['Customers', '/customers'],
  ['Rates', '/rates'],
  ['Counters', '/counters'],
  ['Stock Cash', '/stock-cash'],
  ['My Allocations', '/my-allocations'],
  ['Allocations', '/allocations'],
  ['Branch Pools', '/branch-pools'],
  ['Stock Transfers', '/stock-transfers'],
  ['Compliance', '/compliance'],
  ['End of Day', '/eod'],
  ['Branch Closing', '/closing'],
  ['Accounting', '/accounting'],
  ['Reports', '/reports'],
  ['Users', '/users'],
  ['Role Permissions', '/admin/role-permissions'],
  ['Thresholds', '/admin/thresholds'],
  ['Branches', '/branches'],
  ['Currencies', '/system/currencies'],
];

// Expected sidebar per role — each nav item's target route carries role:
// middleware aliases; a role sees the item iff UserRole::matchesRoleAlias()
// accepts any alias through the effective permission matrix. Derived from
// route middleware + role_permissions (verified via artisan):
//   teller:      create_transactions, operate_counters, request_stock
//   manager:     manage_transactions, manage_customers, access_rates,
//                operate_counters, manage_counters, manage_stock,
//                manage_allocations, manage_stock_transfers, manage_eod,
//                manage_branch_closing, access_accounting, view_reports,
//                manage_users, access_branches
//   compliance:  approve_transactions, manage_customers, access_compliance,
//                view_reports
//   accountant:  access_accounting, view_reports
//   admin:       matrix-exempt — every alias matches
const EXPECTED: Record<string, string[]> = {
  admin: NAV.map(([label]) => label),
  teller: ['Dashboard', 'Transactions', 'Customers', 'Counters', 'My Allocations'],
  manager: [
    'Dashboard', 'Transactions', 'Customers', 'Rates', 'Counters', 'Stock Cash',
    'Allocations', 'Branch Pools', 'Stock Transfers', 'End of Day',
    'Branch Closing', 'Accounting', 'Reports', 'Users', 'Branches',
  ],
  compliance: ['Dashboard', 'Transactions', 'Customers', 'Compliance', 'Reports'],
  accountant: ['Dashboard', 'Accounting', 'Reports'],
};

async function login(page: Page, username: string) {
  await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded' });
  await page.fill('input#username', username);
  await page.fill('input#password', PASSWORD);
  await page.click('button[type="submit"]');
  await page.waitForLoadState('domcontentloaded');
  if (page.url().includes('/login')) {
    throw new Error(`Login failed for ${username}`);
  }
}

for (const [role, username] of Object.entries(USERS)) {
  test(`sidebar matches RBAC grants — ${role} (${username})`, async ({ page }) => {
    await login(page, username);
    await page.goto(`${BASE_URL}/dashboard`, { waitUntil: 'domcontentloaded' });

    // Rendered sidebar: label + path per nav link.
    const rendered = await page.$$eval('aside nav a', (els) =>
      els.map((a) => ({
        label: (a.textContent ?? '').trim(),
        path: new URL((a as HTMLAnchorElement).href).pathname,
      }))
    );
    const renderedLabels = rendered.map((r) => r.label);

    console.log(`\n=== ${role} sidebar ===`);
    console.log(renderedLabels.join(', '));

    // 1. Rendered sidebar must equal the role's permission-derived set —
    //    no missing entries, no extra entries, in nav order.
    expect(renderedLabels, `${role}: sidebar labels`).toEqual(EXPECTED[role]);

    // 2. Every rendered link must be one of the known nav routes.
    const unknown = rendered.filter(
      (r) => !NAV.some(([, path]) => path === r.path)
    );
    expect(unknown, `${role}: links outside nav universe`).toEqual([]);

    // 3. Sidebar must mirror real enforcement: probe each nav route as this
    //    user (following redirects — /closing 302s to the branch workflow)
    //    and require rendered === reachable.
    const reachable = new Set<string>();
    for (const [label, path] of NAV) {
      const res = await page.context().request.get(`${BASE_URL}${path}`);
      if (res.status() === 200) {
        reachable.add(path);
      } else {
        console.log(`${role} ${path} -> ${res.status()}`);
      }
    }
    const reachableButHidden = NAV.filter(
      ([, path]) => reachable.has(path) && !rendered.some((r) => r.path === path)
    ).map(([label]) => label);
    const shownButDenied = rendered
      .filter((r) => !reachable.has(r.path))
      .map((r) => r.label);

    expect(reachableButHidden, `${role}: allowed pages missing from sidebar`).toEqual([]);
    expect(shownButDenied, `${role}: denied pages shown in sidebar`).toEqual([]);
  });
}
