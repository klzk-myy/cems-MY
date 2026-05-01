@extends('layouts.base')

@section('title', 'Reporting - CEMS-MY')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold text-[--color-ink]">Compliance Reporting</h1>
        <p class="text-sm text-[--color-ink-muted] mt-1">Regulatory report generation and scheduling</p>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="card p-6">
        <h3 class="text-base font-semibold text-[--color-ink] mb-2">LCTR</h3>
        <p class="text-sm text-[--color-ink-muted]">Large Cash Transaction Reports</p>
        <a href="{{ route('reports.lctr.generate') }}" class="mt-4 inline-flex items-center text-sm text-[--color-accent] hover:underline">
            Generate Report
        </a>
    </div>
    <div class="card p-6">
        <h3 class="text-base font-semibold text-[--color-ink] mb-2">LMCA</h3>
        <p class="text-sm text-[--color-ink-muted]">Leakage of Currency Reports</p>
        <a href="{{ route('reports.lmca.generate') }}" class="mt-4 inline-flex items-center text-sm text-[--color-accent] hover:underline">
            Generate Report
        </a>
    </div>
    <div class="card p-6">
        <h3 class="text-base font-semibold text-[--color-ink] mb-2">MSB2</h3>
        <p class="text-sm text-[--color-ink-muted]">MSB Annual Return</p>
        <a href="{{ route('reports.msb2.generate') }}" class="mt-4 inline-flex items-center text-sm text-[--color-accent] hover:underline">
            Generate Report
        </a>
    </div>
    <div class="card p-6">
        <h3 class="text-base font-semibold text-[--color-ink] mb-2">STR</h3>
        <p class="text-sm text-[--color-ink-muted]">Suspicious Transaction Reports</p>
        <a href="{{ route('str.index') }}" class="mt-4 inline-flex items-center text-sm text-[--color-accent] hover:underline">
            View STRs
        </a>
    </div>
</div>

<div class="card">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">Recent Reports</h3>
    </div>
    <div class="p-6">
        <p class="text-sm text-[--color-ink-muted] text-center">Report history will appear here</p>
    </div>
</div>
@endsection