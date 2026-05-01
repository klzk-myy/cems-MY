@extends('layouts.base')

@section('title', 'Risk: ' . ($customer->full_name ?? ''))

@section('content')
<div class="bg-white border border-[--color-border] rounded-xl">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">{{ $customer->full_name ?? 'Customer' }}</h3>
        @php $riskClass = match($customer->risk_level ?? '') { 'High' => 'bg-red-100 text-red-700', 'Medium' => 'bg-yellow-100 text-yellow-700', default => 'bg-green-100 text-green-700' }; @endphp
        <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded {{ $riskClass }}">{{ $customer->risk_level ?? 'Unknown' }}</span>
    </div>
    <div class="p-6">
        <p><strong>Risk Score:</strong> {{ $customer->risk_score ?? 'N/A' }}</p>
        <p><strong>Risk Rating:</strong> {{ $customer->risk_rating ?? 'N/A' }}</p>
        <p><strong>CDD Level:</strong> {{ $customer->cdd_level ?? 'N/A' }}</p>
        <p><strong>PEP Status:</strong> {{ $customer->pep_status ? 'Yes' : 'No' }}</p>
        <p><strong>Sanction Hit:</strong> {{ $customer->sanction_hit ? 'Yes' : 'No' }}</p>
    </div>
</div>

@if(isset($trends) && !empty($trends['snapshots']))
<div class="bg-white border border-[--color-border] rounded-xl mt-6">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">Risk Score Trend</h3>
    </div>
    <div class="p-6">
        <p><strong>Current Score:</strong> {{ $trends['current_score'] ?? 'N/A' }}</p>
        <p><strong>Trend:</strong> {{ $trends['trend']?->value ?? 'Unknown' }}</p>
        <p><strong>Data Points:</strong> {{ $trends['data_points'] ?? 0 }}</p>
    </div>
</div>
@endif
@endsection
