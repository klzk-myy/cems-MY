@extends('layouts.base')

@section('title', 'Cash Flow')

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Cash Flow Statement</h3>
        <span class="text-sm text-[--color-ink-muted]">{{ $fromDate ?? '' }} - {{ $toDate ?? '' }}</span>
    </div>
    <div class="card-body">
        <h4 class="font-semibold mb-4">Operating Activities</h4>
        @forelse($cashFlow['operating'] ?? [] as $item)
        <div class="flex justify-between py-2 border-b border-[--color-border]">
            <span>{{ $item['name'] }}</span>
            <span class="font-mono">{{ number_format($item['amount'], 2) }}</span>
        </div>
        @empty
        <p class="text-[--color-ink-muted]">No data</p>
        @endforelse

        <h4 class="font-semibold mb-4 mt-6">Investing Activities</h4>
        @forelse($cashFlow['investing'] ?? [] as $item)
        <div class="flex justify-between py-2 border-b border-[--color-border]">
            <span>{{ $item['name'] }}</span>
            <span class="font-mono">{{ number_format($item['amount'], 2) }}</span>
        </div>
        @empty
        <p class="text-[--color-ink-muted]">No data</p>
        @endforelse

        <h4 class="font-semibold mb-4 mt-6">Financing Activities</h4>
        @forelse($cashFlow['financing'] ?? [] as $item)
        <div class="flex justify-between py-2 border-b border-[--color-border]">
            <span>{{ $item['name'] }}</span>
            <span class="font-mono">{{ number_format($item['amount'], 2) }}</span>
        </div>
        @empty
        <p class="text-[--color-ink-muted]">No data</p>
        @endforelse

        <div class="flex justify-between text-lg font-bold mt-6 pt-4 border-t-2 border-[--color-ink]">
            <span>Net Cash Flow</span>
            <span class="font-mono">{{ number_format($cashFlow['net_cash_flow'] ?? 0, 2) }} MYR</span>
        </div>
    </div>
</div>
@endsection
