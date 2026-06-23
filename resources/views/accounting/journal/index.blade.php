<x-app-layout title="Journal Entries">
    <div class="space-y-6">
        <x-page-header title="Journal Entries" description="Manage double-entry journal entries">
            <x-slot:actions>
                <x-button href="{{ route('accounting.journal.create') }}" variant="primary">+ New Entry</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-filter-bar method="GET">
            <x-input name="search" placeholder="Search entries..." inline />
            <x-select name="status" :options="['' => 'All Status', 'draft' => 'Draft', 'pending' => 'Pending', 'posted' => 'Posted']" inline />
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
                            <td class="px-4 py-3 text-sm">{{ $entry['date'] ?? '2026-05-01' }}</td>
                            <td class="px-4 py-3 text-sm font-mono">{{ $entry['entry_no'] ?? 'JE-0001' }}</td>
                            <td class="px-4 py-3 text-sm">{{ $entry['description'] ?? 'Currency revaluation gain' }}</td>
                            <td class="px-4 py-3 text-sm">{{ $entry['account'] ?? '7100-001' }}</td>
                            <td class="px-4 py-3 text-sm text-right">{{ $entry['debit'] ?? '0.00' }}</td>
                            <td class="px-4 py-3 text-sm text-right">{{ $entry['credit'] ?? '0.00' }}</td>
                            <td class="px-4 py-3 text-center">
                                <x-badge variant="success">Posted</x-badge>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <x-button href="{{ route('accounting.journal.show', $entry['id'] ?? 1) }}" variant="ghost" size="sm">View</x-button>
                            </td>
                        </tr>
                    @empty
                        <x-empty-state message="No journal entries found" :colspan="8" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>

        <div class="flex items-center justify-between">
            <p class="text-sm text-ink-muted">Showing 1-10 of 0 entries</p>
            <div class="flex gap-2">
                <x-button variant="secondary" disabled>Previous</x-button>
                <x-button variant="secondary" disabled>Next</x-button>
            </div>
        </div>
    </div>
</x-app-layout>
