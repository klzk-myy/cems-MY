<x-app-layout title="Confirm Transaction">
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">Confirm Transaction</h1>
            <p class="text-sm text-ink-muted mt-1">Review and confirm transaction details</p>
        </div>

        <div class="bg-surface border border-border rounded-xl mb-6">
            <div class="px-6 py-4 border-b border-border">
                <h2 class="text-lg font-medium text-ink">Transaction Summary</h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Transaction Type</label>
                        <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded {{ ($transaction['type'] ?? '') === 'Buy' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700' }}">
                            {{ $transaction['type'] ?? 'N/A' }}
                        </span>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Amount</label>
                        <p class="text-sm font-medium text-ink">{{ $transaction['amount'] ?? 'N/A' }} {{ $transaction['currency'] ?? '' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Exchange Rate</label>
                        <p class="text-sm text-ink">{{ $transaction['rate'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Counter</label>
                        <p class="text-sm text-ink">{{ $transaction['counter_id'] ?? 'N/A' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-surface border border-border rounded-xl mb-6">
            <div class="px-6 py-4 border-b border-border">
                <h2 class="text-lg font-medium text-ink">Customer Details</h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Customer Name</label>
                        <p class="text-sm text-ink">{{ $transaction['customer_name'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Customer ID</label>
                        <p class="text-sm text-ink">{{ $transaction['customer_id'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">ID Type</label>
                        <p class="text-sm text-ink">{{ $transaction['id_type'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">ID Number</label>
                        <p class="text-sm text-ink">{{ $transaction->customer->id_number_masked ?? 'N/A' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-surface border border-border rounded-xl mb-6">
            <div class="px-6 py-4 border-b border-border">
                <h2 class="text-lg font-medium text-ink">Financial Details</h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">MYR Amount</label>
                        <p class="text-sm font-medium text-ink">{{ $transaction['myr_amount'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">FCY Amount</label>
                        <p class="text-sm font-medium text-ink">{{ $transaction['fcy_amount'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Spread</label>
                        <p class="text-sm text-ink">{{ $transaction['spread'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Total</label>
                        <p class="text-sm font-bold text-ink">{{ $transaction['total'] ?? 'N/A' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-surface border border-border rounded-xl">
            <div class="px-6 py-4 border-b border-border">
                <h2 class="text-lg font-medium text-ink">Confirm</h2>
            </div>
            <div class="p-6">
                <form method="POST" action="{{ route('transactions.confirm.store', $transaction['id'] ?? 0) }}">
                    @csrf
                    <div class="mb-4">
                        <label for="mfa_code" class="block text-sm font-medium text-gray-700 mb-2">
                            MFA Verification Code <span class="text-red-600">*</span>
                        </label>
                        <input type="text" id="mfa_code" name="mfa_code" required maxlength="6" pattern="[0-9]{6}" class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-lg" placeholder="Enter 6-digit MFA code">
                        @error('mfa_code')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="flex items-center gap-4">
                        <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-primary text-white hover:bg-primary-hover">
                            Confirm Transaction
                        </button>
                        <a href="{{ route('transactions.create') }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-surface border border-border hover:bg-canvas-subtle">
                            Back
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>