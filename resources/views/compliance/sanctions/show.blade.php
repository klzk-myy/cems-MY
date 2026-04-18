@extends('layouts.base')

@section('title', $list['name'] ?? 'Sanction List')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">{{ $list['name'] ?? 'Sanction List' }}</h1>
    <p class="text-sm text-[--color-ink-muted]">
        @if(($list['is_active'] ?? false))
            <span class="badge badge-success">Active</span>
        @else
            <span class="badge badge-default">Inactive</span>
        @endif
    </p>
</div>
@endsection

@section('header-actions')
<form method="POST" action="/compliance/sanctions/{{ $list['id'] }}/import" class="inline">
    @csrf
    <button type="submit" class="btn btn-primary">Trigger Import</button>
</form>
@endsection

@section('content')
<div class="max-w-4xl">
    <div class="card mb-6">
        <div class="card-header">
            <h3 class="card-title">List Information</h3>
        </div>
        <div class="card-body">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-[--color-ink-muted]">Name</p>
                    <p class="font-medium">{{ $list['name'] ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm text-[--color-ink-muted]">Type</p>
                    <p class="font-medium">{{ $list['list_type'] ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm text-[--color-ink-muted]">Source URL</p>
                    <p class="font-medium text-sm truncate">{{ $list['source_url'] ?? 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-sm text-[--color-ink-muted]">Entry Count</p>
                    <p class="font-medium">{{ number_format($list['entry_count'] ?? 0) }}</p>
                </div>
                <div>
                    <p class="text-sm text-[--color-ink-muted]">Last Updated</p>
                    <p class="font-medium">{{ isset($list['last_updated_at']) ? \Carbon\Carbon::parse($list['last_updated_at'])->format('d M Y H:i') : 'Never' }}</p>
                </div>
                <div>
                    <p class="text-sm text-[--color-ink-muted]">Status</p>
                    <p class="font-medium">
                        @if(($list['is_active'] ?? false))
                            <span class="badge badge-success">Active</span>
                        @else
                            <span class="badge badge-default">Inactive</span>
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header flex justify-between items-center">
            <h3 class="card-title">Recent Entries</h3>
            <a href="/compliance/sanctions/entries?list_id={{ $list['id'] }}" class="btn btn-ghost btn-sm">View All</a>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Entity Name</th>
                        <th>Type</th>
                        <th>Nationality</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($list['recent_entries'] ?? [] as $entry)
                    <tr>
                        <td class="font-medium">{{ $entry['entity_name'] }}</td>
                        <td>
                            <span class="badge badge-default">{{ ucfirst($entry['entity_type'] ?? 'N/A') }}</span>
                        </td>
                        <td>{{ $entry['nationality'] ?? 'N/A' }}</td>
                        <td>
                            @if(($entry['status'] ?? '') === 'active')
                                <span class="badge badge-success">Active</span>
                            @else
                                <span class="badge badge-default">Inactive</span>
                            @endif
                        </td>
                        <td>
                            <a href="/compliance/sanctions/entries/{{ $entry['id'] }}" class="btn btn-ghost btn-sm">View</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-8 text-[--color-ink-muted]">No entries in this list</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
