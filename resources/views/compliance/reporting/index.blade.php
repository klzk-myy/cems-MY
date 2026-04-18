@extends('layouts.base')

@section('title', 'Compliance Reporting')

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Compliance Reports</h3>
        <a href="/compliance/reporting/generate" class="btn btn-primary btn-sm">Generate Report</a>
    </div>
    <div class="card-body">
        @forelse($reports ?? [] as $report)
        <div class="flex items-center justify-between p-4 border-b border-[--color-border] last:border-0">
            <div>
                <p class="font-medium">{{ $report->name }}</p>
                <p class="text-sm text-[--color-ink-muted]">{{ $report->created_at->format('d M Y') }}</p>
            </div>
            <a href="/compliance/reporting/{{ $report->id }}/download" class="btn btn-ghost btn-sm">Download</a>
        </div>
        @empty
        <p class="text-center py-8 text-[--color-ink-muted]">No reports generated yet</p>
        @endforelse
    </div>
</div>
@endsection
