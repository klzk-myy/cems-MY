@extends('layouts.base')

@section('title', 'Dashboard - CEMS-MY')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div>
        <h1 class="text-2xl font-semibold text-[--color-ink]">Dashboard</h1>
        <p class="text-sm text-[--color-ink-muted] mt-1">Welcome to CEMS-MY</p>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white border border-[--color-border] rounded-xl p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-[--color-ink-muted]">Today's Transactions</p>
                    <p class="text-2xl font-semibold text-[--color-ink] mt-1">{{ $stats['total_transactions'] ?? 0 }}</p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <span class="text-xl">💱</span>
                </div>
            </div>
        </div>

        <div class="bg-white border border-[--color-border] rounded-xl p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-[--color-ink-muted]">Buy Volume</p>
                    <p class="text-2xl font-semibold text-[--color-ink] mt-1">RM {{ number_format($stats['buy_volume'] ?? 0, 2) }}</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <span class="text-xl">📈</span>
                </div>
            </div>
        </div>

        <div class="bg-white border border-[--color-border] rounded-xl p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-[--color-ink-muted]">Sell Volume</p>
                    <p class="text-2xl font-semibold text-[--color-ink] mt-1">RM {{ number_format($stats['sell_volume'] ?? 0, 2) }}</p>
                </div>
                <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                    <span class="text-xl">📉</span>
                </div>
            </div>
        </div>

        <div class="bg-white border border-[--color-border] rounded-xl p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-[--color-ink-muted]">Flagged Transactions</p>
                    <p class="text-2xl font-semibold text-[--color-ink] mt-1">{{ $stats['flagged'] ?? 0 }}</p>
                </div>
                <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                    <span class="text-xl">🚩</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Recent Transactions --}}
    <div class="bg-white border border-[--color-border] rounded-xl">
        <div class="px-6 py-4 border-b border-[--color-border]">
            <h2 class="text-lg font-semibold text-[--color-ink]">Recent Transactions</h2>
        </div>
        <div class="p-6">
            @if(isset($recent_transactions) && $recent_transactions->count() > 0)
                <table class="w-full">
                    <thead>
                        <tr class="text-left text-sm text-[--color-ink-muted]">
                            <th class="pb-3">ID</th>
                            <th class="pb-3">Customer</th>
                            <th class="pb-3">Type</th>
                            <th class="pb-3">Amount</th>
                            <th class="pb-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        @foreach($recent_transactions as $txn)
                        <tr class="border-t border-[--color-border]">
                            <td class="py-3">{{ $txn->id }}</td>
                            <td class="py-3">{{ $txn->customer->name ?? 'N/A' }}</td>
                            <td class="py-3">
                                <span class="px-2 py-1 rounded text-xs font-medium {{ $txn->type === 'Buy' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                    {{ $txn->type }}
                                </span>
                            </td>
                            <td class="py-3">RM {{ number_format($txn->amount_local, 2) }}</td>
                            <td class="py-3">{{ $txn->status->value ?? $txn->status }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="text-[--color-ink-muted] text-center py-8">No transactions today</p>
            @endif
        </div>
    </div>
</div>
@endsection