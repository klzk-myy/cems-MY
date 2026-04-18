@extends('layouts.base')

@section('title', 'Open Counter')

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">Open Counter - {{ $counter->name ?? 'N/A' }}</h3></div>
    <div class="card-body">
        <form method="POST" action="{{ route('counters.open', $counter->id ?? 0) }}">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="form-label">Opening Float (MYR)</label>
                    <input type="number" step="0.01" name="opening_float" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-input" rows="2"></textarea>
                </div>
            </div>
            <div class="mt-6 flex gap-3">
                <button type="submit" class="btn btn-primary">Open Counter</button>
                <a href="{{ route('counters.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection