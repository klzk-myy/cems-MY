@extends('layouts.base')

@section('title', 'Sanction Entries')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">Sanction Entries</h1>
    <p class="text-sm text-[--color-ink-muted]">All entries across sanction lists</p>
</div>
@endsection

@section('header-actions')
<a href="/compliance/sanctions/entries/create" class="px-4 py-2 text-sm font-medium rounded-lg bg-[--color-primary] text-white hover:bg-[--color-ink]">Add Entry</a>
@endsection

@section('content')
<div class="bg-white border border-[--color-border] rounded-xl mb-6">
    <div class="p-6">
        <form method="GET" action="/compliance/sanctions/entries" class="flex gap-4 items-end">
            <div class="flex-1">
                <label class="block text-sm font-medium text-[--color-ink] mb-1">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" placeholder="Search by name...">
            </div>
            <div>
                <label class="block text-sm font-medium text-[--color-ink] mb-1">List</label>
                <select name="list_id" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg">
                    <option value="">All Lists</option>
                    @foreach($lists as $list)
                        <option value="{{ $list['id'] }}" {{ request('list_id') == $list['id'] ? 'selected' : '' }}>
                            {{ $list['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-[--color-ink] mb-1">Status</label>
                <select name="status" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg">
                    <option value="">All</option>
                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-[--color-ink] mb-1">Type</label>
                <select name="type" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg">
                    <option value="">All</option>
                    <option value="individual" {{ request('type') == 'individual' ? 'selected' : '' }}>Individual</option>
                    <option value="entity" {{ request('type') == 'entity' ? 'selected' : '' }}>Entity</option>
                </select>
            </div>
            <div>
                <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-[--color-primary] text-white hover:bg-[--color-ink]">Filter</button>
                <a href="/compliance/sanctions/entries" class="px-4 py-2 text-sm font-medium rounded-lg hover:bg-[--color-canvas-subtle]">Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="bg-white border border-[--color-border] rounded-xl">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
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
                        <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-blue-100 text-blue-700">{{ $entry['list_name'] ?? 'N/A' }}</span>
                    </td>
                    <td>
                        <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-gray-100 text-gray-700">{{ ucfirst($entry['entity_type'] ?? 'N/A') }}</span>
                    </td>
                    <td>{{ $entry['nationality'] ?? 'N/A' }}</td>
                    <td>{{ isset($entry['date_of_birth']) ? \Carbon\Carbon::parse($entry['date_of_birth'])->format('d M Y') : 'N/A' }}</td>
                    <td>
                        @if(($entry['status'] ?? '') === 'active')
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Active</span>
                        @else
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-gray-100 text-gray-700">Inactive</span>
                        @endif
                    </td>
                    <td>
                        <div class="flex gap-2">
                            <a href="/compliance/sanctions/entries/{{ $entry['id'] }}" class="px-4 py-2 text-sm font-medium rounded-lg hover:bg-[--color-canvas-subtle]">View</a>
                            <a href="/compliance/sanctions/entries/{{ $entry['id'] }}/edit" class="px-4 py-2 text-sm font-medium rounded-lg hover:bg-[--color-canvas-subtle]">Edit</a>
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
    <div class="px-6 py-4 border-t border-[--color-border] flex justify-between items-center">
        <p class="text-sm text-[--color-ink-muted]">
            Showing {{ ($pagination['current_page'] - 1) * $pagination['per_page'] + 1 }} to
            {{ min($pagination['current_page'] * $pagination['per_page'], $pagination['total']) }}
            of {{ $pagination['total'] }} entries
        </p>
        <div class="flex gap-2">
            @if($pagination['current_page'] > 1)
                <a href="/compliance/sanctions/entries?{{ http_build_query(array_merge(request()->except('page'), ['page' => $pagination['current_page'] - 1])) }}" class="px-4 py-2 text-sm font-medium rounded-lg hover:bg-[--color-canvas-subtle]">Previous</a>
            @endif
            @if($pagination['current_page'] < $pagination['last_page'])
                <a href="/compliance/sanctions/entries?{{ http_build_query(array_merge(request()->except('page'), ['page' => $pagination['current_page'] + 1])) }}" class="px-4 py-2 text-sm font-medium rounded-lg hover:bg-[--color-canvas-subtle]">Next</a>
            @endif
        </div>
    </div>
    @endif
</div>
@endsection
