@extends('layouts.base')

@section('title', 'Alert Triage')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">Alert Triage</h1>
    <p class="text-sm text-[--color-ink-muted]">Review and resolve compliance alerts</p>
</div>
@endsection

@section('header-actions')
<div class="flex items-center gap-3">
    <a href="{{ route('compliance.alerts.index', ['status' => 'Open']) }}" class="btn btn-secondary">
        Pending Only
    </a>
</div>
@endsection

@section('content')
{{-- Summary Stats --}}
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon bg-[--color-danger]/10 text-[--color-danger]">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
        </div>
        <p class="stat-card-label">Total Alerts</p>
        <p class="stat-card-value">{{ number_format($summary['total'] ?? 0) }}</p>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon bg-[--color-warning]/10 text-[--color-warning]">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
        <p class="stat-card-label">Pending Review</p>
        <p class="stat-card-value">{{ number_format($summary['pending'] ?? 0) }}</p>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon bg-[--color-info]/10 text-[--color-info]">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
        <p class="stat-card-label">In Progress</p>
        <p class="stat-card-value">{{ number_format($summary['in_progress'] ?? 0) }}</p>
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
        <p class="stat-card-value">{{ number_format($summary['resolved_today'] ?? 0) }}</p>
    </div>
</div>

{{-- Filters --}}
<div class="card mb-6">
    <div class="card-body">
        <form wire:submit="applyFilters" class="flex flex-wrap gap-4">
            <div class="form-group mb-0">
                <label class="form-label">Search</label>
                <input type="text" wire:model="search" class="form-input" placeholder="Alert ID or description...">
            </div>
            <div class="form-group mb-0">
                <label class="form-label">Status</label>
                <select wire:model="status" class="form-select">
                    <option value="">All Status</option>
                    @foreach($alertStatuses as $status)
                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group mb-0">
                <label class="form-label">Priority</label>
                <select wire:model="priority" class="form-select">
                    <option value="">All Priorities</option>
                    @foreach($alertPriorities as $priority)
                        <option value="{{ $priority->value }}">{{ $priority->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group mb-0">
                <label class="form-label">Assigned To</label>
                <select wire:model="assignedTo" class="form-select">
                    <option value="">All</option>
                    <option value="unassigned">Unassigned</option>
                </select>
            </div>
            <div class="flex items-end gap-3">
                <button type="button" wire:click="clearFilters" class="btn btn-ghost">Clear Filters</button>
                <button type="submit" class="btn btn-secondary">Apply Filters</button>
            </div>
        </form>
    </div>
</div>

{{-- Alerts Table --}}
<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Priority</th>
                    <th>Type</th>
                    <th>Customer</th>
                    <th>Description</th>
                    <th>Assigned To</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($alerts as $alert)
                <tr>
                    <td>
                        @php
                            $priorityClass = match($alert->priority->value ?? '') {
                                'critical' => 'badge-danger',
                                'high' => 'badge-warning',
                                'medium' => 'badge-info',
                                default => 'badge-default'
                            };
                        @endphp
                        <span class="badge {{ $priorityClass }}">{{ $alert->priority->label() ?? 'Low' }}</span>
                    </td>
                    <td>
                        <span class="text-sm">{{ $alert->type->label() ?? 'Unknown' }}</span>
                    </td>
                    <td>
                        @if($alert->customer)
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-[--color-canvas-subtle] rounded-lg flex items-center justify-center text-xs">
                                {{ substr($alert->customer->full_name, 0, 1) }}
                            </div>
                            <div>
                                <p class="text-sm font-medium">{{ $alert->customer->full_name }}</p>
                                <p class="text-xs text-[--color-ink-muted]">{{ $alert->customer->id_type ?? 'N/A' }}</p>
                            </div>
                        </div>
                        @else
                        <span class="text-[--color-ink-muted]">System</span>
                        @endif
                    </td>
                    <td class="max-w-xs truncate">{{ $alert->reason }}</td>
                    <td>
                        @if($alert->assignedTo)
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 bg-[--color-canvas-subtle] rounded flex items-center justify-center text-xs">
                                {{ substr($alert->assignedTo->username, 0, 1) }}
                            </div>
                            <span class="text-sm">{{ $alert->assignedTo->username }}</span>
                        </div>
                        @else
                        <span class="badge badge-warning">Unassigned</span>
                        @endif
                    </td>
                    <td>
                        @php
                            $statusClass = match($alert->status->value ?? '') {
                                'Resolved' => 'badge-success',
                                'Rejected' => 'badge-default',
                                'UnderReview' => 'badge-info',
                                default => 'badge-warning'
                            };
                        @endphp
                        <span class="badge {{ $statusClass }}">{{ $alert->status->label() ?? 'Pending' }}</span>
                    </td>
                    <td class="text-[--color-ink-muted]">{{ $alert->created_at->diffForHumans() }}</td>
                    <td>
                        <div class="table-actions">
                            <a href="{{ route('compliance.alerts.show', $alert->id) }}" class="btn btn-ghost btn-icon" title="View">
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
                            <p class="empty-state-title">No alerts found</p>
                            <p class="empty-state-description">All systems are operating normally</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($alerts->hasPages())
        <div class="card-footer">
            {{ $alerts->withQueryString()->links() }}
        </div>
    @endif
</div>
@endsection
