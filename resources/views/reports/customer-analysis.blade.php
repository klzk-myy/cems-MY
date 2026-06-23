<x-app-layout title="Top Customer Analysis">
    <div class="space-y-6 p-6">
        <x-page-header
            title="Top Customer Analysis"
            description="Top 50 customers by transaction count and volume with risk ratings"
        />

        <!-- Risk Distribution Summary -->
        <x-stat-grid cols="4">
            <x-card>
                <div class="p-4">
                    <div class="text-xs font-medium text-ink-muted uppercase tracking-wide mb-1">Total Analyzed</div>
                    <div class="text-2xl font-semibold text-ink">{{ number_format($riskDistribution['total']) }}</div>
                </div>
            </x-card>
            <x-card>
                <div class="p-4">
                    <div class="text-xs font-medium text-ink-muted uppercase tracking-wide mb-1">High Risk</div>
                    <div class="text-2xl font-semibold text-danger-text">{{ number_format($riskDistribution['high']) }}</div>
                    <div class="text-xs text-ink-muted/50 mt-1">{{ $riskDistribution['total'] > 0 ? round($riskDistribution['high'] / $riskDistribution['total'] * 100, 1) : 0 }}%</div>
                </div>
            </x-card>
            <x-card>
                <div class="p-4">
                    <div class="text-xs font-medium text-ink-muted uppercase tracking-wide mb-1">Medium Risk</div>
                    <div class="text-2xl font-semibold text-warning-text">{{ number_format($riskDistribution['medium']) }}</div>
                    <div class="text-xs text-ink-muted/50 mt-1">{{ $riskDistribution['total'] > 0 ? round($riskDistribution['medium'] / $riskDistribution['total'] * 100, 1) : 0 }}%</div>
                </div>
            </x-card>
            <x-card>
                <div class="p-4">
                    <div class="text-xs font-medium text-ink-muted uppercase tracking-wide mb-1">Low Risk</div>
                    <div class="text-2xl font-semibold text-success-text">{{ number_format($riskDistribution['low']) }}</div>
                    <div class="text-xs text-ink-muted/50 mt-1">{{ $riskDistribution['total'] > 0 ? round($riskDistribution['low'] / $riskDistribution['total'] * 100, 1) : 0 }}%</div>
                </div>
            </x-card>
        </x-stat-grid>

        <!-- Top Customers Table -->
        <x-card title="Top 50 Customers">
            <x-table>
                <x-slot:thead>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Rank</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Customer</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">ID Number</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Transactions</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Total Volume (MYR)</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Avg Value</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-ink-muted uppercase">Risk Rating</th>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse($topCustomers as $index => $customer)
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ $index + 1 }}</td>
                            <td class="px-4 py-3">
                                <div class="text-sm font-medium text-ink">{{ $customer['name'] }}</div>
                                <div class="text-xs text-ink-muted">{{ $customer['customer_code'] }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm text-ink-muted">{{ $customer['id_number'] }}</td>
                            <td class="px-4 py-3 text-sm text-ink text-right">{{ number_format($customer['transaction_count']) }}</td>
                            <td class="px-4 py-3 text-sm text-ink text-right">{{ number_format($customer['total_volume'], 2) }}</td>
                            <td class="px-4 py-3 text-sm text-ink text-right">{{ number_format($customer['avg_value'], 2) }}</td>
                            <td class="px-4 py-3 text-center">
                                @switch($customer['risk_rating'])
                                    @case('high')
                                        <x-badge variant="danger">High</x-badge>
                                        @break
                                    @case('medium')
                                        <x-badge variant="warning">Medium</x-badge>
                                        @break
                                    @case('low')
                                        <x-badge variant="success">Low</x-badge>
                                        @break
                                    @default
                                        <x-badge>Unknown</x-badge>
                                @endswitch
                            </td>
                        </tr>
                    @empty
                        <x-empty-state message="No customer data available" :colspan="7" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>
    </div>
</x-app-layout>
