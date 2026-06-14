<x-app-layout title="Reject Cancellation">
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">Reject Cancellation</h1>
            <p class="text-sm text-ink-muted mt-1">Reject transaction cancellation request</p>
        </div>

        <div class="bg-white border border-[#e5e5e5] rounded-xl mb-6">
            <div class="px-6 py-4 border-b border-[#e5e5e5]">
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
                        <label class="block text-sm font-medium text-ink-muted mb-1">Original Date</label>
                        <p class="text-sm text-ink">{{ $transaction['created_at'] ?? 'N/A' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white border border-[#e5e5e5] rounded-xl mb-6">
            <div class="px-6 py-4 border-b border-[#e5e5e5]">
                <h2 class="text-lg font-medium text-ink">Cancellation Request</h2>
            </div>
            <div class="p-6">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-ink-muted mb-1">Reason Provided</label>
                    <p class="text-sm text-gray-700">{{ $cancellation['reason'] ?? 'No reason provided' }}</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Requested By</label>
                        <p class="text-sm text-ink">{{ $cancellation['requested_by'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Requested At</label>
                        <p class="text-sm text-ink">{{ $cancellation['requested_at'] ?? 'N/A' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white border border-[#e5e5e5] rounded-xl mb-6">
            <div class="px-6 py-4 border-b border-[#e5e5e5]">
                <h2 class="text-lg font-medium text-ink">Rejection Details</h2>
            </div>
            <div class="p-6">
                <form method="POST" action="{{ route('transactions.reject-cancellation.store', $transaction['id'] ?? 0) }}">
                    @csrf
                    <div class="mb-4">
                        <label for="rejection_reason" class="block text-sm font-medium text-gray-700 mb-2">
                            Rejection Reason <span class="text-red-600">*</span>
                        </label>
                        <textarea
                            id="rejection_reason"
                            name="rejection_reason"
                            rows="4"
                            required
                            class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg"
                            placeholder="Enter the reason for rejecting this cancellation request"
                        ></textarea>
                        @error('rejection_reason')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center gap-4">
                        <button
                            type="submit"
                            class="px-4 py-2 text-sm font-medium rounded-lg bg-red-600 text-white hover:bg-red-700"
                        >
                            Reject Cancellation
                        </button>
                        <a
                            href="{{ route('transactions.index') }}"
                            class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-canvas-subtle"
                        >
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="bg-white border border-[#e5e5e5] rounded-xl">
            <div class="px-6 py-4 border-b border-[#e5e5e5]">
                <h2 class="text-lg font-medium text-ink">Notice</h2>
            </div>
            <div class="p-6">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-yellow-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <p class="text-sm text-gray-700">Rejecting this cancellation request will keep the transaction in its current state.</p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>