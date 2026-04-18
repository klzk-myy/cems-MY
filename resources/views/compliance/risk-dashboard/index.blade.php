@extends('layouts.base')

@section('title', 'Risk Dashboard')

@section('content')
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <div class="stat-card">
        <p class="stat-card-label">High Risk Customers</p>
        <p class="stat-card-value">{{ $summary['high_risk'] ?? 0 }}</p>
    </div>
    <div class="stat-card">
        <p class="stat-card-label">Medium Risk Customers</p>
        <p class="stat-card-value">{{ $summary['medium_risk'] ?? 0 }}</p>
    </div>
    <div class="stat-card">
        <p class="stat-card-label">Low Risk Customers</p>
        <p class="stat-card-value">{{ $summary['low_risk'] ?? 0 }}</p>
    </div>
</div>

<div class="card mt-6">
    <div class="card-header"><h3 class="card-title">Customers by Risk Level</h3></div>
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
                @forelse($customers ?? [] as $customer)
                <tr>
                    <td>
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-[--color-canvas-subtle] rounded-lg flex items-center justify-center text-xs">
                                {{ substr($customer->full_name, 0, 1) }}
                            </div>
                            <span class="font-medium">{{ $customer->full_name }}</span>
                        </div>
                    </td>
                    <td>
                        @php $riskClass = match($customer->risk_level ?? '') { 'High' => 'badge-danger', 'Medium' => 'badge-warning', default => 'badge-success' }; @endphp
                        <span class="badge {{ $riskClass }}">{{ $customer->risk_level ?? 'Unknown' }}</span>
                    </td>
                    <td class="font-mono">{{ $customer->risk_score ?? 'N/A' }}</td>
                    <td>
                        <a href="/compliance/risk-dashboard/customer/{{ $customer->id }}" class="btn btn-ghost btn-sm">Details</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="text-center py-8 text-[--color-ink-muted]">No customers found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
