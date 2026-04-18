@extends('layouts.base')

@section('title', 'Edit EDD Record')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Edit EDD Record</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="/compliance/edd/{{ $record->id }}">
                @csrf
                @method('PUT')
                <div class="form-group">
                    <label class="form-label">Risk Level</label>
                    <select name="risk_level" class="form-select" required>
                        <option value="Low" {{ $record->risk_level === 'Low' ? 'selected' : '' }}>Low</option>
                        <option value="Medium" {{ $record->risk_level === 'Medium' ? 'selected' : '' }}>Medium</option>
                        <option value="High" {{ $record->risk_level === 'High' ? 'selected' : '' }}>High</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-textarea" rows="4">{{ $record->notes ?? '' }}</textarea>
                </div>
                <div class="flex justify-end gap-3">
                    <a href="/compliance/edd/{{ $record->id }}" class="btn btn-ghost">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
