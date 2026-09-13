<x-app-layout title="Revaluation History">
    <div class="space-y-6">
        <x-page-header title="Revaluation History" description="Historical currency revaluation entries">
            <x-slot:actions>
                <x-button variant="secondary" href="{{ route('accounting.revaluation') }}">Back to Revaluation</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-filter-bar>
            <form method="GET" class="flex flex-wrap items-end gap-3">
                <x-input name="month" label="Month" type="month" :value="$month" inline />
                <x-button variant="secondary" type="submit">Filter</x-button>
            </form>
        </x-filter-bar>

        <x-card title="Entries — {{ \Carbon\Carbon::parse($month)->format('F Y') }}">
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Currency</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Position</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Old Rate</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">New Rate</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Gain / Loss</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Posted By</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse ($history as $entry)
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm">{{ $entry->revaluation_date?->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-sm font-medium text-ink">{{ $entry->currency_code }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) $entry->position_amount, 2) }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) $entry->old_rate, 6) }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) $entry->new_rate, 6) }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono {{ (float) $entry->gain_loss_amount < 0 ? 'text-danger' : ((float) $entry->gain_loss_amount > 0 ? 'text-success' : '') }}">
                                {{ number_format((float) $entry->gain_loss_amount, 2) }}
                            </td>
                            <td class="px-4 py-3 text-sm">{{ $entry->postedBy?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <x-empty-state message="No revaluation entries for this month." :colspan="7" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>

        <div class="flex items-center justify-between">
            <p class="text-sm text-ink-muted">Showing {{ $history->firstItem() ?? 0 }}-{{ $history->lastItem() ?? 0 }} of {{ $history->total() }} entries</p>
            {{ $history->links() }}
        </div>
    </div>
</x-app-layout>
