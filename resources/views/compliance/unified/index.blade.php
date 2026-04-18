@extends('layouts.base')

@section('title', 'Unified Compliance Alerts')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">Unified Compliance Alerts</h1>
    <p class="text-sm text-[--color-ink-muted]">Alerts and findings in one view</p>
</div>
@endsection

@section('header-actions')
<div class="flex items-center gap-3">
    <a href="/compliance/alerts" class="btn btn-ghost btn-sm">Alert Triage</a>
    <a href="/compliance/findings" class="btn btn-ghost btn-sm">Findings</a>
</div>
@endsection

@section('content')
{{-- Stats Bar --}}
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon bg-[--color-accent]/10 text-[--color-accent]">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
        <p class="stat-card-label">Total Items</p>
        <p class="stat-card-value">{{ number_format($stats['total'] ?? 0) }}</p>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon bg-[--color-danger]/10 text-[--color-danger]">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
        </div>
        <p class="stat-card-label">Critical</p>
        <p class="stat-card-value">{{ number_format($stats['critical'] ?? 0) }}</p>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon bg-[--color-warning]/10 text-[--color-warning]">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
        <p class="stat-card-label">Pending/Open</p>
        <p class="stat-card-value">{{ number_format($stats['pending'] ?? 0) }}</p>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon bg-[--color-success]/10 text-[--color-success]">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
        <p class="stat-card-label">Resolved Today</p>
        <p class="stat-card-value">{{ number_format($stats['resolved_today'] ?? 0) }}</p>
    </div>
</div>

{{-- Filters --}}
<div class="card mb-6">
    <div class="card-body">
        <form method="GET" action="/compliance/unified" class="flex flex-wrap gap-4 items-end">
            <div>
                <label class="form-label">Source</label>
                <select name="source" class="form-select">
                    <option value="">All</option>
                    <option value="alert" {{ ($request->get('source') ?? '') === 'alert' ? 'selected' : '' }}>Alerts Only</option>
                    <option value="finding" {{ ($request->get('source') ?? '') === 'finding' ? 'selected' : '' }}>Findings Only</option>
                </select>
            </div>
            <div>
                <label class="form-label">Priority</label>
                <select name="priority" class="form-select">
                    <option value="">All</option>
                    <option value="Critical" {{ ($request->get('priority') ?? '') === 'Critical' ? 'selected' : '' }}>Critical</option>
                    <option value="High" {{ ($request->get('priority') ?? '') === 'High' ? 'selected' : '' }}>High</option>
                    <option value="Medium" {{ ($request->get('priority') ?? '') === 'Medium' ? 'selected' : '' }}>Medium</option>
                    <option value="Low" {{ ($request->get('priority') ?? '') === 'Low' ? 'selected' : '' }}>Low</option>
                </select>
            </div>
            <div>
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="open" {{ ($request->get('status') ?? '') === 'open' ? 'selected' : '' }}>Open</option>
                    <option value="in_review" {{ ($request->get('status') ?? '') === 'in_review' ? 'selected' : '' }}>In Review</option>
                    <option value="resolved" {{ ($request->get('status') ?? '') === 'resolved' ? 'selected' : '' }}>Resolved</option>
                    <option value="dismissed" {{ ($request->get('status') ?? '') === 'dismissed' ? 'selected' : '' }}>Dismissed</option>
                </select>
            </div>
            <div>
                <label class="form-label">Type</label>
                <select name="type" class="form-select">
                    <option value="">All</option>
                    <optgroup label="Alert Types">
                        <option value="LargeAmount" {{ ($request->get('type') ?? '') === 'LargeAmount' ? 'selected' : '' }}>Large Amount</option>
                        <option value="SanctionsHit" {{ ($request->get('type') ?? '') === 'SanctionsHit' ? 'selected' : '' }}>Sanctions Hit</option>
                        <option value="Velocity" {{ ($request->get('type') ?? '') === 'Velocity' ? 'selected' : '' }}>Velocity</option>
                        <option value="Structuring" {{ ($request->get('type') ?? '') === 'Structuring' ? 'selected' : '' }}>Structuring</option>
                        <option value="EddRequired" {{ ($request->get('type') ?? '') === 'EddRequired' ? 'selected' : '' }}>EDD Required</option>
                        <option value="PepStatus" {{ ($request->get('type') ?? '') === 'PepStatus' ? 'selected' : '' }}>PEP Status</option>
                        <option value="SanctionMatch" {{ ($request->get('type') ?? '') === 'SanctionMatch' ? 'selected' : '' }}>Sanction Match</option>
                        <option value="HighRiskCustomer" {{ ($request->get('type') ?? '') === 'HighRiskCustomer' ? 'selected' : '' }}>High Risk Customer</option>
                        <option value="UnusualPattern" {{ ($request->get('type') ?? '') === 'UnusualPattern' ? 'selected' : '' }}>Unusual Pattern</option>
                        <option value="HighRiskCountry" {{ ($request->get('type') ?? '') === 'HighRiskCountry' ? 'selected' : '' }}>High Risk Country</option>
                        <option value="RoundAmount" {{ ($request->get('type') ?? '') === 'RoundAmount' ? 'selected' : '' }}>Round Amount</option>
                        <option value="CounterfeitCurrency" {{ ($request->get('type') ?? '') === 'CounterfeitCurrency' ? 'selected' : '' }}>Counterfeit Currency</option>
                    </optgroup>
                    <optgroup label="Finding Types">
                        <option value="Velocity_Exceeded" {{ ($request->get('type') ?? '') === 'Velocity_Exceeded' ? 'selected' : '' }}>Velocity Exceeded</option>
                        <option value="Structuring_Pattern" {{ ($request->get('type') ?? '') === 'Structuring_Pattern' ? 'selected' : '' }}>Structuring Pattern</option>
                        <option value="Sanction_Match" {{ ($request->get('type') ?? '') === 'Sanction_Match' ? 'selected' : '' }}>Sanction Match</option>
                        <option value="STR_Deadline" {{ ($request->get('type') ?? '') === 'STR_Deadline' ? 'selected' : '' }}>STR Deadline</option>
                        <option value="Counterfeit_Alert" {{ ($request->get('type') ?? '') === 'Counterfeit_Alert' ? 'selected' : '' }}>Counterfeit Alert</option>
                        <option value="Location_Anomaly" {{ ($request->get('type') ?? '') === 'Location_Anomaly' ? 'selected' : '' }}>Location Anomaly</option>
                        <option value="Currency_Flow_Anomaly" {{ ($request->get('type') ?? '') === 'Currency_Flow_Anomaly' ? 'selected' : '' }}>Currency Flow Anomaly</option>
                        <option value="Risk_Score_Change" {{ ($request->get('type') ?? '') === 'Risk_Score_Change' ? 'selected' : '' }}>Risk Score Change</option>
                        <option value="Aggregate_Transaction" {{ ($request->get('type') ?? '') === 'Aggregate_Transaction' ? 'selected' : '' }}>Aggregate Transaction</option>
                    </optgroup>
                </select>
            </div>
            <div>
                <label class="form-label">Customer</label>
                <input type="text" name="customer" value="{{ $request->get('customer') ?? '' }}" class="form-input" placeholder="Search customer name...">
            </div>
            <div>
                <label class="form-label">From Date</label>
                <input type="date" name="from_date" value="{{ $request->get('from_date') ?? '' }}" class="form-input">
            </div>
            <div>
                <label class="form-label">To Date</label>
                <input type="date" name="to_date" value="{{ $request->get('to_date') ?? '' }}" class="form-input">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="/compliance/unified" class="btn btn-ghost">Clear</a>
            </div>
        </form>
    </div>
</div>

{{-- Unified Table --}}
<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Source</th>
                    <th>Priority</th>
                    <th>Type</th>
                    <th>Customer</th>
                    <th>Status</th>
                    <th>Assigned To</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items ?? [] as $item)
                <tr>
                    <td>
                        @if($item['source'] === 'Alert')
                            <span class="badge badge-info">Alert</span>
                        @else
                            <span class="badge badge-finding">Finding</span>
                        @endif
                    </td>
                    <td>
                        @php
                            $priorityClass = match($item['priority'] ?? 'Low') {
                                'Critical' => 'badge-danger',
                                'High' => 'badge-warning',
                                'Medium' => 'badge-info',
                                default => 'badge-default'
                            };
                        @endphp
                        <span class="badge {{ $priorityClass }}">{{ $item['priority_label'] ?? 'Low' }}</span>
                    </td>
                    <td>
                        <span class="text-sm">{{ $item['type_label'] ?? 'Unknown' }}</span>
                    </td>
                    <td>
                        @if($item['customer'])
                            <a href="/customers/{{ $item['customer']['id'] }}" class="text-[--color-accent] hover:underline">
                                {{ $item['customer']['name'] }}
                            </a>
                        @else
                            <span class="text-[--color-ink-muted]">—</span>
                        @endif
                    </td>
                    <td>
                        @php
                            $statusClass = match($item['status'] ?? '') {
                                'Open', 'New' => 'badge-info',
                                'Under_Review', 'Reviewed', 'InProgress' => 'badge-warning',
                                'Resolved', 'CaseCreated' => 'badge-success',
                                'Dismissed', 'Rejected' => 'badge-default',
                                default => 'badge-default'
                            };
                        @endphp
                        <span class="badge {{ $statusClass }}">{{ $item['status_label'] ?? 'Unknown' }}</span>
                    </td>
                    <td>
                        @if($item['assigned_to'])
                            <span class="text-sm">{{ $item['assigned_to'] }}</span>
                        @else
                            <span class="badge badge-warning">Unassigned</span>
                        @endif
                    </td>
                    <td class="text-[--color-ink-muted]">{{ $item['date']->format('d M Y') }}</td>
                    <td>
                        <div class="table-actions">
                            <a href="{{ $item['url'] }}" class="btn btn-ghost btn-icon" title="View">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8">
                        <div class="empty-state py-12">
                            <div class="empty-state-icon">
                                <svg class="w-8 h-8 text-[--color-success]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <p class="empty-state-title">No items found</p>
                            <p class="empty-state-description">Try adjusting your filters or check back later</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if(($pagination['last_page'] ?? 1) > 1)
        <div class="card-footer">
            <p class="text-sm text-[--color-ink-muted]">
                Page {{ $pagination['current_page'] }} of {{ $pagination['last_page'] }}
                ({{ $pagination['total'] }} total items)
            </p>
            <div class="flex gap-2">
                @if($pagination['current_page'] > 1)
                    <a href="{{ request()->fullUrlWithQuery(['page' => $pagination['current_page'] - 1]) }}" class="btn btn-ghost btn-sm">Previous</a>
                @endif
                @if($pagination['current_page'] < $pagination['last_page'])
                    <a href="{{ request()->fullUrlWithQuery(['page' => $pagination['current_page'] + 1]) }}" class="btn btn-ghost btn-sm">Next</a>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection