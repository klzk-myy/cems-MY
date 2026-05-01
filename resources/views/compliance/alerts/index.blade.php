@extends('layouts.base')

@section('title', 'Alert Triage - CEMS-MY')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold text-[--color-ink]">Alert Triage</h1>
        <p class="text-sm text-[--color-ink-muted] mt-1">Review and action compliance alerts</p>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="card p-4">
        <div class="text-2xl font-bold text-red-600">{{ $summary['critical'] ?? 0 }}</div>
        <div class="text-sm text-[--color-ink-muted]">Critical</div>
    </div>
    <div class="card p-4">
        <div class="text-2xl font-bold text-orange-600">{{ $summary['high'] ?? 0 }}</div>
        <div class="text-sm text-[--color-ink-muted]">High</div>
    </div>
    <div class="card p-4">
        <div class="text-2xl font-bold text-yellow-600">{{ $summary['medium'] ?? 0 }}</div>
        <div class="text-sm text-[--color-ink-muted]">Medium</div>
    </div>
    <div class="card p-4">
        <div class="text-2xl font-bold text-[--color-ink]">{{ $summary['low'] ?? 0 }}</div>
        <div class="text-sm text-[--color-ink-muted]">Low</div>
    </div>
</div>

<div class="card">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">All Alerts</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>Priority</th>
                    <th>Type</th>
                    <th>Customer</th>
                    <th>Risk Score</th>
                    <th>Status</th>
                    <th>Assigned To</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($alerts as $alert)
                <tr class="border-b border-[--color-border] hover:bg-[--color-canvas-subtle]/50">
                    <td class="text-[--color-ink]">
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded
                            @if($alert->priority?->value === 'critical') bg-red-100 text-red-700
                            @elseif($alert->priority?->value === 'high') bg-orange-100 text-orange-700
                            @elseif($alert->priority?->value === 'medium') bg-yellow-100 text-yellow-700
                            @else bg-blue-100 text-blue-700
                            @endif">
                            {{ ucfirst($alert->priority?->value ?? 'low') }}
                        </span>
                    </td>
                    <td class="text-[--color-ink]">{{ $alert->type?->label() ?? 'Unknown' }}</td>
                    <td class="text-[--color-ink]">{{ $alert->customer->full_name ?? 'N/A' }}</td>
                    <td class="text-[--color-ink] font-mono">{{ $alert->risk_score ?? 0 }}</td>
                    <td class="text-[--color-ink]">
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded
                            @if($alert->status?->value === 'open') bg-yellow-100 text-yellow-700
                            @elseif($alert->status?->value === 'assigned') bg-blue-100 text-blue-700
                            @else bg-green-100 text-green-700
                            @endif">
                            {{ $alert->status?->label() ?? 'Open' }}
                        </span>
                    </td>
                    <td class="text-[--color-ink]">{{ $alert->assignedTo?->username ?? 'Unassigned' }}</td>
                    <td class="text-[--color-ink]">
                        <a href="{{ route('compliance.alerts.show', $alert) }}" class="text-[--color-accent] hover:underline">View</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-8 text-center text-[--color-ink-muted]">No alerts found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($alerts->hasPages())
    <div class="px-6 py-4 border-t border-[--color-border]">
        {{ $alerts->links() }}
    </div>
    @endif
</div>
@endsection