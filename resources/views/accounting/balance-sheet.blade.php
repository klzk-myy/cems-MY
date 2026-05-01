@extends('layouts.base')

@section('title', 'Balance Sheet')

@section('content')
<div class="card">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">Balance Sheet</h3>
        <span class="text-sm text-[--color-ink-muted]">As of {{ $asOfDate ?? date('d M Y') }}</span>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-2 gap-8">
            <div>
                <h4 class="font-semibold mb-4">Assets</h4>
                @forelse($balanceSheet['assets'] ?? [] as $item)
                <div class="flex justify-between py-2 border-b border-[--color-border]">
                    <span>{{ $item['account_name'] ?? $item['name'] ?? 'N/A' }}</span>
                    <span class="font-mono">{{ number_format($item['balance'] ?? 0, 2) }}</span>
                </div>
                @empty
                <p class="text-[--color-ink-muted]">No assets</p>
                @endforelse
                <div class="flex justify-between py-2 font-semibold">
                    <span>Total Assets</span>
                    <span class="font-mono">{{ number_format($balanceSheet['total_assets'] ?? 0, 2) }}</span>
                </div>
            </div>
            <div>
                <h4 class="font-semibold mb-4">Liabilities & Equity</h4>
                @forelse($balanceSheet['liabilities'] ?? [] as $item)
                <div class="flex justify-between py-2 border-b border-[--color-border]">
                    <span>{{ $item['account_name'] ?? $item['name'] ?? 'N/A' }}</span>
                    <span class="font-mono">{{ number_format($item['balance'] ?? 0, 2) }}</span>
                </div>
                @empty
                <p class="text-[--color-ink-muted]">No liabilities</p>
                @endforelse
                @forelse($balanceSheet['equity'] ?? [] as $item)
                <div class="flex justify-between py-2 border-b border-[--color-border]">
                    <span>{{ $item['account_name'] ?? $item['name'] ?? 'N/A' }}</span>
                    <span class="font-mono">{{ number_format($item['balance'] ?? 0, 2) }}</span>
                </div>
                @empty
                @endforelse
                <div class="flex justify-between py-2 font-semibold">
                    <span>Total Liabilities & Equity</span>
                    <span class="font-mono">{{ number_format($balanceSheet['total_liabilities_equity'] ?? 0, 2) }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection