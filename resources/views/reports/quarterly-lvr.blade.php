<x-app-layout title="Quarterly Large Value Report">
    <div class="space-y-6">
        <x-page-header
            title="Quarterly Large Value Report"
            description="QLVR - Quarterly Large Value Transaction Report"
        >
            <x-slot:actions>
                <x-button variant="secondary" data-print>Print</x-button>
                <form method="POST" action="{{ route('reports.quarterly-lvr.export', ['quarter' => $quarter]) }}">
                    @csrf
                    <x-button variant="primary" type="submit">Export</x-button>
                </form>
            </x-slot:actions>
        </x-page-header>

        @php
            $quarterOptions = [];
            for ($y = date('Y'); $y >= date('Y') - 2; $y--) {
                $quarterOptions[$y . '-Q1'] = $y . ' Q1 (Jan - Mar)';
                $quarterOptions[$y . '-Q2'] = $y . ' Q2 (Apr - Jun)';
                $quarterOptions[$y . '-Q3'] = $y . ' Q3 (Jul - Sep)';
                $quarterOptions[$y . '-Q4'] = $y . ' Q4 (Oct - Dec)';
            }
        @endphp

        <x-filter-bar method="GET" action="{{ route('reports.quarterly-lvr') }}">
            <x-select
                id="quarter"
                name="quarter"
                label="Select Quarter"
                :options="$quarterOptions"
                :selected="$quarter"
                inline
            />
            <x-button variant="primary" type="submit">Generate Report</x-button>
        </x-filter-bar>

        <x-stat-grid cols="4">
            <x-stat-card label="Total Transactions" :value="number_format($reportData['total_transactions'] ?? 0)" />
            <x-stat-card
                label="Total Volume (MYR)"
                :value="'MYR ' . number_format((float) ($reportData['total_amount_myr'] ?? 0), 2)"
            />
            <x-stat-card
                label="Period"
                :value="($reportData['period_start'] ?? '') . ' to ' . ($reportData['period_end'] ?? '')"
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

        @if(!empty($reportData['monthly_breakdown']))
            <x-card title="Monthly Breakdown">
                <x-table>
                    <x-slot:thead>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Month</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Transactions</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Volume (MYR)</th>
                    </x-slot:thead>
                    <x-slot:tbody>
                        @foreach($reportData['monthly_breakdown'] as $month)
                            <tr class="hover:bg-canvas-subtle">
                                <td class="px-4 py-3 text-sm text-ink font-medium">{{ \Carbon\Carbon::parse($month['month'] . '-01')->format('F Y') }}</td>
                                <td class="px-4 py-3 text-sm text-right text-ink-muted">{{ number_format($month['count']) }}</td>
                                <td class="px-4 py-3 text-sm text-right text-ink-muted">{{ number_format((float) $month['total_amount_myr'], 2) }}</td>
                            </tr>
                        @endforeach
                    </x-slot:tbody>
                </x-table>
            </x-card>
        @endif

        @if(!empty($reportData['by_currency']))
            <x-card title="By Currency">
                <x-table>
                    <x-slot:thead>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Currency</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Transactions</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Volume (MYR)</th>
                    </x-slot:thead>
                    <x-slot:tbody>
                        @foreach($reportData['by_currency'] as $currency)
                            <tr class="hover:bg-canvas-subtle">
                                <td class="px-4 py-3 text-sm text-ink font-medium">{{ $currency['currency'] }}</td>
                                <td class="px-4 py-3 text-sm text-right text-ink-muted">{{ number_format($currency['count']) }}</td>
                                <td class="px-4 py-3 text-sm text-right text-ink-muted">{{ number_format((float) $currency['total_amount_myr'], 2) }}</td>
                            </tr>
                        @endforeach
                    </x-slot:tbody>
                </x-table>
            </x-card>
        @endif

        <x-card title="Large Value Transactions">
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Transaction ID</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Customer</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Amount (MYR)</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Currency</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Type</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse($reportData['data'] ?? [] as $txn)
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm text-ink font-medium">{{ $txn['Transaction_ID'] }}</td>
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ $txn['Date'] }}</td>
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ $txn['Customer_Name'] }}</td>
                            <td class="px-4 py-3 text-sm text-right text-ink-muted">{{ number_format((float) $txn['Amount_Local'], 2) }}</td>
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ $txn['Currency'] }}</td>
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ $txn['Transaction_Type'] }}</td>
                        </tr>
                    @empty
                        <x-empty-state message="No large-value transactions found for this quarter" :colspan="6" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>
    </div>
</x-app-layout>
