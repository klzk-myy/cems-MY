@extends('layouts.base')

@section('title', 'Compare Reports')

@section('content')
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="card">
        <div class="card-header"><h3 class="card-title">Report 1</h3></div>
        <div class="card-body">
            @if(isset($report1) && $report1)
                <pre class="text-xs overflow-auto">{{ json_encode($report1, JSON_PRETTY_PRINT) }}</pre>
            @else
                <p class="text-[--color-ink-muted]">No report data</p>
            @endif
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h3 class="card-title">Report 2</h3></div>
        <div class="card-body">
            @if(isset($report2) && $report2)
                <pre class="text-xs overflow-auto">{{ json_encode($report2, JSON_PRETTY_PRINT) }}</pre>
            @else
                <p class="text-[--color-ink-muted]">No report data</p>
            @endif
        </div>
    </div>
</div>
@endsection
