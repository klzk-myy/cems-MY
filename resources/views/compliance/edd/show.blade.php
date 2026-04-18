@extends('layouts.base')

@section('title', 'EDD Record #' . ($record->id ?? ''))

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">EDD Record</h3>
        <a href="/compliance/edd" class="btn btn-ghost btn-sm">Back</a>
    </div>
    <div class="card-body">
        <p><strong>Customer:</strong> {{ $record->customer->full_name ?? 'N/A' }}</p>
        <p><strong>Risk Level:</strong> {{ $record->risk_level ?? 'N/A' }}</p>
        <p><strong>Status:</strong> {{ $record->status->label() ?? 'Pending' }}</p>
        <p><strong>Created:</strong> {{ $record->created_at->format('d M Y') }}</p>
    </div>
</div>
@endsection
