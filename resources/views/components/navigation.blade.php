<nav class="bg-gray-900 text-white w-64 min-h-screen flex flex-col">
    <div class="p-4 border-b border-gray-700">
        <h1 class="text-xl font-bold">{{ config('app.name') }}</h1>
    </div>

    <ul class="flex-1 overflow-y-auto py-4">
        <li>
            <a href="{{ route('dashboard') }}" class="flex items-center px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('dashboard') ? 'bg-gray-800' : '' }}">
                Dashboard
            </a>
        </li>

        @can('role:manager')
        <li>
            <a href="{{ route('performance') }}" class="flex items-center px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('performance') ? 'bg-gray-800' : '' }}">
                Performance
            </a>
        </li>
        @endcan

        <li class="px-4 py-2 text-xs text-gray-400 uppercase tracking-wider mt-4">Operations</li>

        <li class="px-4 py-2 text-xs text-gray-400 uppercase tracking-wider mt-2">Transactions</li>

        <li>
            <a href="{{ route('transactions.index') }}" class="flex items-center pl-6 py-2 text-sm hover:bg-gray-800 {{ request()->routeIs('transactions.index') ? 'bg-gray-800' : '' }}">
                Transaction List
            </a>
        </li>

        <li>
            <a href="{{ route('transactions.create') }}" class="flex items-center pl-6 py-2 text-sm hover:bg-gray-800 {{ request()->routeIs('transactions.create') ? 'bg-gray-800' : '' }}">
                New Transaction
            </a>
        </li>

        <li>
            <a href="{{ route('transactions.batch-upload') }}" class="flex items-center pl-6 py-2 text-sm hover:bg-gray-800 {{ request()->routeIs('transactions.batch-upload') ? 'bg-gray-800' : '' }}">
                Batch Upload
            </a>
        </li>

        <li class="px-4 py-2 text-xs text-gray-400 uppercase tracking-wider mt-2">Customers</li>

        <li>
            <a href="{{ route('customers.index') }}" class="flex items-center pl-6 py-2 text-sm hover:bg-gray-800 {{ request()->routeIs('customers.index') ? 'bg-gray-800' : '' }}">
                Customer List
            </a>
        </li>

        <li>
            <a href="{{ route('customers.create') }}" class="flex items-center pl-6 py-2 text-sm hover:bg-gray-800 {{ request()->routeIs('customers.create') ? 'bg-gray-800' : '' }}">
                Create Customer
            </a>
        </li>

        <li class="px-4 py-2 text-xs text-gray-400 uppercase tracking-wider mt-2">Counters</li>

        <li>
            <a href="{{ route('counters.index') }}" class="flex items-center pl-6 py-2 text-sm hover:bg-gray-800 {{ request()->routeIs('counters.*') ? 'bg-gray-800' : '' }}">
                Counter List
            </a>
        </li>

        <li class="px-4 py-2 text-xs text-gray-400 uppercase tracking-wider mt-2">Stock & Cash</li>

        <li>
            <a href="{{ route('stock-cash.index') }}" class="flex items-center pl-6 py-2 text-sm hover:bg-gray-800 {{ request()->routeIs('stock-cash.index') ? 'bg-gray-800' : '' }}">
                Overview
            </a>
        </li>

        <li>
            <a href="{{ route('stock-cash.index') }}" class="flex items-center pl-6 py-2 text-sm hover:bg-gray-800 {{ request()->routeIs('stock-cash.position') ? 'bg-gray-800' : '' }}">
                Position
            </a>
        </li>

        <li>
            <a href="{{ route('stock-cash.reconciliation') }}" class="flex items-center pl-6 py-2 text-sm hover:bg-gray-800 {{ request()->routeIs('stock-cash.reconciliation') ? 'bg-gray-800' : '' }}">
                Reconciliation
            </a>
        </li>

        <li>
            <a href="{{ route('stock-cash.till-report') }}" class="flex items-center pl-6 py-2 text-sm hover:bg-gray-800 {{ request()->routeIs('stock-cash.till-report') ? 'bg-gray-800' : '' }}">
                Till Report
            </a>
        </li>

        <li class="px-4 py-2 text-xs text-gray-400 uppercase tracking-wider mt-2">Stock Transfers</li>

        <li>
            <a href="{{ route('stock-transfers.index') }}" class="flex items-center pl-6 py-2 text-sm hover:bg-gray-800 {{ request()->routeIs('stock-transfers.index') ? 'bg-gray-800' : '' }}">
                Overview
            </a>
        </li>

        <li>
            <a href="{{ route('stock-transfers.create') }}" class="flex items-center pl-6 py-2 text-sm hover:bg-gray-800 {{ request()->routeIs('stock-transfers.create') ? 'bg-gray-800' : '' }}">
                Create Transfer
            </a>
        </li>

        @if(auth()->user()->role->isAdmin() || auth()->user()->role->isManager())
        <li class="px-4 py-2 text-xs text-gray-400 uppercase tracking-wider mt-4">Finance</li>

        <li class="px-4 py-2 text-xs text-gray-400 uppercase tracking-wider mt-2">Accounting</li>

        <li>
            <a href="{{ route('accounting.index') }}" class="flex items-center pl-6 py-2 text-sm hover:bg-gray-800 {{ request()->routeIs('accounting.index') ? 'bg-gray-800' : '' }}">
                Overview
            </a>
        </li>

        <li>
            <a href="{{ route('accounting.journal') }}" class="flex items-center pl-6 py-2 text-sm hover:bg-gray-800 {{ request()->routeIs('accounting.journal') ? 'bg-gray-800' : '' }}">
                Journal
            </a>
        </li>

        <li>
            <a href="{{ route('accounting.journal.create') }}" class="flex items-center pl-6 py-2 text-sm hover:bg-gray-800 {{ request()->routeIs('accounting.journal.create') ? 'bg-gray-800' : '' }}">
                Create Journal
            </a>
        </li>

        <li>
            <a href="{{ route('accounting.trial-balance') }}" class="flex items-center pl-6 py-2 text-sm hover:bg-gray-800 {{ request()->routeIs('accounting.trial-balance') ? 'bg-gray-800' : '' }}">
                Trial Balance
            </a>
        </li>

        <li>
            <a href="{{ route('accounting.profit-loss') }}" class="flex items-center pl-6 py-2 text-sm hover:bg-gray-800 {{ request()->routeIs('accounting.profit-loss') ? 'bg-gray-800' : '' }}">
                P&L Statement
            </a>
        </li>

        <li>
            <a href="{{ route('accounting.balance-sheet') }}" class="flex items-center pl-6 py-2 text-sm hover:bg-gray-800 {{ request()->routeIs('accounting.balance-sheet') ? 'bg-gray-800' : '' }}">
                Balance Sheet
            </a>
        </li>

        <li>
            <a href="{{ route('accounting.cash-flow') }}" class="flex items-center pl-6 py-2 text-sm hover:bg-gray-800 {{ request()->routeIs('accounting.cash-flow') ? 'bg-gray-800' : '' }}">
                Cash Flow
            </a>
        </li>

        <li>
            <a href="{{ route('accounting.ratios') }}" class="flex items-center pl-6 py-2 text-sm hover:bg-gray-800 {{ request()->routeIs('accounting.ratios') ? 'bg-gray-800' : '' }}">
                Financial Ratios
            </a>
        </li>

        <li>
            <a href="{{ route('accounting.budget') }}" class="flex items-center pl-6 py-2 text-sm hover:bg-gray-800 {{ request()->routeIs('accounting.budget') ? 'bg-gray-800' : '' }}">
                Budget
            </a>
        </li>

        <li>
            <a href="{{ route('accounting.revaluation') }}" class="flex items-center pl-6 py-2 text-sm hover:bg-gray-800 {{ request()->routeIs('accounting.revaluation') ? 'bg-gray-800' : '' }}">
                Revaluation
            </a>
        </li>

        <li>
            <a href="{{ route('accounting.revaluation.history') }}" class="flex items-center pl-6 py-2 text-sm hover:bg-gray-800 {{ request()->routeIs('accounting.revaluation.history') ? 'bg-gray-800' : '' }}">
                Revaluation History
            </a>
        </li>

        <li>
            <a href="{{ route('accounting.reconciliation') }}" class="flex items-center pl-6 py-2 text-sm hover:bg-gray-800 {{ request()->routeIs('accounting.reconciliation') ? 'bg-gray-800' : '' }}">
                Reconciliation
            </a>
        </li>

        <li>
            <a href="{{ route('accounting.reconciliation.report') }}" class="flex items-center pl-6 py-2 text-sm hover:bg-gray-800 {{ request()->routeIs('accounting.reconciliation.report') ? 'bg-gray-800' : '' }}">
                Recon Report
            </a>
        </li>

        <li>
            <a href="{{ route('accounting.fiscal-years') }}" class="flex items-center pl-6 py-2 text-sm hover:bg-gray-800 {{ request()->routeIs('accounting.fiscal-years') ? 'bg-gray-800' : '' }}">
                Fiscal Years
            </a>
        </li>

        <li>
            <a href="{{ route('accounting.periods') }}" class="flex items-center pl-6 py-2 text-sm hover:bg-gray-800 {{ request()->routeIs('accounting.periods') ? 'bg-gray-800' : '' }}">
                Periods
            </a>
        </li>

        <li>
            <a href="{{ route('accounting.ledger') }}" class="flex items-center pl-6 py-2 text-sm hover:bg-gray-800 {{ request()->routeIs('accounting.ledger') ? 'bg-gray-800' : '' }}">
                Ledger
            </a>
        </li>

        <li>
            <a href="{{ route('rates.index') }}" class="flex items-center px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('rates.*') ? 'bg-gray-800' : '' }}">
                Rates
            </a>
        </li>
        @endif

        @can('role:compliance')
        <li class="px-4 py-2 text-xs text-gray-400 uppercase tracking-wider mt-4">Compliance</li>

        <li>
            <a href="{{ route('compliance') }}" class="flex items-center px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('compliance') && !request()->routeIs('compliance.*') ? 'bg-gray-800' : '' }}">
                Compliance Dashboard
            </a>
        </li>

        <li>
            <a href="{{ route('compliance.alerts.index') }}" class="flex items-center px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('compliance.alerts.*') ? 'bg-gray-800' : '' }}">
                Alerts
            </a>
        </li>

        <li>
            <a href="{{ route('compliance.cases.index') }}" class="flex items-center px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('compliance.cases.*') ? 'bg-gray-800' : '' }}">
                Cases
            </a>
        </li>

        <li>
            <a href="{{ route('compliance.findings.index') }}" class="flex items-center px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('compliance.findings.*') ? 'bg-gray-800' : '' }}">
                Findings
            </a>
        </li>

        <li>
            <a href="{{ route('compliance.flagged') }}" class="flex items-center px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('compliance.flagged') ? 'bg-gray-800' : '' }}">
                Flagged Transactions
            </a>
        </li>

        <li>
            <a href="{{ route('compliance.workspace') }}" class="flex items-center px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('compliance.workspace') ? 'bg-gray-800' : '' }}">
                Workspace
            </a>
        </li>

        <li>
            <a href="{{ route('compliance.unified.index') }}" class="flex items-center px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('compliance.unified.*') ? 'bg-gray-800' : '' }}">
                Unified View
            </a>
        </li>

        <li>
            <a href="{{ route('compliance.risk-dashboard.index') }}" class="flex items-center px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('compliance.risk-dashboard.*') ? 'bg-gray-800' : '' }}">
                Risk Dashboard
            </a>
        </li>

        <li>
            <a href="{{ route('compliance.sanctions.index') }}" class="flex items-center px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('compliance.sanctions.*') ? 'bg-gray-800' : '' }}">
                Sanctions
            </a>
        </li>

        <li>
            <a href="{{ route('compliance.sanctions.entries.index') }}" class="flex items-center px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('compliance.sanctions.entries.*') ? 'bg-gray-800' : '' }}">
                Sanctions Entries
            </a>
        </li>

        <li>
            <a href="{{ route('compliance.sanctions.entries.create') }}" class="flex items-center px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('compliance.sanctions.entries.create') ? 'bg-gray-800' : '' }}">
                Create Sanction
            </a>
        </li>

        <li>
            <a href="{{ route('compliance.sanctions.import-logs') }}" class="flex items-center px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('compliance.sanctions.import-logs') ? 'bg-gray-800' : '' }}">
                Import Logs
            </a>
        </li>

        <li>
            <a href="{{ route('compliance.risk-dashboard.trends') }}" class="flex items-center px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('compliance.risk-dashboard.trends') ? 'bg-gray-800' : '' }}">
                Risk Trends
            </a>
        </li>
        @endcan

        @if(auth()->user()->role->isAdmin() || auth()->user()->role->isManager())
        <li class="px-4 py-2 text-xs text-gray-400 uppercase tracking-wider mt-4">Reports</li>

        <li>
            <a href="{{ route('reports.index') }}" class="flex items-center pl-6 py-2 text-sm hover:bg-gray-800 {{ request()->routeIs('reports.index') ? 'bg-gray-800' : '' }}">
                Overview
            </a>
        </li>

        <li>
            <a href="{{ route('reports.lmca') }}" class="flex items-center pl-6 py-2 text-sm hover:bg-gray-800 {{ request()->routeIs('reports.lmca') ? 'bg-gray-800' : '' }}">
                LMCA
            </a>
        </li>

        <li>
            <a href="{{ route('reports.msb2') }}" class="flex items-center pl-6 py-2 text-sm hover:bg-gray-800 {{ request()->routeIs('reports.msb2') ? 'bg-gray-800' : '' }}">
                MSB2
            </a>
        </li>

        <li>
            <a href="{{ route('reports.position-limit') }}" class="flex items-center pl-6 py-2 text-sm hover:bg-gray-800 {{ request()->routeIs('reports.position-limit') ? 'bg-gray-800' : '' }}">
                Position Limit
            </a>
        </li>

        <li>
            <a href="{{ route('reports.profitability') }}" class="flex items-center pl-6 py-2 text-sm hover:bg-gray-800 {{ request()->routeIs('reports.profitability') ? 'bg-gray-800' : '' }}">
                Profitability
            </a>
        </li>

        <li>
            <a href="{{ route('reports.customer-analysis') }}" class="flex items-center pl-6 py-2 text-sm hover:bg-gray-800 {{ request()->routeIs('reports.customer-analysis') ? 'bg-gray-800' : '' }}">
                Customer Analysis
            </a>
        </li>

        <li>
            <a href="{{ route('reports.compliance-summary') }}" class="flex items-center pl-6 py-2 text-sm hover:bg-gray-800 {{ request()->routeIs('reports.compliance-summary') ? 'bg-gray-800' : '' }}">
                Compliance Summary
            </a>
        </li>

        <li>
            <a href="{{ route('reports.quarterly-lvr') }}" class="flex items-center pl-6 py-2 text-sm hover:bg-gray-800 {{ request()->routeIs('reports.quarterly-lvr') ? 'bg-gray-800' : '' }}">
                Quarterly LVR
            </a>
        </li>

        <li>
            <a href="{{ route('reports.monthly-trends') }}" class="flex items-center pl-6 py-2 text-sm hover:bg-gray-800 {{ request()->routeIs('reports.monthly-trends') ? 'bg-gray-800' : '' }}">
                Monthly Trends
            </a>
        </li>

        {{-- <li>
            <a href="{{ route('reports.compare') }}" class="flex items-center pl-6 py-2 text-sm hover:bg-gray-800 {{ request()->routeIs('reports.compare') ? 'bg-gray-800' : '' }}">
                Compare Reports
            </a>
        </li>

        <li>
            <a href="{{ route('reports.history') }}" class="flex items-center pl-6 py-2 text-sm hover:bg-gray-800 {{ request()->routeIs('reports.history') ? 'bg-gray-800' : '' }}">
                Report History
            </a>
        </li> --}}
        @endif

        @can('role:admin')
        <li class="px-4 py-2 text-xs text-gray-400 uppercase tracking-wider mt-4">System</li>

        <li class="px-4 py-2 text-xs text-gray-400 uppercase tracking-wider mt-2">Users</li>

        <li>
            <a href="{{ route('users.index') }}" class="flex items-center pl-6 py-2 text-sm hover:bg-gray-800 {{ request()->routeIs('users.index') ? 'bg-gray-800' : '' }}">
                User List
            </a>
        </li>

        <li>
            <a href="{{ route('users.create') }}" class="flex items-center pl-6 py-2 text-sm hover:bg-gray-800 {{ request()->routeIs('users.create') ? 'bg-gray-800' : '' }}">
                Create User
            </a>
        </li>

        <li>
            <a href="{{ route('branches.index') }}" class="flex items-center px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('branches.*') ? 'bg-gray-800' : '' }}">
                Branches
            </a>
        </li>

        <li>
            <a href="{{ route('setup.index') }}" class="flex items-center px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('setup.*') ? 'bg-gray-800' : '' }}">
                Setup
            </a>
        </li>

        <li>
            <a href="{{ route('test-results.index') }}" class="flex items-center px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('test-results.*') ? 'bg-gray-800' : '' }}">
                Test Results
            </a>
        </li>

        <li>
            <a href="{{ route('test-results.statistics') }}" class="flex items-center px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('test-results.statistics') ? 'bg-gray-800' : '' }}">
                Test Statistics
            </a>
        </li>
        @endcan
    </ul>

    <div class="p-4 border-t border-gray-700">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="w-full text-left px-4 py-2 hover:bg-gray-800 rounded">
                Logout
            </button>
        </form>
    </div>
</nav>