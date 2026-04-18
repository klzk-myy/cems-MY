@extends('layouts.base')

@section('title', 'Compliance Findings')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">Compliance Findings</h1>
    <p class="text-sm text-[--color-ink-muted]">Automated monitor alerts</p>
</div>
@endsection

@section('content')
<div class="grid grid-cols-4 gap-4 mb-6">
    <div class="card">
        <div class="card-body">
            <p class="text-sm text-[--color-ink-muted]">Total</p>
            <p class="text-2xl font-bold">{{ $stats['total'] ?? 0 }}</p>
        </div>
    </div>
    <div class="card border-l-4 border-red-500">
        <div class="card-body">
            <p class="text-sm text-[--color-ink-muted]">Critical</p>
            <p class="text-2xl font-bold text-red-600">{{ $stats['by_severity']['Critical'] ?? 0 }}</p>
        </div>
    </div>
    <div class="card border-l-4 border-orange-500">
        <div class="card-body">
            <p class="text-sm text-[--color-ink-muted]">High</p>
            <p class="text-2xl font-bold text-orange-600">{{ $stats['by_severity']['High'] ?? 0 }}</p>
        </div>
    </div>
    <div class="card border-l-4 border-yellow-500">
        <div class="card-body">
            <p class="text-sm text-[--color-ink-muted]">Medium</p>
            <p class="text-2xl font-bold text-yellow-600">{{ $stats['by_severity']['Medium'] ?? 0 }}</p>
        </div>
    </div>
</div>

<div class="card mb-6">
    <div class="card-body">
        <form method="GET" action="/compliance/findings" class="flex gap-4 items-end flex-wrap">
            <div>
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="New" {{ request('status') == 'New' ? 'selected' : '' }}>New</option>
                    <option value="Reviewed" {{ request('status') == 'Reviewed' ? 'selected' : '' }}>Reviewed</option>
                    <option value="Dismissed" {{ request('status') == 'Dismissed' ? 'selected' : '' }}>Dismissed</option>
                    <option value="CaseCreated" {{ request('status') == 'CaseCreated' ? 'selected' : '' }}>Case Created</option>
                </select>
            </div>
            <div>
                <label class="form-label">Severity</label>
                <select name="severity" class="form-select">
                    <option value="">All</option>
                    <option value="Critical" {{ request('severity') == 'Critical' ? 'selected' : '' }}>Critical</option>
                    <option value="High" {{ request('severity') == 'High' ? 'selected' : '' }}>High</option>
                    <option value="Medium" {{ request('severity') == 'Medium' ? 'selected' : '' }}>Medium</option>
                    <option value="Low" {{ request('severity') == 'Low' ? 'selected' : '' }}>Low</option>
                </select>
            </div>
            <div>
                <label class="form-label">Type</label>
                <select name="type" class="form-select">
                    <option value="">All</option>
                    <option value="VelocityExceeded" {{ request('type') == 'VelocityExceeded' ? 'selected' : '' }}>Velocity Exceeded</option>
                    <option value="StructuringPattern" {{ request('type') == 'StructuringPattern' ? 'selected' : '' }}>Structuring Pattern</option>
                    <option value="SanctionMatch" {{ request('type') == 'SanctionMatch' ? 'selected' : '' }}>Sanction Match</option>
                    <option value="StrDeadline" {{ request('type') == 'StrDeadline' ? 'selected' : '' }}>STR Deadline</option>
                    <option value="CounterfeitAlert" {{ request('type') == 'CounterfeitAlert' ? 'selected' : '' }}>Counterfeit Alert</option>
                </select>
            </div>
            <div>
                <label class="form-label">From Date</label>
                <input type="date" name="from_date" value="{{ request('from_date') }}" class="form-input">
            </div>
            <div>
                <label class="form-label">To Date</label>
                <input type="date" name="to_date" value="{{ request('to_date') }}" class="form-input">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="/compliance/findings" class="btn btn-ghost">Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Severity</th>
                    <th>Type</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th>Generated</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($findings as $finding)
                <tr>
                    <td>
                        @switch($finding['severity'] ?? 'Low')
                            @case('Critical')
                                <span class="badge bg-red-900 text-white">Critical</span>
                                @break
                            @case('High')
                                <span class="badge badge-danger">High</span>
                                @break
                            @case('Medium')
                                <span class="badge badge-warning">Medium</span>
                                @break
                            @case('Low')
                                <span class="badge badge-success">Low</span>
                                @break
                            @default
                                <span class="badge badge-default">{{ $finding['severity'] ?? 'N/A' }}</span>
                        @endswitch
                    </td>
                    <td>
                        @switch($finding['finding_type'] ?? 'Unknown')
                            @case('VelocityExceeded')
                                <span>Velocity Exceeded</span>
                                @break
                            @case('StructuringPattern')
                                <span>Structuring Pattern</span>
                                @break
                            @case('SanctionMatch')
                                <span>Sanction Match</span>
                                @break
                            @case('StrDeadline')
                                <span>STR Deadline</span>
                                @break
                            @case('CounterfeitAlert')
                                <span>Counterfeit Alert</span>
                                @break
                            @case('LocationAnomaly')
                                <span>Location Anomaly</span>
                                @break
                            @case('CurrencyFlowAnomaly')
                                <span>Currency Flow Anomaly</span>
                                @break
                            @case('RiskScoreChange')
                                <span>Risk Score Change</span>
                                @break
                            @default
                                <span>{{ $finding['finding_type'] ?? 'N/A' }}</span>
                        @endswitch
                    </td>
                    <td>
                        @if(($finding['subject_type'] ?? '') === 'Customer')
                            <a href="/customers/{{ $finding['subject_id'] }}" class="text-[--color-accent] hover:underline">
                                Customer #{{ $finding['subject_id'] }}
                            </a>
                        @elseif(($finding['subject_type'] ?? '') === 'Transaction')
                            <a href="/transactions/{{ $finding['subject_id'] }}" class="text-[--color-accent] hover:underline">
                                Transaction #{{ $finding['subject_id'] }}
                            </a>
                        @else
                            {{ $finding['subject_type'] ?? 'N/A' }} #{{ $finding['subject_id'] ?? 'N/A' }}
                        @endif
                    </td>
                    <td>
                        @switch($finding['status'] ?? 'New')
                            @case('New')
                                <span class="badge badge-info">New</span>
                                @break
                            @case('Reviewed')
                                <span class="badge badge-warning">Reviewed</span>
                                @break
                            @case('Dismissed')
                                <span class="badge badge-default">Dismissed</span>
                                @break
                            @case('CaseCreated')
                                <span class="badge badge-success">Case Created</span>
                                @break
                            @default
                                <span class="badge badge-default">{{ $finding['status'] ?? 'N/A' }}</span>
                        @endswitch
                    </td>
                    <td>{{ isset($finding['generated_at']) ? \Carbon\Carbon::parse($finding['generated_at'])->format('d M Y H:i') : 'N/A' }}</td>
                    <td>
                        <a href="/compliance/findings/{{ $finding['id'] }}" class="btn btn-ghost btn-sm">View</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center py-12 text-[--color-ink-muted]">No findings found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($pagination['last_page'] > 1)
    <div class="card-footer flex justify-between items-center">
        <p class="text-sm text-[--color-ink-muted]">
            Page {{ $pagination['current_page'] }} of {{ $pagination['last_page'] }}
        </p>
        <div class="flex gap-2">
            @if($pagination['current_page'] > 1)
                <a href="/compliance/findings?{{ http_build_query(array_merge(request()->except('page'), ['page' => $pagination['current_page'] - 1])) }}" class="btn btn-ghost btn-sm">Previous</a>
            @endif
            @if($pagination['current_page'] < $pagination['last_page'])
                <a href="/compliance/findings?{{ http_build_query(array_merge(request()->except('page'), ['page' => $pagination['current_page'] + 1])) }}" class="btn btn-ghost btn-sm">Next</a>
            @endif
        </div>
    </div>
    @endif
</div>
@endsection
