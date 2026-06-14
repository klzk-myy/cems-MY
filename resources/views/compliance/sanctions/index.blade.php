<x-app-layout title="Sanctions Lists">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-ink">Sanctions Lists</h1>
            <p class="mt-1 text-sm text-ink-muted">Manage and monitor sanctions list sources</p>
        </div>

        <!-- Lists Table -->
        <div class="bg-surface border border-border rounded-xl overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-canvas-subtle">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Source URL</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Format</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Update Frequency</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Last Synced</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Entries</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
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
                                <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">
                                    {{ ucfirst($list['status'] ?? 'N/A') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-ink">{{ $list['entries_count'] ?? 0 }}</td>
                            <td class="px-4 py-3 text-sm">
                                <a href="{{ route('compliance.sanctions.show', $list['id']) }}" class="text-blue-600 hover:text-blue-800">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-sm text-ink-muted">
                                No sanctions lists found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
