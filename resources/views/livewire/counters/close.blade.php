<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Close Counter #{{ $counterSession->counter->number }}</h1>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="grid grid-cols-3 gap-6 mb-6">
                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Opened At</label>
                    <p class="mt-1">{{ $counterSession->opened_at->format('H:i') }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Opening Float</label>
                    <p class="mt-1">${{ number_format($counterSession->opening_float, 2) }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Total Sales</label>
                    <p class="mt-1">${{ number_format($totalSales, 2) }}</p>
                </div>
            </div>

            <form wire:submit="close">
                <div class="mb-6">
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Closing Cash Amount</label>
                    <input type="number" step="0.01" wire:model="closing_amount" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2" />
                    @error('closing_amount') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Notes</label>
                    <textarea wire:model="notes" rows="3" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2"></textarea>
                </div>

                <div class="flex justify-end gap-4">
                    <a href="{{ route('counters.index') }}" class="px-4 py-2 border border-[var(--color-border)] rounded">Cancel</a>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded">Close Counter</button>
                </div>
            </form>
        </div>
    </div>
</div>