@extends('layouts.base')

@section('title', 'Report Schedules')

@section('content')
<div class="bg-white border border-[--color-border] rounded-xl">
    <div class="px-6 py-4 border-b border-[--color-border]"><h3 class="text-base font-semibold text-[--color-ink]">Scheduled Reports</h3></div>
    <div class="p-6">
        @forelse($schedules ?? [] as $schedule)
        <div class="flex items-center justify-between p-4 border-b border-[--color-border] last:border-0">
            <div>
                <p class="font-medium">{{ $schedule->name }}</p>
                <p class="text-sm text-[--color-ink-muted]">{{ $schedule->frequency }} - {{ $schedule->next_run }}</p>
            </div>
            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded {{ $schedule->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' }}">
                {{ $schedule->is_active ? 'Active' : 'Inactive' }}
            </span>
        </div>
        @empty
        <p class="text-center py-8 text-[--color-ink-muted]">No scheduled reports</p>
        @endforelse
    </div>
</div>
@endsection
