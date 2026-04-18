@extends('layouts.base')

@section('title', 'Risk Trends')

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">Risk Score Trends</h3></div>
    <div class="card-body">
        @if($needsRescreening->isEmpty())
            <p class="text-[--color-ink-muted]">No customers currently need rescreening.</p>
        @else
            <p class="text-sm text-[--color-ink-muted] mb-4">{{ $needsRescreening->count() }} customer(s) need rescreening.</p>
            <table class="table">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Risk Score</th>
                        <th>Risk Level</th>
                        <th>Next Screening</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($needsRescreening as $customer)
                    <tr>
                        <td>
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 bg-[--color-canvas-subtle] rounded-lg flex items-center justify-center text-xs">
                                    {{ substr($customer->full_name, 0, 1) }}
                                </div>
                                <span class="font-medium">{{ $customer->full_name }}</span>
                            </div>
                        </td>
                        <td class="font-mono">{{ $customer->latestRiskSnapshot?->overall_score ?? 'N/A' }}</td>
                        <td>
                            @php
                                $score = $customer->latestRiskSnapshot?->overall_score ?? 0;
                                $riskClass = match(true) {
                                    $score >= 80 => 'badge-danger',
                                    $score >= 60 => 'badge-warning',
                                    $score >= 30 => 'badge-info',
                                    default => 'badge-success'
                                };
                                $riskLabel = match(true) {
                                    $score >= 80 => 'Critical',
                                    $score >= 60 => 'High',
                                    $score >= 30 => 'Medium',
                                    default => 'Low'
                                };
                            @endphp
                            <span class="badge {{ $riskClass }}">{{ $riskLabel }}</span>
                        </td>
                        <td class="text-[--color-ink-muted]">
                            {{ $customer->latestRiskSnapshot?->next_screening_date?->format('d M Y') ?? 'N/A' }}
                        </td>
                        <td>
                            <form action="/compliance/risk-dashboard/rescreen" method="POST" class="inline">
                                @csrf
                                <input type="hidden" name="customer_id" value="{{ $customer->id }}">
                                <button type="submit" class="btn btn-ghost btn-sm">Rescreen</button>
                            </form>
                            <a href="/compliance/risk-dashboard/customer/{{ $customer->id }}" class="btn btn-ghost btn-sm">View</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
