@extends('layouts.base')

@section('title', 'AML Rule: ' . ($rule->name ?? ''))

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">{{ $rule->name ?? 'Rule' }}</h3>
        @if($rule->is_active)
            <span class="badge badge-success">Active</span>
        @else
            <span class="badge badge-default">Inactive</span>
        @endif
    </div>
    <div class="card-body">
        <p><strong>Type:</strong> {{ $rule->type->label() ?? 'N/A' }}</p>
        <p><strong>Description:</strong> {{ $rule->description ?? 'No description' }}</p>
        <p><strong>Conditions:</strong> {{ $rule->conditions ?? 'N/A' }}</p>
        <p><strong>Hit Count:</strong> {{ number_format($rule->hit_count ?? 0) }}</p>
    </div>
</div>
@endsection
