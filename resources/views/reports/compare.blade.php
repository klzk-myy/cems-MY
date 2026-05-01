@extends('layouts.base')

@section('title', 'Compare Reports')

@section('content')
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="bg-white border border-[--color-border] rounded-xl">
        <div class="px-6 py-4 border-b border-[--color-border]"><h3 class="text-base font-semibold text-[--color-ink]">Report 1</h3></div>
        <div class="p-6">
            @if(isset($report1) && $report1)
                <pre class="text-xs overflow-auto">{{ json_encode($report1, JSON_PRETTY_PRINT) }}</pre>
            @else
                <p class="text-[--color-ink-muted]">No report data</p>
            @endif
        </div>
    </div>
    <div class="bg-white border border-[--color-border] rounded-xl">
        <div class="px-6 py-4 border-b border-[--color-border]"><h3 class="text-base font-semibold text-[--color-ink]">Report 2</h3></div>
        <div class="p-6">
            @if(isset($report2) && $report2)
                <pre class="text-xs overflow-auto">{{ json_encode($report2, JSON_PRETTY_PRINT) }}</pre>
            @else
                <p class="text-[--color-ink-muted]">No report data</p>
            @endif
        </div>
    </div>
</div>
@endsection