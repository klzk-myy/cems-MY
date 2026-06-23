<x-app-layout title="Reject Cancellation">
    <div class="p-6 space-y-6">
        <x-page-header title="Reject Cancellation" description="Reject transaction cancellation request" />

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

        <x-card title="Cancellation Request">
            <div class="mb-4">
                <label class="block text-sm font-medium text-ink-muted mb-1">Reason Provided</label>
                <p class="text-sm text-ink-muted">{{ $cancellation['reason'] ?? 'No reason provided' }}</p>
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
        </x-card>

        <x-card title="Rejection Details">
            <form method="POST" action="{{ route('transactions.reject-cancellation.store', $transaction['id'] ?? 0) }}">
                @csrf
                <x-textarea
                    name="rejection_reason"
                    label="Rejection Reason"
                    :required="true"
                    rows="4"
                    placeholder="Enter the reason for rejecting this cancellation request"
                >{{ old('rejection_reason') }}</x-textarea>

                <div class="flex items-center gap-4">
                    <x-button type="submit" variant="danger">Reject Cancellation</x-button>
                    <x-button variant="secondary" href="{{ route('transactions.index') }}">Cancel</x-button>
                </div>
            </form>
        </x-card>

        <x-alert type="warning">
            Rejecting this cancellation request will keep the transaction in its current state.
        </x-alert>
    </div>
</x-app-layout>
