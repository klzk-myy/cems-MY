<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <a href="{{ route('transactions.index') }}" class="text-[var(--color-ink)] hover:underline mb-4 inline-block">← Back to Transactions</a>

        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Transaction #{{ $transaction->id }}</h1>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="grid grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Date</label>
                    <p class="mt-1">{{ $transaction->created_at->format('Y-m-d H:i:s') }}</p>
                </div>

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
                    <p class="mt-1">
                        @if($transaction->status === 'approved')
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded">Approved</span>
                        @elseif($transaction->status === 'pending')
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded">Pending</span>
                        @else
                            <span class="px-2 py-1 bg-red-100 text-red-800 rounded">{{ ucfirst($transaction->status) }}</span>
                        @endif
                    </p>
                </div>

                @if($transaction->reference)
                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Reference</label>
                    <p class="mt-1">{{ $transaction->reference }}</p>
                </div>
                @endif

                @if($transaction->description)
                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Description</label>
                    <p class="mt-1">{{ $transaction->description }}</p>
                </div>
                @endif
            </div>

            <div class="flex justify-end gap-4 mt-6">
                @if($transaction->status === 'pending')
                <button wire:click="approve" class="px-4 py-2 bg-green-600 text-white rounded">Approve</button>
                <button wire:click="cancel" wire:confirm="Cancel this transaction?" class="px-4 py-2 bg-red-600 text-white rounded">Cancel</button>
                @endif
            </div>
        </div>
    </div>
</div>