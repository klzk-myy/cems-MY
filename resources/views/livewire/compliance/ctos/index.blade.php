@extends('layouts.base')

@section('title', 'CTOS Reports')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">CTOS Reports</h1>
    <p class="text-sm text-[--color-ink-muted]">Cash Transaction Reports to BNM</p>
</div>
@endsection

@section('header-actions')
<div class="flex items-center gap-3">
    <a href="{{ route('compliance.reporting.deadlines') }}" class="btn btn-secondary">
        Deadlines
    </a>
</div>
@endsection

@section('content')
{{-- Summary Stats --}}
<div class="grid grid-cols-5 gap-4 mb-6">
    <div class="stat-card">
        <p class="stat-card-label">Total</p>
        <p class="stat-card-value">{{ number_format($summary['total'] ?? 0) }}</p>
    </div>
    <div class="stat-card">
        <p class="stat-card-label">Draft</p>
        <p class="stat-card-value">{{ number_format($summary['draft'] ?? 0) }}</p>
    </div>
    <div class="stat-card">
        <p class="stat-card-label">Submitted</p>
        <p class="stat-card-value text-[--color-info]">{{ number_format($summary['submitted'] ?? 0) }}</p>
    </div>
    <div class="stat-card">
        <p class="stat-card-label">Acknowledged</p>
        <p class="stat-card-value text-[--color-success]">{{ number_format($summary['acknowledged'] ?? 0) }}</p>
    </div>
    <div class="stat-card">
        <p class="stat-card-label">Rejected</p>
        <p class="stat-card-value text-[--color-danger]">{{ number_format($summary['rejected'] ?? 0) }}</p>
    </div>
</div>

{{-- Filters --}}
<div class="card mb-6">
    <div class="card-body">
        <form wire:submit="applyFilters" class="flex gap-4 items-end flex-wrap">
            <div>
                <label class="form-label">Status</label>
                <select wire:model="status" class="form-select">
                    <option value="">All</option>
                    @foreach($ctosStatuses as $status)
                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">From Date</label>
                <input type="date" wire:model="from_date" class="form-input">
            </div>
            <div>
                <label class="form-label">To Date</label>
                <input type="date" wire:model="to_date" class="form-input">
            </div>
            <div class="flex gap-2">
                <button type="button" wire:click="clearFilters" class="btn btn-ghost">Clear</button>
                <button type="submit" class="btn btn-primary">Filter</button>
            </div>
        </form>
    </div>
</div>

{{-- Reports Table --}}
<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>CTOS Number</th>
                    <th>Customer</th>
                    <th>Amount</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reports as $report)
                <tr>
                    <td class="font-mono">{{ $report->ctos_number ?? 'N/A' }}</td>
                    <td>{{ $report->customer_name ?? 'N/A' }}</td>
                    <td class="font-mono">RM {{ number_format($report->amount_local ?? 0, 2) }}</td>
                    <td>
                        @if(($report->transaction_type ?? '') === 'Buy')
                            <span class="badge badge-success">Buy</span>
                        @else
                            <span class="badge badge-info">Sell</span>
                        @endif
                    </td>
                    <td>
                        @php
                            $statusClass = match($report->status->value ?? 'draft') {
                                'draft' => 'badge-default',
                                'submitted' => 'badge-info',
                                'acknowledged' => 'badge-success',
                                'rejected' => 'badge-danger',
                                default => 'badge-default'
                            };
                        @endphp
                        <span class="badge {{ $statusClass }}">{{ $report->status->label() ?? 'Draft' }}</span>
                    </td>
                    <td>{{ $report->report_date ? $report->report_date->format('d M Y') : 'N/A' }}</td>
                    <td>
                        <div class="table-actions">
                            <a href="{{ route('compliance.ctos.show', $report->id) }}" class="btn btn-ghost btn-icon" title="View">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </a>
                            @if($report->isDraft())
                                <button wire:click="submitToBnm({{ $report->id }})" class="btn btn-ghost btn-icon" title="Submit to BNM">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 19l-7-7 7-7m0 19l7-7-7-7"></path>
                                    </svg>
                                </button>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7">
                        <div class="empty-state py-12">
                            <p class="empty-state-title">No CTOS reports found</p>
                            <p class="empty-state-description">CTOS reports will appear here when transactions meet reporting thresholds</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($reports->hasPages())
        <div class="card-footer flex justify-between items-center">
            <p class="text-sm text-[--color-ink-muted]">
                Page {{ $reports->currentPage() }} of {{ $reports->lastPage() }}
            </p>
            <div class="flex gap-2">
                @if($reports->currentPage() > 1)
                    <a href="{{ $reports->previousPageUrl() }}" class="btn btn-ghost btn-sm">Previous</a>
                @endif
                @if($reports->currentPage() < $reports->lastPage())
                    <a href="{{ $reports->nextPageUrl() }}" class="btn btn-ghost btn-sm">Next</a>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection