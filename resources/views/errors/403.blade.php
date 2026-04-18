@extends('layouts.base')

@section('title', '403 - Access Denied')

@section('content')
<div class="min-h-[60vh] flex items-center justify-center">
    <div class="text-center">
        <div class="w-24 h-24 bg-[--color-warning]/10 rounded-full flex items-center justify-center mx-auto mb-6">
            <svg class="w-12 h-12 text-[--color-warning]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
            </svg>
        </div>
        <h1 class="text-4xl font-bold text-[--color-ink] mb-2">403</h1>
        <p class="text-xl text-[--color-ink-muted] mb-4">Access Denied</p>
        <p class="text-[--color-ink-muted] mb-8 max-w-md mx-auto">
            You don't have permission to access this page. Please contact your administrator if you believe this is an error.
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
