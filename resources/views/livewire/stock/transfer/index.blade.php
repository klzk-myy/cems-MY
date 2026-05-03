<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-[var(--color-ink)]">Stock Transfers</h1>
            <a href="{{ route('stock.transfer.create') }}" class="px-4 py-2 bg-[var(--color-ink)] text-white rounded">New Transfer</a>
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">ID</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">From</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">To</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Product</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Quantity</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Status</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transfers as $transfer)
                    <tr class="border-t border-[var(--color-border)]">
                        <td class="px-4 py-3">{{ $transfer->id }}</td>
                        <td class="px-4 py-3">{{ $transfer->from_location }}</td>
                        <td class="px-4 py-3">{{ $transfer->to_location }}</td>
                        <td class="px-4 py-3">{{ $transfer->product_code }}</td>
                        <td class="px-4 py-3">{{ $transfer->quantity }}</td>
                        <td class="px-4 py-3">
                            @if($transfer->status === 'completed')
                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs">Completed</span>
                            @elseif($transfer->status === 'pending')
                                <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs">Pending</span>
                            @else
                                <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded text-xs">{{ $transfer->status }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $transfer->created_at->format('Y-m-d') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-3 text-center text-[var(--color-ink)]">No transfers found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $transfers->links() }}
        </div>
    </div>
</div>