@extends('layouts.base')

@section('title', 'MSB2 Report')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-gray-900">MSB2 Report</h1>
    <p class="text-sm text-gray-500">Daily Transaction Summary for Bank Negara Malaysia</p>
</div>
@endsection

@section('header-actions')
<div class="flex items-center gap-3">
    <form wire:submit="loadReport" class="flex items-center gap-2">
        <input type="date" wire:model="selectedDate" class="form-input" value="{{ $selectedDate }}">
        <button type="submit" class="btn btn-secondary">View</button>
    </form>
    @if($report)
        <a href="/reports/msb2/export?date={{ $selectedDate }}" class="btn btn-primary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
            </svg>
            Export PDF
        </a>
    @endif
</div>
@endsection

@section('content')
@if($report)
<div class="card mb-6">
    <div class="card-header">
        <h3 class="card-title">Report Summary - {{ \Carbon\Carbon::parse($report['date'])->format('d M Y') }}</h3>
    </div>
    <div class="card-body">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            <div>
                <p class="text-sm text-gray-500">Total Transactions</p>
                <p class="text-2xl font-semibold">{{ number_format($report['total_transactions']) }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Buy Volume</p>
                <p class="text-2xl font-semibold">{{ number_format($report['buy_volume'], 2) }} MYR</p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Sell Volume</p>
                <p class="text-2xl font-semibold">{{ number_format($report['sell_volume'], 2) }} MYR</p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Net Volume</p>
                <p class="text-2xl font-semibold">{{ number_format(bcsub($report['buy_volume'], $report['sell_volume'], 2), 2) }} MYR</p>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title">Transaction Details</h3></div>
    <div class="table-container">
        <table class="table">
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
                @forelse($report['currencies'] as $currency)
                <tr>
                    <td class="font-mono font-medium">{{ $currency['code'] ?? 'N/A' }}</td>
                    <td class="font-mono text-right">{{ number_format($currency['buy_amount'] ?? 0, 2) }}</td>
                    <td class="font-mono text-right">{{ number_format($currency['buy_count'] ?? 0) }}</td>
                    <td class="font-mono text-right">{{ number_format($currency['sell_amount'] ?? 0, 2) }}</td>
                    <td class="font-mono text-right">{{ number_format($currency['sell_count'] ?? 0) }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center py-8 text-gray-500">No data</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@else
<div class="card">
    <div class="card-body">
        <div class="empty-state py-16">
            <div class="empty-state-icon">
                <svg class="w-12 h-12 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
            </div>
            <p class="empty-state-title">Select a Date</p>
            <p class="empty-state-description">Choose a date above to view the MSB2 report</p>
        </div>
    </div>
</div>
@endif
@endsection