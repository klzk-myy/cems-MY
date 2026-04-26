@extends('layouts.base')

@section('title', 'Quarterly LVR Report')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-gray-900">Quarterly Large Value Report</h1>
    <p class="text-sm text-gray-500">{{ $quarter ?? 'Q' . ceil(now()->format('n') / 3) . ' ' . now()->year }}</p>
</div>
@endsection

@section('header-actions')
<form wire:submit="loadReport" class="flex items-center gap-2">
    <select wire:model="selectedQuarter" class="form-input">
        @for($y = now()->year; $y >= now()->year - 2; $y--)
            @for($q = 4; $q >= 1; $q--)
                <option value="{{ $y }}-Q{{ $q }}">{{ $y }} Q{{ $q }}</option>
            @endfor
        @endfor
    </select>
    <button type="submit" class="btn btn-secondary">View</button>
</form>
@endsection

@section('content')
@if($reportData)
<div class="card mb-6">
    <div class="card-header">
        <h3 class="card-title">Report Summary - {{ $reportData['quarter'] ?? 'N/A' }}</h3>
        <span class="text-sm text-gray-500">{{ $reportData['period_start'] ?? '' }} to {{ $reportData['period_end'] ?? '' }}</span>
    </div>
    <div class="card-body">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            <div>
                <p class="text-sm text-gray-500">Total Transactions</p>
                <p class="text-2xl font-semibold">{{ number_format($reportData['total_transactions'] ?? 0) }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Total Amount</p>
                <p class="text-2xl font-semibold">{{ number_format($reportData['total_amount'] ?? 0, 2) }} MYR</p>
            </div>
        </div>
    </div>
</div>

<div class="card mb-6">
    <div class="card-header"><h3 class="card-title">Monthly Breakdown</h3></div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Month</th>
                    <th class="text-right">Count</th>
                    <th class="text-right">Total Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reportData['monthly_breakdown'] ?? [] as $month)
                <tr>
                    <td>{{ $month['month'] ?? 'N/A' }}</td>
                    <td class="font-mono text-right">{{ number_format($month['count'] ?? 0) }}</td>
                    <td class="font-mono text-right">{{ number_format($month['total_amount'] ?? 0, 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="3" class="text-center py-8 text-gray-500">No data</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card mb-6">
    <div class="card-header"><h3 class="card-title">By Currency</h3></div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Currency</th>
                    <th class="text-right">Count</th>
                    <th class="text-right">Total Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reportData['by_currency'] ?? [] as $currency)
                <tr>
                    <td class="font-mono font-medium">{{ $currency['currency'] ?? 'N/A' }}</td>
                    <td class="font-mono text-right">{{ number_format($currency['count'] ?? 0) }}</td>
                    <td class="font-mono text-right">{{ number_format($currency['total_amount'] ?? 0, 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="3" class="text-center py-8 text-gray-500">No data</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title">Transaction Details</h3></div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Transaction ID</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th class="text-right">Amount (MYR)</th>
                    <th>Currency</th>
                    <th>Type</th>
                </tr>
            </thead>
            <tbody>
                @forelse(array_slice($reportData['data'] ?? [], 0, 50) as $tx)
                <tr>
                    <td class="font-mono text-sm">{{ $tx['Transaction_ID'] ?? 'N/A' }}</td>
                    <td>{{ $tx['Date'] ?? 'N/A' }}</td>
                    <td>{{ $tx['Customer_Name'] ?? 'N/A' }}</td>
                    <td class="font-mono text-right">{{ number_format($tx['Amount_Local'] ?? 0, 2) }}</td>
                    <td>{{ $tx['Currency'] ?? 'N/A' }}</td>
                    <td>{{ $tx['Transaction_Type'] ?? 'N/A' }}</td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center py-8 text-gray-500">No data</td></tr>
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
            <p class="empty-state-title">Select a Quarter</p>
            <p class="empty-state-description">Choose a quarter above to view the Quarterly LVR report</p>
        </div>
    </div>
</div>
@endif
@endsection