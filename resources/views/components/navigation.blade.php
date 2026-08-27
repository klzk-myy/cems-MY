@props([
    'collapsible' => true,
    'collapsed' => false,
])

<aside
    class="flex h-full w-64 flex-col border-r border-sidebar-border bg-sidebar transition-all"
    :class="{ 'w-20': sidebarCollapsed }"
>
    <div class="flex h-16 items-center justify-between border-b border-sidebar-border px-4">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2" x-show="!sidebarCollapsed">
            <span class="text-lg font-bold text-sidebar-text">CEMS</span>
        </a>
        @if($collapsible)
            <button
                @click="sidebarCollapsed = !sidebarCollapsed"
                class="rounded-md p-1.5 text-sidebar-text-muted hover:bg-sidebar-hover hover:text-sidebar-text"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        @endif
    </div>

    <nav class="flex-1 overflow-y-auto p-3">
        <ul class="space-y-1">
            <li>
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-sidebar-text hover:bg-sidebar-hover">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
                    <span x-show="!sidebarCollapsed">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="{{ route('transactions.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-sidebar-text hover:bg-sidebar-hover">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" /></svg>
                    <span x-show="!sidebarCollapsed">Transactions</span>
                </a>
            </li>
            <li>
                <a href="{{ route('customers.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-sidebar-text hover:bg-sidebar-hover">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                    <span x-show="!sidebarCollapsed">Customers</span>
                </a>
            </li>
            <li>
                <a href="{{ route('rates.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-sidebar-text hover:bg-sidebar-hover">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span x-show="!sidebarCollapsed">Rates</span>
                </a>
            </li>
            <li>
                <a href="{{ route('counters.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-sidebar-text hover:bg-sidebar-hover">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                    <span x-show="!sidebarCollapsed">Counters</span>
                </a>
            </li>
            <li>
                <a href="{{ route('stock-cash.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-sidebar-text hover:bg-sidebar-hover">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                    <span x-show="!sidebarCollapsed">Stock Cash</span>
                </a>
            </li>
            <li>
                <a href="{{ route('stock-transfers.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-sidebar-text hover:bg-sidebar-hover">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
                    <span x-show="!sidebarCollapsed">Stock Transfers</span>
                </a>
            </li>
            <li>
                <a href="{{ route('compliance') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-sidebar-text hover:bg-sidebar-hover">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                    <span x-show="!sidebarCollapsed">Compliance</span>
                </a>
            </li>
            <li>
                <a href="{{ route('accounting.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-sidebar-text hover:bg-sidebar-hover">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                    <span x-show="!sidebarCollapsed">Accounting</span>
                </a>
            </li>
            <li>
                <a href="{{ route('reports.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-sidebar-text hover:bg-sidebar-hover">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    <span x-show="!sidebarCollapsed">Reports</span>
                </a>
            </li>
            <li>
                <a href="{{ route('users.index') }}" class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-sidebar-text hover:bg-sidebar-hover">
                    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                    <span x-show="!sidebarCollapsed">Users</span>
                </a>
            </li>
        </ul>
    </nav>

    <div class="border-t border-sidebar-border p-3">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm text-sidebar-text hover:bg-sidebar-hover">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                <span x-show="!sidebarCollapsed">Logout</span>
            </button>
        </form>
    </div>
</aside>
