<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <a href="{{ route('branch-openings.index') }}" class="text-[var(--color-ink)] hover:underline mb-4 inline-block">← Back to Branch Openings</a>

        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Branch Opening - Step 2</h1>

        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-medium text-[var(--color-ink)] mb-4">Staff Allocation</h2>

            <div class="mb-6">
                <label class="block text-sm font-medium text-[var(--color-ink)]">Select Manager</label>
                <select wire:model="manager_id" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2">
                    <option value="">Select manager</option>
                    @foreach($availableManagers as $manager)
                    <option value="{{ $manager->id }}">{{ $manager->name }}</option>
                    @endforeach
                </select>
                @error('manager_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-[var(--color-ink)]">Select Staff</label>
                <div class="space-y-2">
                    @foreach($availableStaff as $staff)
                    <label class="flex items-center p-2 border border-[var(--color-border)] rounded">
                        <input type="checkbox" wire:model="staff_ids" value="{{ $staff->id }}" class="mr-3" />
                        {{ $staff->name }} - {{ $staff->role }}
                    </label>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-between mt-6">
                <button wire:click="back" class="px-4 py-2 border border-[var(--color-border)] rounded">← Back</button>
                <button wire:click="next" class="px-4 py-2 bg-[var(--color-ink)] text-white rounded">Next →</button>
            </div>
        </div>
    </div>
</div>