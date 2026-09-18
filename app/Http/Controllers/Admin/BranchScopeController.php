<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * BranchScopeController
 *
 * Read-only documentation of the branch/HQ operating boundary: which
 * domains a branch user operates standalone, what the HQ administrative
 * office owns, and where manage_all_branches (accountant) cross-branch
 * access applies. Mirrors the role-permission matrix layout.
 */
class BranchScopeController extends Controller
{
    /**
     * Display the branch/HQ scope matrix.
     */
    public function index(): View
    {
        $this->requirePermission(Permission::ManageRolePermissions);

        $columns = [
            'branch' => 'Branch user',
            'hq' => 'HQ admin office',
            'cross' => 'manage_all_branches',
        ];

        $rows = [
            [
                'domain' => 'Counters, tills & allocations',
                'description' => 'Open/close counter sessions, till counts, handovers, teller custody.',
                'branch' => 'Operate',
                'hq' => '—',
                'cross' => 'Operate',
            ],
            [
                'domain' => 'Branch pool & stock',
                'description' => 'Branch pools, currency positions, funding and debiting the branch pool.',
                'branch' => 'Operate',
                'hq' => '—',
                'cross' => 'Operate',
            ],
            [
                'domain' => 'Transactions',
                'description' => 'Buy/sell bookings on the branch\'s counters under its rate card.',
                'branch' => 'Book own branch',
                'hq' => '—',
                'cross' => 'View',
            ],
            [
                'domain' => 'Exchange rates',
                'description' => 'Branch rate-card overrides; the company card is a system market feed.',
                'branch' => 'Operate (own card)',
                'hq' => '—',
                'cross' => 'View',
            ],
            [
                'domain' => 'Expenses',
                'description' => 'Branch-local expenses vs central HQ operating expenses.',
                'branch' => 'Operate (own branch)',
                'hq' => 'Central expenses',
                'cross' => 'View',
            ],
            [
                'domain' => 'Manual journals',
                'description' => 'Ad-hoc postings; the null-branch company book is HQ-only.',
                'branch' => 'Own branch',
                'hq' => 'Any + company book',
                'cross' => 'Operate',
            ],
            [
                'domain' => 'Reports (P&L, TB, BS, ledger)',
                'description' => 'Branches see their own numbers; HQ/accountant see per-branch + consolidated.',
                'branch' => 'Own branch',
                'hq' => 'All + consolidated',
                'cross' => 'All + consolidated',
            ],
            [
                'domain' => 'Daily close',
                'description' => 'Merged reconciliation + freeze of the branch\'s business date.',
                'branch' => 'Operate + freeze own date',
                'hq' => 'View + reopen',
                'cross' => 'View + reopen',
            ],
            [
                'domain' => 'Cash remittance',
                'description' => 'Branch ↔ HQ only, two-step acknowledge; 2300 clears on receipt.',
                'branch' => 'Initiate + acknowledge',
                'hq' => 'Initiate + acknowledge',
                'cross' => 'Initiate + acknowledge',
            ],
            [
                'domain' => 'Stock transfers',
                'description' => 'Direct branch ↔ branch movement via the stock-transfer workflow.',
                'branch' => 'Operate',
                'hq' => 'Excluded — non-trading',
                'cross' => 'Same workflow',
            ],
            [
                'domain' => 'Fiscal / month close',
                'description' => 'Company-book period close; branches close daily only.',
                'branch' => '—',
                'hq' => 'Operate',
                'cross' => 'View',
            ],
            [
                'domain' => 'Customers & compliance',
                'description' => 'Shared global customer book; compliance oversight is central.',
                'branch' => 'Shared book',
                'hq' => 'Oversight',
                'cross' => 'Oversight',
            ],
            [
                'domain' => 'CoA, users, roles, branches',
                'description' => 'Global chart of accounts, staff, roles, and branch records.',
                'branch' => '—',
                'hq' => 'Operate',
                'cross' => 'Operate',
            ],
        ];

        return view('admin.branch-scope.index', compact('columns', 'rows'));
    }
}
