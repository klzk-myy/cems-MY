@extends('layouts.base')

@section('title', 'Compliance Cases')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">Compliance Cases</h1>
    <p class="text-sm text-[--color-ink-muted]">Manage compliance investigation cases</p>
</div>
@endsection

@section('header-actions')
<div class="flex items-center gap-3">
    <a href="{{ route('compliance.cases.create') }}" class="btn btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        New Case
    </a>
</div>
@endsection

@section('content')
{{-- Summary Stats --}}
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon bg-[--color-info]/10 text-[--color-info]">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
            </div>
        </div>
        <p class="stat-card-label">Total Cases</p>
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
        <p class="stat-card-label">Open</p>
        <p class="stat-card-value">{{ number_format($summary['open'] ?? 0) }}</p>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon bg-[--color-danger]/10 text-[--color-danger]">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
        </div>
        <p class="stat-card-label">Escalated</p>
        <p class="stat-card-value">{{ number_format($summary['escalated'] ?? 0) }}</p>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon bg-[--color-success]/10 text-[--color-success]">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
        <p class="stat-card-label">Closed</p>
        <p class="stat-card-value">{{ number_format($summary['closed'] ?? 0) }}</p>
    </div>
</div>

{{-- Filters --}}
<div class="card mb-6">
    <div class="card-body">
        <form wire:submit="applyFilters" class="flex flex-wrap gap-4">
            <div class="form-group mb-0">
                <label class="form-label">Search</label>
                <input type="text" wire:model="search" class="form-input" placeholder="Case number or description...">
            </div>
            <div class="form-group mb-0">
                <label class="form-label">Status</label>
                <select wire:model="status" class="form-select">
                    <option value="">All Status</option>
                    @foreach($caseStatuses as $status)
                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group mb-0">
                <label class="form-label">Priority</label>
                <select wire:model="priority" class="form-select">
                    <option value="">All Priorities</option>
                    @foreach($casePriorities as $priority)
                        <option value="{{ $priority->value }}">{{ $priority->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group mb-0">
                <label class="form-label">Case Type</label>
                <select wire:model="caseType" class="form-select">
                    <option value="">All Types</option>
                    @foreach($caseTypes as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
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

{{-- Cases Table --}}
<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Case ID</th>
                    <th>Type</th>
                    <th>Customer</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Assigned To</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($cases as $case)
                <tr>
                    <td class="font-mono text-xs">#{{ $case->id }}</td>
                    <td>
                        <span class="text-sm">{{ $case->case_type->label() ?? 'Unknown' }}</span>
                    </td>
                    <td>
                        @if($case->customer)
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 bg-[--color-canvas-subtle] rounded-lg flex items-center justify-center text-xs">
                                {{ substr($case->customer->full_name, 0, 1) }}
                            </div>
                            <span class="text-sm font-medium">{{ $case->customer->full_name }}</span>
                        </div>
                        @else
                        <span class="text-[--color-ink-muted]">N/A</span>
                        @endif
                    </td>
                    <td>
                        @php
                            $priorityClass = match($case->priority->value ?? '') {
                                'Critical' => 'badge-danger',
                                'High' => 'badge-warning',
                                'Medium' => 'badge-info',
                                default => 'badge-default'
                            };
                        @endphp
                        <span class="badge {{ $priorityClass }}">{{ $case->priority->label() ?? 'Low' }}</span>
                    </td>
                    <td>
                        @php
                            $statusClass = match($case->status->value ?? '') {
                                'Closed' => 'badge-success',
                                'Escalated' => 'badge-danger',
                                'UnderReview' => 'badge-info',
                                default => 'badge-warning'
                            };
                        @endphp
                        <span class="badge {{ $statusClass }}">{{ $case->status->label() ?? 'Open' }}</span>
                    </td>
                    <td>
                        @if($case->assignee)
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 bg-[--color-canvas-subtle] rounded flex items-center justify-center text-xs">
                                {{ substr($case->assignee->username, 0, 1) }}
                            </div>
                            <span class="text-sm">{{ $case->assignee->username }}</span>
                        </div>
                        @else
                        <span class="badge badge-warning">Unassigned</span>
                        @endif
                    </td>
                    <td class="text-[--color-ink-muted]">{{ $case->created_at->format('d M Y') }}</td>
                    <td>
                        <div class="table-actions">
                            <a href="{{ route('compliance.cases.show', $case->id) }}" class="btn btn-ghost btn-icon" title="View">
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
                                <svg class="w-8 h-8 text-[--color-ink-muted]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                </svg>
                            </div>
                            <p class="empty-state-title">No cases found</p>
                            <p class="empty-state-description">Create a new case to start investigating</p>
                            <a href="{{ route('compliance.cases.create') }}" class="btn btn-primary mt-4">New Case</a>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($cases->hasPages())
        <div class="card-footer">
            {{ $cases->withQueryString()->links() }}
        </div>
    @endif
</div>
@endsection
