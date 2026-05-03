<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <a href="{{ route('compliance.cases.index') }}" class="text-[var(--color-ink)] hover:underline mb-4 inline-block">← Back to Cases</a>

        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Create Compliance Case</h1>

        <form wire:submit="save" class="bg-white rounded-lg shadow p-6">
            <div class="grid grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Case Type</label>
                    <select wire:model="type" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2">
                        <option value="">Select type</option>
                        <option value="kyc">KYC Review</option>
                        <option value="suspicious_activity">Suspicious Activity</option>
                        <option value="transaction_review">Transaction Review</option>
                        <option value="regulatory">Regulatory</option>
                        <option value="other">Other</option>
                    </select>
                    @error('type') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Priority</label>
                    <select wire:model="priority" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Customer Name</label>
                    <input type="text" wire:model="customer_name" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2" />
                    @error('customer_name') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Customer ID</label>
                    <input type="text" wire:model="customer_id" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2" />
                </div>

                <div class="col-span-2">
                    <label class="block text-sm font-medium text-[var(--color-ink)]">Description</label>
                    <textarea wire:model="description" rows="4" class="mt-1 block w-full rounded border border-[var(--color-border)] px-3 py-2"></textarea>
                    @error('description') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="flex justify-end gap-4 mt-6">
                <a href="{{ route('compliance.cases.index') }}" class="px-4 py-2 border border-[var(--color-border)] rounded">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-[var(--color-ink)] text-white rounded">Create Case</button>
            </div>
        </form>
    </div>
</div>