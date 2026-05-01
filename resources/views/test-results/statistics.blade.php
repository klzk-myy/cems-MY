@extends('layouts.base')

@section('title', 'Test Statistics - CEMS-MY')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-[--color-ink]">Test Statistics</h1>
    <p class="text-sm text-[--color-ink-muted] mt-1">Aggregate test performance metrics</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="card p-6">
        <div class="text-sm text-[--color-ink-muted] mb-1">Total Tests</div>
        <div class="text-2xl font-bold text-[--color-ink]">0</div>
    </div>
    <div class="card p-6">
        <div class="text-sm text-[--color-ink-muted] mb-1">Passed</div>
        <div class="text-2xl font-bold text-green-600">0</div>
    </div>
    <div class="card p-6">
        <div class="text-sm text-[--color-ink-muted] mb-1">Failed</div>
        <div class="text-2xl font-bold text-red-500">0</div>
    </div>
    <div class="card p-6">
        <div class="text-sm text-[--color-ink-muted] mb-1">Pass Rate</div>
        <div class="text-2xl font-bold text-[--color-ink]">0%</div>
    </div>
</div>
@endsection