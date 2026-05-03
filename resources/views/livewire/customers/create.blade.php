<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <a href="{{ route('customers.index') }}" class="text-[var(--color-ink)] hover:underline mb-4 inline-block">← Back to Customers</a>

        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Create Customer</h1>

        <form wire:submit="save" class="bg-white rounded-lg shadow p-6">
            <div class="grid grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Name</label>
                    <input type="text" wire:model="name" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2" />
                    @error('name') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Email</label>
                    <input type="email" wire:model="email" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2" />
                    @error('email') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Phone</label>
                    <input type="text" wire:model="phone" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2" />
                    @error('phone') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="flex justify-end gap-4 mt-6">
                <a href="{{ route('customers.index') }}" class="px-4 py-2 border border-[var(--color-border)] rounded">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-[var(--color-ink)] text-white rounded">Create</button>
            </div>
        </form>
    </div>
</div>