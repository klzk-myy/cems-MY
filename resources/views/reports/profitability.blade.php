<x-app-layout title="Currency Profitability Analysis">
    <x-page-header title="Currency Profitability Analysis" description="Unrealized and realized P&L by currency" />

    @if (isset($totals))
        <div class="mt-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-stat-card label="Total Unrealized P&L" :value="'RM '.number_format((float) ($totals['unrealized_pnl'] ?? 0), 2)" :color="(float) ($totals['unrealized_pnl'] ?? 0) >= 0 ? 'green' : 'red'" />
            <x-stat-card label="Total Realized P&L" :value="'RM '.number_format((float) ($totals['realized_pnl'] ?? 0), 2)" :color="(float) ($totals['realized_pnl'] ?? 0) >= 0 ? 'green' : 'red'" />
            <x-stat-card label="Total P&L" :value="'RM '.number_format((float) ($totals['total_pnl'] ?? 0), 2)" :color="(float) ($totals['total_pnl'] ?? 0) >= 0 ? 'green' : 'red'" />
            <x-stat-card label="Currencies" :value="count($positions ?? [])" color="blue" />
        </div>
    @endif

    <x-card class="mt-4 !p-0">
        @if (!empty($positions))
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border text-sm">
                    <thead class="bg-canvas-subtle">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-ink-muted">Currency</th>
                            <th class="px-4 py-3 text-right font-medium text-ink-muted">Position</th>
                            <th class="px-4 py-3 text-right font-medium text-ink-muted">Avg Buy Rate</th>
                            <th class="px-4 py-3 text-right font-medium text-ink-muted">Avg Sell Rate</th>
                            <th class="px-4 py-3 text-right font-medium text-ink-muted">Unrealized P&L</th>
                            <th class="px-4 py-3 text-right font-medium text-ink-muted">Realized P&L</th>
                            <th class="px-4 py-3 text-right font-medium text-ink-muted">Total P&L</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($positions as $position)
                            <tr class="hover:bg-canvas-subtle">
                                <td class="px-4 py-3 font-medium">{{ $position['currency'] ?? '—' }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) ($position['position'] ?? 0), 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) ($position['avg_buy_rate'] ?? 0), 4) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) ($position['avg_sell_rate'] ?? 0), 4) }}</td>
                                <td class="px-4 py-3 text-right {{ (float) ($position['unrealized_pnl'] ?? 0) >= 0 ? 'text-success-text' : 'text-danger' }}">
                                    {{ (float) ($position['unrealized_pnl'] ?? 0) >= 0 ? '+' : '' }}{{ number_format((float) ($position['unrealized_pnl'] ?? 0), 2) }}
                                </td>
                                <td class="px-4 py-3 text-right {{ (float) ($position['realized_pnl'] ?? 0) >= 0 ? 'text-success-text' : 'text-danger' }}">
                                    {{ (float) ($position['realized_pnl'] ?? 0) >= 0 ? '+' : '' }}{{ number_format((float) ($position['realized_pnl'] ?? 0), 2) }}
                                </td>
                                <td class="px-4 py-3 text-right {{ (float) ($position['total_pnl'] ?? 0) >= 0 ? 'text-success-text' : 'text-danger' }}">
                                    {{ (float) ($position['total_pnl'] ?? 0) >= 0 ? '+' : '' }}{{ number_format((float) ($position['total_pnl'] ?? 0), 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-empty-state title="No data available" description="No position data found for the selected period." />
        @endif
    </x-card>
</x-app-layout>

