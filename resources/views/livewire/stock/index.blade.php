<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-[var(--color-ink)]">Stock Items</h1>
            <a href="{{ route('stock.create') }}" class="px-4 py-2 bg-[var(--color-ink)] text-white rounded">Add Stock Item</a>
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Product Code</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Description</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Quantity</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Unit Price</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Category</th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-[var(--color-ink)]">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stockItems as $item)
                    <tr class="border-t border-[var(--color-border)]">
                        <td class="px-4 py-3">{{ $item->product_code }}</td>
                        <td class="px-4 py-3">{{ $item->description }}</td>
                        <td class="px-4 py-3">{{ $item->quantity }} {{ $item->unit }}</td>
                        <td class="px-4 py-3">${{ number_format($item->unit_price, 2) }}</td>
                        <td class="px-4 py-3">{{ $item->category ?? 'N/A' }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('stock.show', $item->id) }}" class="text-blue-600 hover:underline mr-2">View</a>
                            <a href="{{ route('stock.edit', $item->id) }}" class="text-blue-600 hover:underline mr-2">Edit</a>
                            <button wire:click="delete({{ $item->id }})" wire:confirm="Delete this item?" class="text-red-600 hover:underline">Delete</button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-3 text-center text-[var(--color-ink)]">No stock items found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $stockItems->links() }}
        </div>
    </div>
</div>