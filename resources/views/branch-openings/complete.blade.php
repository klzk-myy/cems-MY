@extends('layouts.base')

@section('title', 'Branch Opening Complete - CEMS-MY')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-[--color-ink]">Branch Opening Complete</h1>
    <p class="text-sm text-[--color-ink-muted] mt-1">Branch has been successfully initialized</p>
</div>

<div class="card">
    <div class="p-6">
        <p class="text-[--color-ink]">Branch {{ $branch->code ?? 'N/A' }} - {{ $branch->name ?? 'N/A' }} has been successfully opened.</p>
        <div class="mt-4">
            <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">Return to Dashboard</a>
        </div>
    </div>
</div>
@endsection