@extends('layouts.base')

@section('title', 'Create Stock Transfer')

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">Create Stock Transfer</h3></div>
    <div class="card-body">
        <form method="POST" action="{{ route('stock-transfers.store') }}">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="form-label">Transfer Date</label>
                    <input type="date" name="transfer_date" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">Source Branch</label>
                    <select name="source_branch_id" class="form-input" required>
                        <option value="">Select Source</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Destination Branch</label>
                    <select name="destination_branch_id" class="form-input" required>
                        <option value="">Select Destination</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-input" rows="2"></textarea>
                </div>
            </div>
            <div class="mt-6 flex gap-3">
                <button type="submit" class="btn btn-primary">Create Transfer</button>
                <a href="{{ route('stock-transfers.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection