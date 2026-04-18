@extends('layouts.base')

@section('title', 'Sanction Entries')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">Sanction Entries</h1>
    <p class="text-sm text-[--color-ink-muted]">All entries across sanction lists</p>
</div>
@endsection

@section('header-actions')
<a href="/compliance/sanctions/entries/create" class="btn btn-primary">Add Entry</a>
@endsection

@section('content')
<div class="card mb-6">
    <div class="card-body">
        <form method="GET" action="/compliance/sanctions/entries" class="flex gap-4 items-end">
            <div class="flex-1">
                <label class="form-label">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" class="form-input" placeholder="Search by name...">
            </div>
            <div>
                <label class="form-label">List</label>
                <select name="list_id" class="form-select">
                    <option value="">All Lists</option>
                    @foreach($lists as $list)
                        <option value="{{ $list['id'] }}" {{ request('list_id') == $list['id'] ? 'selected' : '' }}>
                            {{ $list['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
            <div>
                <label class="form-label">Type</label>
                <select name="type" class="form-select">
                    <option value="">All</option>
                    <option value="individual" {{ request('type') == 'individual' ? 'selected' : '' }}>Individual</option>
                    <option value="entity" {{ request('type') == 'entity' ? 'selected' : '' }}>Entity</option>
                </select>
            </div>
            <div>
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="/compliance/sanctions/entries" class="btn btn-ghost">Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Entity Name</th>
                    <th>List</th>
                    <th>Type</th>
                    <th>Nationality</th>
                    <th>DOB</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($entries as $entry)
                <tr>
                    <td class="font-medium">{{ $entry['entity_name'] }}</td>
                    <td>
                        <span class="badge badge-info">{{ $entry['list_name'] ?? 'N/A' }}</span>
                    </td>
                    <td>
                        <span class="badge badge-default">{{ ucfirst($entry['entity_type'] ?? 'N/A') }}</span>
                    </td>
                    <td>{{ $entry['nationality'] ?? 'N/A' }}</td>
                    <td>{{ isset($entry['date_of_birth']) ? \Carbon\Carbon::parse($entry['date_of_birth'])->format('d M Y') : 'N/A' }}</td>
                    <td>
                        @if(($entry['status'] ?? '') === 'active')
                            <span class="badge badge-success">Active</span>
                        @else
                            <span class="badge badge-default">Inactive</span>
                        @endif
                    </td>
                    <td>
                        <div class="flex gap-2">
                            <a href="/compliance/sanctions/entries/{{ $entry['id'] }}" class="btn btn-ghost btn-sm">View</a>
                            <a href="/compliance/sanctions/entries/{{ $entry['id'] }}/edit" class="btn btn-ghost btn-sm">Edit</a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-12 text-[--color-ink-muted]">No entries found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($pagination['last_page'] > 1)
    <div class="card-footer flex justify-between items-center">
        <p class="text-sm text-[--color-ink-muted]">
            Showing {{ ($pagination['current_page'] - 1) * $pagination['per_page'] + 1 }} to
            {{ min($pagination['current_page'] * $pagination['per_page'], $pagination['total']) }}
            of {{ $pagination['total'] }} entries
        </p>
        <div class="flex gap-2">
            @if($pagination['current_page'] > 1)
                <a href="/compliance/sanctions/entries?{{ http_build_query(array_merge(request()->except('page'), ['page' => $pagination['current_page'] - 1])) }}" class="btn btn-ghost btn-sm">Previous</a>
            @endif
            @if($pagination['current_page'] < $pagination['last_page'])
                <a href="/compliance/sanctions/entries?{{ http_build_query(array_merge(request()->except('page'), ['page' => $pagination['current_page'] + 1])) }}" class="btn btn-ghost btn-sm">Next</a>
            @endif
        </div>
    </div>
    @endif
</div>
@endsection
