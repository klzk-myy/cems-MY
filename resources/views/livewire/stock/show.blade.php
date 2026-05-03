<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <a href="{{ route('stock.index') }}" class="text-[var(--color-ink)] hover:underline mb-4 inline-block">← Back to Stock</a>

        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Stock Item: {{ $stockItem->product_code }}</h1>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="grid grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Product Code</label>
                    <p class="mt-1">{{ $stockItem->product_code }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Description</label>
                    <p class="mt-1">{{ $stockItem->description }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Quantity</label>
                    <p class="mt-1">{{ $stockItem->quantity }} {{ $stockItem->unit }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Unit Price</label>
                    <p class="mt-1">${{ number_format($stockItem->unit_price, 2) }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Category</label>
                    <p class="mt-1">{{ $stockItem->category ?? 'N/A' }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Total Value</label>
                    <p class="mt-1">${{ number_format($stockItem->quantity * $stockItem->unit_price, 2) }}</p>
                </div>
            </div>

            <div class="flex justify-end gap-4 mt-6">
                <a href="{{ route('stock.edit', $stockItem->id) }}" class="px-4 py-2 bg-[var(--color-ink)] text-white rounded">Edit</a>
                <button wire:click="delete" wire:confirm="Are you sure?" class="px-4 py-2 bg-red-600 text-white rounded">Delete</button>
            </div>
        </div>
    </div>
</div>