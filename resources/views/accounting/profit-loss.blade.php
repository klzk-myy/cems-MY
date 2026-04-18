@extends('layouts.base')

@section('title', 'Profit & Loss')

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Profit & Loss Statement</h3>
        <span class="text-sm text-[--color-ink-muted]">{{ $fromDate ?? '' }} - {{ $toDate ?? '' }}</span>
    </div>
    <div class="card-body">
        <div class="grid grid-cols-2 gap-8">
            <div>
                <h4 class="font-semibold mb-4">Revenue</h4>
                @forelse($pl['revenues'] ?? [] as $item)
                <div class="flex justify-between py-2 border-b border-[--color-border]">
                    <span>{{ $item['account_name'] }}</span>
                    <span class="font-mono">{{ number_format($item['amount'], 2) }}</span>
                </div>
                @empty
                <p class="text-[--color-ink-muted]">No revenue</p>
                @endforelse
                <div class="flex justify-between py-2 font-semibold">
                    <span>Total Revenue</span>
                    <span class="font-mono">{{ number_format($pl['total_revenue'] ?? 0, 2) }}</span>
                </div>
            </div>
            <div>
                <h4 class="font-semibold mb-4">Expenses</h4>
                @forelse($pl['expenses'] ?? [] as $item)
                <div class="flex justify-between py-2 border-b border-[--color-border]">
                    <span>{{ $item['account_name'] }}</span>
                    <span class="font-mono">{{ number_format($item['amount'], 2) }}</span>
                </div>
                @empty
                <p class="text-[--color-ink-muted]">No expenses</p>
                @endforelse
                <div class="flex justify-between py-2 font-semibold">
                    <span>Total Expenses</span>
                    <span class="font-mono">{{ number_format($pl['total_expenses'] ?? 0, 2) }}</span>
                </div>
            </div>
        </div>
        <div class="mt-8 pt-4 border-t-2 border-[--color-ink]">
            <div class="flex justify-between text-lg font-bold">
                <span>Net Profit / (Loss)</span>
                <span class="font-mono {{ ($pl['net_profit'] ?? 0) >= 0 ? 'text-[--color-success]' : 'text-[--color-danger]' }}">
                    {{ number_format($pl['net_profit'] ?? 0, 2) }} MYR
                </span>
            </div>
        </div>
    </div>
</div>
@endsection
