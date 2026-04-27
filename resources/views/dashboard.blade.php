<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CEMS-MY</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <div class="flex min-h-screen">
        {{-- Sidebar --}}
        <aside class="w-60 bg-white border-r border-[--color-border] flex flex-col shrink-0">
            <div class="px-6 py-4 border-b border-[--color-border]">
                <h1 class="text-lg font-semibold text-[--color-ink]">CEMS-MY</h1>
            </div>
            <nav class="flex-1 p-4 space-y-6 overflow-y-auto">
                <div>
                    <div class="px-3 py-2 text-xs font-semibold text-[--color-ink-muted] uppercase tracking-wide">Main</div>
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 bg-[--color-canvas-subtle] text-[--color-ink]">Dashboard</a>
                    <a href="{{ route('transactions.index') }}" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[--color-ink-muted] hover:bg-[--color-canvas-subtle] hover:text-[--color-ink]">Transactions</a>
                    <a href="{{ route('counters.index') }}" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[--color-ink-muted] hover:bg-[--color-canvas-subtle] hover:text-[--color-ink]">Counters</a>
                </div>
                <div>
                    <div class="px-3 py-2 text-xs font-semibold text-[--color-ink-muted] uppercase tracking-wide">Management</div>
                    <a href="{{ route('customers.index') }}" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[--color-ink-muted] hover:bg-[--color-canvas-subtle] hover:text-[--color-ink]">Customers</a>
                    <a href="{{ route('compliance.risk-dashboard.index') }}" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[--color-ink-muted] hover:bg-[--color-canvas-subtle] hover:text-[--color-ink]">Compliance</a>
                    <a href="{{ route('reports.index') }}" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[--color-ink-muted] hover:bg-[--color-canvas-subtle] hover:text-[--color-ink]">Reports</a>
                </div>
                <div>
                    <div class="px-3 py-2 text-xs font-semibold text-[--color-ink-muted] uppercase tracking-wide">System</div>
                    <a href="{{ route('users.index') }}" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[--color-ink-muted] hover:bg-[--color-canvas-subtle] hover:text-[--color-ink]">Users</a>
                    <a href="{{ route('rates.index') }}" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[--color-ink-muted] hover:bg-[--color-canvas-subtle] hover:text-[--color-ink]">Rates</a>
                    <a href="{{ route('accounting.index') }}" class="flex items-center gap-3 px-3 py-2 text-sm rounded-lg mb-1 text-[--color-ink-muted] hover:bg-[--color-canvas-subtle] hover:text-[--color-ink]">Accounting</a>
                </div>
            </nav>
        </aside>

        {{-- Main Content --}}
        <main class="flex-1 bg-[--color-canvas] p-8 overflow-y-auto">
            {{-- Page Header --}}
            <div class="mb-6">
                <h1 class="text-2xl font-semibold text-[--color-ink]">Dashboard</h1>
                <p class="text-sm text-[--color-ink-muted] mt-1">Welcome back. Here's what's happening today.</p>
            </div>

            {{-- Stats Grid --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white border border-[--color-border] rounded-xl p-6">
                    <div class="text-sm text-[--color-ink-muted] mb-1">Today's Transactions</div>
                    <div class="text-2xl font-semibold text-[--color-ink]">{{ number_format($stats['total_transactions']) }}</div>
                    <div class="text-xs text-[--color-ink-muted] mt-1">Buy: RM {{ number_format($stats['buy_volume'] ?? 0, 2) }} / Sell: RM {{ number_format($stats['sell_volume'] ?? 0, 2) }}</div>
                </div>
                <div class="bg-white border border-[--color-border] rounded-xl p-6">
                    <div class="text-sm text-[--color-ink-muted] mb-1">Total Customers</div>
                    <div class="text-2xl font-semibold text-[--color-ink]">{{ number_format($stats['active_customers']) }}</div>
                    <div class="text-xs text-[--color-ink-muted] mt-1">Active records</div>
                </div>
                <div class="bg-white border border-[--color-border] rounded-xl p-6">
                    <div class="text-sm text-[--color-ink-muted] mb-1">Flagged Transactions</div>
                    <div class="text-2xl font-semibold text-[--color-accent]">{{ number_format($stats['flagged']) }}</div>
                    <div class="text-xs text-[--color-ink-muted] mt-1">Open alerts</div>
                </div>
                <div class="bg-white border border-[--color-border] rounded-xl p-6">
                    <div class="text-sm text-[--color-ink-muted] mb-1">Transaction Volume</div>
                    <div class="text-2xl font-semibold text-[--color-ink]">RM {{ number_format(($stats['buy_volume'] ?? 0) + ($stats['sell_volume'] ?? 0), 2) }}</div>
                    <div class="text-xs text-[--color-ink-muted] mt-1">Today's total</div>
                </div>
            </div>

            {{-- Recent Transactions --}}
            <div class="bg-white border border-[--color-border] rounded-xl">
                <div class="px-6 py-4 border-b border-[--color-border] flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-[--color-ink]">Recent Transactions</h2>
                    <a href="{{ route('transactions.index') }}" class="text-sm text-[--color-accent] hover:underline">View all</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-[--color-canvas-subtle] border-b border-[--color-border]">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[--color-ink-muted]">Reference</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[--color-ink-muted]">Customer</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[--color-ink-muted]">Type</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[--color-ink-muted]">Amount</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[--color-ink-muted]">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[--color-ink-muted]">Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recent_transactions as $transaction)
                            <tr class="border-b border-[--color-border] hover:bg-[--color-canvas-subtle]/50">
                                <td class="px-4 py-3 font-mono text-xs text-[--color-ink]">{{ $transaction->reference ?? $transaction->id }}</td>
                                <td class="px-4 py-3 text-[--color-ink]">{{ $transaction->customer->full_name ?? 'N/A' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded {{ $transaction->type === 'Buy' ? 'bg-blue-100 text-blue-700' : 'bg-orange-100 text-orange-700' }}">
                                        {{ $transaction->type }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-[--color-ink] font-semibold">RM {{ number_format($transaction->amount_local ?? 0, 2) }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded
                                        @if($transaction->status === 'Completed') bg-green-100 text-green-700
                                        @elseif($transaction->status === 'Pending' || $transaction->status === 'PendingApproval' || $transaction->status === 'PendingCancellation') bg-yellow-100 text-yellow-700
                                        @else bg-gray-100 text-gray-700
                                        @endif">
                                        {{ $transaction->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-[--color-ink-muted] text-xs">{{ $transaction->created_at->format('h:i A') }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-[--color-ink-muted]">No transactions today</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>