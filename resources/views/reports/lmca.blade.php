<x-app-layout title="Form LMCA">
    <x-page-header title="Form LMCA" description="BNM Form LMCA monthly regulatory report for {{ $month }}" />

    @if (isset($reportData['summary']))
        <div class="mt-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <x-stat-card label="Total Transactions" :value="$reportData['summary']['total_transactions'] ?? 0" color="blue" />
            <x-stat-card label="Total Buy (MYR)" :value="'RM '.number_format((float) ($reportData['summary']['total_buy_myr'] ?? 0), 2)" color="green" />
            <x-stat-card label="Total Sell (MYR)" :value="'RM '.number_format((float) ($reportData['summary']['total_sell_myr'] ?? 0), 2)" color="yellow" />
            <x-stat-card label="Net Position" :value="'RM '.number_format((float) ($reportData['summary']['net_position'] ?? 0), 2)" color="purple" />
        </div>
    @endif

    <x-card class="mt-4 !p-0">
        @if (!empty($reportData['currencies']))
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border text-sm">
                    <thead class="bg-canvas-subtle">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-ink-muted">Currency</th>
                            <th class="px-4 py-3 text-right font-medium text-ink-muted">Transaction Count</th>
                            <th class="px-4 py-3 text-right font-medium text-ink-muted">Total Volume</th>
                            <th class="px-4 py-3 text-right font-medium text-ink-muted">Total Amount (MYR)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($reportData['currencies'] as $currencyCode => $data)
                            <tr class="hover:bg-canvas-subtle">
                                <td class="px-4 py-3 font-medium">{{ $currencyCode }}</td>
                                <td class="px-4 py-3 text-right">{{ $data['count'] ?? 0 }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) ($data['volume'] ?? 0), 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) ($data['amount_myr'] ?? 0), 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <x-empty-state title="No data available" description="No transactions found for this month." />
        @endif
    </x-card>

    @if ($reportGenerated)
        <x-card class="mt-4">
            <div class="flex items-center gap-2 text-sm text-ink-muted">
                <span class="inline-flex items-center rounded-full bg-success-subtle px-2.5 py-0.5 text-xs font-medium text-success-text">Generated</span>
                <span>Report generated on {{ $reportGenerated->created_at->format('Y-m-d H:i') }}</span>
            </div>
        </x-card>
    @endif
</x-app-layout>

