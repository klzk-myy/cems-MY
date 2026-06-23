<x-app-layout title="BNM Form LMCA">
    <div class="p-6 space-y-6">
        <x-page-header
            title="BNM Form LMCA"
            description="Monthly Large Cash Transaction Report"
            :actions="$reportGenerated"
        >
            <x-slot:actions>
                <x-button variant="secondary" onclick="window.print()">Print</x-button>
                <form method="POST" action="{{ route('reports.lmca.export', ['month' => $month]) }}">
                    @csrf
                    <x-button variant="primary" type="submit">Export</x-button>
                </form>
            </x-slot:actions>
        </x-page-header>

        <x-filter-bar method="GET" action="{{ route('reports.lmca') }}">
            <x-input
                type="month"
                id="month"
                name="month"
                label="Select Month"
                :value="$month"
                inline
            />
            <x-button variant="primary" type="submit">Generate Report</x-button>
        </x-filter-bar>

        @if($reportGenerated && !empty($reportData))
            <x-stat-grid cols="4">
                <x-stat-card label="Total Transactions" :value="number_format($reportData['total_transactions'] ?? 0)" />
                <x-stat-card label="Total Buy Volume" :value="number_format($reportData['total_buy_volume'] ?? 0, 2)" />
                <x-stat-card label="Total Sell Volume" :value="number_format($reportData['total_sell_volume'] ?? 0, 2)" />
                <x-stat-card label="Report Status" value="Generated" color="green" />
            </x-stat-grid>

            <x-card
                title="Monthly Large Cash Transaction Report"
                description="Reporting Period: {{ \Carbon\Carbon::parse($month . '-01')->format('F Y') }}"
            >
                <x-table>
                    <x-slot:thead>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Currency</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">No. of Transactions</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Buy Volume (MYR)</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Sell Volume (MYR)</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Net Volume (MYR)</th>
                    </x-slot:thead>
                    <x-slot:tbody>
                        @forelse($reportData['currency_breakdown'] ?? [] as $currency)
                            <tr class="hover:bg-canvas-subtle">
                                <td class="px-4 py-3 text-sm text-ink font-medium">{{ $currency['currency'] }}</td>
                                <td class="px-4 py-3 text-sm text-right text-ink-muted">{{ number_format($currency['transaction_count']) }}</td>
                                <td class="px-4 py-3 text-sm text-right text-ink-muted">{{ number_format($currency['buy_volume'], 2) }}</td>
                                <td class="px-4 py-3 text-sm text-right text-ink-muted">{{ number_format($currency['sell_volume'], 2) }}</td>
                                <td class="px-4 py-3 text-sm text-right text-ink-muted">{{ number_format($currency['net_volume'], 2) }}</td>
                            </tr>
                        @empty
                            <x-empty-state message="No transaction data available" :colspan="5" />
                        @endforelse
                    </x-slot:tbody>
                </x-table>

                <div class="px-6 py-4 border-t border-border">
                    <p class="text-xs text-ink-muted">
                        Note: This report includes all cash transactions >= RM 25,000 in MYR equivalent.
                    </p>
                </div>
            </x-card>
        @elseif($reportGenerated && empty($reportData))
            <x-empty-state
                title="No Report Data"
                message="No LMCA transactions found for {{ \Carbon\Carbon::parse($month . '-01')->format('F Y') }}"
            />
        @else
            <x-empty-state
                title="Select a Month"
                message="Choose a month above to generate the LMCA report."
            />
        @endif
    </div>
</x-app-layout>
