<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Transaction Analytics</h1>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="grid grid-cols-4 gap-6 mb-6">
                <div>
                    <p class="text-sm text-gray-500">Total Count</p>
                    <p class="text-3xl font-bold">{{ number_format($totalCount) }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Total Volume</p>
                    <p class="text-3xl font-bold">${{ number_format($totalVolume, 0) }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Avg Amount</p>
                    <p class="text-3xl font-bold">${{ number_format($avgAmount, 2) }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Flagged</p>
                    <p class="text-3xl font-bold text-yellow-600">{{ $flaggedCount }}</p>
                </div>
            </div>

            <h3 class="font-medium text-[var(--color-ink)] mb-4">By Transaction Type</h3>
            <table class="w-full mb-6">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-sm font-medium text-[var(--color-ink)]">Type</th>
                        <th class="px-4 py-2 text-right text-sm font-medium text-[var(--color-ink)]">Count</th>
                        <th class="px-4 py-2 text-right text-sm font-medium text-[var(--color-ink)]">Volume</th>
                        <th class="px-4 py-2 text-right text-sm font-medium text-[var(--color-ink)]">Avg</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($byType as $type)
                    <tr class="border-t border-[var(--color-border)]">
                        <td class="px-4 py-2">{{ ucfirst($type['name']) }}</td>
                        <td class="px-4 py-2 text-right">{{ number_format($type['count']) }}</td>
                        <td class="px-4 py-2 text-right">${{ number_format($type['volume'], 2) }}</td>
                        <td class="px-4 py-2 text-right">${{ number_format($type['avg'], 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <h3 class="font-medium text-[var(--color-ink)] mb-4">Recent Flagged Transactions</h3>
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Date</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Amount</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Reason</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($flaggedTransactions as $tx)
                    <tr class="border-t border-[var(--color-border)]">
                        <td class="px-4 py-3">{{ $tx->created_at->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3">${{ number_format($tx->amount, 2) }}</td>
                        <td class="px-4 py-3 text-sm">{{ $tx->flag_reason }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="px-4 py-3 text-center">No flagged transactions</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>