<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'CEMS-MY')</title>
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
            <nav class="flex-1 overflow-y-auto px-3 pb-6">
                {{-- Main --}}
                <div class="nav-section">
                    <a href="/dashboard" class="nav-item {{ request()->is('dashboard') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                        </svg>
                        <span>Dashboard</span>
                    </a>
                </div>

                {{-- Operations --}}
                <div class="nav-section">
                    <div class="nav-section-title">Operations</div>
                    <a href="/transactions" class="nav-item {{ request()->is('transactions*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                        </svg>
                        <span>Transactions</span>
                    </a>
                    <a href="/customers" class="nav-item {{ request()->is('customers*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                        <span>Customers</span>
                    </a>
                </div>

                {{-- Counter Management --}}
                <div class="nav-section">
                    <div class="nav-section-title">Counter</div>
                    <a href="/counters" class="nav-item {{ request()->is('counters*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 657a2 2 0 100-4 2 2 0 000 4zm0 0v6a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2v6z"></path>
                        </svg>
                        <span>Counters</span>
                    </a>
                    <a href="/branches" class="nav-item {{ request()->is('branches*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                        <span>Branches</span>
                    </a>
                </div>

                {{-- Stock Management --}}
                <div class="nav-section">
                    <div class="nav-section-title">Stock</div>
                    <a href="/stock-cash" class="nav-item {{ request()->is('stock-cash*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                        <span>Stock & Cash</span>
                    </a>
                    <a href="/stock-transfers" class="nav-item {{ request()->is('stock-transfers*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                        </svg>
                        <span>Stock Transfers</span>
                    </a>
                </div>

                {{-- Compliance & AML --}}
                <div class="nav-section">
                    <div class="nav-section-title">Compliance</div>
                    <a href="/compliance" class="nav-item {{ request()->is('compliance') && !request()->is('compliance/*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                        <span>Compliance</span>
                    </a>
                    <a href="/compliance/workspace" class="nav-item {{ request()->is('compliance/workspace*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                        <span>Workspace</span>
                    </a>
                    <a href="/compliance/alerts" class="nav-item {{ request()->is('compliance/alerts*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                        <span>Alert Triage</span>
                        @if(($pendingAlerts?->count() ?? 0) > 0)
                            <span class="nav-item-badge danger">{{ $pendingAlerts->count() }}</span>
                        @endif
                    </a>
                    <a href="/compliance/cases" class="nav-item {{ request()->is('compliance/cases*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                        </svg>
                        <span>Cases</span>
                    </a>
                    <a href="/compliance/edd" class="nav-item {{ request()->is('compliance/edd*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span>EDD Records</span>
                    </a>
                    <a href="/compliance/findings" class="nav-item {{ request()->is('compliance/findings*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                        <span>Findings</span>
                    </a>
                    <li>
                        <a href="/compliance/unified" class="flex items-center gap-3 px-4 py-2.5 text-sm rounded-lg {{ request()->is('compliance/unified*') ? 'bg-[--color-accent] text-white' : 'text-[--color-ink-muted] hover:bg-[--color-canvas-subtle]' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Unified Alerts
                        </a>
                    </li>
                    <a href="/compliance/edd-templates" class="nav-item {{ request()->is('compliance/edd-templates*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"></path>
                        </svg>
                        <span>EDD Templates</span>
                    </a>
                    <a href="/compliance/rules" class="nav-item {{ request()->is('compliance/rules*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                        </svg>
                        <span>AML Rules</span>
                    </a>
                    <a href="/compliance/sanctions" class="nav-item {{ request()->is('compliance/sanctions*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                        <span>Sanction Lists</span>
                    </a>
                    <a href="/compliance/ctos" class="nav-item {{ request()->is('compliance/ctos*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span>CTOS Reports</span>
                    </a>
                    <a href="/compliance/risk-dashboard" class="nav-item {{ request()->is('compliance/risk-dashboard*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                        <span>Risk Dashboard</span>
                    </a>
                    <a href="/compliance/str-studio" class="nav-item {{ request()->is('compliance/str-studio*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span>STR Studio</span>
                    </a>
                    <a href="/compliance/reporting" class="nav-item {{ request()->is('compliance/reporting*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span>Reporting</span>
                    </a>
                    <a href="/str" class="nav-item {{ request()->is('str*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        <span>STR Reports</span>
                    </a>
                </div>

                {{-- Accounting --}}
                <div class="nav-section">
                    <div class="nav-section-title">Accounting</div>
                    <a href="/accounting" class="nav-item {{ request()->is('accounting') && !request()->is('accounting/*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                        </svg>
                        <span>Accounting</span>
                    </a>
                    <a href="/accounting/journal" class="nav-item {{ request()->is('accounting/journal*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                        <span>Journal Entries</span>
                    </a>
                    <a href="/accounting/ledger" class="nav-item {{ request()->is('accounting/ledger*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
                        </svg>
                        <span>Ledger</span>
                    </a>
                    <a href="/accounting/trial-balance" class="nav-item {{ request()->is('accounting/trial-balance*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"></path>
                        </svg>
                        <span>Trial Balance</span>
                    </a>
                    <a href="/accounting/profit-loss" class="nav-item {{ request()->is('accounting/profit-loss*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path>
                        </svg>
                        <span>Profit & Loss</span>
                    </a>
                    <a href="/accounting/balance-sheet" class="nav-item {{ request()->is('accounting/balance-sheet*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                        </svg>
                        <span>Balance Sheet</span>
                    </a>
                    <a href="/accounting/cash-flow" class="nav-item {{ request()->is('accounting/cash-flow*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span>Cash Flow</span>
                    </a>
                    <a href="/accounting/ratios" class="nav-item {{ request()->is('accounting/ratios*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        <span>Financial Ratios</span>
                    </a>
                    <a href="/accounting/revaluation" class="nav-item {{ request()->is('accounting/revaluation*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v04m0 0l-3-3m3 3l3-3m-4 8l-4-4m4 4l4-4"></path>
                        </svg>
                        <span>Revaluation</span>
                    </a>
                    <a href="/accounting/reconciliation" class="nav-item {{ request()->is('accounting/reconciliation*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                        </svg>
                        <span>Reconciliation</span>
                    </a>
                    <a href="/accounting/budget" class="nav-item {{ request()->is('accounting/budget*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                        </svg>
                        <span>Budget</span>
                    </a>
                    <a href="/accounting/periods" class="nav-item {{ request()->is('accounting/periods*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <span>Periods</span>
                    </a>
                    <a href="/accounting/fiscal-years" class="nav-item {{ request()->is('accounting/fiscal-years*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <span>Fiscal Years</span>
                    </a>
                </div>

                {{-- Reports --}}
                <div class="nav-section">
                    <div class="nav-section-title">Reports</div>
                    <a href="/reports" class="nav-item {{ request()->is('reports') && !request()->is('reports/*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span>Reports</span>
                    </a>
                    <a href="/reports/msb2" class="nav-item {{ request()->is('reports/msb2*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span>MSB2 Report</span>
                    </a>
                    <a href="/reports/lctr" class="nav-item {{ request()->is('reports/lctr*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 657a2 2 0 100-4 2 2 0 000 4zm0 0v6a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2v6z"></path>
                        </svg>
                        <span>LCTR</span>
                    </a>
                    <a href="/reports/lmca" class="nav-item {{ request()->is('reports/lmca*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span>LMCA</span>
                    </a>
                    <a href="/reports/quarterly-lvr" class="nav-item {{ request()->is('reports/quarterly-lvr*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        <span>Quarterly LVR</span>
                    </a>
                    <a href="/reports/position-limit" class="nav-item {{ request()->is('reports/position-limit*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        <span>Position Limits</span>
                    </a>
                    <a href="/reports/history" class="nav-item {{ request()->is('reports/history*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span>Report History</span>
                    </a>
                </div>

                {{-- System --}}
                <div class="nav-section">
                    <div class="nav-section-title">System</div>
                    <a href="/tasks" class="nav-item {{ request()->is('tasks') && !request()->is('tasks/*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                        </svg>
                        <span>All Tasks</span>
                    </a>
                    <a href="/tasks/my" class="nav-item {{ request()->is('tasks/my*') ? 'active' : '' }}" style="padding-left: 48px;">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        <span>My Tasks</span>
                    </a>
                    <a href="/tasks/overdue" class="nav-item {{ request()->is('tasks/overdue*') ? 'active' : '' }}" style="padding-left: 48px;">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span>Overdue Tasks</span>
                    </a>
                    <a href="/transactions/batch-upload" class="nav-item {{ request()->is('transactions/batch-upload*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                        </svg>
                        <span>Transaction Imports</span>
                    </a>
                    <a href="/audit" class="nav-item {{ request()->is('audit') && !request()->is('audit/*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span>Audit Dashboard</span>
                    </a>
                    <a href="/audit/log" class="nav-item {{ request()->is('audit/log*') ? 'active' : '' }}" style="padding-left: 48px;">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span>Audit Log</span>
                    </a>
                    @if(auth()->check() && auth()->user()->isAdmin())
                    <a href="/users" class="nav-item {{ request()->is('users*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                        <span>Users</span>
                    </a>
                    <a href="/data-breach-alerts" class="nav-item {{ request()->is('data-breach-alerts*') ? 'active' : '' }}">
                        <svg class="nav-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        <span>Data Breach Alerts</span>
                    </a>
                    @endif
                </div>
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