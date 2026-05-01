@extends('layouts.base')

@section('title', 'Compliance Summary')

@section('content')
<div class="bg-white border border-[--color-border] rounded-xl">
    <div class="px-6 py-4 border-b border-[--color-border]"><h3 class="text-base font-semibold text-[--color-ink]">Compliance Summary Report</h3></div>
    <div class="p-6">
        <pre class="text-xs overflow-auto">{{ json_encode($complianceSummary ?? [], JSON_PRETTY_PRINT) }}</pre>
    </div>
</div>
@endsection