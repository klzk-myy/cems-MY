<x-app-layout title="Cancel Transaction">
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">Cancel Transaction</h1>
            <p class="text-sm text-ink-muted mt-1">Request cancellation for transaction</p>
        </div>

        <div class="bg-surface border border-border rounded-xl mb-6">
            <div class="px-6 py-4 border-b border-border">
                <h2 class="text-lg font-medium text-ink">Transaction Details</h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Transaction ID</label>
                        <p class="text-sm text-ink">{{ $transaction['id'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Transaction Type</label>
                        <p class="text-sm text-ink">{{ $transaction['type'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Amount</label>
                        <p class="text-sm text-ink">{{ $transaction['amount'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Currency</label>
                        <p class="text-sm text-ink">{{ $transaction['currency'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Customer</label>
                        <p class="text-sm text-ink">{{ $transaction['customer_name'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Rate</label>
                        <p class="text-sm text-ink">{{ $transaction['rate'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Counter</label>
                        <p class="text-sm text-ink">{{ $transaction['counter_id'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Original Date</label>
                        <p class="text-sm text-ink">{{ $transaction['created_at'] ?? 'N/A' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-surface border border-border rounded-xl mb-6">
            <div class="px-6 py-4 border-b border-border">
                <h2 class="text-lg font-medium text-ink">Cancellation Request</h2>
            </div>
            <div class="p-6">
                <form method="POST" action="{{ route('transactions.cancel.store', $transaction['id'] ?? 0) }}">
                    @csrf
                    <div class="mb-4">
                        <label for="reason" class="block text-sm font-medium text-gray-700 mb-2">
                            Reason for Cancellation <span class="text-red-600">*</span>
                        </label>
                        <textarea id="reason" name="reason" rows="4" required class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-lg" placeholder="Enter the reason for cancellation"></textarea>
                        @error('reason')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="flex items-center gap-4">
                        <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-primary text-white hover:bg-primary-hover">
                            Submit Cancellation Request
                        </button>
                        <a href="{{ route('transactions.show', $transaction['id'] ?? 0) }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-surface border border-border hover:bg-canvas-subtle">
                            Back to Transaction
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="bg-surface border border-border rounded-xl">
            <div class="px-6 py-4 border-b border-border">
                <h2 class="text-lg font-medium text-ink">Important Notice</h2>
            </div>
            <div class="p-6">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-yellow-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <div>
                        <p class="text-sm text-gray-700">Cancellation requests require manager approval.</p>
                        <p class="text-sm text-ink-muted mt-1">You will be notified once the request has been processed.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>