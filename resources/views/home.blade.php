@extends('layouts.base')

@section('title', 'CEMS-MY')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-[--content-bg]">
    <div class="text-center max-w-lg mx-auto px-6">
        {{-- Logo --}}
        <div class="w-20 h-20 bg-[--color-accent] rounded-2xl flex items-center justify-center mx-auto mb-8 shadow-lg">
            <span class="text-white font-bold text-4xl">C</span>
        </div>

        {{-- Title --}}
        <h1 class="text-4xl font-bold text-[--color-ink] mb-3">CEMS-MY</h1>
        <p class="text-xl text-[--color-ink-muted] mb-8">Currency Exchange Management System</p>

        {{-- Description --}}
        <p class="text-[--color-ink-muted] mb-10 leading-relaxed">
            Bank Negara Malaysia compliant MSB management system for foreign currency trading, till management, compliance reporting, and double-entry accounting.
        </p>

        {{-- Features --}}
        <div class="grid grid-cols-3 gap-6 mb-12">
            <div class="text-center">
                <div class="w-12 h-12 bg-[--color-success]/10 rounded-xl flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6 text-[--color-success]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                </div>
                <p class="text-sm font-medium text-[--color-ink]">AML/CFT Compliant</p>
            </div>
            <div class="text-center">
                <div class="w-12 h-12 bg-[--color-info]/10 rounded-xl flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6 text-[--color-info]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <p class="text-sm font-medium text-[--color-ink]">Double-Entry Accounting</p>
            </div>
            <div class="text-center">
                <div class="w-12 h-12 bg-[--color-warning]/10 rounded-xl flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6 text-[--color-warning]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                <p class="text-sm font-medium text-[--color-ink]">STR Reporting</p>
            </div>
        </div>

        {{-- Login --}}
        @guest
            <a href="{{ route('login') }}" class="btn btn-primary btn-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                </svg>
                Login to Dashboard
            </a>
        @else
            <a href="/dashboard" class="btn btn-primary btn-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
                Go to Dashboard
            </a>
        @endguest
    </div>
</div>
@endsection
