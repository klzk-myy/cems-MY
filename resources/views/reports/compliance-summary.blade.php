@extends('layouts.base')

@section('title', 'Compliance Summary')

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">Compliance Summary Report</h3></div>
    <div class="card-body">
        <pre class="text-xs overflow-auto">{{ json_encode($complianceSummary ?? [], JSON_PRETTY_PRINT) }}</pre>
    </div>
</div>
@endsection
