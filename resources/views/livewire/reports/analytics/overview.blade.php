<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Analytics Overview</h1>

        <div class="grid grid-cols-4 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow p-6">
                <p class="text-sm text-gray-500">Total Transactions</p>
                <p class="text-3xl font-bold mt-1">{{ number_format($totalTransactions) }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <p class="text-sm text-gray-500">Total Volume</p>
                <p class="text-3xl font-bold mt-1">${{ number_format($totalVolume, 0) }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <p class="text-sm text-gray-500">Average Transaction</p>
                <p class="text-3xl font-bold mt-1">${{ number_format($avgTransaction, 2) }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <p class="text-sm text-gray-500">Growth</p>
                <p class="text-3xl font-bold mt-1 {{ $growth >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ $growth >= 0 ? '+' : '' }}{{ $growth }}%
                </p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-medium text-[var(--color-ink)] mb-4">Transaction Volume Trend</h3>
                <div class="h-64 flex items-end justify-around gap-2">
                    @foreach($volumeTrend as $point)
                    <div class="flex flex-col items-center">
                        <div class="bg-[var(--color-ink)] w-8" style="height: {{ $point['height'] }}%"></div>
                        <span class="text-xs text-gray-500 mt-1">{{ $point['label'] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="font-medium text-[var(--color-ink)] mb-4">Top Branches by Volume</h3>
                <table class="w-full">
                    <tbody>
                        @foreach($topBranches as $branch)
                        <tr class="border-b border-[var(--color-border)] py-2">
                            <td class="text-sm">{{ $branch['name'] }}</td>
                            <td class="text-sm text-right font-medium">${{ number_format($branch['volume'], 0) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>