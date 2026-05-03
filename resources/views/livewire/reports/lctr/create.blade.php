<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">LCTR Report</h1>

        <div class="bg-white rounded-lg shadow p-6">
            <form wire:submit="save">
                <div class="grid grid-cols-3 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink)]">Period</label>
                        <input type="month" wire:model="period" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2" />
                        @error('period') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink)]">Total Transactions</label>
                        <input type="number" wire:model="total_transactions" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2" />
                        @error('total_transactions') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-[var(--color-ink)]">Total Value</label>
                        <input type="number" step="0.01" wire:model="total_value" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2" />
                        @error('total_value') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Notes</label>
                    <textarea wire:model="notes" rows="4" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2"></textarea>
                </div>

                <div class="flex justify-end gap-4">
                    <a href="{{ route('reports.lctr.index') }}" class="px-4 py-2 border border-[var(--color-border)] rounded">Cancel</a>
                    <button type="submit" class="px-4 py-2 bg-[var(--color-ink)] text-white rounded">Save Report</button>
                </div>
            </form>
        </div>
    </div>
</div>