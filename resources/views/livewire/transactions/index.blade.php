@extends('layouts.base')

@section('title', 'Transactions')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-gray-900">Transactions</h1>
    <p class="text-sm text-gray-500">Manage currency exchange transactions</p>
</div>
@endsection

@section('header-actions')
<div class="flex items-center gap-3">
    <a href="{{ route('transactions.create') }}" class="btn btn-primary">
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
        <form wire:submit="applyFilters" class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div class="form-group mb-0">
                <label class="form-label">Search</label>
                <input type="text" wire:model="search" class="form-input" placeholder="Transaction ID or customer...">
            </div>
            <div class="form-group mb-0">
                <label class="form-label">Type</label>
                <select wire:model="type" class="form-select">
                    <option value="">All Types</option>
                    @foreach($transactionTypes as $t)
                        <option value="{{ $t->value }}">{{ $t->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group mb-0">
                <label class="form-label">Status</label>
                <select wire:model="status" class="form-select">
                    <option value="">All Status</option>
                    @foreach($transactionStatuses as $s)
                        <option value="{{ $s->value }}">{{ $s->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group mb-0">
                <label class="form-label">Date From</label>
                <input type="date" wire:model="dateFrom" class="form-input">
            </div>
            <div class="form-group mb-0">
                <label class="form-label">Date To</label>
                <input type="date" wire:model="dateTo" class="form-input">
            </div>
            <div class="md:col-span-5 flex justify-end gap-3">
                <button type="button" wire:click="clearFilters" class="btn btn-ghost">Clear Filters</button>
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
                @forelse($transactions as $tx)
                <tr>
                    <td class="font-mono text-xs">#{{ $tx->id }}</td>
                    <td>
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center">
                                <span class="text-xs font-medium">{{ substr($tx->customer->full_name ?? 'N/A', 0, 1) }}</span>
                            </div>
                            <span class="font-medium">{{ $tx->customer->full_name ?? 'N/A' }}</span>
                        </div>
                    </td>
                    <td>
                        <span class="badge {{ $tx->type === App\Enums\TransactionType::Buy ? 'badge-success' : 'badge-warning' }}">
                            {{ $tx->type->label() }}
                        </span>
                    </td>
                    <td class="font-mono">{{ $tx->currency_code }}</td>
                    <td class="font-mono">{{ number_format((float) $tx->amount_foreign, 2) }} {{ $tx->currency_code }}</td>
                    <td class="font-mono">{{ $tx->rate }}</td>
                    <td class="font-mono">{{ number_format((float) $tx->amount_local, 2) }} MYR</td>
                    <td>
                        @php
                            $statusClass = match($tx->status->value) {
                                'Completed' => 'badge-success',
                                'PendingApproval' => 'badge-warning',
                                'PendingCancellation' => 'badge-warning',
                                'Cancelled' => 'badge-danger',
                                'Failed' => 'badge-danger',
                                'Rejected' => 'badge-danger',
                                'Reversed' => 'badge-danger',
                                'Draft' => 'badge-default',
                                'Approved' => 'badge-info',
                                'Processing' => 'badge-primary',
                                'Finalized' => 'badge-success',
                                'Pending' => 'badge-warning',
                                'OnHold' => 'badge-warning',
                                default => 'badge-default'
                            };
                        @endphp
                        <span class="badge {{ $statusClass }}">{{ $tx->status->label() }}</span>
                    </td>
                    <td class="text-gray-500">{{ $tx->created_at->format('d M Y, H:i') }}</td>
                    <td>
                        <div class="table-actions">
                            <a href="{{ route('transactions.show', $tx->id) }}" class="btn btn-ghost btn-icon" title="View">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </a>
                            @if($tx->status->value === 'PendingApproval')
                                <a href="{{ route('transactions.confirm.show', $tx->id) }}" class="btn btn-ghost btn-icon" title="Confirm">
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
                                <svg class="w-8 h-8 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                </svg>
                            </div>
                            <p class="empty-state-title">No transactions found</p>
                            <p class="empty-state-description">Try adjusting your filters or create a new transaction</p>
                            <a href="{{ route('transactions.create') }}" class="btn btn-primary mt-4">Create Transaction</a>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($transactions->hasPages())
        <div class="card-footer">
            {{ $transactions->withQueryString()->links() }}
        </div>
    @endif
</div>
@endsection
