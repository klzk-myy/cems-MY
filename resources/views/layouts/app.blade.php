<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'CEMS-MY' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <div class="flex min-h-screen">
        {{-- Sidebar --}}
        <aside class="w-60 bg-white border-r border-[#e5e5e5] flex flex-col shrink-0">
            {{-- Logo --}}
            <div class="px-6 py-4 border-b border-[#e5e5e5]">
                <h1 class="text-lg font-semibold text-[#171717]">CEMS-MY</h1>
            </div>

            {{-- Navigation --}}
            <nav class="flex-1 p-4 space-y-6 overflow-y-auto">
                {{-- Main Section --}}
                <div>
                    <div class="px-3 py-2 text-xs font-semibold text-[#6b6b6b] uppercase tracking-wide">Main</div>
                    <a href="{{ route('dashboard') }}"
                       class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 {{ request()->routeIs('dashboard') ? 'bg-[#f7f7f8] text-[#171717]' : 'text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]' }}">
                        Dashboard
                    </a>
                    <a href="{{ route('transactions.index') }}"
                       class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 {{ request()->routeIs('transactions.*') ? 'bg-[#f7f7f8] text-[#171717]' : 'text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]' }}">
                        Transactions
                    </a>
                    <a href="{{ route('counters.index') }}"
                       class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 {{ request()->routeIs('counters.*') ? 'bg-[#f7f7f8] text-[#171717]' : 'text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]' }}">
                        Counters
                    </a>
                </div>

                {{-- Management Section --}}
                <div>
                    <div class="px-3 py-2 text-xs font-semibold text-[#6b6b6b] uppercase tracking-wide">Management</div>
                    <a href="{{ route('customers.index') }}"
                       class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 {{ request()->routeIs('customers.*') ? 'bg-[#f7f7f8] text-[#171717]' : 'text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]' }}">
                        Customers
                    </a>
                    <a href="{{ route('compliance.index') }}"
                       class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 {{ request()->routeIs('compliance.*') ? 'bg-[#f7f7f8] text-[#171717]' : 'text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]' }}">
                        Compliance
                    </a>
                    <a href="{{ route('reports.index') }}"
                       class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 {{ request()->routeIs('reports.*') ? 'bg-[#f7f7f8] text-[#171717]' : 'text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]' }}">
                        Reports
                    </a>
                </div>

                {{-- System Section --}}
                <div>
                    <div class="px-3 py-2 text-xs font-semibold text-[#6b6b6b] uppercase tracking-wide">System</div>
                    <a href="{{ route('users.index') }}"
                       class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 {{ request()->routeIs('users.*') ? 'bg-[#f7f7f8] text-[#171717]' : 'text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]' }}">
                        Users
                    </a>
                    <a href="{{ route('rates.index') }}"
                       class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 {{ request()->routeIs('rates.*') ? 'bg-[#f7f7f8] text-[#171717]' : 'text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]' }}">
                        Rates
                    </a>
                    <a href="{{ route('accounting.index') }}"
                       class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 {{ request()->routeIs('accounting.*') ? 'bg-[#f7f7f8] text-[#171717]' : 'text-[#6b6b6b] hover:bg-[#f7f7f8] hover:text-[#171717]' }}">
                        Accounting
                    </a>
                </div>
            </nav>
        </aside>

        {{-- Main Content --}}
        <main class="flex-1 bg-[#f7f7f8] p-8 overflow-y-auto">
            {{ $slot }}
        </main>
    </div>
</body>
</html>