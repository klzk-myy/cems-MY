<x-app-layout title="Dashboard">
    <div class="space-y-6">
        <x-page-header title="Dashboard" />

        <x-stat-grid :cols="4" class="mb-8">
            <x-stat-card label="Today's Transactions" :value="$stats['total_transactions'] ?? 0" />
            <x-stat-card label="Buy Volume" :value="number_format($stats['buy_volume'] ?? 0, 2)" color="green" />
            <x-stat-card label="Sell Volume" :value="number_format($stats['sell_volume'] ?? 0, 2)" color="red" />
            <x-stat-card label="Open Flags" :value="$stats['flagged'] ?? 0" color="yellow" />
        </x-stat-grid>

        <x-card title="Recent Transactions">
            @if($recent_transactions->isEmpty())
                <p class="p-6 text-ink-muted">No transactions today.</p>
            @else
                <x-table>
                    <x-slot:thead>
                        <tr class="text-left text-ink-muted text-sm">
                            <th class="px-4 py-3">Time</th>
                            <th class="px-4 py-3">Customer</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Amount</th>
                            <th class="px-4 py-3">Rate</th>
                        </tr>
                    </x-slot:thead>
                    <x-slot:tbody>
                        @foreach($recent_transactions as $transaction)
                            <tr class="border-t border-border">
                                <td class="px-4 py-3">{{ $transaction->created_at->format('H:i') }}</td>
                                <td class="px-4 py-3">{{ $transaction->customer->name ?? 'N/A' }}</td>
                                <td class="px-4 py-3">
                                    <x-badge variant="{{ $transaction->type === 'Buy' ? 'success' : 'danger' }}">
                                        {{ $transaction->type }}
                                    </x-badge>
                                </td>
                                <td class="px-4 py-3">{{ number_format($transaction->amount_foreign, 2) }} {{ $transaction->currency_code }}</td>
                                <td class="px-4 py-3">{{ number_format($transaction->rate_used, 4) }}</td>
                            </tr>
                        @endforeach
                    </x-slot:tbody>
                </x-table>
            @endif
        </x-card>
    </div>
</x-app-layout>
