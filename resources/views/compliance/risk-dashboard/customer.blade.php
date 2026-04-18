@extends('layouts.base')

@section('title', 'Risk: ' . ($customer->full_name ?? ''))

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">{{ $customer->full_name ?? 'Customer' }}</h3>
        @php $riskClass = match($customer->risk_level ?? '') { 'High' => 'badge-danger', 'Medium' => 'badge-warning', default => 'badge-success' }; @endphp
        <span class="badge {{ $riskClass }}">{{ $customer->risk_level ?? 'Unknown' }}</span>
    </div>
    <div class="card-body">
        <p><strong>Risk Score:</strong> {{ $customer->risk_score ?? 'N/A' }}</p>
        <p><strong>Risk Rating:</strong> {{ $customer->risk_rating ?? 'N/A' }}</p>
        <p><strong>CDD Level:</strong> {{ $customer->cdd_level ?? 'N/A' }}</p>
        <p><strong>PEP Status:</strong> {{ $customer->pep_status ? 'Yes' : 'No' }}</p>
        <p><strong>Sanction Hit:</strong> {{ $customer->sanction_hit ? 'Yes' : 'No' }}</p>
    </div>
</div>

@if(isset($trends) && !empty($trends['snapshots']))
<div class="card mt-6">
    <div class="card-header">
        <h3 class="card-title">Risk Score Trend</h3>
    </div>
    <div class="card-body">
        <p><strong>Current Score:</strong> {{ $trends['current_score'] ?? 'N/A' }}</p>
        <p><strong>Trend:</strong> {{ $trends['trend']?->value ?? 'Unknown' }}</p>
        <p><strong>Data Points:</strong> {{ $trends['data_points'] ?? 0 }}</p>
    </div>
</div>
@endif
@endsection
