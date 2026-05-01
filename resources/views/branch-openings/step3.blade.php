@extends('layouts.base')

@section('title', 'Branch Opening - Step 3 - CEMS-MY')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-[--color-ink]">Step 3: Review & Confirm</h1>
    <p class="text-sm text-[--color-ink-muted] mt-1">Review branch opening summary</p>
</div>

<div class="card">
    <div class="p-6">
        <p class="text-[--color-ink-muted] mb-4">Total Pool Amount: RM {{ number_format($totalPoolAmount ?? 0, 2) }}</p>
        <a href="{{ route('branches.open.complete', $branch->id ?? 0) }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">Complete Branch Opening</a>
    </div>
</div>
@endsection