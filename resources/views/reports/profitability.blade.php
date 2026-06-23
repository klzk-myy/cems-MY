<x-app-layout title="Currency Profitability Analysis">
    <div class="p-6 space-y-6">
        <x-page-header
            title="Currency Profitability Analysis"
            description="P&L analysis by currency position"
            :actions="true"
        >
            <x-slot:actions>
                <span class="text-sm text-ink-muted">
                    {{ \Carbon\Carbon::parse($startDate)->format('M d, Y') }} - {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}
                </span>
            </x-slot:actions>
        </x-page-header>

        <x-stat-grid cols="3">
            <x-stat-card
                label="Total Realized P&L"
                :value="($totals['realized_pnl'] >= 0 ? '+' : '') . number_format($totals['realized_pnl'], 2) . ' MYR'"
                :color="$totals['realized_pnl'] >= 0 ? 'green' : 'red'"
            />
            <x-stat-card
                label="Total Unrealized P&L"
                :value="($totals['unrealized_pnl'] >= 0 ? '+' : '') . number_format($totals['unrealized_pnl'], 2) . ' MYR'"
                :color="$totals['unrealized_pnl'] >= 0 ? 'green' : 'red'"
            />
            <x-stat-card
                label="Total P&L"
                :value="($totals['total_pnl'] >= 0 ? '+' : '') . number_format($totals['total_pnl'], 2) . ' MYR'"
                :color="$totals['total_pnl'] >= 0 ? 'green' : 'red'"
            />
        </x-stat-grid>

        <x-filter-bar method="GET">
            <x-input
                name="start_date"
                type="date"
                label="Start Date"
                :value="$startDate"
                inline
            />
            <x-input
                name="end_date"
                type="date"
                label="End Date"
                :value="$endDate"
                inline
            />
            <x-button variant="primary" type="submit">Update Report</x-button>
        </x-filter-bar>

        <x-card title="Position P&L Breakdown">
            <x-table>
                <x-slot:thead>
                    <th class="px-6 py-3 text-left text-xs font-medium text-ink-muted uppercase">Currency</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-ink-muted uppercase">Position</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-ink-muted uppercase">Avg Buy Rate</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-ink-muted uppercase">Avg Sell Rate</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-ink-muted uppercase">Realized P&L</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-ink-muted uppercase">Unrealized P&L</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-ink-muted uppercase">Total P&L</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse($positions as $position)
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-6 py-4 text-sm font-medium text-ink">{{ $position['currency'] }}</td>
                            <td class="px-6 py-4 text-sm text-ink text-right">{{ number_format($position['position'], 2) }}</td>
                            <td class="px-6 py-4 text-sm text-ink text-right">{{ number_format($position['avg_buy_rate'], 4) }}</td>
                            <td class="px-6 py-4 text-sm text-ink text-right">{{ number_format($position['avg_sell_rate'], 4) }}</td>
                            <td class="px-6 py-4 text-sm text-right {{ $position['realized_pnl'] >= 0 ? 'text-success-text' : 'text-danger-text' }}">
                                {{ $position['realized_pnl'] >= 0 ? '+' : '' }}{{ number_format($position['realized_pnl'], 2) }}
                            </td>
                            <td class="px-6 py-4 text-sm text-right {{ $position['unrealized_pnl'] >= 0 ? 'text-success-text' : 'text-danger-text' }}">
                                {{ $position['unrealized_pnl'] >= 0 ? '+' : '' }}{{ number_format($position['unrealized_pnl'], 2) }}
                            </td>
                            <td class="px-6 py-4 text-sm font-medium text-right {{ $position['total_pnl'] >= 0 ? 'text-success-text' : 'text-danger-text' }}">
                                {{ $position['total_pnl'] >= 0 ? '+' : '' }}{{ number_format($position['total_pnl'], 2) }}
                            </td>
                        </tr>
                    @empty
                        <x-empty-state message="No position data available" :colspan="7" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>
    </div>
</x-app-layout>
