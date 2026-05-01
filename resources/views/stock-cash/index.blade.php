@extends('layouts.base')

@section('title', 'Stock Cash Management - CEMS-MY')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-semibold text-[--color-ink]">Stock Cash Management</h1>
        <p class="text-sm text-[--color-ink-muted] mt-1">Currency stock and till management</p>
    </div>
</div>

{{-- Stats Cards --}}
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="card p-6">
        <div class="text-sm text-[--color-ink-muted] mb-1">Total Currencies</div>
        <div class="text-2xl font-bold text-[--color-ink]">{{ $stats['total_currencies'] }}</div>
    </div>
    <div class="card p-6">
        <div class="text-sm text-[--color-ink-muted] mb-1">Active Positions</div>
        <div class="text-2xl font-bold text-[--color-ink]">{{ $stats['active_positions'] }}</div>
    </div>
    <div class="card p-6">
        <div class="text-sm text-[--color-ink-muted] mb-1">Open Tills</div>
        <div class="text-2xl font-bold text-green-600">{{ $stats['open_tills'] }}</div>
    </div>
    <div class="card p-6">
        <div class="text-sm text-[--color-ink-muted] mb-1">MYR Cash in Hand</div>
        <div class="text-2xl font-bold text-[--color-ink]">RM {{ number_format((float) $myrCashInHand, 2) }}</div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Left: Currency Positions --}}
    <div class="lg:col-span-2 space-y-6">
        <div class="card">
            <div class="px-6 py-4 border-b border-[--color-border]">
                <h3 class="text-base font-semibold text-[--color-ink]">Currency Positions</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Currency</th>
                            <th class="text-right">Balance</th>
                            <th class="text-right">Avg Rate</th>
                            <th class="text-right">MYR Value</th>
                            <th class="text-right">P&L</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($positions as $position)
                        <tr class="border-b border-[--color-border] hover:bg-[--color-canvas-subtle]/50">
                            <td class="text-[--color-ink] font-medium">{{ $position->currency_code }}</td>
                            <td class="text-[--color-ink] text-right font-mono">{{ number_format($position->balance ?? 0, 2) }}</td>
                            <td class="text-[--color-ink-muted] text-right font-mono">{{ number_format($position->avg_rate ?? 0, 4) }}</td>
                            <td class="text-[--color-ink] text-right font-semibold">RM {{ number_format($position->myr_value ?? 0, 2) }}</td>
                            <td class="text-right {{ ($position->pnl ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ ($position->pnl ?? 0) >= 0 ? '+' : '' }}{{ number_format($position->pnl ?? 0, 2) }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-[--color-ink-muted]">No positions found</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="px-6 py-4 border-b border-[--color-border]">
                <h3 class="text-base font-semibold text-[--color-ink]">Today's Till Balances</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Till ID</th>
                            <th>Currency</th>
                            <th class="text-right">Opening</th>
                            <th class="text-right">Closing</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($todayBalances as $balance)
                        <tr class="border-b border-[--color-border] hover:bg-[--color-canvas-subtle]/50">
                            <td class="text-[--color-ink] font-mono text-sm">{{ $balance->till_id }}</td>
                            <td class="text-[--color-ink]">{{ $balance->currency_code }}</td>
                            <td class="text-[--color-ink] text-right font-mono">{{ number_format((float) ($balance->opening_balance ?? 0), 2) }}</td>
                            <td class="text-[--color-ink] text-right font-mono">
                                {{ $balance->closing_balance ? number_format((float) $balance->closing_balance, 2) : '-' }}
                            </td>
                            <td>
                                @if($balance->closed_at)
                                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-gray-100 text-gray-700">Closed</span>
                                @else
                                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Open</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-[--color-ink-muted]">No till balances today</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Right: Actions Panel --}}
    <div class="space-y-6">
        <div class="card">
            <div class="px-6 py-4 border-b border-[--color-border]">
                <h3 class="text-base font-semibold text-[--color-ink]">Open Till</h3>
            </div>
            <div class="p-6">
                <form method="POST" action="{{ route('stock-cash.open') }}">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-[--color-ink] mb-1">Till ID</label>
                            <input type="text" name="till_id" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" placeholder="TILL-001" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[--color-ink] mb-1">Currency</label>
                            <select name="currency_code" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" required>
                                <option value="">Select currency</option>
                                @foreach($currencies as $currency)
                                    <option value="{{ $currency->code }}">{{ $currency->code }} - {{ $currency->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[--color-ink] mb-1">Opening Balance</label>
                            <input type="number" name="opening_balance" step="0.01" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" placeholder="0.00" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[--color-ink] mb-1">Notes (optional)</label>
                            <textarea name="notes" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" rows="2" placeholder="Any notes..."></textarea>
                        </div>
                        <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Open Till
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="px-6 py-4 border-b border-[--color-border]">
                <h3 class="text-base font-semibold text-[--color-ink]">Close Till</h3>
            </div>
            <div class="p-6">
                <form method="POST" action="{{ route('stock-cash.close') }}">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-[--color-ink] mb-1">Till ID</label>
                            <input type="text" name="till_id" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" placeholder="TILL-001" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[--color-ink] mb-1">Currency</label>
                            <select name="currency_code" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" required>
                                <option value="">Select currency</option>
                                @foreach($currencies as $currency)
                                    <option value="{{ $currency->code }}">{{ $currency->code }} - {{ $currency->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[--color-ink] mb-1">Closing Balance</label>
                            <input type="number" name="closing_balance" step="0.01" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" placeholder="0.00" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-[--color-ink] mb-1">Notes (optional)</label>
                            <textarea name="notes" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" rows="2" placeholder="Any notes..."></textarea>
                        </div>
                        <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg bg-red-600 text-white hover:bg-red-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            Close Till
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection