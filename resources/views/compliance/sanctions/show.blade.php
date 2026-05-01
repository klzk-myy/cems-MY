@extends('layouts.base')

@section('title', 'Sanctions - CEMS-MY')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold text-[--color-ink]">{{ $entry->entity_name }}</h1>
        <p class="text-sm text-[--color-ink-muted] mt-1">Sanctions entry detail</p>
    </div>
    <a href="{{ route('compliance.sanctions.index') }}" class="px-4 py-2 border border-[--color-border] text-[--color-ink] text-sm font-medium rounded-lg hover:bg-[--color-canvas-subtle]">
        Back
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="card">
        <div class="px-6 py-4 border-b border-[--color-border]">
            <h3 class="text-base font-semibold text-[--color-ink]">Entry Details</h3>
        </div>
        <div class="p-6 space-y-4">
            <div class="flex justify-between">
                <span class="text-sm text-[--color-ink-muted]">Name</span>
                <span class="text-sm text-[--color-ink]">{{ $entry->entity_name }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-[--color-ink-muted]">List</span>
                <span class="text-sm text-[--color-ink]">{{ $entry->list_name ?? 'N/A' }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-[--color-ink-muted]">Score</span>
                <span class="text-sm font-mono text-[--color-ink]">{{ round($entry->score ?? 0, 1) }}%</span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-[--color-ink-muted]">Entity Type</span>
                <span class="text-sm text-[--color-ink]">{{ $entry->entity_type ?? 'Individual' }}</span>
            </div>
            @if($entry->additional_info)
            <div class="pt-4 border-t border-[--color-border]">
                <p class="text-sm text-[--color-ink-muted] mb-2">Additional Info</p>
                <p class="text-sm text-[--color-ink]">{{ $entry->additional_info }}</p>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection