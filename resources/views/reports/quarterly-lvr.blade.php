<x-app-layout title="Quarterly Large Value Report">
    <div class="p-6 space-y-6">
        <x-page-header
            title="Quarterly Large Value Report"
            description="QLVR - Quarterly Large Value Transaction Report"
            :actions="$reportGenerated"
        >
            <x-slot:actions>
                <x-button variant="secondary" onclick="window.print()">Print</x-button>
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

        @if($reportGenerated && !empty($reportData))
            <x-stat-grid cols="4">
                <x-stat-card label="Total Transactions" :value="number_format($reportData['total_transactions'] ?? 0)" />
                <x-stat-card label="Total Volume (MYR)" :value="number_format($reportData['total_volume'] ?? 0, 2)" />
                <x-stat-card label="Average Value" :value="number_format($reportData['average_value'] ?? 0, 2)" />
                <x-stat-card label="Report Status" value="Complete" color="green" />
            </x-stat-grid>

            @if(!empty($reportData['monthly_breakdown']))
                <x-card title="Monthly Breakdown">
                    <x-table>
                        <x-slot:thead>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Month</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Transactions</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Volume (MYR)</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Average (MYR)</th>
                        </x-slot:thead>
                        <x-slot:tbody>
                            @foreach($reportData['monthly_breakdown'] as $month)
                                <tr class="hover:bg-canvas-subtle">
                                    <td class="px-4 py-3 text-sm text-ink font-medium">{{ $month['label'] }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-ink-muted">{{ number_format($month['transaction_count']) }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-ink-muted">{{ number_format($month['volume'], 2) }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-ink-muted">{{ number_format($month['average'], 2) }}</td>
                                </tr>
                            @endforeach
                        </x-slot:tbody>
                    </x-table>
                </x-card>
            @endif
        @elseif($reportGenerated && empty($reportData))
            <x-empty-state title="No Report Data" message="No high-value transactions found for the selected quarter." />
        @else
            <x-empty-state title="Select a Quarter" message="Choose a quarter above to generate the LVR report." />
        @endif
    </div>
</x-app-layout>
