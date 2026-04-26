@extends('layouts.base')

@section('title', 'Compliance Dashboard')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-gray-900">Compliance Dashboard</h1>
    <p class="text-sm text-gray-500">AML/CFT monitoring and reporting</p>
</div>
@endsection

@section('header-actions')
<div class="flex items-center gap-3">
    <a href="{{ route('compliance.alerts.index') }}" class="btn btn-secondary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
        </svg>
        Alert Triage
    </a>
    <a href="{{ route('str.create') }}" class="btn btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
        </svg>
        New STR
    </a>
</div>
@endsection

@section('content')
{{-- Compliance Stats --}}
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon bg-red-600/10 text-red-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
        </div>
        <p class="stat-card-label">Active Alerts</p>
        <p class="stat-card-value">{{ number_format($stats['active_alerts'] ?? 0) }}</p>
        <p class="stat-card-change text-gray-500">{{ $stats['pending_review'] ?? 0 }} pending review</p>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon bg-amber-500/10 text-amber-500">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
            </div>
        </div>
        <p class="stat-card-label">Open Cases</p>
        <p class="stat-card-value">{{ number_format($stats['open_cases'] ?? 0) }}</p>
        <p class="stat-card-change text-gray-500">{{ $stats['cases_needing_attention'] ?? 0 }} need attention</p>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon bg-blue-500/10 text-blue-500">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
            </div>
        </div>
        <p class="stat-card-label">EDD Records</p>
        <p class="stat-card-value">{{ number_format($stats['edd_records'] ?? 0) }}</p>
        <p class="stat-card-change text-gray-500">{{ $stats['edd_pending'] ?? 0 }} pending</p>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon bg-green-600/10 text-green-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
        <p class="stat-card-label">STR Submitted</p>
        <p class="stat-card-value">{{ number_format($stats['str_submitted'] ?? 0) }}</p>
        <p class="stat-card-change text-gray-500">{{ $stats['str_pending'] ?? 0 }} pending</p>
    </div>
</div>

{{-- Quick Actions & Alerts --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    {{-- Quick Actions --}}
    <div class="card lg:col-span-2">
        <div class="card-header">
            <h3 class="card-title">Compliance Actions</h3>
        </div>
        <div class="card-body">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <a href="{{ route('compliance.alerts.index') }}" class="flex flex-col items-center gap-3 p-4 rounded-xl bg-gray-100 hover:bg-gray-200 transition-colors">
                    <div class="w-12 h-12 bg-red-600 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                    </div>
                    <span class="text-sm font-medium text-gray-900">Alert Triage</span>
                </a>
                <a href="{{ route('compliance.cases.index') }}" class="flex flex-col items-center gap-3 p-4 rounded-xl bg-gray-100 hover:bg-gray-200 transition-colors">
                    <div class="w-12 h-12 bg-amber-500 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                    </div>
                    <span class="text-sm font-medium text-gray-900">Cases</span>
                </a>
                <a href="{{ route('compliance.edd.index') }}" class="flex flex-col items-center gap-3 p-4 rounded-xl bg-gray-100 hover:bg-gray-200 transition-colors">
                    <div class="w-12 h-12 bg-blue-500 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <span class="text-sm font-medium text-gray-900">EDD</span>
                </a>
                <a href="{{ route('str.create') }}" class="flex flex-col items-center gap-3 p-4 rounded-xl bg-gray-100 hover:bg-gray-200 transition-colors">
                    <div class="w-12 h-12 bg-amber-500 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                    <span class="text-sm font-medium text-gray-900">STR Studio</span>
                </a>
            </div>
        </div>
    </div>

    {{-- Compliance Status --}}
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">System Status</h3>
        </div>
        <div class="card-body space-y-4">
            <div class="flex items-center justify-between">
                <span class="text-sm text-gray-900">Sanctions Screening</span>
                <span class="badge badge-success">Active</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm text-gray-900">CTOS Reporting</span>
                <span class="badge badge-success">Operational</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm text-gray-900">STR Automation</span>
                <span class="badge badge-success">Ready</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-sm text-gray-900">EDD Workflow</span>
                <span class="badge badge-success">Active</span>
            </div>
        </div>
    </div>
</div>

{{-- Recent Alerts --}}
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Recent Alerts</h3>
        <a href="{{ route('compliance.alerts.index') }}" class="btn btn-ghost btn-sm">View All</a>
    </div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Priority</th>
                    <th>Type</th>
                    <th>Customer</th>
                    <th>Description</th>
                    <th>Created</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recent_alerts ?? [] as $alert)
                <tr>
                    <td>
                        @php
                            $priorityClass = match($alert['priority']['value'] ?? '') {
                                'critical' => 'badge-danger',
                                'high' => 'badge-warning',
                                'medium' => 'badge-info',
                                default => 'badge-default'
                            };
                        @endphp
                        <span class="badge {{ $priorityClass }}">{{ $alert['priority']['value'] ?? 'low' }}</span>
                    </td>
                    <td>{{ $alert['type']['label'] ?? 'Unknown' }}</td>
                    <td>
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 bg-gray-100 rounded flex items-center justify-center">
                                <span class="text-xs">{{ substr($alert['customer']['full_name'] ?? '?', 0, 1) }}</span>
                            </div>
                            <span class="text-sm">{{ $alert['customer']['full_name'] ?? 'N/A' }}</span>
                        </div>
                    </td>
                    <td class="max-w-xs truncate">{{ $alert['reason'] ?? '' }}</td>
                    <td class="text-gray-500">{{ \Carbon\Carbon::parse($alert['created_at'])->diffForHumans() }}</td>
                    <td>
                        @php
                            $statusClass = match($alert['status']['value'] ?? '') {
                                'Resolved' => 'badge-success',
                                'Rejected' => 'badge-default',
                                default => 'badge-warning'
                            };
                        @endphp
                        <span class="badge {{ $statusClass }}">{{ $alert['status']['label'] ?? 'Pending' }}</span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6">
                        <div class="empty-state py-8">
                            <div class="empty-state-icon">
                                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <p class="empty-state-title">No alerts</p>
                            <p class="empty-state-description">All systems are operating normally</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
