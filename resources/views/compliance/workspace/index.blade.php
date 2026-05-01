@extends('layouts.base')

@section('title', 'Cases - CEMS-MY')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold text-[--color-ink]">Compliance Workspace</h1>
        <p class="text-sm text-[--color-ink-muted] mt-1">Your compliance workflow</p>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <div class="card p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-[--color-ink]">Pending Reviews</h3>
            <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">5</span>
        </div>
        <p class="text-sm text-[--color-ink-muted]">Cases awaiting your review</p>
    </div>
    <div class="card p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-[--color-ink]">Escalated</h3>
            <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">2</span>
        </div>
        <p class="text-sm text-[--color-ink-muted]">Cases requiring immediate attention</p>
    </div>
    <div class="card p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-[--color-ink]">Completed Today</h3>
            <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">3</span>
        </div>
        <p class="text-sm text-[--color-ink-muted]">Cases resolved today</p>
    </div>
</div>

<div class="card mt-6">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">Recent Activity</h3>
    </div>
    <div class="p-6">
        <p class="text-sm text-[--color-ink-muted] text-center">Your recent compliance activity will appear here</p>
    </div>
</div>
@endsection