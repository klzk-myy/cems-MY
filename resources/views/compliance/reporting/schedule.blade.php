<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Schedule Reports - CEMS-MY')</title>
    @vite(['resources/css/app.css'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Instrument+Serif&family=Geist+Mono:wght@400;500&display=swap" rel="stylesheet">
    @stack('styles')
</head>
<body>
    <div class="app-shell">
        {{-- Sidebar --}}
        <aside class="sidebar">
            {{-- Logo --}}
            <div class="px-4 py-5 border-b border-[--sidebar-border]">
                <a href="{{ auth()->check() ? '/dashboard' : '/' }}" class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-[--color-accent] rounded-lg flex items-center justify-center">
                        <span class="text-white font-bold text-lg">C</span>
                    </div>
                    <div>
                        <span class="text-white font-semibold">CEMS-MY</span>
                        <p class="text-[10px] text-[--sidebar-text-muted]">Currency Exchange MSB</p>
                    </div>
                </a>
            </div>

            {{-- Search --}}
            <div class="px-4 py-4">
                <div class="relative">
                    <input
                        type="search"
                        placeholder="Search..."
                        class="w-full pl-9 pr-4 py-2 text-sm bg-[--sidebar-hover] border border-[--sidebar-border] rounded-lg text-[--sidebar-text] placeholder:text-[--sidebar-text-muted] focus:outline-none focus:border-[--sidebar-border] focus:ring-1 focus:ring-[--color-accent]/30"
                    >
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[--sidebar-text-muted]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
            </div>

{{-- Navigation --}}
            <nav class="flex-1 overflow-y-auto px-3 pb-6" x-data="{ open: null }">

                {{-- Dashboard Section --}}
                <div class="nav-section">
                    <x-sidebar-dropdown title="Dashboard" icon="home">
                        <x-sidebar-link href="/dashboard" :active="request()->is('dashboard')">Dashboard</x-sidebar-link>
                        <x-sidebar-link href="/performance" :active="request()->is('performance')">Performance</x-sidebar-link>
                        <x-sidebar-link href="/rates" :active="request()->is('rates')">Exchange Rates</x-sidebar-link>
                    </x-sidebar-dropdown>
                </div>

                {{-- Operations Section --}}
                <div class="nav-section">
                    <x-sidebar-dropdown title="Operations" icon="cash">
                        <x-sidebar-link href="/transactions" :active="request()->is('transactions') && !request()->is('transactions/*')">Transactions</x-sidebar-link>
                        <x-sidebar-link href="/customers" :active="request()->is('customers') && !request()->is('customers/*')">Customers</x-sidebar-link>
                    </x-sidebar-dropdown>
                </div>

                {{-- Counter Section --}}
                <div class="nav-section">
                    <x-sidebar-dropdown title="Counter" icon="register">
                        <x-sidebar-link href="/counters" :active="request()->is('counters')">Counters</x-sidebar-link>
                    </x-sidebar-dropdown>
                </div>

                {{-- Stock Section (Manager+) --}}
                @if(auth()->check() && (auth()->user()->role->isManager() || auth()->user()->role->isAdmin()))
                <div class="nav-section">
                    <x-sidebar-dropdown title="Stock" icon="boxes">
                        <x-sidebar-link href="/stock-cash" :active="request()->is('stock-cash')">Stock & Cash</x-sidebar-link>
                        <x-sidebar-link href="/stock-transfers" :active="request()->is('stock-transfers')">Stock Transfers</x-sidebar-link>
                    </x-sidebar-dropdown>
                </div>
                @endif

                {{-- Compliance Section (Compliance+) --}}
                @if(auth()->check() && (auth()->user()->role->isComplianceOfficer() || auth()->user()->role->isAdmin()))
                <div class="nav-section">
                    <x-sidebar-dropdown title="Compliance" icon="shield">
                        <x-sidebar-link href="/compliance" :active="request()->is('compliance') && !request()->is('compliance/*')">Dashboard</x-sidebar-link>
                        <x-sidebar-link href="/compliance/alerts" :active="request()->is('compliance/alerts*')">Alert Triage</x-sidebar-link>
                        <x-sidebar-link href="/compliance/cases" :active="request()->is('compliance/cases*')">Cases</x-sidebar-link>
                        <x-sidebar-link href="/str" :active="request()->is('str*')">STR Reports</x-sidebar-link>
                        <x-sidebar-link href="/compliance/edd" :active="request()->is('compliance/edd*')">EDD Records</x-sidebar-link>
                        <x-sidebar-link href="/compliance/sanctions" :active="request()->is('compliance/sanctions*')">Sanctions</x-sidebar-link>
                        <x-sidebar-link href="/compliance/risk-dashboard" :active="request()->is('compliance/risk-dashboard*')">Risk Dashboard</x-sidebar-link>
                        <x-sidebar-link href="/compliance/reporting" :active="request()->is('compliance/reporting*')">Reporting</x-sidebar-link>
                        <x-sidebar-link href="/compliance/rules" :active="request()->is('compliance/rules*')">AML Rules</x-sidebar-link>
                        <x-sidebar-link href="/compliance/ctos" :active="request()->is('compliance/ctos*')">CTOS</x-sidebar-link>
                        <x-sidebar-link href="/compliance/findings" :active="request()->is('compliance/findings*')">Findings</x-sidebar-link>
                        <x-sidebar-link href="/compliance/edd-templates" :active="request()->is('compliance/edd-templates*')">EDD Templates</x-sidebar-link>
                        <x-sidebar-link href="/compliance/workspace" :active="request()->is('compliance/workspace*')">Workspace</x-sidebar-link>
                        <x-sidebar-link href="/compliance/unified" :active="request()->is('compliance/unified*')">Unified Alerts</x-sidebar-link>
                    </x-sidebar-dropdown>
                </div>
                @endif

                {{-- Accounting Section (Manager+) --}}
                @if(auth()->check() && (auth()->user()->role->isManager() || auth()->user()->role->isAdmin()))
                <div class="nav-section">
                    <x-sidebar-dropdown title="Accounting" icon="book">
                        <x-sidebar-link href="/accounting" :active="request()->is('accounting') && !request()->is('accounting/*')">Overview</x-sidebar-link>
                        <x-sidebar-link href="/accounting/journal" :active="request()->is('accounting/journal*')">Journal</x-sidebar-link>
                        <x-sidebar-link href="/accounting/ledger" :active="request()->is('accounting/ledger*')">Ledger</x-sidebar-link>
                        <x-sidebar-link href="/accounting/trial-balance" :active="request()->is('accounting/trial-balance*')">Trial Balance</x-sidebar-link>
                        <x-sidebar-link href="/accounting/profit-loss" :active="request()->is('accounting/profit-loss*')">Profit & Loss</x-sidebar-link>
                        <x-sidebar-link href="/accounting/balance-sheet" :active="request()->is('accounting/balance-sheet*')">Balance Sheet</x-sidebar-link>
                        <x-sidebar-link href="/accounting/reconciliation" :active="request()->is('accounting/reconciliation*')">Reconciliation</x-sidebar-link>
                        <x-sidebar-link href="/accounting/budget" :active="request()->is('accounting/budget*')">Budget</x-sidebar-link>
                        <x-sidebar-link href="/accounting/revaluation" :active="request()->is('accounting/revaluation*')">Revaluation</x-sidebar-link>
                        <x-sidebar-link href="/accounting/month-end" :active="request()->is('accounting/month-end*')">Month End</x-sidebar-link>
                        <x-sidebar-link href="/accounting/periods" :active="request()->is('accounting/periods*')">Periods</x-sidebar-link>
                        <x-sidebar-link href="/accounting/fiscal-years" :active="request()->is('accounting/fiscal-years*')">Fiscal Years</x-sidebar-link>
                    </x-sidebar-dropdown>
                </div>
                @endif

                {{-- Reports Section (Manager+) --}}
                @if(auth()->check() && (auth()->user()->role->isManager() || auth()->user()->role->isAdmin()))
                <div class="nav-section">
                    <x-sidebar-dropdown title="Reports" icon="chart">
                        <x-sidebar-link href="/reports" :active="request()->is('reports') && !request()->is('reports/*')">Overview</x-sidebar-link>
                        <x-sidebar-link href="/reports/msb2" :active="request()->is('reports/msb2*')">MSB2</x-sidebar-link>
                        <x-sidebar-link href="/reports/lctr" :active="request()->is('reports/lctr*')">LCTR</x-sidebar-link>
                        <x-sidebar-link href="/reports/lmca" :active="request()->is('reports/lmca*')">LMCA</x-sidebar-link>
                        <x-sidebar-link href="/reports/quarterly-lvr" :active="request()->is('reports/quarterly-lvr*')">Quarterly LVR</x-sidebar-link>
                        <x-sidebar-link href="/reports/position-limit" :active="request()->is('reports/position-limit*')">Position Limits</x-sidebar-link>
                        <x-sidebar-link href="/reports/monthly-trends" :active="request()->is('reports/monthly-trends*')">Monthly Trends</x-sidebar-link>
                        <x-sidebar-link href="/reports/profitability" :active="request()->is('reports/profitability*')">Profitability</x-sidebar-link>
                        <x-sidebar-link href="/reports/customer-analysis" :active="request()->is('reports/customer-analysis*')">Customer Analysis</x-sidebar-link>
                        <x-sidebar-link href="/reports/compliance-summary" :active="request()->is('reports/compliance-summary*')">Compliance Summary</x-sidebar-link>
                        <x-sidebar-link href="/reports/history" :active="request()->is('reports/history*')">Report History</x-sidebar-link>
                    </x-sidebar-dropdown>
                </div>
                @endif

                {{-- System Section (Admin only) --}}
                @if(auth()->check() && auth()->user()->role->isAdmin())
                <div class="nav-section">
                    <x-sidebar-dropdown title="System" icon="cog">
                        <x-sidebar-link href="/users" :active="request()->is('users*')">Users</x-sidebar-link>
                        <x-sidebar-link href="/branches" :active="request()->is('branches*')">Branches</x-sidebar-link>
                        <x-sidebar-link href="/audit" :active="request()->is('audit*')">Audit</x-sidebar-link>
                    </x-sidebar-dropdown>
                </div>
                @endif

            </nav>

            {{-- User Section --}}
            <div class="px-4 py-4 border-t border-[--sidebar-border]">
                @auth
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-9 h-9 bg-[--sidebar-hover] rounded-lg flex items-center justify-center">
                            <span class="text-white font-semibold text-sm">{{ substr(auth()->user()->username, 0, 1) }}</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-white truncate">{{ auth()->user()->username }}</p>
                            <p class="text-xs text-[--sidebar-text-muted]">{{ auth()->user()->role->label() }}</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-ghost w-full text-[--sidebar-text] hover:bg-[--sidebar-hover] hover:text-white">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                            </svg>
                            Log out
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary w-full">
                        Log in
                    </a>
                @endauth
            </div>
        </aside>

        {{-- Main Content --}}
        <div class="main-wrapper">
            {{-- Header --}}
            <header class="sticky top-0 z-40 bg-[--content-bg]/80 backdrop-blur-md border-b border-[--color-border]">
                <div class="flex items-center justify-between px-8 py-4">
                    {{-- Page Title --}}
                    <div>
                        @hasSection('header-title')
                            @yield('header-title')
                        @else
                            <h1 class="text-xl font-semibold text-[--color-ink]">@yield('title', 'CEMS-MY')</h1>
                        @endif
                    </div>

                    {{-- Header Actions --}}
                    <div class="flex items-center gap-4">
                        @yield('header-actions')
                    </div>
                </div>
            </header>

            {{-- Main Content Area --}}
            <main class="main-content">
                <div class="page-container">
                    {{-- Flash Messages --}}
                    @if(session('success'))
                        <div class="alert alert-success mb-6 animate-slideDown">
                            <svg class="alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div class="alert-content">
                                <p class="alert-title">Success</p>
                                <p class="alert-description">{{ session('success') }}</p>
                            </div>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger mb-6 animate-slideDown">
                            <svg class="alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div class="alert-content">
                                <p class="alert-title">Error</p>
                                <p class="alert-description">{{ session('error') }}</p>
                            </div>
                        </div>
                    @endif

                    @if(session('warning'))
                        <div class="alert alert-warning mb-6 animate-slideDown">
                            <svg class="alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                            <div class="alert-content">
                                <p class="alert-title">Warning</p>
                                <p class="alert-description">{{ session('warning') }}</p>
                            </div>
                        </div>
                    @endif

                    {{-- Page Content --}}
                    @yield('content')
                </div>
            </main>

            {{-- Footer --}}
            <footer class="border-t border-[--color-border] py-6 px-8 bg-[--color-canvas]">
                <div class="flex items-center justify-between text-sm text-[--color-ink-muted]">
                    <div class="flex items-center gap-3">
                        <div class="w-6 h-6 bg-[--color-accent] rounded flex items-center justify-center">
                            <span class="text-white font-bold text-xs">C</span>
                        </div>
                        <span>CEMS-MY v1.0 - Bank Negara Malaysia Compliant MSB Management System</span>
                    </div>
                    <div class="flex items-center gap-6">
                        <a href="#" class="hover:text-[--color-ink] transition-colors">Documentation</a>
                        <a href="#" class="hover:text-[--color-ink] transition-colors">Support</a>
                        <a href="#" class="hover:text-[--color-ink] transition-colors">Security</a>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    @stack('scripts')
    
    {{-- Loading Overlay --}}
    @include('components.loading')
    
    {{-- Notifications --}}
    @include('components.notifications')
    </body>
</html>