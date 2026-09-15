<x-app-layout title="Export Reconciliation">
    <div class="space-y-6">
        <x-page-header title="Reconciliation Export">
            Account {{ $report['account_code'] }} · {{ $report['period']['from'] }} to {{ $report['period']['to'] }}

            <x-slot:actions>
                <x-button variant="secondary" data-print>Print</x-button>
                <x-button variant="secondary" href="{{ route('accounting.reconciliation') }}">Back</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-card title="Summary">
            <x-stat-grid cols="3">
                <x-stat-card label="Statement Balance" :value="'RM '.number_format((float) $report['statement_balance'], 2)" />
                <x-stat-card label="Unmatched Items" :value="$report['unmatched_count']" />
                <x-stat-card label="Exceptions" :value="$report['exception_count']" />
            </x-stat-grid>
        </x-card>

        <x-card title="Statement Lines">
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
                    @forelse ($report['unmatched_items']->merge($report['exceptions'])->sortBy('date') as $item)
                        <tr>
                            <td class="px-4 py-3 text-sm">{{ $item['date'] }}</td>
                            <td class="px-4 py-3 text-sm">{{ $item['reference'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-sm">{{ $item['description'] }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono">{{ (float) $item['debit'] > 0 ? number_format((float) $item['debit'], 2) : '-' }}</td>
                            <td class="px-4 py-3 text-sm text-right font-mono">{{ (float) $item['credit'] > 0 ? number_format((float) $item['credit'], 2) : '-' }}</td>
                            <td class="px-4 py-3 text-center"><x-badge :variant="$item['status']->color()">{{ $item['status']->label() }}</x-badge></td>
                        </tr>
                    @empty
                        <x-empty-state message="No lines in range." :colspan="6" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>
    </div>
</x-app-layout>
