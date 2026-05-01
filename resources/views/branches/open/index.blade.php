@extends('layouts.base')

@section('title', 'Open Branch')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-semibold text-[--color-ink]">Open Branch Workflow</h1>
        <p class="text-sm text-[--color-ink-muted] mt-1">Initialize a new branch with counters and currency pools</p>
    </div>
</div>

<div class="card">
    <div class="p-6">
        <p class="text-[--color-ink-muted]">This wizard guides you through setting up a new branch.</p>
    </div>
</div>
@endsection
