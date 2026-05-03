<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Open Counter</h1>

        <div class="bg-white rounded-lg shadow p-6">
            <form wire:submit="open">
                <div class="mb-6">
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Select Counter</label>
                    <select wire:model="counter_id" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2">
                        <option value="">Select a counter</option>
                        @foreach($availableCounters as $counter)
                        <option value="{{ $counter->id }}">Counter #{{ $counter->number }} - {{ $counter->branch->name ?? 'Main' }}</option>
                        @endforeach
                    </select>
                    @error('counter_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Opening Float Amount</label>
                    <input type="number" step="0.01" wire:model="opening_float" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2" />
                    @error('opening_float') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>

                <div class="flex justify-end gap-4">
                    <a href="{{ route('counters.index') }}" class="px-4 py-2 border border-[var(--color-border)] rounded">Cancel</a>
                    <button type="submit" class="px-4 py-2 bg-[var(--color-ink)] text-white rounded">Open Counter</button>
                </div>
            </form>
        </div>
    </div>
</div>