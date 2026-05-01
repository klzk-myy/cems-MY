@extends('layouts.base')

@section('title', 'LMCA Report')

@section('content')
<div class="bg-white border border-[--color-border] rounded-xl">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">Local Money Changing Activity Report</h3>
        <span class="text-sm text-[--color-ink-muted]">{{ $month ?? date('F Y') }}</span>
    </div>
    <div class="p-6">
        <pre class="text-xs overflow-auto">{{ json_encode($reportData ?? [], JSON_PRETTY_PRINT) }}</pre>
    </div>
</div>
@endsection