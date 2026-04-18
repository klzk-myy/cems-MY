@extends('layouts.base')

@section('title', 'Edit STR')

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">Edit STR</h3></div>
    <div class="card-body">
        <form method="POST" action="{{ route('str.update', $str->id ?? 0) }}">
            @csrf
            @method('PUT')
            <div class="space-y-6">
                <div>
                    <label class="form-label">Report Date</label>
                    <input type="date" name="report_date" class="form-input" value="{{ $str->report_date ?? '' }}" required>
                </div>
                <div>
                    <label class="form-label">Suspicious Activity Description</label>
                    <textarea name="description" class="form-input" rows="4" required>{{ $str->description ?? '' }}</textarea>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="form-label">Transaction Amount (MYR)</label>
                        <input type="number" step="0.01" name="amount" class="form-input" value="{{ $str->amount ?? 0 }}">
                    </div>
                    <div>
                        <label class="form-label">Status</label>
                        <select name="status" class="form-input">
                            @if(isset($str->status))
                                @statuslabel($str->status)
                            @endif
                        </select>
                    </div>
                </div>
            </div>
            <div class="mt-6 flex gap-3">
                <button type="submit" class="btn btn-primary">Update STR</button>
                <a href="{{ route('str.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection