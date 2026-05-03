<div class="mb-6">
    <a href="/str" class="inline-flex items-center gap-2 text-sm text-[--color-ink-muted] hover:text-[--color-ink] transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
        </svg>
        Back to STRs
    </a>
</div>

<form wire:submit="create" class="space-y-6">
    <div class="bg-white rounded-xl border border-[--color-border] p-6">
        <h2 class="text-lg font-semibold text-[--color-ink] mb-6">Transaction Information</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-[--color-ink] mb-2">Transaction Date</label>
                <input type="date" wire:model="transactionDate" class="w-full px-4 py-2 border border-[--color-border] rounded-lg focus:outline-none focus:ring-2 focus:ring-[--color-accent]/30" required>
            </div>

            <div>
                <label class="block text-sm font-medium text-[--color-ink] mb-2">Transaction Amount (MYR)</label>
                <input type="number" wire:model="amount" step="0.01" class="w-full px-4 py-2 border border-[--color-border] rounded-lg focus:outline-none focus:ring-2 focus:ring-[--color-accent]/30" required>
            </div>

            <div>
                <label class="block text-sm font-medium text-[--color-ink] mb-2">Currency</label>
                <select wire:model="currency" class="w-full px-4 py-2 border border-[--color-border] rounded-lg focus:outline-none focus:ring-2 focus:ring-[--color-accent]/30" required>
                    <option value="MYR">MYR</option>
                    <option value="USD">USD</option>
                    <option value="EUR">EUR</option>
                    <option value="GBP">GBP</option>
                    <option value="SGD">SGD</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-[--color-ink] mb-2">Transaction Type</label>
                <select wire:model="transactionType" class="w-full px-4 py-2 border border-[--color-border] rounded-lg focus:outline-none focus:ring-2 focus:ring-[--color-accent]/30" required>
                    <option value="cash_deposit">Cash Deposit</option>
                    <option value="cash_withdrawal">Cash Withdrawal</option>
                    <option value="transfer">Transfer</option>
                    <option value="exchange">Currency Exchange</option>
                    <option value="other">Other</option>
                </select>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-[--color-border] p-6">
        <h2 class="text-lg font-semibold text-[--color-ink] mb-6">Customer Information</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-[--color-ink] mb-2">Customer Name</label>
                <input type="text" wire:model="customerName" class="w-full px-4 py-2 border border-[--color-border] rounded-lg focus:outline-none focus:ring-2 focus:ring-[--color-accent]/30" required>
            </div>

            <div>
                <label class="block text-sm font-medium text-[--color-ink] mb-2">IC/Passport Number</label>
                <input type="text" wire:model="customerIc" class="w-full px-4 py-2 border border-[--color-border] rounded-lg focus:outline-none focus:ring-2 focus:ring-[--color-accent]/30">
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-[--color-ink] mb-2">Customer Address</label>
                <textarea wire:model="customerAddress" rows="3" class="w-full px-4 py-2 border border-[--color-border] rounded-lg focus:outline-none focus:ring-2 focus:ring-[--color-accent]/30"></textarea>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-[--color-border] p-6">
        <h2 class="text-lg font-semibold text-[--color-ink] mb-6">Suspicious Activity Details</h2>

        <div class="space-y-6">
            <div>
                <label class="block text-sm font-medium text-[--color-ink] mb-2">Description of Suspicious Activity</label>
                <textarea wire:model="description" rows="4" class="w-full px-4 py-2 border border-[--color-border] rounded-lg focus:outline-none focus:ring-2 focus:ring-[--color-accent]/30" required></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-[--color-ink] mb-2">Reasons for Suspicion</label>
                <textarea wire:model="reasons" rows="3" class="w-full px-4 py-2 border border-[--color-border] rounded-lg focus:outline-none focus:ring-2 focus:ring-[--color-accent]/30" required></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-[--color-ink] mb-2">Initial Risk Rating</label>
                    <select wire:model="riskRating" class="w-full px-4 py-2 border border-[--color-border] rounded-lg focus:outline-none focus:ring-2 focus:ring-[--color-accent]/30">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="flex items-center justify-end gap-4">
        <a href="/str" class="px-6 py-2 text-sm font-medium text-[--color-ink-muted] hover:text-[--color-ink] transition-colors">
            Cancel
        </a>
        <button type="submit" class="px-6 py-2 bg-[--color-accent] text-white text-sm font-medium rounded-lg hover:bg-[--color-accent-dark] transition-colors">
            Submit STR
        </button>
    </div>
</form>