@extends('layouts.base')

@section('title', 'Risk Dashboard')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-gray-900">Customer Risk Dashboard</h1>
    <p class="text-sm text-gray-500">Risk scoring and monitoring</p>
</div>
@endsection

@section('header-actions')
<div class="flex items-center gap-3">
    <a href="{{ route('compliance.risk-dashboard.trends') }}" class="btn btn-secondary">
        View Trends
    </a>
    <button wire:click="rescreen" class="btn btn-primary">Rescreen All Customers</button>
</div>
@endsection

@section('content')
{{-- Summary Stats --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon bg-red-600/10 text-red-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
        </div>
        <p class="stat-card-label">High Risk Customers</p>
        <p class="stat-card-value text-red-600">{{ number_format($summary['high_risk'] ?? 0) }}</p>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon bg-amber-500/10 text-amber-500">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
        <p class="stat-card-label">Medium Risk Customers</p>
        <p class="stat-card-value text-amber-500">{{ number_format($summary['medium_risk'] ?? 0) }}</p>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon bg-green-600/10 text-green-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
        <p class="stat-card-label">Low Risk Customers</p>
        <p class="stat-card-value text-green-600">{{ number_format($summary['low_risk'] ?? 0) }}</p>
    </div>
</div>

{{-- Customers Table --}}
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Customers by Risk Level</h3>
    </div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Risk Level</th>
                    <th>Score</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($customers as $customer)
                <tr>
                    <td>
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center text-xs">
                                {{ substr($customer->full_name ?? $customer->name ?? 'U', 0, 1) }}
                            </div>
                            <span class="font-medium">{{ $customer->full_name ?? $customer->name ?? 'Unknown' }}</span>
                        </div>
                    </td>
                    <td>
                        @php
                            $riskClass = match($customer->risk_level ?? '') {
                                'High' => 'badge-danger',
                                'Medium' => 'badge-warning',
                                'Low' => 'badge-success',
                                default => 'badge-default'
                            };
                        @endphp
                        <span class="badge {{ $riskClass }}">{{ $customer->risk_level ?? 'Unknown' }}</span>
                    </td>
                    <td class="font-mono">{{ $customer->risk_score ?? 'N/A' }}</td>
                    <td>
                        <div class="table-actions">
                            <a href="{{ route('compliance.risk-dashboard.customer', $customer->id) }}" class="btn btn-ghost btn-icon" title="View Details">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4">
                        <div class="empty-state py-8">
                            <p class="empty-state-title">No customers found</p>
                            <p class="empty-state-description">Customer risk scores will appear here after initial scoring</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection