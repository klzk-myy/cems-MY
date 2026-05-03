<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <a href="{{ route('branch-openings.index') }}" class="text-[var(--color-ink)] hover:underline mb-4 inline-block">← Back to Branch Openings</a>

        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Branch Opening - Step 3</h1>

        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-medium text-[var(--color-ink)] mb-4">Equipment & Setup</h2>

            <div class="grid grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Number of Counters</label>
                    <input type="number" wire:model="counter_count" min="1" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2" />
                    @error('counter_count') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Safe Amount</label>
                    <input type="number" step="0.01" wire:model="safe_amount" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2" />
                    @error('safe_amount') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>

                <div class="col-span-2">
                    <label class="flex items-center">
                        <input type="checkbox" wire:model="has_atm" class="mr-3" />
                        <span class="text-sm font-medium text-[var(--color-ink)]">ATM Available</span>
                    </label>
                </div>

                <div class="col-span-2">
                    <label class="flex items-center">
                        <input type="checkbox" wire:model="has_foreign_exchange" class="mr-3" />
                        <span class="text-sm font-medium text-[var(--color-ink)]">Foreign Exchange Services</span>
                    </label>
                </div>
            </div>

            <div class="flex justify-between mt-6">
                <button wire:click="back" class="px-4 py-2 border border-[var(--color-border)] rounded">← Back</button>
                <button wire:click="next" class="px-4 py-2 bg-[var(--color-ink)] text-white rounded">Next →</button>
            </div>
        </div>
    </div>
</div>