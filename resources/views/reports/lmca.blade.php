<x-app-layout title="BNM Form LMCA">
    <div class="space-y-6">
        <x-page-header
            title="BNM Form LMCA"
            description="Monthly Large Cash Transaction Report"
        >
            <x-slot:actions>
                <x-button variant="secondary" data-print>Print</x-button>
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

        <x-stat-grid cols="4">
            <x-stat-card
                label="License Number"
                :value="$reportData['license_number'] ?? '—'"
            />
            <x-stat-card
                label="Customers Served"
                :value="number_format($reportData['customer_count'] ?? 0)"
            />
            <x-stat-card
                label="Active Staff"
                :value="number_format($reportData['staff_count'] ?? 0)"
            />
            <x-stat-card
                label="Report Status"
                :value="$reportGenerated ? 'Generated' : 'Preview'"
                :color="$reportGenerated ? 'green' : 'blue'"
            />
        </x-stat-grid>

        @unless($reportGenerated)
            <x-alert type="info">
                Live preview — this report has not been generated yet. Export to record it in report history.
            </x-alert>
        @endunless

        <x-card
            title="Monthly Large Cash Transaction Report"
            description="Reporting Period: {{ \Carbon\Carbon::parse($month . '-01')->format('F Y') }}"
        >
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Currency</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Buy Count</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Buy Volume</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Buy Value (MYR)</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Sell Count</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Sell Volume</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Sell Value (MYR)</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Opening Stock</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Closing Stock</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse($reportData['currencies'] ?? [] as $currency)
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm text-ink font-medium">{{ $currency['currency_code'] }}</td>
                            <td class="px-4 py-3 text-sm text-right text-ink-muted">{{ number_format($currency['buy_count']) }}</td>
                            <td class="px-4 py-3 text-sm text-right text-ink-muted">{{ number_format((float) $currency['buy_volume'], 2) }}</td>
                            <td class="px-4 py-3 text-sm text-right text-ink-muted">{{ number_format((float) $currency['buy_value_myr'], 2) }}</td>
                            <td class="px-4 py-3 text-sm text-right text-ink-muted">{{ number_format($currency['sell_count']) }}</td>
                            <td class="px-4 py-3 text-sm text-right text-ink-muted">{{ number_format((float) $currency['sell_volume'], 2) }}</td>
                            <td class="px-4 py-3 text-sm text-right text-ink-muted">{{ number_format((float) $currency['sell_value_myr'], 2) }}</td>
                            <td class="px-4 py-3 text-sm text-right text-ink-muted">{{ number_format((float) $currency['opening_stock'], 2) }}</td>
                            <td class="px-4 py-3 text-sm text-right text-ink-muted">{{ number_format((float) $currency['closing_stock'], 2) }}</td>
                        </tr>
                    @empty
                        <x-empty-state message="No transaction data available" :colspan="9" />
                    @endforelse
                </x-slot:tbody>
            </x-table>

            <div class="px-5 py-3 border-t border-border">
                <p class="text-xs text-ink-muted">
                    Note: This report includes all cash transactions &gt;= RM 25,000 in MYR equivalent.
                </p>
            </div>
        </x-card>
    </div>
</x-app-layout>
