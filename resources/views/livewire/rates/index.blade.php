<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Exchange Rates</h1>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Currency</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Buy Rate</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Sell Rate</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Spread</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Updated</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rates as $rate)
                    <tr class="border-t border-[var(--color-border)]">
                        <td class="px-4 py-3 font-medium">{{ $rate['currency_code'] }}</td>
                        <td class="px-4 py-3">{{ number_format($rate['rate_buy'], 4) }}</td>
                        <td class="px-4 py-3">{{ number_format($rate['rate_sell'], 4) }}</td>
                        <td class="px-4 py-3">{{ number_format($rate['spread'], 4) }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ \Carbon\Carbon::parse($rate['fetched_at'])->format('H:i') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-4 py-3 text-center">No rates available</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex justify-end">
            <button wire:click="refresh" class="px-4 py-2 border border-[var(--color-border)] rounded">Refresh Rates</button>
        </div>
    </div>
</div>