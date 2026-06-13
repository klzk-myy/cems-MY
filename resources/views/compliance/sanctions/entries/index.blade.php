<x-app-layout title="Sanctions Entries">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Sanctions Entries</h1>
                    <p class="mt-1 text-sm text-gray-500">Manage sanctions list entries</p>
                </div>
                <a href="{{ route('compliance.sanctions.entries.create') }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                    Add Entry
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 mb-6">
            <form method="GET" action="{{ route('compliance.sanctions.entries.index') }}" class="flex flex-wrap gap-4">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name or reference..." class="flex-1 px-4 py-2 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                <select name="list_id" class="px-4 py-2 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                    <option value="">All Sources</option>
                    @foreach ($lists as $list)
                        <option value="{{ $list->id }}" @selected(request('list_id') == $list->id)>{{ $list->name }}</option>
                    @endforeach
                </select>
                <select name="status" class="px-4 py-2 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                    <option value="active" @selected(request('status', 'active') === 'active')>Active</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                    <option value="deleted" @selected(request('status') === 'deleted')>Deleted</option>
                    <option value="all" @selected(request('status') === 'all')>All</option>
                </select>
                <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                    Search
                </button>
            </form>
        </div>

        <!-- Entries Table -->
        <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Entry ID</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Entity Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Source</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Listed</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($entries as $entry)
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ $entry['id'] }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900">{{ $entry['entity_name'] }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500">
                                {{ $entry['entity_type']?->value ?? ucfirst($entry['entity_type'] ?? 'N/A') }}
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-blue-100 text-blue-700">
                                    {{ strtoupper($entry['list_source'] ?: ($entry['list']['name'] ?? 'N/A')) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ $entry['reference_number'] ?: 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ $entry['listing_date'] ?: 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">
                                    {{ ucfirst($entry['status']?->value ?? $entry['status'] ?? 'N/A') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <a href="{{ route('compliance.sanctions.entries.show', $entry['id']) }}" class="text-blue-600 hover:text-blue-800 mr-3">View</a>
                                <a href="{{ route('compliance.sanctions.entries.edit', $entry['id']) }}" class="text-blue-600 hover:text-blue-800">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-sm text-gray-500">
                                No entries found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
