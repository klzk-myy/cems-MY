<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <a href="{{ route('stock.index') }}" class="text-[var(--color-ink)] hover:underline mb-4 inline-block">← Back to Stock</a>

        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Stock Transfer #{{ $transfer->id }}</h1>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="grid grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">From Location</label>
                    <p class="mt-1">{{ $transfer->from_location }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">To Location</label>
                    <p class="mt-1">{{ $transfer->to_location }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Product</label>
                    <p class="mt-1">{{ $transfer->product_code }} - {{ $transfer->description }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Quantity</label>
                    <p class="mt-1">{{ $transfer->quantity }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Status</label>
                    <p class="mt-1">
                        @if($transfer->status === 'completed')
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded">Completed</span>
                        @elseif($transfer->status === 'pending')
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded">Pending</span>
                        @else
                            <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded">{{ $transfer->status }}</span>
                        @endif
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Date</label>
                    <p class="mt-1">{{ $transfer->created_at->format('Y-m-d H:i') }}</p>
                </div>
            </div>

            @if($transfer->notes)
            <div class="mt-4">
                <label class="block text-sm font-medium text-[var(--color-ink)]">Notes</label>
                <p class="mt-1">{{ $transfer->notes }}</p>
            </div>
            @endif

            <div class="flex justify-end gap-4 mt-6">
                @if($transfer->status === 'pending')
                <button wire:click="complete" class="px-4 py-2 bg-green-600 text-white rounded">Complete Transfer</button>
                <button wire:click="cancel" wire:confirm="Cancel this transfer?" class="px-4 py-2 bg-red-600 text-white rounded">Cancel</button>
                @endif
            </div>
        </div>
    </div>
</div>