@extends('layouts.base')

@section('title', 'Dashboard')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">Dashboard</h1>
    <p class="text-sm text-[--color-ink-muted]">Welcome back, {{ auth()->user()->username }}</p>
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
{{-- Stats Grid --}}
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    {{-- Total Transactions --}}
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon bg-[--color-info]/10 text-[--color-info]">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
            </div>
        </div>
        <p class="stat-card-label">Total Transactions</p>
        <p class="stat-card-value">{{ number_format($stats['total_transactions'] ?? 0) }}</p>
        <p class="stat-card-change text-[--color-ink-muted]">Today: {{ $stats['today_transactions'] ?? 0 }}</p>
    </div>

    {{-- Buy Volume --}}
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon bg-[--color-success]/10 text-[--color-success]">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
        <p class="stat-card-label">Buy Volume (MYR)</p>
        <p class="stat-card-value">{{ number_format($stats['buy_volume'] ?? 0, 2) }}</p>
        <p class="stat-card-change positive">+{{ $stats['buy_count'] ?? 0 }} transactions</p>
    </div>

    {{-- Sell Volume --}}
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon bg-[--color-warning]/10 text-[--color-warning]">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
            </div>
        </div>
        <p class="stat-card-label">Sell Volume (MYR)</p>
        <p class="stat-card-value">{{ number_format($stats['sell_volume'] ?? 0, 2) }}</p>
        <p class="stat-card-change negative">{{ $stats['sell_count'] ?? 0 }} transactions</p>
    </div>

    {{-- Flagged Transactions --}}
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon bg-[--color-danger]/10 text-[--color-danger]">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
        </div>
        <p class="stat-card-label">Flagged Transactions</p>
        <p class="stat-card-value">{{ number_format($stats['flagged'] ?? 0) }}</p>
        <p class="stat-card-change text-[--color-ink-muted]">Requires review</p>
    </div>
</div>

{{-- Quick Actions & System Status --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    {{-- Quick Actions --}}
    <div class="card lg:col-span-2">
        <div class="card-header">
            <h3 class="card-title">Quick Actions</h3>
        </div>
        <div class="card-body">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <a href="/transactions/create" class="flex flex-col items-center gap-3 p-4 rounded-xl bg-[--color-canvas-subtle] hover:bg-[--color-canvas-emphasis] transition-colors">
                    <div class="w-12 h-12 bg-[--color-accent] rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                    </div>
                    <span class="text-sm font-medium text-[--color-ink]">New Transaction</span>
                </a>
                <a href="/customers/create" class="flex flex-col items-center gap-3 p-4 rounded-xl bg-[--color-canvas-subtle] hover:bg-[--color-canvas-emphasis] transition-colors">
                    <div class="w-12 h-12 bg-[--color-info] rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                        </svg>
                    </div>
                    <span class="text-sm font-medium text-[--color-ink]">Register Customer</span>
                </a>
                <a href="/compliance/alerts" class="flex flex-col items-center gap-3 p-4 rounded-xl bg-[--color-canvas-subtle] hover:bg-[--color-canvas-emphasis] transition-colors">
                    <div class="w-12 h-12 bg-[--color-danger] rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                    </div>
                    <span class="text-sm font-medium text-[--color-ink]">View Alerts</span>
                    @if(($stats['flagged'] ?? 0) > 0)
                        <span class="badge badge-danger">{{ $stats['flagged'] }}</span>
                    @endif
                </a>
                <a href="/reports" class="flex flex-col items-center gap-3 p-4 rounded-xl bg-[--color-canvas-subtle] hover:bg-[--color-canvas-emphasis] transition-colors">
                    <div class="w-12 h-12 bg-[--color-success] rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <span class="text-sm font-medium text-[--color-ink]">View Reports</span>
                </a>
            </div>
        </div>
    </div>

    {{-- System Status --}}
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">System Status</h3>
            <span class="badge badge-success">All Systems Operational</span>
        </div>
        <div class="card-body space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-[--color-success]/10 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4 text-[--color-success]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"></path>
                        </svg>
                    </div>
                    <span class="text-sm text-[--color-ink]">Database</span>
                </div>
                <span class="badge badge-success">Connected</span>
            </div>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-[--color-success]/10 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4 text-[--color-success]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <span class="text-sm text-[--color-ink]">Redis Cache</span>
                </div>
                <span class="badge badge-success">Active</span>
            </div>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-[--color-success]/10 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4 text-[--color-success]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                    </div>
                    <span class="text-sm text-[--color-ink]">Rate API</span>
                </div>
                <span class="badge badge-success">Online</span>
            </div>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-[--color-success]/10 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4 text-[--color-success]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                    </div>
                    <span class="text-sm text-[--color-ink]">Encryption</span>
                </div>
                <span class="badge badge-success">AES-256</span>
            </div>
        </div>
    </div>
</div>

{{-- Recent Transactions --}}
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Recent Transactions</h3>
        <a href="/transactions" class="btn btn-ghost btn-sm">
            View All
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
        </a>
    </div>
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
                    <th>Status</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recent_transactions ?? [] as $tx)
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
                    <td class="font-mono">{{ number_format($tx->amount_local, 2) }} MYR</td>
                    <td class="font-mono">{{ $tx->rate }}</td>
                    <td>
                        @php
                            $statusClass = match($tx->status->value) {
                                'Completed' => 'badge-success',
                                'Pending' => 'badge-warning',
                                'OnHold' => 'badge-warning',
                                'PendingCancellation' => 'badge-warning',
                                'Cancelled' => 'badge-danger',
                                default => 'badge-default'
                            };
                        @endphp
                        <span class="badge {{ $statusClass }}">{{ $tx->status->label() }}</span>
                    </td>
                    <td class="text-[--color-ink-muted]">{{ $tx->created_at->diffForHumans() }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="8">
                        <div class="empty-state py-12">
                            <div class="empty-state-icon">
                                <svg class="w-8 h-8 text-[--color-ink-muted]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                </svg>
                            </div>
                            <p class="empty-state-title">No transactions yet</p>
                            <p class="empty-state-description">Start by creating your first transaction</p>
                            <a href="/transactions/create" class="btn btn-primary mt-4">Create Transaction</a>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
