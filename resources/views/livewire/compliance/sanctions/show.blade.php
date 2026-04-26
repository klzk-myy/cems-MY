@extends('layouts.base')

@section('title', $list->name ?? 'Sanction List')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-gray-900">{{ $list->name ?? 'Sanction List' }}</h1>
    <p class="text-sm text-gray-500">
        @if($list->is_active)
            <span class="badge badge-success">Active</span>
        @else
            <span class="badge badge-default">Inactive</span>
        @endif
    </p>
</div>
@endsection

@section('header-actions')
<button wire:click="triggerImport" class="btn btn-primary">
    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
    </svg>
    Trigger Import
</button>
@endsection

@section('content')
<div class="max-w-6xl">
    {{-- List Information --}}
    <div class="card mb-6">
        <div class="card-header">
            <h3 class="card-title">List Information</h3>
        </div>
        <div class="card-body">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <p class="text-sm text-gray-500">Name</p>
                    <p class="font-medium">{{ $list->name ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Type</p>
                    <p class="font-medium">{{ $list->list_type ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Entry Count</p>
                    <p class="font-medium">{{ number_format($list->entry_count ?? 0) }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Last Updated</p>
                    <p class="font-medium">
                        @if($list->last_updated_at)
                            {{ $list->last_updated_at->format('d M Y H:i') }}
                        @else
                            Never
                        @endif
                    </p>
                </div>
                <div class="col-span-2">
                    <p class="text-sm text-gray-500">Source URL</p>
                    <p class="font-medium text-sm truncate">{{ $list->source_url ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Update Status</p>
                    <p class="font-medium">
                        <span class="badge {{ $list->update_status_badge ?? 'badge-neutral' }}">
                            {{ ucfirst($list->update_status ?? 'unknown') }}
                        </span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-6">
        <div class="card-body">
            <form wire:submit="applyFilters" class="flex flex-wrap gap-4">
                <div class="form-group mb-0">
                    <label class="form-label">Search</label>
                    <input type="text" wire:model="search" class="form-input" placeholder="Entity name, reference...">
                </div>
                <div class="form-group mb-0">
                    <label class="form-label">Entity Type</label>
                    <select wire:model="entityType" class="form-select">
                        <option value="">All Types</option>
                        @foreach($listTypes as $type)
                            <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label">Status</label>
                    <select wire:model="status" class="form-select">
                        <option value="">All</option>
                        @foreach($entryStatuses as $entryStatus)
                            <option value="{{ $entryStatus }}">{{ ucfirst($entryStatus) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-3">
                    <button type="button" wire:click="clearFilters" class="btn btn-ghost">Clear Filters</button>
                    <button type="submit" class="btn btn-secondary">Apply Filters</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Entries Table --}}
    <div class="card">
        <div class="card-header flex justify-between items-center">
            <h3 class="card-title">Entries</h3>
            <a href="{{ route('compliance.sanctions.entries.index', ['list_id' => $list->id]) }}" class="btn btn-ghost btn-sm">
                View All Entries
            </a>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Entity Name</th>
                        <th>Type</th>
                        <th>Nationality</th>
                        <th>Reference</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $entry)
                    <tr>
                        <td>
                            <div>
                                <p class="font-medium">{{ $entry->entity_name }}</p>
                                @if($entry->aliases && count($entry->aliases) > 0)
                                    <p class="text-xs text-gray-500">
                                        Also known as: {{ implode(', ', array_slice($entry->aliases, 0, 3)) }}
                                        @if(count($entry->aliases) > 3)
                                            +{{ count($entry->aliases) - 3 }} more
                                        @endif
                                    </p>
                                @endif
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-default">{{ ucfirst($entry->entity_type ?? 'N/A') }}</span>
                        </td>
                        <td>{{ $entry->nationality ?? 'N/A' }}</td>
                        <td class="font-mono text-sm">{{ $entry->reference_number ?? 'N/A' }}</td>
                        <td>
                            @if(($entry->status ?? '') === 'active')
                                <span class="badge badge-success">Active</span>
                            @else
                                <span class="badge badge-default">{{ ucfirst($entry->status ?? 'Unknown') }}</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('compliance.sanctions.entries.show', $entry->id) }}" class="btn btn-ghost btn-sm">View</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6">
                            <div class="empty-state py-12">
                                <div class="empty-state-icon">
                                    <svg class="w-8 h-8 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                </div>
                                <p class="empty-state-title">No entries found</p>
                                <p class="empty-state-description">
                                    @if($search || $entityType || $status)
                                        Try adjusting your filters
                                    @else
                                        Trigger an import to populate this list
                                    @endif
                                </p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($entries->hasPages())
            <div class="card-footer">
                {{ $entries->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
