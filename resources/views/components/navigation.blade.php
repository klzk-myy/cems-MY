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

        <li>
            <a href="{{ route('transactions.index') }}" class="flex items-center px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('transactions.*') ? 'bg-gray-800' : '' }}">
                Transactions
            </a>
        </li>

        <li>
            <a href="{{ route('customers.index') }}" class="flex items-center px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('customers.*') ? 'bg-gray-800' : '' }}">
                Customers
            </a>
        </li>

        <!-- Counters Dropdown -->
        <li x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false">
            <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('counters.*') ? 'bg-gray-800' : '' }}">
                <span>Counters</span>
                <svg class="w-4 h-4 transform transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
            <div x-show="open" x-transition class="bg-gray-800">
                <a href="{{ route('counters.index') }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('counters.index') ? 'bg-gray-700' : '' }}">
                    Counter List
                </a>
                <a href="{{ route('counters.open') }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('counters.open') ? 'bg-gray-700' : '' }}">
                    Open Counter
                </a>
                <a href="{{ route('counters.close.show', ['counter' => 1]) }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('counters.close.show') ? 'bg-gray-700' : '' }}">
                    Close Counter
                </a>
                <a href="{{ route('counters.handover.show', ['counter' => 1]) }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('counters.handover.show') ? 'bg-gray-700' : '' }}">
                    Handover
                </a>
                <a href="{{ route('counters.history') }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('counters.history') ? 'bg-gray-700' : '' }}">
                    History
                </a>
            </div>
        </li>

        <!-- Stock & Cash Dropdown -->
        <li x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false">
            <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('stock-cash.*') ? 'bg-gray-800' : '' }}">
                <span>Stock & Cash</span>
                <svg class="w-4 h-4 transform transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
            <div x-show="open" x-transition class="bg-gray-800">
                <a href="{{ route('stock-cash.index') }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('stock-cash.index') ? 'bg-gray-700' : '' }}">
                    Overview
                </a>
                <a href="{{ route('stock-cash.position', ['position' => 1]) }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('stock-cash.position') ? 'bg-gray-700' : '' }}">
                    Position
                </a>
                <a href="{{ route('stock-cash.reconciliation') }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('stock-cash.reconciliation') ? 'bg-gray-700' : '' }}">
                    Reconciliation
                </a>
                <a href="{{ route('stock-cash.till-report') }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('stock-cash.till-report') ? 'bg-gray-700' : '' }}">
                    Till Report
                </a>
            </div>
        </li>

        <!-- Stock Transfers Dropdown -->
        <li x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false">
            <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('stock-transfers.*') ? 'bg-gray-800' : '' }}">
                <span>Stock Transfers</span>
                <svg class="w-4 h-4 transform transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
            <div x-show="open" x-transition class="bg-gray-800">
                <a href="{{ route('stock-transfers.index') }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('stock-transfers.index') ? 'bg-gray-700' : '' }}">
                    Overview
                </a>
                <a href="{{ route('stock-transfers.create') }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('stock-transfers.create') ? 'bg-gray-700' : '' }}">
                    Create Transfer
                </a>
            </div>
        </li>

        @can('role:manager')
        <li class="px-4 py-2 text-xs text-gray-400 uppercase tracking-wider mt-4">Finance</li>

        <!-- Accounting Dropdown -->
        <li x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false">
            <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('accounting.*') ? 'bg-gray-800' : '' }}">
                <span>Accounting</span>
                <svg class="w-4 h-4 transform transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
            <div x-show="open" x-transition class="bg-gray-800">
                <a href="{{ route('accounting.index') }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('accounting.index') ? 'bg-gray-700' : '' }}">
                    Overview
                </a>
                <a href="{{ route('accounting.journal') }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('accounting.journal') ? 'bg-gray-700' : '' }}">
                    Journal
                </a>
                <a href="{{ route('accounting.trial-balance') }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('accounting.trial-balance') ? 'bg-gray-700' : '' }}">
                    Trial Balance
                </a>
                <a href="{{ route('accounting.profit-loss') }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('accounting.profit-loss') ? 'bg-gray-700' : '' }}">
                    P&L Statement
                </a>
                <a href="{{ route('accounting.balance-sheet') }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('accounting.balance-sheet') ? 'bg-gray-700' : '' }}">
                    Balance Sheet
                </a>
                <a href="{{ route('accounting.cash-flow') }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('accounting.cash-flow') ? 'bg-gray-700' : '' }}">
                    Cash Flow
                </a>
                <a href="{{ route('accounting.ratios') }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('accounting.ratios') ? 'bg-gray-700' : '' }}">
                    Financial Ratios
                </a>
                <a href="{{ route('accounting.budget') }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('accounting.budget') ? 'bg-gray-700' : '' }}">
                    Budget
                </a>
                <a href="{{ route('accounting.revaluation') }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('accounting.revaluation') ? 'bg-gray-700' : '' }}">
                    Revaluation
                </a>
                <a href="{{ route('accounting.reconciliation') }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('accounting.reconciliation') ? 'bg-gray-700' : '' }}">
                    Reconciliation
                </a>
            </div>
        </li>

        <li>
            <a href="{{ route('rates.index') }}" class="flex items-center px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('rates.*') ? 'bg-gray-800' : '' }}">
                Rates
            </a>
        </li>
        @endcan

        @can('role:compliance')
        <li class="px-4 py-2 text-xs text-gray-400 uppercase tracking-wider mt-4">Compliance</li>

        <!-- Compliance Dashboard Dropdown -->
        <li x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false">
            <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('compliance*') ? 'bg-gray-800' : '' }}">
                <span>Compliance Dashboard</span>
                <svg class="w-4 h-4 transform transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
            <div x-show="open" x-transition class="bg-gray-800">
                <a href="{{ route('compliance') }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('compliance') && !request()->routeIs('compliance.*') ? 'bg-gray-700' : '' }}">
                    Overview
                </a>
                <a href="{{ route('compliance.alerts.index') }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('compliance.alerts.*') ? 'bg-gray-700' : '' }}">
                    Alerts
                </a>
                <a href="{{ route('compliance.cases.index') }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('compliance.cases.*') ? 'bg-gray-700' : '' }}">
                    Cases
                </a>
                <a href="{{ route('str.index') }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('str.*') ? 'bg-gray-700' : '' }}">
                    STR
                </a>
                <a href="{{ route('compliance.ctos.index') }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('compliance.ctos.*') ? 'bg-gray-700' : '' }}">
                    CTOS Reports
                </a>
                <a href="{{ route('compliance.reporting.index') }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('compliance.reporting.*') ? 'bg-gray-700' : '' }}">
                    Reporting
                </a>
                <a href="{{ route('compliance.findings.index') }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('compliance.findings.*') ? 'bg-gray-700' : '' }}">
                    Findings
                </a>
                <a href="{{ route('compliance.screening.show', ['customerId' => 1]) }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('compliance.screening.*') ? 'bg-gray-700' : '' }}">
                    Screening
                </a>
                <a href="{{ route('compliance.workspace') }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('compliance.workspace') ? 'bg-gray-700' : '' }}">
                    Workspace
                </a>
                <a href="{{ route('compliance.unified.index') }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('compliance.unified.*') ? 'bg-gray-700' : '' }}">
                    Unified View
                </a>
                <a href="{{ route('compliance.risk-dashboard.index') }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('compliance.risk-dashboard.*') ? 'bg-gray-700' : '' }}">
                    Risk Dashboard
                </a>
                <a href="{{ route('compliance.sanctions.index') }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('compliance.sanctions.*') ? 'bg-gray-700' : '' }}">
                    Sanctions
                </a>
            </div>
        </li>
        @endcan

        @can('role:manager')
        <li class="px-4 py-2 text-xs text-gray-400 uppercase tracking-wider mt-4">Reports</li>

        <!-- Reports Dropdown -->
        <li x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false">
            <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('reports.*') ? 'bg-gray-800' : '' }}">
                <span>Reports</span>
                <svg class="w-4 h-4 transform transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
            <div x-show="open" x-transition class="bg-gray-800">
                <a href="{{ route('reports.index') }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('reports.index') ? 'bg-gray-700' : '' }}">
                    Overview
                </a>
                <a href="{{ route('reports.lctr') }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('reports.lctr') ? 'bg-gray-700' : '' }}">
                    LCTR
                </a>
                <a href="{{ route('reports.lmca') }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('reports.lmca') ? 'bg-gray-700' : '' }}">
                    LMCA
                </a>
                <a href="{{ route('reports.msb2') }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('reports.msb2') ? 'bg-gray-700' : '' }}">
                    MSB2
                </a>
                <a href="{{ route('reports.position-limit') }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('reports.position-limit') ? 'bg-gray-700' : '' }}">
                    Position Limit
                </a>
                <a href="{{ route('reports.profitability') }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('reports.profitability') ? 'bg-gray-700' : '' }}">
                    Profitability
                </a>
                <a href="{{ route('reports.customer-analysis') }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('reports.customer-analysis') ? 'bg-gray-700' : '' }}">
                    Customer Analysis
                </a>
                <a href="{{ route('reports.compliance-summary') }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('reports.compliance-summary') ? 'bg-gray-700' : '' }}">
                    Compliance Summary
                </a>
                <a href="{{ route('reports.quarterly-lvr') }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('reports.quarterly-lvr') ? 'bg-gray-700' : '' }}">
                    Quarterly LVR
                </a>
            </div>
        </li>
        @endcan

        @can('role:admin')
        <li class="px-4 py-2 text-xs text-gray-400 uppercase tracking-wider mt-4">System</li>

        <!-- Users Dropdown -->
        <li x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false">
            <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('users.*') ? 'bg-gray-800' : '' }}">
                <span>Users</span>
                <svg class="w-4 h-4 transform transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
            <div x-show="open" x-transition class="bg-gray-800">
                <a href="{{ route('users.index') }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('users.index') ? 'bg-gray-700' : '' }}">
                    User List
                </a>
                <a href="{{ route('users.create') }}" class="flex items-center pl-8 py-2 text-sm hover:bg-gray-700 {{ request()->routeIs('users.create') ? 'bg-gray-700' : '' }}">
                    Create User
                </a>
            </div>
        </li>

        <li>
            <a href="{{ route('branches.index') }}" class="flex items-center px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('branches.*') ? 'bg-gray-800' : '' }}">
                Branches
            </a>
        </li>

        <li>
            <a href="{{ route('audit.index') }}" class="flex items-center px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('audit.*') ? 'bg-gray-800' : '' }}">
                Audit
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
            <a href="{{ route('branches.closing.show', ['branch' => auth()->user()->branch_id ?? 1]) }}" class="flex items-center px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('branches.closing.*') ? 'bg-gray-800' : '' }}">
                Branch Closing
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