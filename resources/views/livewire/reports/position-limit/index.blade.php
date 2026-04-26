@extends('layouts.base')

@section('title', 'Position Limits')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">Currency Position Limits</h1>
    <p class="text-sm text-[--color-ink-muted]">Monitor currency exposure against configured limits</p>
</div>
@endsection

@section('header-actions')
<button wire:click="loadReport" class="btn btn-secondary">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
    </svg>
    Refresh
</button>
@endsection

@section('content')
@if($reportData)
<div class="card mb-6">
    <div class="card-header">
        <h3 class="card-title">Position Summary</h3>
    </div>
    <div class="card-body">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            <div>
                <p class="text-sm text-[--color-ink-muted]">Total Currencies</p>
                <p class="text-2xl font-semibold">{{ number_format($reportData['summary']['total_currencies'] ?? 0) }}</p>
            </div>
            <div>
                <p class="text-sm text-[--color-ink-muted]">Total Exposure</p>
                <p class="text-2xl font-semibold">{{ number_format($reportData['total_exposure_myr'] ?? 0, 2) }} MYR</p>
            </div>
            <div>
                <p class="text-sm text-[--color-ink-muted]">At Warning</p>
                <p class="text-2xl font-semibold text-yellow-600">{{ number_format($reportData['summary']['currencies_at_warning'] ?? 0) }}</p>
            </div>
            <div>
                <p class="text-sm text-[--color-ink-muted]">At Critical</p>
                <p class="text-2xl font-semibold text-red-600">{{ number_format($reportData['summary']['currencies_at_critical'] ?? 0) }}</p>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3 class="card-title">Currency Positions</h3></div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Currency</th>
                    <th class="text-right">Current Position</th>
                    <th class="text-right">Limit</th>
                    <th class="text-right">Utilization</th>
                    <th class="text-right">Exposure (MYR)</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reportData['positions'] ?? [] as $data)
                <tr>
                    <td class="font-mono font-medium">{{ $data['currency_code'] ?? 'N/A' }}</td>
                    <td class="font-mono text-right">{{ number_format($data['current_balance'] ?? 0, 2) }}</td>
                    <td class="font-mono text-right">{{ number_format($data['position_limit'] ?? 0, 2) }}</td>
                    <td class="font-mono text-right">{{ number_format($data['utilization_percent'] ?? 0, 1) }}%</td>
                    <td class="font-mono text-right">{{ number_format($data['exposure_myr'] ?? 0, 2) }}</td>
                    <td>
                        @if(($data['status'] ?? '') === 'Critical')
                            <span class="badge badge-danger">Critical</span>
                        @elseif(($data['status'] ?? '') === 'Warning')
                            <span class="badge badge-warning">Warning</span>
                        @else
                            <span class="badge badge-success">OK</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center py-8 text-[--color-ink-muted]">No data</td></tr>
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
            <p class="empty-state-title">No Position Data</p>
            <p class="empty-state-description">Unable to load position limit data</p>
        </div>
    </div>
</div>
@endif
@endsection