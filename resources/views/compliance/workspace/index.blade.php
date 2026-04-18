@extends('layouts.base')

@section('title', 'Compliance Workspace')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">Compliance Workspace</h1>
    <p class="text-sm text-[--color-ink-muted]">Unified compliance management</p>
</div>
@endsection

@section('content')
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <a href="/compliance/alerts" class="card p-6 hover:shadow-lg transition-shadow">
        <div class="w-12 h-12 bg-[--color-danger]/10 rounded-xl flex items-center justify-center mb-4">
            <svg class="w-6 h-6 text-[--color-danger]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
            </svg>
        </div>
        <h3 class="font-semibold text-lg mb-1">Alert Triage</h3>
        <p class="text-sm text-[--color-ink-muted]">Review and resolve alerts</p>
    </a>

    <a href="/compliance/cases" class="card p-6 hover:shadow-lg transition-shadow">
        <div class="w-12 h-12 bg-[--color-warning]/10 rounded-xl flex items-center justify-center mb-4">
            <svg class="w-6 h-6 text-[--color-warning]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
            </svg>
        </div>
        <h3 class="font-semibold text-lg mb-1">Cases</h3>
        <p class="text-sm text-[--color-ink-muted]">Manage investigation cases</p>
    </a>

    <a href="/compliance/edd" class="card p-6 hover:shadow-lg transition-shadow">
        <div class="w-12 h-12 bg-[--color-info]/10 rounded-xl flex items-center justify-center mb-4">
            <svg class="w-6 h-6 text-[--color-info]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
        </div>
        <h3 class="font-semibold text-lg mb-1">EDD Records</h3>
        <p class="text-sm text-[--color-ink-muted]">Enhanced due diligence</p>
    </a>
</div>
@endsection
