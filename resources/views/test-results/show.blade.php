@extends('layouts.base')

@section('title', 'Test Result #' . ($testResult->id ?? 'N/A') . ' - CEMS-MY')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-[--color-ink]">Test Result #{{ $testResult->id ?? 'N/A' }}</h1>
    <p class="text-sm text-[--color-ink-muted] mt-1">Detailed test result view</p>
</div>

<div class="card">
    <div class="p-6">
        <p class="text-[--color-ink-muted]">Test result details</p>
    </div>
</div>
@endsection