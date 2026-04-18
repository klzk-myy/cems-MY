@extends('layouts.base')

@section('title', 'Report Schedules')

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">Scheduled Reports</h3></div>
    <div class="card-body">
        @forelse($schedules ?? [] as $schedule)
        <div class="flex items-center justify-between p-4 border-b border-[--color-border] last:border-0">
            <div>
                <p class="font-medium">{{ $schedule->name }}</p>
                <p class="text-sm text-[--color-ink-muted]">{{ $schedule->frequency }} - {{ $schedule->next_run }}</p>
            </div>
            <span class="badge {{ $schedule->is_active ? 'badge-success' : 'badge-default' }}">
                {{ $schedule->is_active ? 'Active' : 'Inactive' }}
            </span>
        </div>
        @empty
        <p class="text-center py-8 text-[--color-ink-muted]">No scheduled reports</p>
        @endforelse
    </div>
</div>
@endsection
