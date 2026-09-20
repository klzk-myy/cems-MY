<x-app-layout title="Sanction Sources">
    <div class="space-y-6">
        <x-page-header
            title="Sanction Sources"
            description="Manage sanctions list sources — add or remove a source, or pull the latest data on demand. Removing a source also removes its entries from screening."
        />

        <x-card title="Add Source">
            <form method="POST" action="{{ route('admin.sanctions.store') }}">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    <x-input name="name" label="List Name" value="{{ old('name') }}" placeholder="e.g. UNSC Consolidated" required />
                    <x-select name="list_type" label="Type" :options="$listTypes" :selected="old('list_type')" placeholder="-- Select --" required />
                    <x-input name="source_url" label="Source URL" value="{{ old('source_url') }}" placeholder="https://…" />
                    <x-select name="source_format" label="Format" :options="$sourceFormats" :selected="old('source_format')" placeholder="-- Select --" />
                </div>
                <div class="mt-4 flex justify-end">
                    <x-button type="submit" variant="primary">Add Source</x-button>
                </div>
            </form>
        </x-card>

        <x-card>
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Source URL</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Format</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Entries</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Last Synced</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Status</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Actions</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse ($lists as $list)
                        <tr class="border-t border-border hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm font-medium text-ink">{{ $list->name }}</td>
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ $list->list_type?->value ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm">
                                @if ($list->source_url)
                                    <a href="{{ $list->source_url }}" target="_blank" rel="noopener" class="text-info hover:text-info-hover">
                                        {{ Str::limit($list->source_url, 45) }}
                                    </a>
                                @else
                                    <span class="text-ink-muted">Manual</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ $list->source_format?->value ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm text-ink text-right">{{ number_format($list->entries_count) }}</td>
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ $list->last_updated_at?->format('Y-m-d H:i') ?? 'Never' }}</td>
                            <td class="px-4 py-3 text-center">
                                @php
                                    $statusValue = $list->update_status?->value ?? 'never_run';
                                @endphp
                                <x-badge :variant="match ($statusValue) {
                                    'success' => 'success',
                                    'failed' => 'danger',
                                    'pending' => 'info',
                                    default => 'gray',
                                }">
                                    {{ ucfirst(str_replace('_', ' ', $statusValue)) }}
                                </x-badge>
                                @if($list->last_error_message)
                                    <p class="mt-1 text-xs text-danger-text">{{ Str::limit($list->last_error_message, 60) }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex gap-2 justify-center">
                                    <form method="POST" action="{{ route('admin.sanctions.sync', $list) }}">
                                        @csrf
                                        <x-button type="submit" variant="secondary" size="sm">Update Now</x-button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.sanctions.destroy', $list) }}"
                                          data-confirm="Remove '{{ $list->name }}' and its {{ number_format($list->entries_count) }} entries?">
                                        @csrf
                                        @method('DELETE')
                                        <x-button type="submit" variant="danger" size="sm">Remove</x-button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <x-empty-state message="No sanction sources configured." :colspan="8" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
            <div class="mt-4">{{ $lists->links() }}</div>
        </x-card>

        <x-card title="Update History">
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Source</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Imported At</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Added</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Updated</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Deactivated</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Trigger</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Error</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse ($history as $log)
                        <tr class="border-t border-border hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm font-medium text-ink">
                                {{ $log->sanctionList?->name ?? 'Removed source' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-muted whitespace-nowrap">
                                {{ $log->imported_at?->format('d M Y H:i') }}
                            </td>
                            <td class="px-4 py-3 text-sm text-success-text text-right tabular-nums">{{ number_format($log->records_added) }}</td>
                            <td class="px-4 py-3 text-sm text-warning-text text-right tabular-nums">{{ number_format($log->records_updated) }}</td>
                            <td class="px-4 py-3 text-sm text-danger-text text-right tabular-nums">{{ number_format($log->records_deactivated) }}</td>
                            <td class="px-4 py-3 text-center">
                                <x-badge :variant="match ($log->status?->value) {
                                    'success' => 'success',
                                    'partial' => 'warning',
                                    default => 'danger',
                                }">
                                    {{ $log->status?->label() ?? 'Unknown' }}
                                </x-badge>
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-muted">
                                {{ $log->triggered_by?->label() ?? '—' }}
                                @if ($log->user)
                                    <div class="text-xs text-ink-muted">{{ $log->user->name }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-muted max-w-xs truncate" title="{{ $log->error_message ?? '' }}">
                                {{ $log->error_message ? Str::limit($log->error_message, 80) : '—' }}
                            </td>
                        </tr>
                    @empty
                        <x-empty-state message="No updates recorded yet." :colspan="8" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
            <div class="mt-4">{{ $history->links() }}</div>
        </x-card>
    </div>
</x-app-layout>
