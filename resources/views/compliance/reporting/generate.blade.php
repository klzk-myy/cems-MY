@extends('layouts.base')

@section('title', 'Generate Report')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="card">
        <div class="card-header"><h3 class="card-title">Generate Compliance Report</h3></div>
        <div class="card-body">
            <form method="POST" action="/compliance/reporting/generate">
                @csrf
                <div class="form-group">
                    <label class="form-label">Report Type</label>
                    <select name="report_type" class="form-select" required>
                        @foreach($reportTypes ?? [] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Period</label>
                    <input type="month" name="period" class="form-input" required>
                </div>
                <div class="flex justify-end gap-3">
                    <a href="/compliance/reporting" class="btn btn-ghost">Cancel</a>
                    <button type="submit" class="btn btn-primary">Generate</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
