<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <a href="{{ route('transactions.index') }}" class="text-[var(--color-ink)] hover:underline mb-4 inline-block">← Back to Transactions</a>

        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Approve Transaction #{{ $transaction->id }}</h1>

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
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Created By</label>
                    <p class="mt-1">{{ $transaction->user->name ?? 'N/A' }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Date</label>
                    <p class="mt-1">{{ $transaction->created_at->format('Y-m-d H:i') }}</p>
                </div>

                @if($transaction->description)
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Description</label>
                    <p class="mt-1">{{ $transaction->description }}</p>
                </div>
                @endif
            </div>

            <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded">
                <p class="text-yellow-800">Please review this transaction carefully before approving.</p>
            </div>

            <div class="flex justify-end gap-4 mt-6">
                <a href="{{ route('transactions.index') }}" class="px-4 py-2 border border-[var(--color-border)] rounded">Cancel</a>
                <button wire:click="reject" class="px-4 py-2 bg-red-600 text-white rounded">Reject</button>
                <button wire:click="approve" class="px-4 py-2 bg-green-600 text-white rounded">Approve</button>
            </div>
        </div>
    </div>
</div>