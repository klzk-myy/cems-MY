@extends('layouts.base')

@section('title', 'Customer: ' . ($customer->full_name ?? ''))

@section('header-title')
<div class="flex items-center gap-3">
    <a href="/customers" class="btn btn-ghost btn-icon">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
        </svg>
    </a>
    <div>
        <h1 class="text-xl font-semibold text-[--color-ink]">{{ $customer->full_name ?? 'N/A' }}</h1>
        <p class="text-sm text-[--color-ink-muted]">Customer since {{ $customer->created_at->format('M Y') }}</p>
    </div>
</div>
@endsection

@section('header-actions')
<div class="flex items-center gap-3">
    <a href="/customers/{{ $customer->id }}/edit" class="btn btn-secondary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
        </svg>
        Edit
    </a>
    <a href="/transactions/create?customer_id={{ $customer->id }}" class="btn btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        New Transaction
    </a>
</div>
@endsection

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Main Content --}}
    <div class="lg:col-span-2 space-y-6">
        {{-- Customer Details --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Customer Details</h3>
                <div class="flex gap-2">
                    @if($customer->sanction_hit ?? false)
                        <span class="badge badge-danger">Sanctioned</span>
                    @endif
                    @if($customer->pep_status ?? false)
                        <span class="badge badge-warning">PEP</span>
                    @endif
                </div>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <p class="text-sm text-[--color-ink-muted] mb-1">Full Name</p>
                        <p class="font-medium">{{ $customer->full_name ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-[--color-ink-muted] mb-1">ID Type</p>
                        <p class="font-mono font-medium">{{ $customer->id_type ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-[--color-ink-muted] mb-1">Email</p>
                        <p class="font-medium">{{ $customer->email ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-[--color-ink-muted] mb-1">Phone</p>
                        <p class="font-mono font-medium">{{ $customer->phone ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-[--color-ink-muted] mb-1">Address</p>
                        <p class="font-medium">{{ $customer->address ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-[--color-ink-muted] mb-1">Nationality</p>
                        <p class="font-medium">{{ $customer->nationality ?? 'N/A' }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Risk & Compliance --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Risk & Compliance</h3>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-3 gap-6">
                    <div>
                        <p class="text-sm text-[--color-ink-muted] mb-1">CDD Level</p>
                        @php
                            $cddClass = match($customer->cdd_level ?? '') {
                                'Simplified' => 'badge-info',
                                'Standard' => 'badge-warning',
                                'Enhanced' => 'badge-danger',
                                default => 'badge-default'
                            };
                        @endphp
                        <span class="badge {{ $cddClass }}">{{ $customer->cdd_level ?? 'N/A' }}</span>
                    </div>
                    <div>
                        <p class="text-sm text-[--color-ink-muted] mb-1">Risk Level</p>
                        @php
                            $riskClass = match($customer->risk_level ?? '') {
                                'Low' => 'badge-success',
                                'Medium' => 'badge-warning',
                                'High' => 'badge-danger',
                                'Critical' => 'badge-danger',
                                default => 'badge-default'
                            };
                        @endphp
                        <span class="badge {{ $riskClass }}">{{ $customer->risk_level ?? 'N/A' }}</span>
                    </div>
                    <div>
                        <p class="text-sm text-[--color-ink-muted] mb-1">Risk Score</p>
                        <p class="font-mono">{{ $customer->risk_score ?? 'N/A' }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Recent Transactions --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Recent Transactions</h3>
                <a href="/transactions?customer_id={{ $customer->id }}" class="btn btn-ghost btn-sm">View All</a>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Type</th>
                            <th>Currency</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($customer->transactions ?? [] as $tx)
                        <tr>
                            <td class="font-mono text-xs">#{{ $tx->id }}</td>
                            <td>
                                <span class="badge {{ $tx->type->value === 'Buy' ? 'badge-success' : 'badge-warning' }}">
                                    {{ $tx->type->label() }}
                                </span>
                            </td>
                            <td class="font-mono">{{ $tx->currency_code }}</td>
                            <td class="font-mono">{{ number_format($tx->amount_local, 2) }} MYR</td>
                            <td>
                                @php
                                    $statusClass = match($tx->status->value) {
                                        'Completed' => 'badge-success',
                                        'Pending' => 'badge-warning',
                                        'Cancelled' => 'badge-danger',
                                        default => 'badge-default'
                                    };
                                @endphp
                                <span class="badge {{ $statusClass }}">{{ $tx->status->label() }}</span>
                            </td>
                            <td class="text-[--color-ink-muted]">{{ $tx->created_at->format('d M Y') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center py-8 text-[--color-ink-muted]">No transactions found</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Sidebar --}}
    <div class="space-y-6">
        {{-- Quick Stats --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Summary</h3>
            </div>
            <div class="card-body space-y-4">
                <div>
                    <p class="text-sm text-[--color-ink-muted]">Total Transactions</p>
                    <p class="text-2xl font-semibold">{{ number_format($customer->transactions_count ?? 0) }}</p>
                </div>
                <div>
                    <p class="text-sm text-[--color-ink-muted]">Total Volume</p>
                    <p class="text-2xl font-semibold">{{ number_format($customer->total_volume ?? 0, 2) }} MYR</p>
                </div>
                <div>
                    <p class="text-sm text-[--color-ink-muted]">Last Transaction</p>
                    <p class="text-sm">{{ $customer->last_transaction_at?->diffForHumans() ?? 'Never' }}</p>
                </div>
            </div>
        </div>

        {{-- Documents --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Documents</h3>
                <a href="/customers/{{ $customer->id }}/kyc" class="btn btn-ghost btn-sm">Manage</a>
            </div>
            <div class="card-body">
                @forelse($customer->documents ?? [] as $doc)
                <div class="flex items-center gap-3 p-2 bg-[--color-canvas-subtle] rounded-lg mb-2">
                    <svg class="w-5 h-5 text-[--color-ink-muted]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <span class="text-sm">{{ $doc->type }}</span>
                </div>
                @empty
                <p class="text-sm text-[--color-ink-muted] text-center py-4">No documents uploaded</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
