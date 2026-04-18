@extends('layouts.base')

@section('title', 'New EDD Record')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Create EDD Record</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="/compliance/edd">
                @csrf
                <div class="form-group">
                    <label class="form-label">Customer</label>
                    <select name="customer_id" class="form-select" required>
                        <option value="">Select customer...</option>
                        @foreach($customers ?? [] as $c)
                            <option value="{{ $c->id }}">{{ $c->full_name }} ({{ $c->ic_number }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Risk Level</label>
                    <select name="risk_level" class="form-select" required>
                        <option value="Low">Low</option>
                        <option value="Medium">Medium</option>
                        <option value="High">High</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-textarea" rows="4"></textarea>
                </div>
                <div class="flex justify-end gap-3">
                    <a href="/compliance/edd" class="btn btn-ghost">Cancel</a>
                    <button type="submit" class="btn btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
