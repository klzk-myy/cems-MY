@extends('layouts.base')

@section('title', 'Case #' . ($case->id ?? ''))

@section('header-title')
<div class="flex items-center gap-3">
    <a href="/compliance/cases" class="btn btn-ghost btn-icon">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
        </svg>
    </a>
    <div>
        <h1 class="text-xl font-semibold text-[--color-ink]">Case #{{ $case->id ?? '' }}</h1>
        <p class="text-sm text-[--color-ink-muted]">{{ $case->type->label() ?? 'Unknown Type' }}</p>
    </div>
</div>
@endsection

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Case Details</h3>
                @php
                    $statusClass = match($case->status->value ?? '') {
                        'Closed' => 'badge-success',
                        'Escalated' => 'badge-danger',
                        'InProgress' => 'badge-info',
                        default => 'badge-warning'
                    };
                @endphp
                <span class="badge {{ $statusClass }}">{{ $case->status->label() ?? 'Open' }}</span>
            </div>
            <div class="card-body">
                <p class="text-[--color-ink]">{{ $case->description ?? 'No description provided.' }}</p>
            </div>
        </div>

        @if($case->customer)
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Customer</h3>
                <a href="/customers/{{ $case->customer_id }}" class="btn btn-ghost btn-sm">View Profile</a>
            </div>
            <div class="card-body">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-[--color-canvas-subtle] rounded-lg flex items-center justify-center font-semibold">
                        {{ substr($case->customer->full_name, 0, 1) }}
                    </div>
                    <div>
                        <p class="font-medium">{{ $case->customer->full_name }}</p>
                        <p class="text-sm text-[--color-ink-muted]">{{ $case->customer->ic_number }}</p>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Notes</h3>
            </div>
            <div class="card-body">
                @forelse($case->notes ?? [] as $note)
                <div class="border-l-2 border-[--color-border] pl-4 mb-4">
                    <p class="text-sm">{{ $note->content }}</p>
                    <p class="text-xs text-[--color-ink-muted] mt-1">
                        {{ $note->creator->username ?? 'System' }} - {{ $note->created_at->diffForHumans() }}
                    </p>
                </div>
                @empty
                <p class="text-[--color-ink-muted] text-sm">No notes yet</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="space-y-6">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Details</h3></div>
            <div class="card-body space-y-3">
                <div>
                    <p class="text-sm text-[--color-ink-muted]">Priority</p>
                    @php $priorityClass = match($case->priority->value ?? '') { 'Critical' => 'badge-danger', 'High' => 'badge-warning', 'Medium' => 'badge-info', default => 'badge-default' }; @endphp
                    <span class="badge {{ $priorityClass }}">{{ $case->priority->label() ?? 'Low' }}</span>
                </div>
                <div>
                    <p class="text-sm text-[--color-ink-muted]">Assigned To</p>
                    <p class="text-sm font-medium">{{ $case->assignee->username ?? 'Unassigned' }}</p>
                </div>
                <div>
                    <p class="text-sm text-[--color-ink-muted]">Created</p>
                    <p class="text-sm">{{ $case->created_at->format('d M Y, H:i') }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
