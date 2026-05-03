<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-[var(--color-ink)]">Transactions</h1>
            <a href="{{ route('transactions.create') }}" class="px-4 py-2 bg-[var(--color-ink)] text-white rounded">New Transaction</a>
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">ID</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Date</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Type</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Amount</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Status</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $tx)
                    <tr class="border-t border-[var(--color-border)]">
                        <td class="px-4 py-3">{{ $tx->id }}</td>
                        <td class="px-4 py-3">{{ $tx->created_at->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3">{{ ucfirst($tx->type) }}</td>
                        <td class="px-4 py-3">${{ number_format($tx->amount, 2) }}</td>
                        <td class="px-4 py-3">
                            @if($tx->status === 'approved')
                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">Approved</span>
                            @elseif($tx->status === 'pending')
                                <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs">Pending</span>
                            @else
                                <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs">{{ $tx->status }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('transactions.show', $tx->id) }}" class="text-blue-600 hover:underline">View</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-3 text-center">No transactions found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $transactions->links() }}
        </div>
    </div>
</div>