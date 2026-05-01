@extends('layouts.base')

@section('title', 'Branch Opening Workflow - CEMS-MY')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-[--color-ink]">Branch Opening Workflow</h1>
    <p class="text-sm text-[--color-ink-muted] mt-1">Initialize a new branch with counters and currency pools</p>
</div>

<div class="card">
    <div class="p-6">
        <p class="text-[--color-ink-muted]">This wizard guides you through setting up a new branch.</p>
        <a href="{{ route('branches.open.step1') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626] mt-4">Start Branch Opening</a>
    </div>
</div>
@endsection