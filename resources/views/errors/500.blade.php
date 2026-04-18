@extends('layouts.base')

@section('title', '500 - Server Error')

@section('content')
<div class="min-h-[60vh] flex items-center justify-center">
    <div class="text-center">
        <div class="w-24 h-24 bg-[--color-danger]/10 rounded-full flex items-center justify-center mx-auto mb-6">
            <svg class="w-12 h-12 text-[--color-danger]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
        </div>
        <h1 class="text-4xl font-bold text-[--color-ink] mb-2">500</h1>
        <p class="text-xl text-[--color-ink-muted] mb-4">Server Error</p>
        <p class="text-[--color-ink-muted] mb-8 max-w-md mx-auto">
            Something went wrong on our end. Please try again later or contact support if the problem persists.
        </p>
        <a href="/" class="btn btn-primary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
            </svg>
            Back to Home
        </a>
    </div>
</div>
@endsection
