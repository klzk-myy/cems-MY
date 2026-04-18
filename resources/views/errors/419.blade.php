@extends('layouts.base')

@section('title', '419 - Page Expired')

@section('content')
<div class="min-h-[60vh] flex items-center justify-center">
    <div class="text-center">
        <div class="w-24 h-24 bg-[--color-warning]/10 rounded-full flex items-center justify-center mx-auto mb-6">
            <svg class="w-12 h-12 text-[--color-warning]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
        <h1 class="text-4xl font-bold text-[--color-ink] mb-2">419</h1>
        <p class="text-xl text-[--color-ink-muted] mb-4">Page Expired</p>
        <p class="text-[--color-ink-muted] mb-8 max-w-md mx-auto">
            Your session has expired. Please refresh the page and try again.
        </p>
        <a href="/" class="btn btn-primary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
            </svg>
            Refresh Page
        </a>
    </div>
</div>
@endsection
