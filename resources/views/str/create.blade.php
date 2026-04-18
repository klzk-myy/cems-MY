@extends('layouts.base')

@section('title', 'Create STR')

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">Create Suspicious Transaction Report</h3></div>
    <div class="card-body">
        <form method="POST" action="{{ route('str.store') }}">
            @csrf
            <div class="space-y-6">
                <div>
                    <label class="form-label">Report Date</label>
                    <input type="date" name="report_date" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">Related Alert</label>
                    <select name="alert_id" class="form-input">
                        <option value="">Select Alert (Optional)</option>
                        @foreach($pendingAlerts ?? [] as $alert)
                        <option value="{{ $alert->id }}">{{ $alert->id }} - {{ $alert->type ?? 'N/A' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Customer</label>
                    <select name="customer_id" class="form-input">
                        <option value="">Select Customer</option>
                        @if(isset($customer))
                        <option value="{{ $customer->id }}" selected>{{ $customer->name }} ({{ $customer->ic_number ?? 'N/A' }})</option>
                        @endif
                    </select>
                </div>
                <div>
                    <label class="form-label">Suspicious Activity Description</label>
                    <textarea name="description" class="form-input" rows="4" required></textarea>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="form-label">Transaction Amount (MYR)</label>
                        <input type="number" step="0.01" name="amount" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Currency</label>
                        <input type="text" name="currency" class="form-input" value="{{ $customer->currency ?? 'MYR' }}">
                    </div>
                </div>
            </div>
            <div class="mt-6 flex gap-3">
                <button type="submit" class="btn btn-primary">Create STR</button>
                <a href="{{ route('str.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection