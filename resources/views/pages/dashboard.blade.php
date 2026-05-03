<x-app-layout title="Dashboard">
    <div class="p-6">
        <h1 class="text-2xl font-bold mb-6">Dashboard</h1>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-gray-500 text-sm">Today's Transactions</div>
                <div class="text-2xl font-bold">{{ $stats['total_transactions'] ?? 0 }}</div>
            </div>

            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-gray-500 text-sm">Buy Volume</div>
                <div class="text-2xl font-bold text-green-600">
                    {{ number_format($stats['buy_volume'] ?? 0, 2) }}
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-gray-500 text-sm">Sell Volume</div>
                <div class="text-2xl font-bold text-red-600">
                    {{ number_format($stats['sell_volume'] ?? 0, 2) }}
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-gray-500 text-sm">Open Flags</div>
                <div class="text-2xl font-bold text-yellow-600">
                    {{ $stats['flagged'] ?? 0 }}
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold mb-4">Recent Transactions</h2>

            @if($recent_transactions->isEmpty())
                <p class="text-gray-500">No transactions today.</p>
            @else
                <table class="w-full">
                    <thead>
                        <tr class="text-left text-gray-500 text-sm">
                            <th class="pb-2">Time</th>
                            <th class="pb-2">Customer</th>
                            <th class="pb-2">Type</th>
                            <th class="pb-2">Amount</th>
                            <th class="pb-2">Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recent_transactions as $transaction)
                        <tr class="border-t">
                            <td class="py-2">{{ $transaction->created_at->format('H:i') }}</td>
                            <td class="py-2">{{ $transaction->customer->name ?? 'N/A' }}</td>
                            <td class="py-2">
                                <span class="px-2 py-1 rounded text-xs {{ $transaction->type === 'Buy' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $transaction->type }}
                                </span>
                            </td>
                            <td class="py-2">{{ number_format($transaction->amount_foreign, 2) }} {{ $transaction->currency_code }}</td>
                            <td class="py-2">{{ number_format($transaction->rate_used, 4) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-app-layout>