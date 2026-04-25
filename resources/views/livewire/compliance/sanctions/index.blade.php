@extends('layouts.base')

@section('title', 'Sanction Lists')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">Sanction Lists</h1>
    <p class="text-sm text-[--color-ink-muted]">Manage sanctions lists and entries</p>
</div>
@endsection

@section('header-actions')
<a href="{{ route('compliance.sanctions.import-logs') }}" class="btn btn-ghost">
    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
    </svg>
    Import Logs
</a>
@endsection

@section('content')
{{-- Summary Stats --}}
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="stat-card">
        <div class="card-body">
            <p class="text-sm text-[--color-ink-muted]">Total Lists</p>
            <p class="text-2xl font-bold">{{ number_format($summary['total_lists'] ?? 0) }}</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="card-body">
            <p class="text-sm text-[--color-ink-muted]">Active Lists</p>
            <p class="text-2xl font-bold">{{ number_format($summary['active_lists'] ?? 0) }}</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="card-body">
            <p class="text-sm text-[--color-ink-muted]">Total Entries</p>
            <p class="text-2xl font-bold">{{ number_format($summary['total_entries'] ?? 0) }}</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="card-body">
            <p class="text-sm text-[--color-ink-muted]">Last Import</p>
            <p class="text-2xl font-bold">
                @if($summary['last_import'])
                    {{ $summary['last_import']->format('d M Y') }}
                @else
                    N/A
                @endif
            </p>
        </div>
    </div>
</div>

{{-- Filters --}}
<div class="card mb-6">
    <div class="card-body">
        <form wire:submit="applyFilters" class="flex flex-wrap gap-4">
            <div class="form-group mb-0">
                <label class="form-label">Search</label>
                <input type="text" wire:model="search" class="form-input" placeholder="List name...">
            </div>
            <div class="form-group mb-0">
                <label class="form-label">Status</label>
                <select wire:model="status" class="form-select">
                    <option value="">All</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="flex items-end gap-3">
                <button type="button" wire:click="clearFilters" class="btn btn-ghost">Clear Filters</button>
                <button type="submit" class="btn btn-secondary">Apply Filters</button>
            </div>
        </form>
    </div>
</div>

{{-- Sanction Lists Table --}}
<div class="card">
    <div class="card-header">
        <h3 class="card-title">All Sanction Lists</h3>
    </div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Entries</th>
                    <th>Status</th>
                    <th>Last Updated</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($lists as $list)
                <tr>
                    <td>
                        <a href="{{ route('compliance.sanctions.show', $list->id) }}" class="font-medium text-[--color-accent] hover:underline">
                            {{ $list->name }}
                        </a>
                    </td>
                    <td>{{ $list->list_type ?? 'N/A' }}</td>
                    <td>{{ number_format($list->entry_count ?? 0) }}</td>
                    <td>
                        @if($list->is_active)
                            <span class="badge badge-success">Active</span>
                        @else
                            <span class="badge badge-default">Inactive</span>
                        @endif
                    </td>
                    <td>
                        @if($list->last_updated_at)
                            {{ $list->last_updated_at->format('d M Y') }}
                        @else
                            Never
                        @endif
                    </td>
                    <td>
                        <div class="flex gap-2">
                            <a href="{{ route('compliance.sanctions.show', $list->id) }}" class="btn btn-ghost btn-sm">View</a>
                            <button
                                wire:click="triggerImport({{ $list->id }})"
                                class="btn btn-ghost btn-sm"
                                title="Trigger Import"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6">
                        <div class="empty-state py-12">
                            <div class="empty-state-icon">
                                <svg class="w-8 h-8 text-[--color-ink-muted]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                            <p class="empty-state-title">No sanction lists found</p>
                            <p class="empty-state-description">Configure sanction lists to start screening customers</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
