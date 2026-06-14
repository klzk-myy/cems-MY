<x-app-layout title="Account Ledger">
    <div class="space-y-6">
        <x-page-header title="Account Ledger" :actions="true">
            <x-slot:actions>
                <form method="GET" class="flex items-center gap-3">
                    <x-input type="date" name="from" :value="$from" inline />
                    <x-input type="date" name="to" :value="$to" inline />
                    <x-button variant="primary" type="submit">Refresh</x-button>
                </form>
            </x-slot:actions>

            {{ $ledger['account']->account_code }} - {{ $ledger['account']->account_name }}
        </x-page-header>

        <x-stat-grid cols="3">
            <x-stat-card label="Opening Balance" prefix="RM" :value="number_format((float) ($ledger['opening_balance'] ?? '0'), 2)" />
            <x-stat-card label="Closing Balance" prefix="RM" :value="number_format((float) ($ledger['closing_balance'] ?? '0'), 2)" />
            <x-stat-card label="Account Type" :value="$ledger['account']->account_type" />
        </x-stat-grid>

        <x-card>
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Description</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Debit (RM)</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Credit (RM)</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Balance (RM)</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse ($ledger['entries'] as $entry)
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm font-mono">{{ $entry->entry_date }}</td>
                            <td class="px-4 py-3 text-sm text-ink">{{ $entry->description ?? $entry?->journalEntry?->description ?? '-' }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono">{{ (float) $entry->debit > 0 ? number_format((float) $entry->debit, 2) : '-' }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono">{{ (float) $entry->credit > 0 ? number_format((float) $entry->credit, 2) : '-' }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) $entry->running_balance, 2) }}</td>
                        </tr>
                    @empty
                        <x-empty-state message="No entries for this period" :colspan="5" />
                    @endforelse

                    @if(count($ledger['entries']))
                        <tr class="font-semibold bg-canvas-subtle">
                            <td colspan="2" class="px-4 py-3 text-sm text-ink">Period Totals</td>
                            <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) $ledger['total_debits'], 2) }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) $ledger['total_credits'], 2) }}</td>
                            <td></td>
                        </tr>
                    @endif
                </x-slot:tbody>
            </x-table>
        </x-card>
    </div>
</x-app-layout>
