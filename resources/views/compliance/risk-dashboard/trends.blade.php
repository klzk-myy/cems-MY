@extends('layouts.base')

@section('title', 'Risk Trends')

@section('content')
<div class="bg-white border border-[--color-border] rounded-xl">
    <div class="px-6 py-4 border-b border-[--color-border]"><h3 class="text-base font-semibold text-[--color-ink]">Risk Score Trends</h3></div>
    <div class="p-6">
        @if($needsRescreening->isEmpty())
            <p class="text-[--color-ink-muted]">No customers currently need rescreening.</p>
        @else
            <p class="text-sm text-[--color-ink-muted] mb-4">{{ $needsRescreening->count() }} customer(s) need rescreening.</p>
            <table class="w-full text-sm">
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
                                <div class="w-8 h-8 bg-[--color-canvas-subtle] rounded-lg flex items-center justify-center text-xs shrink-0">
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
                                    $score >= 80 => 'bg-red-100 text-red-700',
                                    $score >= 60 => 'bg-yellow-100 text-yellow-700',
                                    $score >= 30 => 'bg-blue-100 text-blue-700',
                                    default => 'bg-green-100 text-green-700'
                                };
                                $riskLabel = match(true) {
                                    $score >= 80 => 'Critical',
                                    $score >= 60 => 'High',
                                    $score >= 30 => 'Medium',
                                    default => 'Low'
                                };
                            @endphp
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded {{ $riskClass }}">{{ $riskLabel }}</span>
                        </td>
                        <td class="text-[--color-ink-muted]">
                            {{ $customer->latestRiskSnapshot?->next_screening_date?->format('d M Y') ?? 'N/A' }}
                        </td>
                        <td>
                            <form action="{{ route('compliance.risk-dashboard.rescreen') }}" method="POST" class="inline">
                                @csrf
                                <input type="hidden" name="customer_id" value="{{ $customer->id }}">
                                <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg hover:bg-[--color-canvas-subtle]">Rescreen</button>
                            </form>
                            <a href="{{ route('compliance.risk-dashboard.customer', $customer->id) }}" class="px-4 py-2 text-sm font-medium rounded-lg hover:bg-[--color-canvas-subtle]">View</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
