<x-app-layout title="Journal Entries">
    <div class="space-y-6">
        <x-page-header title="Journal Entries" description="Manage double-entry journal entries">
            <x-slot:actions>
                <x-button href="{{ route('accounting.journal.create') }}" variant="primary">+ New Entry</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-filter-bar method="GET">
            <x-input name="search" placeholder="Search entries..." inline />
            <x-select name="status" :options="['' => 'All Status', 'Draft' => 'Draft', 'Pending' => 'Pending', 'Posted' => 'Posted', 'Reversed' => 'Reversed', 'Rejected' => 'Rejected']" inline />
            <x-input type="date" name="date" inline />
            <x-button type="submit" variant="secondary">Filter</x-button>
        </x-filter-bar>

        <x-card>
            <x-table>
                <x-slot:thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Entry No.</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Description</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Account</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Debit</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Credit</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Status</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Actions</th>
                    </tr>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse($entries ?? [] as $entry)
                        <tr class="border-t border-border hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm">{{ $entry->entry_date?->format('Y-m-d') ?? $entry->entry_date }}</td>
                            <td class="px-4 py-3 text-sm font-mono">{{ $entry->entry_number }}</td>
                            <td class="px-4 py-3 text-sm">{{ $entry->description }}</td>
                            <td class="px-4 py-3 text-sm">
                                {{ $entry->lines->first()?->account_code ?? '—' }}{{ $entry->lines->count() > 1 ? ' +'.($entry->lines->count() - 1) : '' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-right">{{ number_format((float) $entry->lines->sum('debit'), 2) }}</td>
                            <td class="px-4 py-3 text-sm text-right">{{ number_format((float) $entry->lines->sum('credit'), 2) }}</td>
                            <td class="px-4 py-3 text-center">
                                <x-badge
                                    :variant="match ($entry->status?->value) {
                                        'Posted' => 'success',
                                        'Pending' => 'warning',
                                        'Draft' => 'secondary',
                                        'Rejected' => 'danger',
                                        'Reversed' => 'info',
                                        default => 'secondary',
                                    }"
                                >
                                    {{ $entry->status?->label() ?? 'N/A' }}
                                </x-badge>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <x-button href="{{ route('accounting.journal.show', $entry) }}" variant="ghost" size="sm">View</x-button>
                            </td>
                        </tr>
                    @empty
                        <x-empty-state message="No journal entries found" :colspan="8" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>

        <div class="flex items-center justify-between">
            <p class="text-sm text-ink-muted">
                Showing {{ $entries->firstItem() ?? 0 }}-{{ $entries->lastItem() ?? 0 }} of {{ $entries->total() }} entries
            </p>
            {{ $entries->links() }}
        </div>
    </div>
</x-app-layout>
