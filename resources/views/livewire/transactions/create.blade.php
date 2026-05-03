<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <a href="{{ route('transactions.index') }}" class="text-[var(--color-ink)] hover:underline mb-4 inline-block">← Back to Transactions</a>

        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Create Transaction</h1>

        <form wire:submit="save" class="bg-white rounded-lg shadow p-6">
            <div class="grid grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Type</label>
                    <select wire:model="type" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2">
                        <option value="deposit">Deposit</option>
                        <option value="withdrawal">Withdrawal</option>
                        <option value="transfer">Transfer</option>
                        <option value="exchange">Exchange</option>
                    </select>
                    @error('type') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Amount</label>
                    <input type="number" step="0.01" wire:model="amount" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2" />
                    @error('amount') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Account</label>
                    <select wire:model="account_id" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2">
                        <option value="">Select account</option>
                        @foreach($accounts as $account)
                        <option value="{{ $account->id }}">{{ $account->name }}</option>
                        @endforeach
                    </select>
                    @error('account_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Reference</label>
                    <input type="text" wire:model="reference" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2" />
                </div>

                <div class="col-span-2">
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Description</label>
                    <textarea wire:model="description" rows="3" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2"></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-4 mt-6">
                <a href="{{ route('transactions.index') }}" class="px-4 py-2 border border-[var(--color-border)] rounded">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-[var(--color-ink)] text-white rounded">Create</button>
            </div>
        </form>
    </div>
</div>