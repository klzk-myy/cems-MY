@extends('layouts.base')

@section('title', 'Stock Position - CEMS-MY')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-semibold text-[--color-ink]">Stock Position</h1>
        <p class="text-sm text-[--color-ink-muted] mt-1">Currency stock positions by branch</p>
    </div>
    <a href="{{ route('stock-cash.index') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[--color-border] hover:bg-[--color-canvas-subtle]">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
        </svg>
        Back
    </a>
</div>

<div class="card mb-6">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">Position Details</h3>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-2 gap-6">
            <div>
                <p class="text-sm text-[--color-ink-muted] mb-1">Currency</p>
                <p class="text-lg font-semibold text-[--color-ink]">{{ $position->currency_code ?? 'N/A' }}</p>
            </div>
            <div>
                <p class="text-sm text-[--color-ink-muted] mb-1">Balance</p>
                <p class="text-lg font-semibold text-[--color-ink]">{{ number_format($position->balance ?? 0, 2) }}</p>
            </div>
            <div>
                <p class="text-sm text-[--color-ink-muted] mb-1">Average Rate</p>
                <p class="text-lg font-semibold text-[--color-ink]">{{ number_format($position->avg_rate ?? 0, 4) }}</p>
            </div>
            <div>
                <p class="text-sm text-[--color-ink-muted] mb-1">MYR Value</p>
                <p class="text-lg font-semibold text-[--color-accent]">RM {{ number_format($position->myr_value ?? 0, 2) }}</p>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">Recent Transactions</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th class="text-right">Amount</th>
                    <th class="text-right">MYR Value</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions as $tx)
                <tr class="border-b border-[--color-border] hover:bg-[--color-canvas-subtle]/50">
                    <td class="text-[--color-ink-muted]">{{ $tx->created_at->format('d M Y H:i') }}</td>
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
                    <td colspan="4" class="px-4 py-8 text-center text-[--color-ink-muted]">No transactions found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection