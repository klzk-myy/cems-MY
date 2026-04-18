@extends('layouts.base')

@section('title', 'New STR')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="card">
        <div class="card-header"><h3 class="card-title">Create Suspicious Transaction Report</h3></div>
        <div class="card-body">
            <form method="POST" action="/str">
                @csrf
                <div class="form-group">
                    <label class="form-label">Customer</label>
                    <select name="customer_id" class="form-select" required>
                        <option value="">Select customer...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Suspicious Activity Description</label>
                    <textarea name="description" class="form-textarea" rows="6" required placeholder="Describe the suspicious activity..."></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Amount Involved (MYR)</label>
                    <input type="number" name="amount" class="form-input" step="0.01" required>
                </div>
                <div class="flex justify-end gap-3">
                    <a href="/str" class="btn btn-ghost">Cancel</a>
                    <button type="submit" class="btn btn-primary">Create Draft</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
