<x-app-layout title="Reconciliation Report">
    <div class="space-y-6">
        <x-page-header title="Reconciliation Report">
            Reconciliation summary for account {{ $report['account_code'] }} · {{ $report['period']['from'] }} to {{ $report['period']['to'] }}

            <x-slot:actions>
                <x-button variant="secondary" onclick="window.print()">Print</x-button>
                <x-button variant="secondary" href="{{ route('accounting.reconciliation.export', ['account_code' => $report['account_code'], 'from' => $report['period']['from'], 'to' => $report['period']['to']]) }}">Export</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-filter-bar>
            <form method="GET" class="flex flex-wrap items-end gap-3">
                <x-input name="account_code" label="Account Code" :value="$report['account_code']" inline />
                <x-input name="from" type="date" :value="$report['period']['from']" inline />
                <x-input name="to" type="date" :value="$report['period']['to']" inline />
                <x-button variant="secondary" type="submit">Generate Report</x-button>
            </form>
        </x-filter-bar>

        <x-stat-grid cols="3">
            <x-stat-card label="Statement Balance" :value="'RM '.number_format((float) $report['statement_balance'], 2)" />
            <x-stat-card label="Unmatched Items" :value="$report['unmatched_count']" />
            <x-stat-card label="Exceptions" :value="$report['exception_count']" :color="$report['exception_count'] > 0 ? 'red' : 'green'" />
        </x-stat-grid>

        <x-card title="Unmatched Statement Lines">
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Reference</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Description</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Debit</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Credit</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Status</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse ($report['unmatched_items'] as $item)
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm">{{ $item->statement_date?->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-sm">{{ $item->reference ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm">{{ $item->description }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono">{{ (float) $item->debit > 0 ? number_format((float) $item->debit, 2) : '-' }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono">{{ (float) $item->credit > 0 ? number_format((float) $item->credit, 2) : '-' }}</td>
                            <td class="px-4 py-3 text-center"><x-badge :variant="$item->status->color()">{{ $item->status->label() }}</x-badge></td>
                        </tr>
                    @empty
                        <x-empty-state message="All statement lines are matched." :colspan="6" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>

        <x-card title="Exceptions">
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Reference</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Description</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Amount</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Notes</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse ($report['exceptions'] as $item)
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm">{{ $item->statement_date?->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 text-sm">{{ $item->reference ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm">{{ $item->description }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono">{{ number_format((float) $item->getAmount(), 2) }}</td>
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ $item->notes ?? '—' }}</td>
                        </tr>
                    @empty
                        <x-empty-state message="No exceptions." :colspan="5" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>
    </div>
</x-app-layout>
