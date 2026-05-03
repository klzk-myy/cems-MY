<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Counter Handover</h1>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="mb-6">
                <h2 class="text-lg font-medium text-[var(--color-ink)] mb-4">Current Session Summary</h2>
                <div class="grid grid-cols-4 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Opening Float</label>
                        <p class="mt-1">${{ number_format($session->opening_float, 2) }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Total Cash In</label>
                        <p class="mt-1">${{ number_format($totalCashIn, 2) }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Total Cash Out</label>
                        <p class="mt-1">${{ number_format($totalCashOut, 2) }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-500">Expected Balance</label>
                        <p class="mt-1 font-bold">${{ number_format($expectedBalance, 2) }}</p>
                    </div>
                </div>
            </div>

            <form wire:submit="handover">
                <div class="mb-6">
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Hand Over To (Select User)</label>
                    <select wire:model="target_user_id" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2">
                        <option value="">Select user</option>
                        @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                    @error('target_user_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Actual Cash Count</label>
                    <input type="number" step="0.01" wire:model="actual_cash" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2" />
                    @error('actual_cash') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Notes</label>
                    <textarea wire:model="notes" rows="3" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2"></textarea>
                </div>

                <div class="flex justify-end gap-4">
                    <a href="{{ route('counters.index') }}" class="px-4 py-2 border border-[var(--color-border)] rounded">Cancel</a>
                    <button type="submit" class="px-4 py-2 bg-[var(--color-ink)] text-white rounded">Confirm Handover</button>
                </div>
            </form>
        </div>
    </div>
</div>