<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'CEMS-MY' }}</title>
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

            <nav class="flex-1 overflow-y-auto px-3 pb-6">
                {{-- Dashboard --}}
                <div class="nav-section">
                    <a href="/dashboard" class="nav-link {{ request()->is('dashboard') ? 'active' : '' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        <span>Dashboard</span>
                    </a>
                </div>

                {{-- Operations --}}
                <div class="nav-section">
                    <div class="nav-section-title">Operations</div>
                    <a href="/transactions" class="nav-link {{ request()->is('transactions*') ? 'active' : '' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        <span>Transactions</span>
                    </a>
                    <a href="/customers" class="nav-link {{ request()->is('customers*') ? 'active' : '' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        <span>Customers</span>
                    </a>
                    <a href="/counters" class="nav-link {{ request()->is('counters*') ? 'active' : '' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                        <span>Counters</span>
                    </a>
                    <a href="/stock-cash" class="nav-link {{ request()->is('stock-cash*') ? 'active' : '' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                        <span>Stock & Cash</span>
                    </a>
                </div>

                {{-- Compliance --}}
                <div class="nav-section">
                    <div class="nav-section-title">Compliance</div>
                    <a href="/compliance" class="nav-link {{ request()->is('compliance') ? 'active' : '' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                        <span>Dashboard</span>
                    </a>
                    <a href="/compliance/alerts" class="nav-link {{ request()->is('compliance/alerts*') ? 'active' : '' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                        <span>Alerts</span>
                    </a>
                    <a href="/compliance/cases" class="nav-link {{ request()->is('compliance/cases*') ? 'active' : '' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                        <span>Cases</span>
                    </a>
                    <a href="/compliance/ctos" class="nav-link {{ request()->is('compliance/ctos*') ? 'active' : '' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        <span>CTOS</span>
                    </a>
                </div>

                {{-- Accounting --}}
                <div class="nav-section">
                    <div class="nav-section-title">Accounting</div>
                    <a href="/accounting" class="nav-link {{ request()->is('accounting*') ? 'active' : '' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                        <span>Journal</span>
                    </a>
                    <a href="/accounting/ledger" class="nav-link {{ request()->is('accounting/ledger*') ? 'active' : '' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                        <span>Ledger</span>
                    </a>
                </div>

                {{-- Reports --}}
                <div class="nav-section">
                    <div class="nav-section-title">Reports</div>
                    <a href="/reports" class="nav-link {{ request()->is('reports') ? 'active' : '' }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        <span>Reports</span>
                    </a>
                    <a href="/reports/msb2" class="nav-link {{ request()->is('reports/msb2*') ? 'active' : '' }}">
                        <span>MSB2</span>
                    </a>
                </div>
            </nav>
        </aside>

        <div class="main-wrapper">
            <header class="sticky top-0 z-40 bg-white/80 backdrop-blur-md border-b border-gray-200">
                <div class="flex items-center justify-between px-8 py-4">
                    <div>
                        @if(isset($title))
                            <h1 class="text-xl font-semibold text-gray-900">{{ $title }}</h1>
                        @endif
                    </div>
                    <div class="flex items-center gap-4">
                        @auth
                            <span class="text-sm text-gray-600">{{ auth()->user()->username }}</span>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="text-sm text-gray-500 hover:text-gray-700">Logout</button>
                            </form>
                        @endauth
                    </div>
                </div>
            </header>

            <main class="main-content">
                <div class="page-container">
                    @if(session('success'))
                        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">
                            {{ session('success') }}
                        </div>
                    @endif
                    @if(session('error'))
                        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
                            {{ session('error') }}
                        </div>
                    @endif
                    @if(session('warning'))
                        <div class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg text-yellow-700 text-sm">
                            {{ session('warning') }}
                        </div>
                    @endif

                    {{ $slot }}
                </div>
            </main>

            <footer class="border-t border-gray-200 py-6 px-8 bg-white">
                <div class="flex items-center justify-between text-sm text-gray-500">
                    <div class="flex items-center gap-3">
                        <div class="w-6 h-6 bg-amber-500 rounded flex items-center justify-center">
                            <span class="text-white font-bold text-xs">C</span>
                        </div>
                        <span>CEMS-MY v1.0</span>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    @livewireScripts
    @stack('scripts')
</body>
</html>