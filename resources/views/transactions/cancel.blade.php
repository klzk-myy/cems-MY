<x-app-layout title="Cancel Transaction">
    <div class="p-6 space-y-6">
        <x-page-header title="Cancel Transaction" description="Request cancellation for transaction" />

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
        </x-card>

        <x-card title="Cancellation Request">
            <form method="POST" action="{{ route('transactions.cancel.store', $transaction['id'] ?? 0) }}">
                @csrf
                <x-textarea
                    name="reason"
                    label="Reason for Cancellation"
                    :required="true"
                    rows="4"
                    placeholder="Enter the reason for cancellation"
                >{{ old('reason') }}</x-textarea>

                <div class="flex items-center gap-4">
                    <x-button type="submit" variant="primary">Submit Cancellation Request</x-button>
                    <x-button variant="secondary" href="{{ route('transactions.show', $transaction['id'] ?? 0) }}">Back to Transaction</x-button>
                </div>
            </form>
        </x-card>

        <x-alert type="warning" title="Important Notice">
            <p>Cancellation requests require manager approval.</p>
            <p class="mt-1">You will be notified once the request has been processed.</p>
        </x-alert>
    </div>
</x-app-layout>
