@extends('layouts.base')

@section('title', 'Transactions')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">Transactions</h1>
    <p class="text-sm text-[--color-ink-muted]">Manage currency exchange transactions</p>
</div>
@endsection

@section('header-actions')
<div class="flex items-center gap-3">
    <a href="/transactions/create" class="btn btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        New Transaction
    </a>
</div>
@endsection

@section('content')
{{-- Filters --}}
<div class="card mb-6">
    <div class="card-body">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div class="form-group mb-0">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-input" placeholder="Transaction ID or customer..." value="{{ request('search') }}">
            </div>
            <div class="form-group mb-0">
                <label class="form-label">Type</label>
                <select name="type" class="form-select">
                    <option value="">All Types</option>
                    <option value="Buy" {{ request('type') === 'Buy' ? 'selected' : '' }}>Buy</option>
                    <option value="Sell" {{ request('type') === 'Sell' ? 'selected' : '' }}>Sell</option>
                </select>
            </div>
            <div class="form-group mb-0">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="PendingApproval" {{ request('status') === 'PendingApproval' ? 'selected' : '' }}>Pending Approval</option>
                    <option value="Completed" {{ request('status') === 'Completed' ? 'selected' : '' }}>Completed</option>
                    <option value="Cancelled" {{ request('status') === 'Cancelled' ? 'selected' : '' }}>Cancelled</option>
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
            <div class="md:col-span-5 flex justify-end gap-3">
                <a href="/transactions" class="btn btn-ghost">Clear Filters</a>
                <button type="submit" class="btn btn-secondary">Apply Filters</button>
            </div>
        </form>
    </div>
</div>

{{-- Transactions Table --}}
<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Customer</th>
                    <th>Type</th>
                    <th>Currency</th>
                    <th>Amount</th>
                    <th>Rate</th>
                    <th>MYR Value</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions ?? [] as $tx)
                <tr>
                    <td class="font-mono text-xs">#{{ $tx->id }}</td>
                    <td>
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-[--color-canvas-subtle] rounded-lg flex items-center justify-center">
                                <span class="text-xs font-medium">{{ substr($tx->customer->full_name ?? 'N/A', 0, 1) }}</span>
                            </div>
                            <span class="font-medium">{{ $tx->customer->full_name ?? 'N/A' }}</span>
                        </div>
                    </td>
                    <td>
                        <span class="badge {{ $tx->type->value === 'Buy' ? 'badge-success' : 'badge-warning' }}">
                            {{ $tx->type->label() }}
                        </span>
                    </td>
                    <td class="font-mono">{{ $tx->currency_code }}</td>
                    <td class="font-mono">{{ number_format($tx->amount_foreign, 2) }} {{ $tx->currency_code }}</td>
                    <td class="font-mono">{{ $tx->rate }}</td>
                    <td class="font-mono">{{ number_format($tx->amount_local, 2) }} MYR</td>
                    <td>
                        @php
                            $statusClass = match($tx->status->value) {
                                'Completed' => 'badge-success',
                                'PendingApproval' => 'badge-warning',
                                'PendingCancellation' => 'badge-warning',
                                'Cancelled' => 'badge-danger',
                                default => 'badge-default'
                            };
                        @endphp
                        <span class="badge {{ $statusClass }}">{{ $tx->status->label() }}</span>
                    </td>
                    <td class="text-[--color-ink-muted]">{{ $tx->created_at->format('d M Y, H:i') }}</td>
                    <td>
                        <div class="table-actions">
                            <a href="/transactions/{{ $tx->id }}" class="btn btn-ghost btn-icon" title="View">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </a>
                            @if($tx->status->value === 'PendingApproval')
                                <a href="/transactions/{{ $tx->id }}/confirm" class="btn btn-ghost btn-icon" title="Confirm">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </a>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10">
                        <div class="empty-state py-12">
                            <div class="empty-state-icon">
                                <svg class="w-8 h-8 text-[--color-ink-muted]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                </svg>
                            </div>
                            <p class="empty-state-title">No transactions found</p>
                            <p class="empty-state-description">Try adjusting your filters or create a new transaction</p>
                            <a href="/transactions/create" class="btn btn-primary mt-4">Create Transaction</a>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($transactions && $transactions->hasPages())
        <div class="card-footer">
            {{ $transactions->withQueryString()->links() }}
        </div>
    @endif
</div>
@endsection
