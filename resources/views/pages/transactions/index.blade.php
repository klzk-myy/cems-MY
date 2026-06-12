<x-app-layout title="Transactions">
    <div class="p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Transactions</h1>
            <a href="{{ route('transactions.create') }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                New Transaction
            </a>
        </div>

        <x-filter-bar method="GET">
            <x-input type="text" name="search" placeholder="Search by reference..." value="{{ request('search') }}" class="flex-1" />
            <x-select name="status" placeholder="All Status">
                <option value="">All Status</option>
                @foreach($transactions->pluck('status')->unique() as $status)
                    <option value="{{ $status }}" {{ request('status') == $status ? 'selected' : '' }}>{{ $status }}</option>
                @endforeach
            </x-select>
            <x-button type="submit" variant="primary">Filter</x-button>
        </x-filter-bar>

        <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr class="text-left text-sm text-gray-500">
                        <th class="px-4 py-3">Reference</th>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Amount</th>
                        <th class="px-4 py-3">Rate</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $transaction)
                    <tr class="border-t hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono text-sm">{{ $transaction->reference }}</td>
                        <td class="px-4 py-3">{{ $transaction->created_at->format('M d, Y H:i') }}</td>
                        <td class="px-4 py-3">{{ $transaction->customer->full_name ?? 'N/A' }}</td>
                        <td class="px-4 py-3">
                            <x-badge variant="{{ $transaction->type === 'Buy' ? 'success' : 'danger' }}">
                                {{ $transaction->type }}
                            </x-badge>
                        </td>
                        <td class="px-4 py-3">{{ number_format($transaction->amount_foreign, 2) }} {{ $transaction->currency_code }}</td>
                        <td class="px-4 py-3">{{ number_format($transaction->rate_used, 4) }}</td>
                        <td class="px-4 py-3">
                            <x-badge variant="{{ $transaction->status === 'Completed' ? 'success' : ($transaction->status === 'Pending' ? 'warning' : 'gray') }}">
                                {{ $transaction->status }}
                            </x-badge>
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('transactions.show', $transaction) }}" class="text-blue-600 hover:underline">View</a>
                        </td>
                    </tr>
                    @empty
                    <x-empty-state message="No transactions found." :colspan="8" />
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $transactions->withQueryString()->links() }}
        </div>
    </div>
</x-app-layout>