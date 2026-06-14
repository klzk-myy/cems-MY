<x-app-layout title="Sanctions Lists">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <x-page-header
            title="Sanctions Lists"
            description="Manage and monitor sanctions list sources"
        />

        <x-card>
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Source URL</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Format</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Update Frequency</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Last Synced</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Entries</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Actions</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse ($lists as $list)
                        <tr>
                            <td class="px-4 py-3 text-sm text-ink">{{ $list['name'] }}</td>
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ $list['list_type'] ?? 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm">
                                @if ($list['source_url'])
                                    <a href="{{ $list['source_url'] }}" target="_blank" rel="noopener" class="text-blue-600 hover:text-blue-800">
                                        {{ Str::limit($list['source_url'], 40) }}
                                    </a>
                                @else
                                    <span class="text-ink-muted">N/A</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ $list['source_format'] ?: 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ $list['update_frequency'] ?: 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ $list['last_synced_at'] ?: 'N/A' }}</td>
                            <td class="px-4 py-3 text-sm">
                                @php
                                    $status = $list['status'] ?? '';
                                    $statusValue = $status instanceof \BackedEnum ? $status->value : (string) $status;
                                @endphp
                                <x-badge
                                    :variant="match (strtolower($statusValue)) {
                                        'active', 'synced', 'success' => 'success',
                                        'inactive', 'disabled' => 'gray',
                                        'error', 'failed' => 'danger',
                                        'syncing', 'pending' => 'info',
                                        default => 'success',
                                    }"
                                >
                                    {{ ucfirst($statusValue ?: 'N/A') }}
                                </x-badge>
                            </td>
                            <td class="px-4 py-3 text-sm text-ink">{{ $list['entries_count'] ?? 0 }}</td>
                            <td class="px-4 py-3 text-sm">
                                <x-button variant="ghost" size="sm" href="{{ route('compliance.sanctions.show', $list['id']) }}">View</x-button>
                            </td>
                        </tr>
                    @empty
                        <x-empty-state message="No sanctions lists found" :colspan="9" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>
    </div>
</x-app-layout>
