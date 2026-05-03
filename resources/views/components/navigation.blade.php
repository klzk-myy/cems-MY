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

        <li>
            <a href="{{ route('counters.index') }}" class="flex items-center px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('counters.*') ? 'bg-gray-800' : '' }}">
                Counters
            </a>
        </li>

        <li>
            <a href="{{ route('stock-cash.index') }}" class="flex items-center px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('stock-cash.*') ? 'bg-gray-800' : '' }}">
                Stock & Cash
            </a>
        </li>

        <li>
            <a href="{{ route('stock-transfers.index') }}" class="flex items-center px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('stock-transfers.*') ? 'bg-gray-800' : '' }}">
                Stock Transfers
            </a>
        </li>

        @can('role:manager')
        <li class="px-4 py-2 text-xs text-gray-400 uppercase tracking-wider mt-4">Finance</li>

        <li>
            <a href="{{ route('accounting.index') }}" class="flex items-center px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('accounting.*') ? 'bg-gray-800' : '' }}">
                Accounting
            </a>
        </li>
        @endcan

        @can('role:compliance')
        <li class="px-4 py-2 text-xs text-gray-400 uppercase tracking-wider mt-4">Compliance</li>

        <li>
            <a href="{{ route('compliance') }}" class="flex items-center px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('compliance') ? 'bg-gray-800' : '' }}">
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
            <a href="{{ route('str.index') }}" class="flex items-center px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('str.*') ? 'bg-gray-800' : '' }}">
                STR
            </a>
        </li>
        @endcan

        @can('role:manager')
        <li class="px-4 py-2 text-xs text-gray-400 uppercase tracking-wider mt-4">Reports</li>

        <li>
            <a href="{{ route('reports.index') }}" class="flex items-center px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('reports.*') ? 'bg-gray-800' : '' }}">
                Reports
            </a>
        </li>
        @endcan

        @can('role:admin')
        <li class="px-4 py-2 text-xs text-gray-400 uppercase tracking-wider mt-4">System</li>

        <li>
            <a href="{{ route('users.index') }}" class="flex items-center px-4 py-2 hover:bg-gray-800 {{ request()->routeIs('users.*') ? 'bg-gray-800' : '' }}">
                Users
            </a>
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