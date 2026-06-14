<x-app-layout title="Approve Cancellation">
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">Approve Cancellation</h1>
            <p class="text-sm text-ink-muted mt-1">Review and approve transaction cancellation request</p>
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
                        <label class="block text-sm font-medium text-ink-muted mb-1">Original Date</label>
                        <p class="text-sm text-ink">{{ $transaction['created_at'] ?? 'N/A' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-surface border border-border rounded-xl mb-6">
            <div class="px-6 py-4 border-b border-border">
                <h2 class="text-lg font-medium text-ink">Cancellation Reason</h2>
            </div>
            <div class="p-6">
                <p class="text-sm text-gray-700">{{ $cancellation['reason'] ?? 'No reason provided' }}</p>
                <div class="mt-4">
                    <label class="block text-sm font-medium text-ink-muted mb-1">Requested By</label>
                    <p class="text-sm text-ink">{{ $cancellation['requested_by'] ?? 'N/A' }}</p>
                </div>
                <div class="mt-4">
                    <label class="block text-sm font-medium text-ink-muted mb-1">Requested At</label>
                    <p class="text-sm text-ink">{{ $cancellation['requested_at'] ?? 'N/A' }}</p>
                </div>
            </div>
        </div>

        <div class="bg-surface border border-border rounded-xl mb-6">
            <div class="px-6 py-4 border-b border-border">
                <h2 class="text-lg font-medium text-ink">Manager Approval</h2>
            </div>
            <div class="p-6">
                <form method="POST" action="{{ route('transactions.approve-cancellation.store', $transaction['id'] ?? 0) }}">
                    @csrf
                    <div class="mb-4">
                        <label for="approval_notes" class="block text-sm font-medium text-gray-700 mb-2">Approval Notes</label>
                        <textarea
                            id="approval_notes"
                            name="approval_notes"
                            rows="4"
                            class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-lg"
                            placeholder="Enter approval notes (optional)"
                        ></textarea>
                    </div>
                    <div class="flex items-center gap-4">
                        <button
                            type="submit"
                            class="px-4 py-2 text-sm font-medium rounded-lg bg-primary text-white hover:bg-primary-hover"
                        >
                            Approve Cancellation
                        </button>
                        <a
                            href="{{ route('transactions.index') }}"
                            class="px-4 py-2 text-sm font-medium rounded-lg bg-surface border border-border hover:bg-canvas-subtle"
                        >
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>