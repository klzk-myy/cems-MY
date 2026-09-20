<x-app-layout title="Risk Trends">
    <div class="space-y-6">
        <x-page-header
            title="Risk Trends"
            description="Historical risk metrics and analysis over the last 6 months"
        >
            <x-slot:actions>
                <x-button variant="secondary" href="{{ route('compliance.risk-dashboard.index') }}">
                    Back to Dashboard
                </x-button>
            </x-slot:actions>
        </x-page-header>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <x-chart-trend
                title="High Risk Customer Trend (score 60+)"
                :labels="$highRiskTrend['labels']"
                :values="$highRiskTrend['values']"
                color="red"
            />

            <x-chart-trend
                title="Alert Volume Trend"
                :labels="$alertVolumeTrend['labels']"
                :values="$alertVolumeTrend['values']"
                color="yellow"
            />
        </div>

        <x-card title="Customers Needing Re-screening">
            <div class="overflow-x-auto">
                <x-table>
                    <x-slot:thead>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Customer</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Risk Level</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Score</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Next Screening Due</th>
                    </x-slot:thead>
                    <x-slot:tbody>
                        @forelse ($needsRescreening as $customer)
                            @php
                                $snapshot = $customer->latestRiskSnapshot;
                            @endphp
                            <tr class="border-t border-border hover:bg-canvas-subtle">
                                <td class="px-4 py-3 text-sm">
                                    <x-customer-link :customer="$customer" />
                                    <div class="text-xs text-ink-muted">#{{ $customer->id }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm"><x-risk-badge :customer="$customer" /></td>
                                <td class="px-4 py-3 text-sm text-ink tabular-nums">
                                    {{ $snapshot?->overall_score ?? $customer->risk_score }}
                                </td>
                                <td class="px-4 py-3 text-sm text-ink-muted whitespace-nowrap">
                                    {{ $snapshot?->next_screening_date?->format('d M Y') ?? 'Overdue' }}
                                </td>
                            </tr>
                        @empty
                            <x-empty-state message="No customers are due for re-screening." :colspan="4" />
                        @endforelse
                    </x-slot:tbody>
                </x-table>
            </div>
            <div class="mt-4">
                {{ $needsRescreening->links() }}
            </div>
        </x-card>
    </div>
</x-app-layout>
