@extends('layouts.base')

@section('title', 'LMCA Report')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">Local Money Changing Activity Report</h1>
    <p class="text-sm text-[--color-ink-muted]">{{ $month ? \Carbon\Carbon::parse($month)->format('F Y') : '' }}</p>
</div>
@endsection

@section('header-actions')
<form wire:submit="loadReport" class="flex items-center gap-2">
    <input type="month" wire:model="selectedMonth" class="form-input">
    <button type="submit" class="btn btn-secondary">View</button>
</form>
@endsection

@section('content')
@if($reportData)
<div class="card mb-6">
    <div class="card-header">
        <h3 class="card-title">Report Summary</h3>
    </div>
    <div class="card-body">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            <div>
                <p class="text-sm text-[--color-ink-muted]">License Number</p>
                <p class="text-lg font-medium">{{ $reportData['license_number'] ?? 'N/A' }}</p>
            </div>
            <div>
                <p class="text-sm text-[--color-ink-muted]">Reporting Period</p>
                <p class="text-lg font-medium">{{ $reportData['reporting_period'] ?? 'N/A' }}</p>
            </div>
            <div>
                <p class="text-sm text-[--color-ink-muted]">Customer Count</p>
                <p class="text-2xl font-semibold">{{ number_format($reportData['customer_count'] ?? 0) }}</p>
            </div>
            <div>
                <p class="text-sm text-[--color-ink-muted]">Staff Count</p>
                <p class="text-2xl font-semibold">{{ number_format($reportData['staff_count'] ?? 0) }}</p>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title">Currency Breakdown</h3></div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Currency</th>
                    <th class="text-right">Buy Count</th>
                    <th class="text-right">Buy Volume</th>
                    <th class="text-right">Buy Value (MYR)</th>
                    <th class="text-right">Sell Count</th>
                    <th class="text-right">Sell Volume</th>
                    <th class="text-right">Sell Value (MYR)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reportData['currencies'] ?? [] as $currency)
                <tr>
                    <td class="font-mono font-medium">{{ $currency['currency_code'] ?? 'N/A' }}</td>
                    <td class="font-mono text-right">{{ number_format($currency['buy_count'] ?? 0) }}</td>
                    <td class="font-mono text-right">{{ number_format($currency['buy_volume'] ?? 0, 2) }}</td>
                    <td class="font-mono text-right">{{ number_format($currency['buy_value_myr'] ?? 0, 2) }}</td>
                    <td class="font-mono text-right">{{ number_format($currency['sell_count'] ?? 0) }}</td>
                    <td class="font-mono text-right">{{ number_format($currency['sell_volume'] ?? 0, 2) }}</td>
                    <td class="font-mono text-right">{{ number_format($currency['sell_value_myr'] ?? 0, 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center py-8 text-[--color-ink-muted]">No data</td></tr>
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
                <svg class="w-12 h-12 text-[--color-ink-muted]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
            </div>
            <p class="empty-state-title">Select a Month</p>
            <p class="empty-state-description">Choose a month above to view the LMCA report</p>
        </div>
    </div>
</div>
@endif
@endsection