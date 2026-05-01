@extends('layouts.base')

@section('title', 'Transactions - CEMS-MY')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold text-[--color-ink]">Transactions</h1>
        <p class="text-sm text-[--color-ink-muted] mt-1">Manage foreign currency transactions</p>
    </div>
    <a href="{{ route('transactions.create') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        New Transaction
    </a>
</div>

<div class="card">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">All Transactions</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Type</th>
                    <th>MYR</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions as $transaction)
                <tr class="border-b border-[--color-border] hover:bg-[--color-canvas-subtle]/50">
                    <td class="font-mono text-xs text-[--color-ink]">{{ $transaction->reference ?? $transaction->id }}</td>
                    <td class="text-[--color-ink-muted]">{{ $transaction->created_at->format('d M Y') }}</td>
                    <td class="text-[--color-ink]">{{ $transaction->customer->full_name ?? 'N/A' }}</td>
                    <td class="text-[--color-ink]">
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded {{ $transaction->type->value === 'Buy' ? 'bg-blue-100 text-blue-700' : 'bg-orange-100 text-orange-700' }}">
                            {{ strtoupper($transaction->type->value) }}
                        </span>
                    </td>
                    <td class="text-[--color-ink] font-semibold">RM {{ number_format($transaction->amount_local ?? 0, 2) }}</td>
                    <td class="text-[--color-ink]">
                        @if($transaction->status->value === 'PendingApproval')
                            <a href="{{ route('transactions.confirm.show', $transaction) }}" class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700 hover:bg-yellow-200">
                                {{ $transaction->status->label() }}
                            </a>
                        @else
                            <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded
                                @if($transaction->status->isCompleted()) bg-green-100 text-green-700
                                @elseif($transaction->status->isPending()) bg-yellow-100 text-yellow-700
                                @else bg-gray-100 text-gray-700
                                @endif">
                                {{ $transaction->status->label() }}
                            </span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-[--color-ink-muted]">No transactions found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($transactions->hasPages())
    <div class="px-6 py-4 border-t border-[--color-border]">
        {{ $transactions->links() }}
    </div>
    @endif
</div>
@endsection