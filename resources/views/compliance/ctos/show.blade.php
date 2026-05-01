@extends('layouts.base')

@section('title', 'CTOS Report - CEMS-MY')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold text-[--color-ink]">CTOS Report #{{ $ctosReport->id }}</h1>
        <p class="text-sm text-[--color-ink-muted] mt-1">Credit check result</p>
    </div>
    <a href="{{ route('compliance.ctos.index') }}" class="px-4 py-2 border border-[--color-border] text-[--color-ink] text-sm font-medium rounded-lg hover:bg-[--color-canvas-subtle]">
        Back
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="card">
        <div class="px-6 py-4 border-b border-[--color-border]">
            <h3 class="text-base font-semibold text-[--color-ink]">Report Details</h3>
        </div>
        <div class="p-6 space-y-4">
            <div class="flex justify-between">
                <span class="text-sm text-[--color-ink-muted]">Customer</span>
                <span class="text-sm text-[--color-ink]">{{ $ctosReport->customer->full_name ?? 'N/A' }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-[--color-ink-muted]">Report Type</span>
                <span class="text-sm text-[--color-ink]">{{ $ctosReport->report_type ?? 'Standard' }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-[--color-ink-muted]">Status</span>
                <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded
                    @if($ctosReport->status === 'completed') bg-green-100 text-green-700
                    @else bg-yellow-100 text-yellow-700
                    @endif">
                    {{ ucfirst($ctosReport->status) }}
                </span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-[--color-ink-muted]">Date</span>
                <span class="text-sm text-[--color-ink]">{{ $ctosReport->created_at->format('Y-m-d H:i') }}</span>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="px-6 py-4 border-b border-[--color-border]">
            <h3 class="text-base font-semibold text-[--color-ink]">Credit Score</h3>
        </div>
        <div class="p-6">
            <div class="text-4xl font-bold text-[--color-ink]">{{ $ctosReport->score ?? 'N/A' }}</div>
            <p class="text-sm text-[--color-ink-muted] mt-2">Credit rating</p>
        </div>
    </div>
</div>
@endsection