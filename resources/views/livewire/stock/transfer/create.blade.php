<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <a href="{{ route('stock.transfer.index') }}" class="text-[var(--color-ink)] hover:underline mb-4 inline-block">← Back to Transfers</a>

        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Create Stock Transfer</h1>

        <form wire:submit="save" class="bg-white rounded-lg shadow p-6">
            <div class="grid grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">From Location</label>
                    <input type="text" wire:model="from_location" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2" />
                    @error('from_location') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">To Location</label>
                    <input type="text" wire:model="to_location" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2" />
                    @error('to_location') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Product Code</label>
                    <input type="text" wire:model="product_code" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2" />
                    @error('product_code') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Description</label>
                    <input type="text" wire:model="description" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2" />
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Quantity</label>
                    <input type="number" wire:model="quantity" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2" />
                    @error('quantity') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>

                <div class="col-span-2">
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Notes</label>
                    <textarea wire:model="notes" rows="3" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2"></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-4 mt-6">
                <a href="{{ route('stock.transfer.index') }}" class="px-4 py-2 border border-[var(--color-border)] rounded">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-[var(--color-ink)] text-white rounded">Create Transfer</button>
            </div>
        </form>
    </div>
</div>