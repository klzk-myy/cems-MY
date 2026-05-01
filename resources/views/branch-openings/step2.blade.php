@extends('layouts.base')

@section('title', 'Branch Opening - Step 2 - CEMS-MY')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-[--color-ink]">Step 2: Currency Pools</h1>
    <p class="text-sm text-[--color-ink-muted] mt-1">Configure initial currency holdings for the branch</p>
</div>

<div class="card">
    <div class="p-6">
        <p class="text-[--color-ink-muted] mb-4">Branch: {{ $branch->code ?? 'N/A' }} - {{ $branch->name ?? 'New Branch' }}</p>
        <p class="text-[--color-ink-muted]">Configure currency pools for this branch.</p>
    </div>
</div>
@endsection