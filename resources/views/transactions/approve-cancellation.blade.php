<x-app-layout title="Approve Cancellation">
    <div class="space-y-6">
        <x-page-header
            title="Approve Cancellation"
            description="Review and approve transaction cancellation request"
        />

        <x-card title="Transaction Details">
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
        </x-card>

        <x-card title="Cancellation Reason">
            <p class="text-sm text-ink-muted">{{ $cancellation['reason'] ?? 'No reason provided' }}</p>
            <div class="mt-4">
                <label class="block text-sm font-medium text-ink-muted mb-1">Requested By</label>
                <p class="text-sm text-ink">{{ $cancellation['requested_by'] ?? 'N/A' }}</p>
            </div>
            <div class="mt-4">
                <label class="block text-sm font-medium text-ink-muted mb-1">Requested At</label>
                <p class="text-sm text-ink">{{ $cancellation['requested_at'] ?? 'N/A' }}</p>
            </div>
        </x-card>

        <x-card title="Manager Approval">
            <form method="POST" action="{{ route('transactions.approve-cancellation.store', $transaction['id'] ?? 0) }}">
                @csrf
                <div class="mb-4">
                    <label for="approval_notes" class="block text-sm font-medium text-ink-muted mb-2">Approval Notes</label>
                    <textarea
                        id="approval_notes"
                        name="approval_notes"
                        rows="4"
                        class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-lg"
                        placeholder="Enter approval notes (optional)"
                    ></textarea>
                </div>
                <div class="flex items-center gap-4">
                    <x-button type="submit" variant="primary">Approve Cancellation</x-button>
                    <x-button variant="secondary" href="{{ route('transactions.index') }}">Cancel</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
