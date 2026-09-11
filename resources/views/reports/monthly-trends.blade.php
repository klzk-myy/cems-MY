<x-app-layout title="Monthly Transaction Trends">
    <x-page-header title="Monthly Transaction Trends" description="Monthly buy/sell transaction volume analysis" />

    <x-card class="mt-4 !p-0">
        @if (!empty($monthlyData))
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border text-sm">
                    <thead class="bg-canvas-subtle">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-ink-muted">Month</th>
                            <th class="px-4 py-3 text-right font-medium text-ink-muted">Transactions</th>
                            <th class="px-4 py-3 text-right font-medium text-ink-muted">Volume</th>
                            <th class="px-4 py-3 text-right font-medium text-ink-muted">Avg Value</th>
                            <th class="px-4 py-3 text-right font-medium text-ink-muted">MoM Change</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($monthlyData as $data)
                            @php
                                $monthName = date('F', mktime(0, 0, 0, $data['month'], 1));
                            @endphp
                            <tr class="hover:bg-canvas-subtle">
                                <td class="px-4 py-3 font-medium">{{ $monthName }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format($data['count']) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $data['volume'], 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) ($data['avg_value'] ?? 0), 2) }}</td>
                                <td class="px-4 py-3 text-right">
                                    @if ($data['mom_change'] !== null)
                                        <span class="{{ (float) $data['mom_change'] >= 0 ? 'text-success-text' : 'text-danger' }}">
                                            {{ (float) $data['mom_change'] >= 0 ? '+' : '' }}{{ number_format((float) $data['mom_change'], 2) }}%
                                        </span>
                                    @else
                                        <span class="text-ink-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-empty-state title="No data available" description="No transaction data found for the selected period." />
        @endif
    </x-card>
</x-app-layout>

