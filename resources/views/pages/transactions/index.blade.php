<x-app-layout title="Transactions">
    <div class="p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">Transactions</h1>
            <a href="{{ route('transactions.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                New Transaction
            </a>
        </div>

        <div class="bg-white rounded-lg shadow p-4 mb-6">
            <form method="GET" class="flex gap-4">
                <input type="text" name="search" placeholder="Search by reference..." 
                       class="border rounded px-3 py-2 flex-1" 
                       value="{{ request('search') }}">
                <select name="status" class="border rounded px-3 py-2">
                    <option value="">All Status</option>
                    @foreach($transactions->pluck('status')->unique() as $status)
                        <option value="{{ $status }}" {{ request('status') == $status ? 'selected' : '' }}>{{ $status }}</option>
                    @endforeach
                </select>
                <button type="submit" class="bg-gray-800 text-white px-4 py-2 rounded">Filter</button>
            </form>
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
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
                            <span class="px-2 py-1 rounded text-xs {{ $transaction->type === 'Buy' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $transaction->type }}
                            </span>
                        </td>
                        <td class="px-4 py-3">{{ number_format($transaction->amount_foreign, 2) }} {{ $transaction->currency_code }}</td>
                        <td class="px-4 py-3">{{ number_format($transaction->rate_used, 4) }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 rounded text-xs 
                                @if($transaction->status === 'Completed') bg-green-100 text-green-800
                                @elseif($transaction->status === 'Pending') bg-yellow-100 text-yellow-800
                                @else bg-gray-100 text-gray-800 @endif">
                                {{ $transaction->status }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('transactions.show', $transaction) }}" class="text-blue-600 hover:underline">View</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-gray-500">No transactions found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $transactions->withQueryString()->links() }}
        </div>
    </div>
</x-app-layout>