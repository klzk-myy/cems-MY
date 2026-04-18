@extends('layouts.base')

@section('title', 'STR #' . ($draft->id ?? ''))

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">STR #{{ $draft->id ?? '' }}</h3>
        <span class="badge badge-warning">Draft</span>
    </div>
    <div class="card-body">
        <p><strong>Reference:</strong> {{ $draft->reference_number ?? 'Not assigned' }}</p>
        <p><strong>Description:</strong> {{ $draft->description ?? 'N/A' }}</p>
    </div>
</div>
@endsection
