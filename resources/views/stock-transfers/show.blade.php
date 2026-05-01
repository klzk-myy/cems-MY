@extends('layouts.base')

@section('title', 'Stock Transfer Details - CEMS-MY')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-[--color-ink]">Stock Transfer Details</h1>
    <p class="text-sm text-[--color-ink-muted] mt-1">Transfer #{{ $stockTransfer->id ?? 'N/A' }}</p>
</div>

<div class="card">
    <div class="p-6">
        <p class="text-[--color-ink-muted]">Transfer details view</p>
    </div>
</div>
@endsection