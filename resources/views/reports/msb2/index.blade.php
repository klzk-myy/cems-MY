<x-app-layout title="MSB2 Daily Transaction Summary">
    <div class="space-y-6">
        <x-page-header
            title="MSB2 Daily Transaction Summary"
            description="Daily Summary of Money Service Business Transactions"
        >
            @if($isToday)
                <x-slot:actions>
                    <x-badge variant="success">Today</x-badge>
                </x-slot:actions>
            @endif
        </x-page-header>

        {{-- Date Selector --}}
        <x-card>
            <div class="p-6 space-y-4">
                <form method="GET" action="{{ route('reports.msb2') }}" class="flex flex-wrap gap-4 items-end">
                    <x-input
                        type="date"
                        id="date"
                        name="date"
                        label="Select Date"
                        :value="$date"
                        inline
                    />
                    <x-button type="submit" variant="primary">View Report</x-button>
                </form>

                @if($nextBusinessDay)
                    <p class="text-sm text-ink-muted pt-4 border-t border-border">
                        Next Business Day: <span class="font-medium text-ink">{{ \Carbon\Carbon::parse($nextBusinessDay)->format('d M Y (l)') }}</span>
                    </p>
                @endif
            </div>
        </x-card>

        {{-- Report Content --}}
        @if($reportGenerated)
            <x-stat-grid cols="4">
                <x-stat-card label="Total Transactions" :value="number_format($stats['total_transactions'] ?? 0)" />
                <x-stat-card label="Total Buy Volume" :value="'MYR ' . number_format($stats['total_buy_volume'] ?? 0, 2)" />
                <x-stat-card label="Total Sell Volume" :value="'MYR ' . number_format($stats['total_sell_volume'] ?? 0, 2)" />
                <x-stat-card
                    label="Net Position"
                    :value="'MYR ' . number_format($stats['net_position'] ?? 0, 2)"
                    :color="($stats['net_position'] ?? 0) >= 0 ? 'green' : 'red'"
                />
            </x-stat-grid>

            <x-card title="Currency Breakdown" description="for {{ \Carbon\Carbon::parse($date)->format('d M Y') }}">
                <x-table>
                    <x-slot:thead>
                        <th class="text-left px-4 py-3 text-xs font-medium text-ink-muted uppercase">Currency</th>
                        <th class="text-right px-4 py-3 text-xs font-medium text-ink-muted uppercase">Buy Count</th>
                        <th class="text-right px-4 py-3 text-xs font-medium text-ink-muted uppercase">Buy Volume</th>
                        <th class="text-right px-4 py-3 text-xs font-medium text-ink-muted uppercase">Sell Count</th>
                        <th class="text-right px-4 py-3 text-xs font-medium text-ink-muted uppercase">Sell Volume</th>
                        <th class="text-right px-4 py-3 text-xs font-medium text-ink-muted uppercase">Net Volume</th>
                    </x-slot:thead>
                    <x-slot:tbody>
                        @forelse($summary as $currency => $data)
                            <tr class="hover:bg-canvas-subtle">
                                <td class="px-4 py-3 text-sm font-medium text-ink">{{ $currency }}</td>
                                <td class="px-4 py-3 text-sm text-right text-ink-muted">{{ number_format($data['buy_count']) }}</td>
                                <td class="px-4 py-3 text-sm text-right text-ink-muted">{{ number_format($data['buy_volume'], 2) }}</td>
                                <td class="px-4 py-3 text-sm text-right text-ink-muted">{{ number_format($data['sell_count']) }}</td>
                                <td class="px-4 py-3 text-sm text-right text-ink-muted">{{ number_format($data['sell_volume'], 2) }}</td>
                                <td class="px-4 py-3 text-sm text-right {{ $data['net_volume'] >= 0 ? 'text-success-text' : 'text-danger-text' }}">
                                    {{ number_format($data['net_volume'], 2) }}
                                </td>
                            </tr>
                        @empty
                            <x-empty-state message="No transaction data available" :colspan="6" />
                        @endforelse
                    </x-slot:tbody>
                </x-table>
            </x-card>

            <x-stat-grid cols="3">
                <x-stat-card label="Average Transaction Value" :value="'MYR ' . number_format($stats['avg_transaction_value'] ?? 0, 2)" />
                <x-stat-card label="Pending Approval" :value="number_format($stats['pending_approval'] ?? 0)" />
                <x-stat-card label="Report Status" value="Complete" color="green" />
            </x-stat-grid>

            <div class="flex justify-end gap-3">
                <x-button variant="secondary" type="button" onclick="window.print()">Print Report</x-button>
                <form method="POST" action="{{ route('reports.msb2.export', ['date' => $date]) }}">
                    @csrf
                    <x-button type="submit" variant="primary">Export Report</x-button>
                </form>
            </div>
        @else
            <x-card title="Select a Date">
                <x-table>
                    <x-slot:thead></x-slot:thead>
                    <x-slot:tbody>
                        <x-empty-state message="Choose a date above to view the MSB2 daily transaction summary." :colspan="1" />
                    </x-slot:tbody>
                </x-table>
            </x-card>
        @endif
    </div>
</x-app-layout>
