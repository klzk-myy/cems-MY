<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <a href="{{ route('branch-openings.index') }}" class="text-[var(--color-ink)] hover:underline mb-4 inline-block">← Back to Branch Openings</a>

        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Branch Opening - Step 1</h1>

        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-medium text-[var(--color-ink)] mb-4">Branch Information</h2>

            <div class="grid grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Branch Name</label>
                    <input type="text" wire:model="branch_name" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2" />
                    @error('branch_name') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Location</label>
                    <input type="text" wire:model="location" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2" />
                    @error('location') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Scheduled Date</label>
                    <input type="date" wire:model="scheduled_date" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2" />
                    @error('scheduled_date') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="flex justify-end gap-4 mt-6">
                <a href="{{ route('branch-openings.index') }}" class="px-4 py-2 border border-[var(--color-border)] rounded">Cancel</a>
                <button wire:click="next" class="px-4 py-2 bg-[var(--color-ink)] text-white rounded">Next →</button>
            </div>
        </div>
    </div>
</div>