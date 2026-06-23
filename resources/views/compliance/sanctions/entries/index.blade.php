<x-app-layout title="Sanctions Entries">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <x-page-header
            title="Sanctions Entries"
            description="Manage sanctions list entries"
        >
            <x-slot:actions>
                <x-button variant="primary" href="{{ route('compliance.sanctions.entries.create') }}">
                    Add Entry
                </x-button>
            </x-slot:actions>
        </x-page-header>

        <x-filter-bar method="GET">
            <x-input
                name="search"
                type="text"
                placeholder="Search by name or reference..."
                :value="request('search')"
                inline
                class="flex-1"
            />
            <x-select
                name="list_id"
                :options="$lists->pluck('name', 'id')"
                :selected="request('list_id')"
                placeholder="All Sources"
                inline
            />
            <x-select
                name="status"
                :options="['active' => 'Active', 'inactive' => 'Inactive', 'deleted' => 'Deleted', 'all' => 'All']"
                :selected="request('status', 'active')"
                placeholder=""
                inline
            />
            <x-button variant="primary" type="submit">Search</x-button>
        </x-filter-bar>

        <x-card>
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Entry ID</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Entity Name</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Source</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Reference</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Listed</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Actions</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse ($entries as $entry)
                        <tr>
                            <td class="px-4 py-3 text-sm text-ink">{{ $entry['id'] }}</td>
                            <td class="px-4 py-3 text-sm text-ink">{{ $entry['entity_name'] }}</td>
                            <td class="px-4 py-3 text-sm text-ink-muted">
                                {{ $entry['entity_type']?->value ?? ucfirst($entry['entity_type'] ?? 'N/A') }}
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <x-badge variant="info">
                                    {{ strtoupper($entry['list_source'] ?: ($entry['list']['name'] ?? 'N/A')) }}
                                </x-badge>
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ $entry['reference_number'] ?: 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ $entry['listing_date'] ?: 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm">
                                <x-badge
                                    :variant="match (strtolower($entry['status']?->value ?? $entry['status'] ?? '')) {
                                        'active' => 'success',
                                        'inactive' => 'gray',
                                        'deleted' => 'danger',
                                        default => 'gray',
                                    }"
                                >
                                    {{ ucfirst($entry['status']?->value ?? $entry['status'] ?? 'N/A') }}
                                </x-badge>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <x-button variant="ghost" size="sm" href="{{ route('compliance.sanctions.entries.show', $entry['id']) }}">
                                    View
                                </x-button>
                                <x-button variant="ghost" size="sm" href="{{ route('compliance.sanctions.entries.edit', $entry['id']) }}">
                                    Edit
                                </x-button>
                            </td>
                        </tr>
                    @empty
                        <x-empty-state message="No entries found" :colspan="8" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>
    </div>
</x-app-layout>
