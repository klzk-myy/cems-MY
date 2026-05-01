@extends('layouts.base')

@section('title', 'Dashboard - CEMS-MY')

@section('content')
<div class="mb-6">
    <div>
        <h1 class="text-2xl font-semibold text-[--color-ink]">Dashboard</h1>
        <p class="text-sm text-[--color-ink-muted] mt-1">Welcome back. Here's what's happening today.</p>
    </div>
</div>

{{-- Stats Grid --}}
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="card p-6">
        <div class="text-sm text-[--color-ink-muted] mb-1">Today's Transactions</div>
        <div class="text-2xl font-bold text-[--color-ink]">{{ number_format($stats['total_transactions']) }}</div>
        <div class="text-xs text-[--color-ink-muted] mt-1">Buy: RM {{ number_format($stats['buy_volume'] ?? 0, 2) }} / Sell: RM {{ number_format($stats['sell_volume'] ?? 0, 2) }}</div>
    </div>
    <div class="card p-6">
        <div class="text-sm text-[--color-ink-muted] mb-1">Total Customers</div>
        <div class="text-2xl font-bold text-[--color-ink]">{{ number_format($stats['active_customers']) }}</div>
        <div class="text-xs text-[--color-ink-muted] mt-1">Active records</div>
    </div>
    <div class="card p-6">
        <div class="text-sm text-[--color-ink-muted] mb-1">Flagged Transactions</div>
        <div class="text-2xl font-bold text-[--color-accent]">{{ number_format($stats['flagged']) }}</div>
        <div class="text-xs text-[--color-ink-muted] mt-1">Open alerts</div>
    </div>
    <div class="card p-6">
        <div class="text-sm text-[--color-ink-muted] mb-1">Transaction Volume</div>
        <div class="text-2xl font-bold text-[--color-ink]">RM {{ number_format(($stats['buy_volume'] ?? 0) + ($stats['sell_volume'] ?? 0), 2) }}</div>
        <div class="text-xs text-[--color-ink-muted] mt-1">Today's total</div>
    </div>
</div>

{{-- Recent Transactions --}}
<div class="card">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-[--color-ink]">Recent Transactions</h2>
            <a href="{{ route('transactions.index') }}" class="text-sm text-[--color-accent] hover:underline">View all</a>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Customer</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recent_transactions as $transaction)
                <tr class="border-b border-[--color-border] hover:bg-[--color-canvas-subtle]/50">
                    <td class="font-mono text-xs text-[--color-ink]">{{ $transaction->reference ?? $transaction->id }}</td>
                    <td class="text-[--color-ink]">{{ $transaction->customer->full_name ?? 'N/A' }}</td>
                    <td class="text-[--color-ink]">
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded {{ $transaction->type === 'Buy' ? 'bg-blue-100 text-blue-700' : 'bg-orange-100 text-orange-700' }}">
                            {{ $transaction->type }}
                        </span>
                    </td>
                    <td class="text-[--color-ink] font-semibold">RM {{ number_format($transaction->amount_local ?? 0, 2) }}</td>
                    <td class="text-[--color-ink]">
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded
                            @if($transaction->status === 'Completed') bg-green-100 text-green-700
                            @elseif($transaction->status === 'Pending' || $transaction->status === 'PendingApproval' || $transaction->status === 'PendingCancellation') bg-yellow-100 text-yellow-700
                            @else bg-gray-100 text-gray-700
                            @endif">
                            {{ $transaction->status }}
                        </span>
                    </td>
                    <td class="text-[--color-ink-muted] text-xs">{{ $transaction->created_at->format('h:i A') }}</td>
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
@endsection