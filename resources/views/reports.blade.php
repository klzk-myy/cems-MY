@extends('layouts.base')

@section('title', 'Reports')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">Reports</h1>
    <p class="text-sm text-[--color-ink-muted]">BNM compliance and management reports</p>
</div>
@endsection

@section('content')
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    {{-- MSB2 Report --}}
    <a href="/reports/msb2" class="card hover:shadow-lg transition-shadow">
        <div class="card-body">
            <div class="w-12 h-12 bg-[--color-info]/10 rounded-xl flex items-center justify-center mb-4">
                <svg class="w-6 h-6 text-[--color-info]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
            </div>
            <h3 class="font-semibold text-lg mb-1">MSB2 Report</h3>
            <p class="text-sm text-[--color-ink-muted] mb-4">Daily transaction summary for Bank Negara Malaysia</p>
            <span class="badge badge-info">Daily</span>
        </div>
    </a>

    {{-- LCTR --}}
    <a href="/reports/lctr" class="card hover:shadow-lg transition-shadow">
        <div class="card-body">
            <div class="w-12 h-12 bg-[--color-warning]/10 rounded-xl flex items-center justify-center mb-4">
                <svg class="w-6 h-6 text-[--color-warning]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
            </div>
            <h3 class="font-semibold text-lg mb-1">LCTR</h3>
            <p class="text-sm text-[--color-ink-muted] mb-4">Large Cash Transaction Report (≥ RM 50,000)</p>
            <span class="badge badge-warning">Monthly</span>
        </div>
    </a>

    {{-- LMCA --}}
    <a href="/reports/lmca" class="card hover:shadow-lg transition-shadow">
        <div class="card-body">
            <div class="w-12 h-12 bg-[--color-success]/10 rounded-xl flex items-center justify-center mb-4">
                <svg class="w-6 h-6 text-[--color-success]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                </svg>
            </div>
            <h3 class="font-semibold text-lg mb-1">LMCA</h3>
            <p class="text-sm text-[--color-ink-muted] mb-4">Monthly Local Money Changing Activity Report</p>
            <span class="badge badge-success">Monthly</span>
        </div>
    </a>

    {{-- Quarterly LVR --}}
    <a href="/reports/quarterly-lvr" class="card hover:shadow-lg transition-shadow">
        <div class="card-body">
            <div class="w-12 h-12 bg-[--color-accent]/10 rounded-xl flex items-center justify-center mb-4">
                <svg class="w-6 h-6 text-[--color-accent]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                </svg>
            </div>
            <h3 class="font-semibold text-lg mb-1">Quarterly LVR</h3>
            <p class="text-sm text-[--color-ink-muted] mb-4">Large Value Report for high-value transactions</p>
            <span class="badge badge-accent">Quarterly</span>
        </div>
    </a>

    {{-- Position Limits --}}
    <a href="/reports/position-limit" class="card hover:shadow-lg transition-shadow">
        <div class="card-body">
            <div class="w-12 h-12 bg-[--color-primary]/10 rounded-xl flex items-center justify-center mb-4">
                <svg class="w-6 h-6 text-[--color-primary]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <h3 class="font-semibold text-lg mb-1">Position Limits</h3>
            <p class="text-sm text-[--color-ink-muted] mb-4">Currency position and limit monitoring</p>
            <span class="badge badge-default">Real-time</span>
        </div>
    </a>

    {{-- Report History --}}
    <a href="/reports/history" class="card hover:shadow-lg transition-shadow">
        <div class="card-body">
            <div class="w-12 h-12 bg-[--color-info]/10 rounded-xl flex items-center justify-center mb-4">
                <svg class="w-6 h-6 text-[--color-info]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <h3 class="font-semibold text-lg mb-1">Report History</h3>
            <p class="text-sm text-[--color-ink-muted] mb-4">View and download previously generated reports</p>
            <span class="badge badge-default">Archive</span>
        </div>
    </a>
</div>

{{-- Recent Reports --}}
<div class="card mt-8">
    <div class="card-header">
        <h3 class="card-title">Recent Report Runs</h3>
    </div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Report</th>
                    <th>Period</th>
                    <th>Generated By</th>
                    <th>Generated At</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recent_reports ?? [] as $report)
                <tr>
                    <td class="font-medium">{{ $report->name }}</td>
                    <td>{{ $report->period }}</td>
                    <td>{{ $report->generator->username ?? 'System' }}</td>
                    <td class="text-[--color-ink-muted]">{{ $report->created_at->format('d M Y, H:i') }}</td>
                    <td>
                        @php
                            $statusClass = match($report->status->value ?? '') {
                                'Completed' => 'badge-success',
                                'Failed' => 'badge-danger',
                                default => 'badge-warning'
                            };
                        @endphp
                        <span class="badge {{ $statusClass }}">{{ $report->status->label() ?? 'Processing' }}</span>
                    </td>
                    <td>
                        @if($report->status->value === 'Completed')
                            <a href="/reports/{{ $report->id }}/download" class="btn btn-ghost btn-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                </svg>
                                Download
                            </a>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center py-8 text-[--color-ink-muted]">No reports generated yet</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
