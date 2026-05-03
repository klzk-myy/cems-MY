@extends('layouts.base')

@section('title', 'Reconciliation Report')

@section('header-title')
<h1 class="text-xl font-semibold text-[--color-ink]">Reconciliation Report</h1>
@endsection

@section('content')
<div class="flex items-center justify-between mb-6">
    <a href="{{ route('accounting.reconciliation.index') }}" class="btn btn-ghost">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
        </svg>
        Back to Reconciliation
    </a>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="text-lg font-semibold">Reconciliation Report</h2>
    </div>
    <div class="card-body">
        <p class="text-[--color-ink-muted]">Reconciliation report will be displayed here.</p>
    </div>
</div>
@endsection