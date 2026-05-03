@extends('layouts.base')

@section('title', 'Monthly Trends')

@section('header-title')
<h1 class="text-xl font-semibold text-[--color-ink]">Monthly Trends</h1>
@endsection

@section('content')
<div class="flex items-center justify-between mb-6">
    <a href="{{ route('reports.index') }}" class="btn btn-ghost">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
        </svg>
        Back to Reports
    </a>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="text-lg font-semibold">Monthly Trends</h2>
    </div>
    <div class="card-body">
        <p class="text-[--color-ink-muted]">Monthly trends content will be displayed here.</p>
    </div>
</div>
@endsection