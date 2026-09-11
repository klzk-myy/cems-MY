<x-app-layout title="MSB2 Daily Transaction Summary">
    <x-page-header title="MSB2 Daily Transaction Summary" description="BNM MSB(2) regulatory report for {{ $date }}" />

    <div class="mt-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat-card label="Total Transactions" :value="$stats['total_transactions']" color="blue" />
        <x-stat-card label="Total Buy (MYR)" :value="'RM '.number_format((float) $stats['total_buy_volume'], 2)" color="green" />
        <x-stat-card label="Total Sell (MYR)" :value="'RM '.number_format((float) $stats['total_sell_volume'], 2)" color="yellow" />
        <x-stat-card label="Net Position" :value="'RM '.number_format((float) $stats['net_position'], 2)" color="purple" />
    </div>

    <x-card class="mt-4 !p-0">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-border text-sm">
                <thead class="bg-canvas-subtle">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-ink-muted">Currency</th>
                        <th class="px-4 py-3 text-right font-medium text-ink-muted">Buy Count</th>
                        <th class="px-4 py-3 text-right font-medium text-ink-muted">Buy Volume</th>
                        <th class="px-4 py-3 text-right font-medium text-ink-muted">Buy Amount (MYR)</th>
                        <th class="px-4 py-3 text-right font-medium text-ink-muted">Sell Count</th>
                        <th class="px-4 py-3 text-right font-medium text-ink-muted">Sell Volume</th>
                        <th class="px-4 py-3 text-right font-medium text-ink-muted">Sell Amount (MYR)</th>
                        <th class="px-4 py-3 text-right font-medium text-ink-muted">Net Volume</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($summary as $currencyCode => $row)
                        <tr class="hover:bg-canvas-subtle">
                            <td class="px-4 py-3 font-medium">{{ $currencyCode }}</td>
                            <td class="px-4 py-3 text-right">{{ $row['buy_count'] }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format((float) $row['buy_volume'], 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format((float) $row['buy_amount_myr'], 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ $row['sell_count'] }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format((float) $row['sell_volume'], 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format((float) $row['sell_amount_myr'], 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ number_format((float) $row['net_volume'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-ink-muted">No transactions found for this date.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
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

