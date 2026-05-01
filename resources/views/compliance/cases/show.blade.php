@extends('layouts.base')

@section('title', 'Case Detail - CEMS-MY')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold text-[--color-ink]">Case #{{ $case->id }}</h1>
        <p class="text-sm text-[--color-ink-muted] mt-1">Compliance Case</p>
    </div>
    <a href="{{ route('compliance.cases.index') }}" class="px-4 py-2 border border-[--color-border] text-[--color-ink] text-sm font-medium rounded-lg hover:bg-[--color-canvas-subtle]">
        Back
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="card">
        <div class="px-6 py-4 border-b border-[--color-border]">
            <h3 class="text-base font-semibold text-[--color-ink]">Case Details</h3>
        </div>
        <div class="p-6 space-y-4">
            <div class="flex justify-between">
                <span class="text-sm text-[--color-ink-muted]">Priority</span>
                <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded
                    @if($case->priority === 'critical') bg-red-100 text-red-700
                    @elseif($case->priority === 'high') bg-orange-100 text-orange-700
                    @else bg-yellow-100 text-yellow-700
                    @endif">
                    {{ ucfirst($case->priority) }}
                </span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-[--color-ink-muted]">Status</span>
                <span class="text-sm text-[--color-ink]">{{ str_replace('_', ' ', ucfirst($case->status)) }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-[--color-ink-muted]">Opened By</span>
                <span class="text-sm text-[--color-ink]">{{ $case->openedBy->name ?? 'N/A' }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-[--color-ink-muted]">Assigned To</span>
                <span class="text-sm text-[--color-ink]">{{ $case->assignedTo?->username ?? 'Unassigned' }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-[--color-ink-muted]">SLA Deadline</span>
                <span class="text-sm text-[--color-ink]">{{ $case->sla_deadline?->format('Y-m-d H:i') ?? '-' }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-[--color-ink-muted]">Created</span>
                <span class="text-sm text-[--color-ink]">{{ $case->created_at->format('Y-m-d H:i') }}</span>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="px-6 py-4 border-b border-[--color-border]">
            <h3 class="text-base font-semibold text-[--color-ink]">Linked Alerts</h3>
        </div>
        <div class="p-6">
            @if($case->alerts->count() > 0)
            <div class="space-y-3">
                @foreach($case->alerts as $alert)
                <div class="flex justify-between items-center py-2 border-b border-[--color-border] last:border-0">
                    <span class="text-sm text-[--color-ink]">#{{ $alert->id }} - {{ $alert->alert_type }}</span>
                    <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded
                        @if($alert->status === 'resolved') bg-green-100 text-green-700
                        @else bg-yellow-100 text-yellow-700
                        @endif">
                        {{ $alert->status }}
                    </span>
                </div>
                @endforeach
            </div>
            @else
            <p class="text-sm text-[--color-ink-muted]">No linked alerts</p>
            @endif
        </div>
    </div>
</div>

@if($case->notes)
<div class="card mt-6">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">Notes</h3>
    </div>
    <div class="p-6">
        <p class="text-sm text-[--color-ink]">{{ $case->notes }}</p>
    </div>
</div>
@endif
@endsection