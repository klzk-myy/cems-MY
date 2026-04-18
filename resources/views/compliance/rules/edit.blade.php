@extends('layouts.base')

@section('title', 'Edit AML Rule')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="card">
        <div class="card-header"><h3 class="card-title">Edit AML Rule</h3></div>
        <div class="card-body">
            <form method="POST" action="/compliance/rules/{{ $rule->id }}">
                @csrf
                @method('PUT')
                <div class="form-group">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-input" value="{{ $rule->name }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" rows="3">{{ $rule->description }}</textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Conditions (JSON)</label>
                    <textarea name="conditions" class="form-textarea font-mono" rows="4">{{ $rule->conditions }}</textarea>
                </div>
                <div class="flex justify-end gap-3">
                    <a href="/compliance/rules/{{ $rule->id }}" class="btn btn-ghost">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
