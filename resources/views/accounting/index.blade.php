@extends('layouts.base')

@section('title', 'Accounting')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">Accounting</h1>
    <p class="text-sm text-[--color-ink-muted]">Financial management and reporting</p>
</div>
@endsection

@section('content')
{{-- Quick Links --}}
<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-8">
    <a href="/accounting/journal" class="card p-5 hover:shadow-md transition-shadow">
        <div class="w-10 h-10 bg-[--color-info]/10 rounded-lg flex items-center justify-center mb-3">
            <svg class="w-5 h-5 text-[--color-info]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
        </div>
        <p class="font-medium text-sm">Journal</p>
    </a>
    <a href="/accounting/ledger" class="card p-5 hover:shadow-md transition-shadow">
        <div class="w-10 h-10 bg-[--color-success]/10 rounded-lg flex items-center justify-center mb-3">
            <svg class="w-5 h-5 text-[--color-success]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
            </svg>
        </div>
        <p class="font-medium text-sm">Ledger</p>
    </a>
    <a href="/accounting/trial-balance" class="card p-5 hover:shadow-md transition-shadow">
        <div class="w-10 h-10 bg-[--color-warning]/10 rounded-lg flex items-center justify-center mb-3">
            <svg class="w-5 h-5 text-[--color-warning]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"></path>
            </svg>
        </div>
        <p class="font-medium text-sm">Trial Balance</p>
    </a>
    <a href="/accounting/profit-loss" class="card p-5 hover:shadow-md transition-shadow">
        <div class="w-10 h-10 bg-[--color-accent]/10 rounded-lg flex items-center justify-center mb-3">
            <svg class="w-5 h-5 text-[--color-accent]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
            </svg>
        </div>
        <p class="font-medium text-sm">Profit & Loss</p>
    </a>
    <a href="/accounting/balance-sheet" class="card p-5 hover:shadow-md transition-shadow">
        <div class="w-10 h-10 bg-[--color-primary]/10 rounded-lg flex items-center justify-center mb-3">
            <svg class="w-5 h-5 text-[--color-primary]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
            </svg>
        </div>
        <p class="font-medium text-sm">Balance Sheet</p>
    </a>
    <a href="/accounting/cash-flow" class="card p-5 hover:shadow-md transition-shadow">
        <div class="w-10 h-10 bg-[--color-info]/10 rounded-lg flex items-center justify-center mb-3">
            <svg class="w-5 h-5 text-[--color-info]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
        <p class="font-medium text-sm">Cash Flow</p>
    </a>
</div>

{{-- Financial Summary --}}
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon bg-[--color-success]/10 text-[--color-success]">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
        <p class="stat-card-label">Total Assets</p>
        <p class="stat-card-value">{{ number_format($summary['total_assets'] ?? 0, 2) }}</p>
        <p class="stat-card-change text-[--color-ink-muted]">MYR</p>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon bg-[--color-danger]/10 text-[--color-danger]">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
            </div>
        </div>
        <p class="stat-card-label">Total Liabilities</p>
        <p class="stat-card-value">{{ number_format($summary['total_liabilities'] ?? 0, 2) }}</p>
        <p class="stat-card-change text-[--color-ink-muted]">MYR</p>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon bg-[--color-info]/10 text-[--color-info]">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                </svg>
            </div>
        </div>
        <p class="stat-card-label">Revenue (YTD)</p>
        <p class="stat-card-value">{{ number_format($summary['revenue'] ?? 0, 2) }}</p>
        <p class="stat-card-change positive">+{{ number_format($summary['revenue_change'] ?? 0, 1) }}%</p>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon bg-[--color-warning]/10 text-[--color-warning]">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path>
                </svg>
            </div>
        </div>
        <p class="stat-card-label">Expenses (YTD)</p>
        <p class="stat-card-value">{{ number_format($summary['expenses'] ?? 0, 2) }}</p>
        <p class="stat-card-change text-[--color-ink-muted]">MYR</p>
    </div>
</div>

{{-- Recent Journal Entries --}}
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Recent Journal Entries</h3>
        <a href="/accounting/journal" class="btn btn-ghost btn-sm">View All</a>
    </div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Entry No.</th>
                    <th>Description</th>
                    <th>Accounts</th>
                    <th>Amount</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recent_entries ?? [] as $entry)
                <tr>
                    <td>{{ $entry->date->format('d M Y') }}</td>
                    <td class="font-mono text-xs">JE-{{ str_pad($entry->id, 6, '0', STR_PAD_LEFT) }}</td>
                    <td>{{ $entry->description }}</td>
                    <td class="text-[--color-ink-muted]">{{ $entry->lines->count() }} accounts</td>
                    <td class="font-mono">{{ number_format($entry->total_debit, 2) }} MYR</td>
                    <td>
                        @php
                            $statusClass = match($entry->status->value ?? '') {
                                'Posted' => 'badge-success',
                                'Pending' => 'badge-warning',
                                'Draft' => 'badge-default',
                                default => 'badge-default'
                            };
                        @endphp
                        <span class="badge {{ $statusClass }}">{{ $entry->status->label() ?? 'Draft' }}</span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6">
                        <div class="empty-state py-8">
                            <p class="empty-state-title">No journal entries yet</p>
                            <p class="empty-state-description">Create your first journal entry to get started</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
