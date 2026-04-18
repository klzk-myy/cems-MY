@extends('layouts.base')

@section('title', 'Stock Transfers')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">Stock Transfers</h1>
    <p class="text-sm text-[--color-ink-muted]">Inter-branch currency transfers</p>
</div>
@endsection

@section('header-actions')
<div class="flex items-center gap-3">
    <a href="/stock-transfers/create" class="btn btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        New Transfer
    </a>
</div>
@endsection

@section('content')
{{-- Filters --}}
<div class="card mb-6">
    <div class="card-body">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="form-group mb-0">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="Pending" {{ request('status') === 'Pending' ? 'selected' : '' }}>Pending</option>
                    <option value="Approved" {{ request('status') === 'Approved' ? 'selected' : '' }}>Approved</option>
                    <option value="InTransit" {{ request('status') === 'InTransit' ? 'selected' : '' }}>In Transit</option>
                    <option value="Completed" {{ request('status') === 'Completed' ? 'selected' : '' }}>Completed</option>
                    <option value="Rejected" {{ request('status') === 'Rejected' ? 'selected' : '' }}>Rejected</option>
                </select>
            </div>
            <div class="form-group mb-0">
                <label class="form-label">Date From</label>
                <input type="date" name="date_from" class="form-input" value="{{ request('date_from') }}">
            </div>
            <div class="form-group mb-0">
                <label class="form-label">Date To</label>
                <input type="date" name="date_to" class="form-input" value="{{ request('date_to') }}">
            </div>
            <div class="md:col-span-4 flex justify-end gap-3">
                <a href="/stock-transfers" class="btn btn-ghost">Clear</a>
                <button type="submit" class="btn btn-secondary">Apply Filters</button>
            </div>
        </form>
    </div>
</div>

{{-- Transfers Table --}}
<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Transfer ID</th>
                    <th>From Branch</th>
                    <th>To Branch</th>
                    <th>Currency</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Requested By</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transfers ?? [] as $transfer)
                <tr>
                    <td class="font-mono text-xs">#{{ $transfer->id }}</td>
                    <td>
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-[--color-ink-muted]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                            </svg>
                            <span class="text-sm">{{ $transfer->sourceBranch->name ?? 'N/A' }}</span>
                        </div>
                    </td>
                    <td>
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-[--color-success]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                            </svg>
                            <span class="text-sm">{{ $transfer->destinationBranch->name ?? 'N/A' }}</span>
                        </div>
                    </td>
                    <td class="font-mono">{{ $transfer->currency_code }}</td>
                    <td class="font-mono">{{ number_format($transfer->amount, 2) }}</td>
                    <td>
                        @php
                            $statusClass = match($transfer->status->value ?? '') {
                                'Pending' => 'badge-warning',
                                'Approved' => 'badge-info',
                                'InTransit' => 'badge-accent',
                                'Completed' => 'badge-success',
                                'Rejected' => 'badge-danger',
                                default => 'badge-default'
                            };
                        @endphp
                        <span class="badge {{ $statusClass }}">{{ $transfer->status->label() ?? 'Unknown' }}</span>
                    </td>
                    <td>
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 bg-[--color-canvas-subtle] rounded flex items-center justify-center text-xs">
                                {{ substr($transfer->requester->username ?? '?', 0, 1) }}
                            </div>
                            <span class="text-sm">{{ $transfer->requester->username ?? 'N/A' }}</span>
                        </div>
                    </td>
                    <td class="text-[--color-ink-muted]">{{ $transfer->created_at->format('d M Y') }}</td>
                    <td>
                        <div class="table-actions">
                            <a href="/stock-transfers/{{ $transfer->id }}" class="btn btn-ghost btn-icon" title="View">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </a>
                            @if($transfer->status->value === 'Pending' && auth()->user()->isManager())
                                <a href="/stock-transfers/{{ $transfer->id }}/approve" class="btn btn-ghost btn-icon" title="Approve">
                                    <svg class="w-4 h-4 text-[--color-success]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </a>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9">
                        <div class="empty-state py-12">
                            <div class="empty-state-icon">
                                <svg class="w-8 h-8 text-[--color-ink-muted]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                </svg>
                            </div>
                            <p class="empty-state-title">No transfers found</p>
                            <p class="empty-state-description">Create a stock transfer to move currency between branches</p>
                            <a href="/stock-transfers/create" class="btn btn-primary mt-4">New Transfer</a>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($transfers && $transfers->hasPages())
        <div class="card-footer">
            {{ $transfers->withQueryString()->links() }}
        </div>
    @endif
</div>
@endsection
