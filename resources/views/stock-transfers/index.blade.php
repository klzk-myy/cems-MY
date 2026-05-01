@extends('layouts.base')

@section('title', 'Stock Transfers - CEMS-MY')

@section('content')
<div class="mb-6">
    <div>
        <h1 class="text-2xl font-semibold text-[--color-ink]">Stock Transfers</h1>
        <p class="text-sm text-[--color-ink-muted] mt-1">Inter-branch currency transfers</p>
    </div>
    <a href="{{ route('stock-transfers.create') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        New Transfer
    </a>
</div>

<div class="card">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">All Transfers</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Amount</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transfers ?? [] as $transfer)
                <tr class="border-b border-[--color-border] hover:bg-[--color-canvas-subtle]/50">
                    <td class="text-[--color-ink] font-mono text-xs">{{ $transfer->id }}</td>
                    <td class="text-[--color-ink]">{{ $transfer->from_branch ?? 'N/A' }}</td>
                    <td class="text-[--color-ink]">{{ $transfer->to_branch ?? 'N/A' }}</td>
                    <td class="text-[--color-ink] font-semibold">RM {{ number_format($transfer->amount_myr ?? 0, 2) }}</td>
                    <td class="text-[--color-ink]">
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded
                            @if($transfer->status === 'completed') bg-green-100 text-green-700
                            @elseif($transfer->status === 'pending') bg-yellow-100 text-yellow-700
                            @else bg-gray-100 text-gray-700
                            @endif">
                            {{ ucfirst($transfer->status ?? 'pending') }}
                        </span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-[--color-ink-muted]">No transfers found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection