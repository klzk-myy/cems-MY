@extends('layouts.base')

@section('title', 'Alert Detail - CEMS-MY')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold text-[--color-ink]">Alert #{{ $alert->id }}</h1>
        <p class="text-sm text-[--color-ink-muted] mt-1">{{ $alert->type?->label() ?? 'Unknown Type' }}</p>
    </div>
    <a href="{{ route('compliance.alerts.index') }}" class="px-4 py-2 border border-[--color-border] text-[--color-ink] text-sm font-medium rounded-lg hover:bg-[--color-canvas-subtle]">
        Back
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="card">
        <div class="px-6 py-4 border-b border-[--color-border]">
            <h3 class="text-base font-semibold text-[--color-ink]">Alert Details</h3>
        </div>
        <div class="p-6 space-y-4">
            <div class="flex justify-between">
                <span class="text-sm text-[--color-ink-muted]">Priority</span>
                <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded
                    @if($alert->priority?->value === 'critical') bg-red-100 text-red-700
                    @elseif($alert->priority?->value === 'high') bg-orange-100 text-orange-700
                    @else bg-yellow-100 text-yellow-700
                    @endif">
                    {{ ucfirst($alert->priority?->value ?? 'low') }}
                </span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-[--color-ink-muted]">Status</span>
                <span class="text-sm text-[--color-ink]">{{ $alert->status?->label() ?? 'Open' }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-[--color-ink-muted]">Risk Score</span>
                <span class="text-sm font-mono text-[--color-ink]">{{ $alert->risk_score ?? 0 }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-[--color-ink-muted]">Assigned To</span>
                <span class="text-sm text-[--color-ink]">{{ $alert->assignedTo?->username ?? 'Unassigned' }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-[--color-ink-muted]">Created</span>
                <span class="text-sm text-[--color-ink]">{{ $alert->created_at->format('Y-m-d H:i') }}</span>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="px-6 py-4 border-b border-[--color-border]">
            <h3 class="text-base font-semibold text-[--color-ink]">Customer</h3>
        </div>
        <div class="p-6 space-y-4">
            @if($alert->customer)
            <div class="flex justify-between">
                <span class="text-sm text-[--color-ink-muted]">Name</span>
                <span class="text-sm text-[--color-ink]">{{ $alert->customer->full_name }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-[--color-ink-muted]">ID Type</span>
                <span class="text-sm text-[--color-ink]">{{ $alert->customer->id_type }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-[--color-ink-muted]">Risk Rating</span>
                <span class="text-sm text-[--color-ink]">{{ $alert->customer->risk_rating }}</span>
            </div>
            @else
            <p class="text-sm text-[--color-ink-muted]">No customer associated</p>
            @endif
        </div>
    </div>
</div>

@if($alert->reason)
<div class="card mt-6">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">Reason</h3>
    </div>
    <div class="p-6">
        <p class="text-sm text-[--color-ink]">{{ $alert->reason }}</p>
    </div>
</div>
@endif

<div class="card mt-6">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">Actions</h3>
    </div>
    <div class="p-6 flex flex-wrap gap-3">
        <form action="{{ route('compliance.alerts.assign', $alert) }}" method="POST" class="inline">
            @csrf
            <button type="submit" class="px-4 py-2 bg-[#0a0a0a] text-white text-sm font-medium rounded-lg hover:bg-[#262626]">
                Assign to Me
            </button>
        </form>
        <form action="{{ route('compliance.alerts.resolve', $alert) }}" method="POST" class="inline">
            @csrf
            <button type="submit" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700">
                Resolve
            </button>
        </form>
        <form action="{{ route('compliance.alerts.dismiss', $alert) }}" method="POST" class="inline">
            @csrf
            <button type="submit" class="px-4 py-2 border border-[--color-border] text-[--color-ink] text-sm font-medium rounded-lg hover:bg-[--color-canvas-subtle]">
                Dismiss
            </button>
        </form>
    </div>
</div>
@endsection