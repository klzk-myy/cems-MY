@extends('layouts.base')

@section('title', 'MSB2 Report')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">MSB2 Report</h1>
    <p class="text-sm text-[--color-ink-muted]">Daily Transaction Summary for Bank Negara Malaysia</p>
</div>
@endsection

@section('header-actions')
<div class="flex items-center gap-3">
    <form method="GET" class="flex items-center gap-2">
        <input type="date" name="date" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" value="{{ request('date', date('Y-m-d')) }}">
        <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[--color-border] hover:bg-[--color-canvas-subtle]">View</button>
    </form>
    @if(isset($report))
        <a href="/reports/msb2/export?date={{ request('date', date('Y-m-d')) }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-[--color-primary] text-white hover:bg-[--color-ink]">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
            </svg>
            Export PDF
        </a>
    @endif
</div>
@endsection

@section('content')
@if(isset($report) && $report)
<div class="bg-white border border-[--color-border] rounded-xl mb-6">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">Report Summary - {{ $report['date'] ?? date('d M Y') }}</h3>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            <div>
                <p class="text-sm text-[--color-ink-muted]">Total Transactions</p>
                <p class="text-2xl font-semibold">{{ number_format($report['total_transactions'] ?? 0) }}</p>
            </div>
            <div>
                <p class="text-sm text-[--color-ink-muted]">Buy Volume</p>
                <p class="text-2xl font-semibold">{{ number_format($report['buy_volume'] ?? 0, 2) }} MYR</p>
            </div>
            <div>
                <p class="text-sm text-[--color-ink-muted]">Sell Volume</p>
                <p class="text-2xl font-semibold">{{ number_format($report['sell_volume'] ?? 0, 2) }} MYR</p>
            </div>
            <div>
                <p class="text-sm text-[--color-ink-muted]">Net Volume</p>
                <p class="text-2xl font-semibold">{{ number_format(($report['buy_volume'] ?? 0) - ($report['sell_volume'] ?? 0), 2) }} MYR</p>
            </div>
        </div>
    </div>
</div>

<div class="bg-white border border-[--color-border] rounded-xl">
    <div class="px-6 py-4 border-b border-[--color-border]"><h3 class="text-base font-semibold text-[--color-ink]">Transaction Details</h3></div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr>
                    <th>Currency</th>
                    <th class="text-right">Buy Amount</th>
                    <th class="text-right">Buy Count</th>
                    <th class="text-right">Sell Amount</th>
                    <th class="text-right">Sell Count</th>
                </tr>
            </thead>
            <tbody>
                @forelse($report['currencies'] ?? [] as $currency)
                <tr>
                    <td class="font-mono font-medium">{{ $currency['code'] }}</td>
                    <td class="font-mono text-right">{{ number_format($currency['buy_amount'], 2) }}</td>
                    <td class="font-mono text-right">{{ number_format($currency['buy_count']) }}</td>
                    <td class="font-mono text-right">{{ number_format($currency['sell_amount'], 2) }}</td>
                    <td class="font-mono text-right">{{ number_format($currency['sell_count']) }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center py-8 text-[--color-ink-muted]">No data</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@else
<div class="bg-white border border-[--color-border] rounded-xl">
    <div class="p-6">
        <div class="py-16 text-center">
            <div>
                <svg class="w-12 h-12 text-[--color-ink-muted] mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
            </div>
            <p class="mt-4 text-sm font-medium text-[--color-ink]">Select a Date</p>
            <p class="mt-1 text-sm text-[--color-ink-muted]">Choose a date above to view the MSB2 report</p>
        </div>
    </div>
</div>
@endif
@endsection