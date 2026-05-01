@extends('layouts.base')

@section('title', 'Stock Reconciliation - CEMS-MY')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-semibold text-[--color-ink]">Stock Reconciliation</h1>
        <p class="text-sm text-[--color-ink-muted] mt-1">Daily stock reconciliation</p>
    </div>
    <a href="{{ route('stock-cash.index') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[--color-border] hover:bg-[--color-canvas-subtle]">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
        </svg>
        Back
    </a>
</div>

{{-- Summary Cards --}}
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="card p-6">
        <div class="text-sm text-[--color-ink-muted] mb-1">Opening Balance</div>
        <div class="text-2xl font-bold text-[--color-ink]">RM {{ number_format((float) ($reconciliation['opening_balance'] ?? 0), 2) }}</div>
    </div>
    <div class="card p-6">
        <div class="text-sm text-[--color-ink-muted] mb-1">Total Purchases</div>
        <div class="text-2xl font-bold text-green-600">RM {{ number_format((float) ($reconciliation['purchases']['total'] ?? 0), 2) }}</div>
        <div class="text-xs text-[--color-ink-muted] mt-1">{{ $reconciliation['purchases']['count'] ?? 0 }} transactions</div>
    </div>
    <div class="card p-6">
        <div class="text-sm text-[--color-ink-muted] mb-1">Total Sales</div>
        <div class="text-2xl font-bold text-orange-600">RM {{ number_format((float) ($reconciliation['sales']['total'] ?? 0), 2) }}</div>
        <div class="text-xs text-[--color-ink-muted] mt-1">{{ $reconciliation['sales']['count'] ?? 0 }} transactions</div>
    </div>
    <div class="card p-6">
        <div class="text-sm text-[--color-ink-muted] mb-1">Variance</div>
        <div class="text-2xl font-bold {{ ((float) ($reconciliation['variance'] ?? 0)) == 0 ? 'text-green-600' : 'text-red-600' }}">
            {{ number_format((float) ($reconciliation['variance'] ?? 0), 2) }}
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Left: Transaction List --}}
    <div class="lg:col-span-2">
        <div class="card">
            <div class="px-6 py-4 border-b border-[--color-border]">
                <h3 class="text-base font-semibold text-[--color-ink]">Transactions - {{ $date }}</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Customer</th>
                            <th>Type</th>
                            <th class="text-right">Foreign</th>
                            <th class="text-right">MYR</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transactions as $tx)
                        <tr class="border-b border-[--color-border] hover:bg-[--color-canvas-subtle]/50">
                            <td class="text-[--color-ink-muted] text-xs">{{ $tx->created_at->format('H:i:s') }}</td>
                            <td class="text-[--color-ink]">{{ $tx->customer->full_name ?? 'N/A' }}</td>
                            <td class="text-[--color-ink]">
                                <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded {{ $tx->type->value === 'Buy' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                                    {{ $tx->type->label() }}
                                </span>
                            </td>
                            <td class="text-[--color-ink] text-right font-mono">{{ number_format($tx->amount_foreign, 2) }} {{ $tx->currency_code }}</td>
                            <td class="text-[--color-ink] text-right font-semibold">RM {{ number_format($tx->amount_local, 2) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-[--color-ink-muted]">No transactions found</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Right: Reconciliation Summary --}}
    <div>
        <div class="card">
            <div class="px-6 py-4 border-b border-[--color-border]">
                <h3 class="text-base font-semibold text-[--color-ink]">Reconciliation Summary</h3>
            </div>
            <div class="p-6 space-y-4">
                <div class="flex justify-between items-center py-2 border-b border-[--color-border]">
                    <span class="text-sm text-[--color-ink-muted]">Opening Balance</span>
                    <span class="font-semibold">RM {{ number_format((float) ($reconciliation['opening_balance'] ?? 0), 2) }}</span>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-[--color-border]">
                    <span class="text-sm text-[--color-ink-muted]">+ Purchases</span>
                    <span class="font-semibold text-green-600">RM {{ number_format((float) ($reconciliation['purchases']['total'] ?? 0), 2) }}</span>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-[--color-border]">
                    <span class="text-sm text-[--color-ink-muted]">- Sales</span>
                    <span class="font-semibold text-orange-600">RM {{ number_format((float) ($reconciliation['sales']['total'] ?? 0), 2) }}</span>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-[--color-border]">
                    <span class="text-sm text-[--color-ink-muted]">Expected Closing</span>
                    <span class="font-semibold">RM {{ number_format((float) ($reconciliation['expected_closing'] ?? 0), 2) }}</span>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-[--color-border]">
                    <span class="text-sm text-[--color-ink-muted]">Actual Closing</span>
                    <span class="font-semibold">
                        @if($reconciliation['actual_closing'] !== null)
                            RM {{ number_format((float) $reconciliation['actual_closing'], 2) }}
                        @else
                            <span class="text-[--color-ink-muted]">Not closed</span>
                        @endif
                    </span>
                </div>
                <div class="flex justify-between items-center py-3 bg-[--color-canvas-subtle] rounded-lg px-4">
                    <span class="font-semibold text-[--color-ink]">Variance</span>
                    <span class="font-bold text-xl {{ ((float) ($reconciliation['variance'] ?? 0)) == 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ number_format((float) ($reconciliation['variance'] ?? 0), 2) }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection