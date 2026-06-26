<x-app-layout title="Transactions">
    <div class="space-y-6">
        <x-page-header title="Transactions">
            <x-slot:actions>
                <x-button href="{{ route('transactions.create') }}" variant="primary">New Transaction</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-filter-bar method="GET">
            <x-input type="text" name="search" placeholder="Search by reference..." value="{{ request('search') }}" class="flex-1" inline />
            <x-select name="status" :options="['' => 'All Status'] + $transactions->pluck('status.value', 'status.value')->unique()->toArray()" :selected="request('status')" inline />
            <x-button type="submit" variant="primary">Filter</x-button>
        </x-filter-bar>

        <x-card>
            <x-table>
                <x-slot:thead>
                    <tr class="text-left text-sm text-ink-muted">
                        <th class="px-4 py-3">Reference</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Amount</th>
                        <th class="px-4 py-3">Rate</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </x-slot:thead>
                <x-slot:tbody>
                    @forelse($transactions as $transaction)
                        <tr class="border-t border-border hover:bg-canvas-subtle">
                            <td class="px-4 py-3 font-mono text-sm">{{ $transaction->reference }}</td>
                            <td class="px-4 py-3">{{ $transaction->created_at->format('M d, Y H:i') }}</td>
                            <td class="px-4 py-3">{{ $transaction->customer->full_name ?? 'N/A' }}</td>
                            <td class="px-4 py-3">
                                <x-badge variant="{{ $transaction->type->value === 'Buy' ? 'success' : 'danger' }}">
                                    {{ $transaction->type->label() }}
                                </x-badge>
                            </td>
                            <td class="px-4 py-3">{{ number_format($transaction->amount_foreign, 2) }} {{ $transaction->currency_code }}</td>
                            <td class="px-4 py-3">{{ number_format($transaction->rate, 4) }}</td>
                            <td class="px-4 py-3">
                                <x-badge variant="{{ $transaction->status->value === 'Completed' ? 'success' : ($transaction->status->value === 'Pending' ? 'warning' : 'gray') }}">
                                    {{ $transaction->status->label() }}
                                </x-badge>
                            </td>
                            <td class="px-4 py-3">
                                <x-button href="{{ route('transactions.show', $transaction) }}" variant="ghost" size="sm">View</x-button>
                            </td>
                        </tr>
                    @empty
                        <x-empty-state message="No transactions found." :colspan="8" />
                    @endforelse
                </x-slot:tbody>
            </x-table>
        </x-card>

        <div class="mt-4">
            {{ $transactions->withQueryString()->links() }}
        </div>
    </div>
</x-app-layout>
