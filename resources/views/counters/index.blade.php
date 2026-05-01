@extends('layouts.base')

@section('title', 'Counters - CEMS-MY')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-semibold text-[--color-ink]">Counters</h1>
        <p class="text-sm text-[--color-ink-muted] mt-1">Manage teller counters and till sessions</p>
    </div>
    @role('manager')
    @if(count($availableCounters) > 0)
    <a href="{{ route('counters.open.show', $availableCounters[0]) }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        Open Counter
    </a>
    @endif
    @endrole
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    @foreach($counters as $counter)
    <div class="card">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-[--color-ink]">{{ $counter->name }}</h3>
            @php
            $openSession = $counter->sessions->first();
            @endphp
            @if($openSession)
            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Open</span>
            @else
            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-[--color-canvas-subtle] text-[--color-ink-muted]">Closed</span>
            @endif
        </div>
        @if($openSession)
        <div class="space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-[--color-ink-muted]">Teller</span>
                <span class="text-[--color-ink]">{{ $openSession->user->name ?? 'N/A' }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-[--color-ink-muted]">MYR Balance</span>
                <span class="text-[--color-ink] font-semibold">RM {{ number_format($openSession->opening_float_myr ?? 0, 2) }}</span>
            </div>
        </div>
        @else
        <div class="space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-[--color-ink-muted]">Teller</span>
                <span class="text-[--color-ink-muted]">-</span>
            </div>
            <div class="flex justify-between">
                <span class="text-[--color-ink-muted]">MYR Float</span>
                <span class="text-[--color-ink-muted]">-</span>
            </div>
        </div>
        @endif
    </div>
    @endforeach
</div>

<div class="card">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">All Counters</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>Counter</th>
                    <th>Status</th>
                    <th>Current Teller</th>
                    <th>MYR Float</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($counters as $counter)
                @php
                $openSession = $counter->sessions->first();
                @endphp
                <tr class="border-b border-[--color-border] hover:bg-[--color-canvas-subtle]/50">
                    <td class="text-[--color-ink] font-medium">{{ $counter->name }}</td>
                    <td class="text-[--color-ink]">
                        @if($openSession)
                        <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Open</span>
                        @else
                        <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-[--color-canvas-subtle] text-[--color-ink-muted]">Closed</span>
                        @endif
                    </td>
                    <td class="text-[--color-ink]">{{ $openSession->user->name ?? '-' }}</td>
                    <td class="text-[--color-ink] font-semibold">
                        {{ $openSession ? 'RM '.number_format($openSession->opening_float_myr ?? 0, 2) : '-' }}
                    </td>
                    <td class="text-[--color-ink]">
                        @if($openSession)
                        <a href="{{ route('counters.history', $counter) }}" class="text-[--color-accent] hover:underline">View</a>
                        @else
                        <a href="{{ route('counters.open.show', $counter) }}" class="text-[--color-accent] hover:underline">Open</a>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-[--color-ink-muted]">No counters found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection