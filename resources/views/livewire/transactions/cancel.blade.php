<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <a href="{{ route('transactions.index') }}" class="text-[var(--color-ink)] hover:underline mb-4 inline-block">← Back to Transactions</a>

        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Cancel Transaction #{{ $transaction->id }}</h1>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="grid grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Type</label>
                    <p class="mt-1">{{ ucfirst($transaction->type) }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Amount</label>
                    <p class="mt-1 text-2xl font-bold">${{ number_format($transaction->amount, 2) }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Status</label>
                    <p class="mt-1">{{ $transaction->status }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Date</label>
                    <p class="mt-1">{{ $transaction->created_at->format('Y-m-d H:i') }}</p>
                </div>
            </div>

            <div class="mt-6">
                <label class="block text-sm font-medium text-[var(--color-ink)]">Cancellation Reason</label>
                <textarea wire:model="reason" rows="4" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2" placeholder="Please provide a reason for cancellation..."></textarea>
                @error('reason') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </div>

            <div class="flex justify-end gap-4 mt-6">
                <a href="{{ route('transactions.show', $transaction->id) }}" class="px-4 py-2 border border-[var(--color-border)] rounded">Back</a>
                <button wire:click="cancel" class="px-4 py-2 bg-red-600 text-white rounded">Confirm Cancellation</button>
            </div>
        </div>
    </div>
</div>