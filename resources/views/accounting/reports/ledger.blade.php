<x-app-layout title="General Ledger">
    <div class="space-y-6">
        <x-page-header title="General Ledger" description="View account ledger entries" />

        <x-filter-bar method="GET">
            <x-select
                name="account_code"
                label="Account"
                :options="collect($accounts)->mapWithKeys(fn ($account) => [$account->account_code => $account->account_code.' - '.$account->account_name])->all()"
                :selected="$accountCode"
                placeholder="Select account..."
                inline
            />
            <x-input name="from" type="date" label="From" :value="$from" inline />
            <x-input name="to" type="date" label="To" :value="$to" inline />
            <x-button variant="primary" type="submit">Search</x-button>
        </x-filter-bar>

        @if ($ledger)
            <x-card>
                <div class="px-6 py-4 border-b border-border bg-canvas-subtle">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-ink">
                            {{ $ledger['account']->account_code }} - {{ $ledger['account']->account_name }}
                        </h3>
                        <span class="text-xs text-ink-muted">{{ $ledger['account']->account_type }}</span>
                    </div>
                    <div class="mt-1 flex items-center gap-4 text-xs text-ink-muted">
                        <span>Opening: RM {{ number_format((float) ($ledger['opening_balance'] ?? '0'), 2) }}</span>
                        <span>Closing: RM {{ number_format((float) ($ledger['closing_balance'] ?? '0'), 2) }}</span>
                    </div>
                </div>

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
                            <x-empty-state message="No entries found" :colspan="5" />
                        @endforelse
                    </x-slot:tbody>
                </x-table>
            </x-card>
        @else
            <x-card>
                <x-empty-state message="Select an account and date range to view ledger entries" />
            </x-card>
        @endif
    </div>
</x-app-layout>
