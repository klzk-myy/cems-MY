<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <a href="{{ route('stock.index') }}" class="text-[var(--color-ink)] hover:underline mb-4 inline-block">← Back to Stock</a>

        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">{{ $stockItem ? 'Edit Stock Item' : 'Create Stock Item' }}</h1>

        <form wire:submit="save" class="bg-white rounded-lg shadow p-6">
            <div class="grid grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Product Code</label>
                    <input type="text" wire:model="product_code" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2" />
                    @error('product_code') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Description</label>
                    <input type="text" wire:model="description" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2" />
                    @error('description') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Quantity</label>
                    <input type="number" wire:model="quantity" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2" />
                    @error('quantity') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Unit</label>
                    <input type="text" wire:model="unit" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2" />
                    @error('unit') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Unit Price</label>
                    <input type="number" step="0.01" wire:model="unit_price" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2" />
                    @error('unit_price') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Category</label>
                    <input type="text" wire:model="category" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2" />
                </div>
            </div>

            <div class="flex justify-end gap-4 mt-6">
                <a href="{{ route('stock.index') }}" class="px-4 py-2 border border-[var(--color-border)] rounded">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-[var(--color-ink)] text-white rounded">Save</button>
            </div>
        </form>
    </div>
</div>