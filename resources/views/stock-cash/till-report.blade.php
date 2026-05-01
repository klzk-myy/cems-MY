@extends('layouts.base')

@section('title', 'Till Report - CEMS-MY')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-semibold text-[--color-ink]">Till Report</h1>
        <p class="text-sm text-[--color-ink-muted] mt-1">Counter till closing report</p>
    </div>
    <a href="{{ route('stock-cash.index') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[--color-border] hover:bg-[--color-canvas-subtle]">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
        </svg>
        Back
    </a>
</div>

<div class="card">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">Till Balances - {{ $date }}</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>Till ID</th>
                    <th>Currency</th>
                    <th>Opened By</th>
                    <th class="text-right">Opening</th>
                    <th class="text-right">Closing</th>
                    <th>Closed By</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($balances as $balance)
                <tr class="border-b border-[--color-border] hover:bg-[--color-canvas-subtle]/50">
                    <td class="text-[--color-ink] font-mono">{{ $balance->till_id }}</td>
                    <td class="text-[--color-ink]">{{ $balance->currency_code }}</td>
                    <td class="text-[--color-ink]">{{ $balance->opener->username ?? 'N/A' }}</td>
                    <td class="text-[--color-ink] text-right font-mono">{{ number_format((float) ($balance->opening_balance ?? 0), 2) }}</td>
                    <td class="text-[--color-ink] text-right font-mono">
                        {{ $balance->closing_balance ? number_format((float) $balance->closing_balance, 2) : '-' }}
                    </td>
                    <td class="text-[--color-ink]">{{ $balance->closer->username ?? '-' }}</td>
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
                    <td colspan="7" class="px-4 py-8 text-center text-[--color-ink-muted]">No till balances found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection