@extends('layouts.base')

@section('title', 'Compliance Reporting')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">Compliance Reporting Center</h1>
    <p class="text-sm text-[--color-ink-muted]">Generate and schedule BNM compliance reports</p>
</div>
@endsection

@section('header-actions')
<div class="flex items-center gap-3">
    <a href="{{ route('compliance.reporting.schedule') }}" class="btn btn-secondary">Schedule</a>
    <a href="{{ route('compliance.reporting.deadlines') }}" class="btn btn-secondary">Deadlines</a>
    <a href="{{ route('compliance.reporting.generate') }}" class="btn btn-primary">Generate Report</a>
</div>
@endsection

@section('content')
{{-- Summary Stats --}}
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="stat-card">
        <p class="stat-card-label">Total Reports</p>
        <p class="stat-card-value">{{ number_format($summary['total'] ?? 0) }}</p>
    </div>
    <div class="stat-card">
        <p class="stat-card-label">CTOS Reports</p>
        <p class="stat-card-value">{{ number_format($summary['ctos'] ?? 0) }}</p>
    </div>
    <div class="stat-card">
        <p class="stat-card-label">STR Reports</p>
        <p class="stat-card-value">{{ number_format($summary['str'] ?? 0) }}</p>
    </div>
    <div class="stat-card">
        <p class="stat-card-label">Active Schedules</p>
        <p class="stat-card-value">{{ number_format($summary['active_schedules'] ?? 0) }}</p>
    </div>
</div>

{{-- Active Schedules --}}
@if($schedules->count() > 0)
<div class="card mb-6">
    <div class="card-header">
        <h3 class="card-title">Scheduled Reports</h3>
    </div>
    <div class="card-body">
        @foreach($schedules as $schedule)
        <div class="flex items-center justify-between p-4 border-b border-[--color-border] last:border-0">
            <div>
                <p class="font-medium">{{ $reportTypes[$schedule->report_type] ?? $schedule->report_type }}</p>
                <p class="text-sm text-[--color-ink-muted]">
                    {{ $schedule->getFriendlySchedule() }} - Next: {{ $schedule->next_run_at?->format('d M Y H:i') ?? 'N/A' }}
                </p>
            </div>
            <span class="badge badge-success">Active</span>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- Recent Reports --}}
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Recent Reports</h3>
    </div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Report Type</th>
                    <th>Period</th>
                    <th>Generated At</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reports as $report)
                <tr>
                    <td>
                        <span class="font-medium">{{ $reportTypes[$report->report_type] ?? $report->report_type }}</span>
                    </td>
                    <td class="text-[--color-ink-muted]">
                        {{ $report->period_start?->format('d M Y') ?? 'N/A' }} -
                        {{ $report->period_end?->format('d M Y') ?? 'N/A' }}
                    </td>
                    <td>{{ $report->generated_at?->format('d M Y H:i') ?? 'N/A' }}</td>
                    <td>
                        <span class="badge badge-default">{{ $report->status ?? 'Generated' }}</span>
                    </td>
                    <td>
                        <div class="table-actions">
                            <a href="{{ route('compliance.reporting.history.download', $report->id) }}" class="btn btn-ghost btn-icon" title="Download">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                </svg>
                            </a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5">
                        <div class="empty-state py-12">
                            <p class="empty-state-title">No reports generated yet</p>
                            <p class="empty-state-description">Generate your first compliance report using the button above</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection