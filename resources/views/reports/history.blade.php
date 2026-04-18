@extends('layouts.base')

@section('title', 'Report History')

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">Report History</h3></div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Report</th>
                    <th>Period</th>
                    <th>Generated</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reports ?? [] as $report)
                <tr>
                    <td>{{ $report['name'] ?? 'N/A' }}</td>
                    <td>{{ $report['period'] ?? 'N/A' }}</td>
                    <td>{{ $report['created_at']->format('d M Y') }}</td>
                    <td>
                        @if(($report['status'] ?? '') === 'completed')
                            <span class="badge badge-success">Completed</span>
                        @else
                            <span class="badge badge-warning">Pending</span>
                        @endif
                    </td>
                    <td>
                        <a href="/reports/{{ $report['id'] }}/download" class="btn btn-ghost btn-sm">Download</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center py-8 text-[--color-ink-muted]">No reports found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
