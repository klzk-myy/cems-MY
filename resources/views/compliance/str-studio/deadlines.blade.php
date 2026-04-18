@extends('layouts.base')

@section('title', 'STR Filing Deadlines')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">STR Filing Deadlines</h1>
    <p class="text-sm text-[--color-ink-muted]">Track pending STR submissions and deadlines</p>
</div>
@endsection

@section('header-actions')
<a href="/str" class="btn btn-ghost">Back to STRs</a>
@endsection

@section('content')
<div class="grid grid-cols-4 gap-4 mb-6">
    <div class="card">
        <div class="card-body">
            <p class="text-sm text-[--color-ink-muted]">Overdue</p>
            <p class="text-2xl font-bold text-red-600">{{ $deadlines['overdue_count'] }}</p>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <p class="text-sm text-[--color-ink-muted]">Upcoming (7 days)</p>
            <p class="text-2xl font-bold text-yellow-600">{{ $deadlines['upcoming_count'] }}</p>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <p class="text-sm text-[--color-ink-muted]">Urgent (24h)</p>
            <p class="text-2xl font-bold text-orange-600">{{ $deadlines['urgent_count'] }}</p>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <p class="text-sm text-[--color-ink-muted]">Next Deadline</p>
            <p class="text-2xl font-bold">{{ $deadlines['next_deadline']?->format('d M Y') ?? 'N/A' }}</p>
        </div>
    </div>
</div>

@if($deadlines['overdue_count'] > 0)
<div class="card mb-6 border-l-4 border-red-500">
    <div class="card-header">
        <h3 class="card-title text-red-600">Overdue Reports</h3>
    </div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Customer</th>
                    <th>Status</th>
                    <th>Deadline</th>
                    <th>Days Overdue</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($deadlines['overdue_reports'] as $report)
                <tr>
                    <td class="font-mono">{{ $report->str_no ?? 'N/A' }}</td>
                    <td>{{ $report->customer->name ?? 'Unknown' }}</td>
                    <td><span class="badge badge-danger">{{ $report->status->label() }}</span></td>
                    <td class="text-red-600 font-semibold">{{ $report->filing_deadline?->format('d M Y') }}</td>
                    <td class="text-red-600">{{ $report->filing_deadline?->diffForHumans() }}</td>
                    <td>
                        <a href="/str/{{ $report->id }}" class="btn btn-ghost btn-sm">View</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center py-8 text-[--color-ink-muted]">No overdue reports</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Upcoming Deadlines</h3>
    </div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Customer</th>
                    <th>Status</th>
                    <th>Deadline</th>
                    <th>Time Remaining</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($deadlines['upcoming_reports'] as $report)
                <tr class="{{ $report->filing_deadline && $report->filing_deadline->lte(now()->addHours(24)) ? 'bg-orange-50' : '' }}">
                    <td class="font-mono">{{ $report->str_no ?? 'N/A' }}</td>
                    <td>{{ $report->customer->name ?? 'Unknown' }}</td>
                    <td><span class="badge badge-warning">{{ $report->status->label() }}</span></td>
                    <td>{{ $report->filing_deadline?->format('d M Y') }}</td>
                    <td>{{ $report->filing_deadline?->diffForHumans() }}</td>
                    <td>
                        <a href="/str/{{ $report->id }}" class="btn btn-ghost btn-sm">View</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center py-8 text-[--color-ink-muted]">No upcoming deadlines</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
