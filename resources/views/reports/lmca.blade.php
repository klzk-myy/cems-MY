@extends('layouts.base')

@section('title', 'LMCA Report')

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Local Money Changing Activity Report</h3>
        <span class="text-sm text-[--color-ink-muted]">{{ $month ?? date('F Y') }}</span>
    </div>
    <div class="card-body">
        <pre class="text-xs overflow-auto">{{ json_encode($reportData ?? [], JSON_PRETTY_PRINT) }}</pre>
    </div>
</div>
@endsection
