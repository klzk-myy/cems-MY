@extends('layouts.base')

@section('title', 'Counters')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">Counters</h1>
    <p class="text-sm text-[--color-ink-muted]">Manage till/counter sessions</p>
</div>
@endsection

@section('header-actions')
<div class="flex items-center gap-3">
    @if(auth()->user()->role->isTeller())
        @php $openCounter = auth()->user()->getOpenCounter(); @endphp
        @if($openCounter)
            <a href="/counters/{{ $openCounter->id }}/close" class="btn btn-danger">
                Close Counter
            </a>
        @else
            <a href="/counters/open" class="btn btn-primary">
                Open Counter
            </a>
        @endif
    @endif
</div>
@endsection

@section('content')
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    @forelse($counters ?? [] as $counter)
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">{{ $counter->name }}</h3>
            @php
                $statusValue = $counter->status instanceof \App\Enums\CounterSessionStatus
                    ? $counter->status->value
                    : (string)$counter->status;
                $statusLabel = $counter->status instanceof \App\Enums\CounterSessionStatus
                    ? $counter->status->label()
                    : (string)$counter->status;
                $statusClass = match($statusValue) {
                    'Open' => 'badge-success',
                    'Closed' => 'badge-default',
                    'Paused' => 'badge-warning',
                    default => 'badge-default'
                };
            @endphp
            <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
        </div>
        <div class="card-body">
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-sm text-[--color-ink-muted]">Branch</span>
                    <span class="text-sm font-medium">{{ $counter->branch->name ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-[--color-ink-muted]">Operator</span>
                    <span class="text-sm font-medium">{{ $counter->operator->username ?? 'Unassigned' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-[--color-ink-muted]">Opening Float</span>
                    <span class="text-sm font-medium font-mono">{{ number_format($counter->opening_float ?? 0, 2) }} MYR</span>
                </div>
            </div>
        </div>
        <div class="card-footer flex gap-2">
            <a href="/counters/{{ $counter->id }}" class="btn btn-ghost btn-sm flex-1">View</a>
            @if($statusValue === 'Open' && auth()->user()->role->isTeller())
                <a href="/counters/{{ $counter->id }}/handover" class="btn btn-secondary btn-sm flex-1">Handover</a>
            @endif
        </div>
    </div>
    @empty
    <div class="col-span-full">
        <div class="card">
            <div class="card-body">
                <div class="empty-state">
                    <p class="empty-state-title">No counters found</p>
                    <p class="empty-state-description">Create a counter to get started</p>
                </div>
            </div>
        </div>
    </div>
    @endforelse
</div>
@endsection
