@extends('layouts.base')

@section('title', 'Quarterly LVR Report')

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Quarterly Large Value Report</h3>
        <span class="text-sm text-[--color-ink-muted]">{{ $quarter ?? 'Q' . ceil(date('m') / 3) . ' ' . date('Y') }}</span>
    </div>
    <div class="card-body">
        <pre class="text-xs overflow-auto">{{ json_encode($reportData ?? [], JSON_PRETTY_PRINT) }}</pre>
    </div>
</div>
@endsection
