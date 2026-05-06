<x-app-layout title="Customer Transaction History">
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">Customer Transaction History</h1>
            <p class="text-sm text-gray-500 mt-1">View transaction history for this customer</p>
        </div>

        <div class="bg-white border border-[#e5e5e5] rounded-xl mb-6">
            <div class="px-6 py-4 border-b border-[#e5e5e5]">
                <h2 class="text-lg font-medium text-gray-900">Customer Information</h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Customer Name</label>
                        <p class="text-sm text-gray-900">{{ $customer['name'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Customer ID</label>
                        <p class="text-sm text-gray-900">{{ $customer['id'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">ID Number</label>
                        <p class="text-sm text-gray-900">{{ $customer->id_number_masked ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Risk Level</label>
                        <p class="text-sm">
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded {{ ($customer['risk_level'] ?? 'Low') === 'High' ? 'bg-red-100 text-red-700' : (($customer['risk_level'] ?? 'Low') === 'Medium' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700') }}">
                                {{ $customer['risk_level'] ?? 'Low' }}
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white border border-[#e5e5e5] rounded-xl mb-6">
            <div class="px-6 py-4 border-b border-[#e5e5e5]">
                <h2 class="text-lg font-medium text-gray-900">Summary</h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Total Transactions</label>
                        <p class="text-2xl font-semibold text-gray-900">{{ $summary['total_count'] ?? 0 }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Total Buy Amount</label>
                        <p class="text-2xl font-semibold text-green-700">{{ $summary['total_buy'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Total Sell Amount</label>
                        <p class="text-2xl font-semibold text-blue-700">{{ $summary['total_sell'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500 mb-1">Last Transaction</label>
                        <p class="text-sm text-gray-900">{{ $summary['last_transaction'] ?? 'N/A' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white border border-[#e5e5e5] rounded-xl">
            <div class="px-6 py-4 border-b border-[#e5e5e5]">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-medium text-gray-900">Transaction History</h2>
                    <a
                        href="{{ route('transactions.export.customer', $customer['id'] ?? 0) }}"
                        class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50"
                    >
                        Export
                    </a>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Currency</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">MYR</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e5e5e5]">
                        @forelse($transactions ?? [] as $tx)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm text-gray-900">{{ $tx['created_at'] ?? 'N/A' }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded {{ ($tx['type'] ?? '') === 'Buy' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700' }}">
                                        {{ $tx['type'] ?? 'N/A' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">{{ $tx['amount'] ?? 'N/A' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900">{{ $tx['currency'] ?? 'N/A' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900">{{ $tx['myr_amount'] ?? 'N/A' }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded {{ ($tx['status'] ?? '') === 'Completed' ? 'bg-green-100 text-green-700' : (($tx['status'] ?? '') === 'Pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-700') }}">
                                        {{ $tx['status'] ?? 'N/A' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <a
                                        href="{{ route('transactions.show', $tx['id'] ?? 0) }}"
                                        class="text-blue-600 hover:text-blue-800"
                                    >
                                        View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-3 text-sm text-gray-500 text-center">No transactions found</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if(isset($transactions) && method_exists($transactions, 'links'))
                <div class="px-6 py-4 border-t border-[#e5e5e5]">
                    {{ $transactions->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>