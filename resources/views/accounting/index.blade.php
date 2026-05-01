@extends('layouts.base')

@section('title', 'Accounting - CEMS-MY')

@section('content')
<div class="mb-6">
    <div>
        <h1 class="text-2xl font-semibold text-[--color-ink]">Accounting</h1>
        <p class="text-sm text-[--color-ink-muted] mt-1">Double-entry bookkeeping and financial statements</p>
    </div>
    @role('manager')
    <a href="{{ route('accounting.journal') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        New Journal Entry
    </a>
    @endrole
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <div class="card p-6">
        <div class="text-sm text-[--color-ink-muted]">Total Assets</div>
        <div class="text-2xl font-bold text-[--color-ink] mt-1">RM {{ number_format($positions['total_myr'] ?? 0, 2) }}</div>
    </div>
    <div class="card p-6">
        <div class="text-sm text-[--color-ink-muted]">Total Liabilities</div>
        <div class="text-2xl font-bold text-[--color-ink] mt-1">RM {{ number_format(($totalPnl ?? 0) * 0.5, 2) }}</div>
    </div>
    <div class="card p-6">
        <div class="text-sm text-[--color-ink-muted]">Equity</div>
        <div class="text-2xl font-bold text-[--color-ink] mt-1">RM {{ number_format(($positions['total_myr'] ?? 0) - (($totalPnl ?? 0) * 0.5), 2) }}</div>
    </div>
</div>

<div class="card">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">Currency Positions</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>Currency</th>
                    <th>Balance</th>
                    <th>Avg Rate</th>
                    <th>MYR Value</th>
                    <th>P&L</th>
                </tr>
            </thead>
            <tbody>
                @forelse($positions as $code => $position)
                @if($code !== 'total_myr')
                <tr class="border-b border-[--color-border] hover:bg-[--color-canvas-subtle]/50">
                    <td class="text-[--color-ink] font-medium">{{ $code }}</td>
                    <td class="text-[--color-ink]">{{ number_format($position['balance'] ?? 0, 2) }}</td>
                    <td class="text-[--color-ink-muted]">{{ number_format($position['avg_rate'] ?? 0, 4) }}</td>
                    <td class="text-[--color-ink] font-semibold">RM {{ number_format($position['myr_value'] ?? 0, 2) }}</td>
                    <td class="text-[--color-ink] {{ ($position['pnl'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ ($position['pnl'] ?? 0) >= 0 ? '+' : '' }}{{ number_format($position['pnl'] ?? 0, 2) }}
                    </td>
                </tr>
                @endif
                @empty
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-[--color-ink-muted]">No positions found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection