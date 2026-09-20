<x-app-layout title="Reverse Transaction">
    <div class="space-y-6">
        <x-page-header title="Reverse Transaction" description="Reverse a completed transaction and issue a refund" />

        <x-card title="Transaction Details">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-ink-muted mb-1">Transaction ID</label>
                    <p class="text-sm text-ink">{{ $transaction->id }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink-muted mb-1">Transaction Type</label>
                    <p class="text-sm text-ink">{{ $transaction->type?->value ?? 'N/A' }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink-muted mb-1">Amount</label>
                    <p class="text-sm text-ink">{{ number_format($transaction->quantity ?? 0, 2) }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink-muted mb-1">Currency</label>
                    <p class="text-sm text-ink">{{ $transaction->currency_code ?? 'N/A' }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink-muted mb-1">Customer</label>
                    <p class="text-sm text-ink"><x-customer-link :customer="$transaction->customer" /></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink-muted mb-1">Rate</label>
                    <p class="text-sm text-ink">{{ $transaction->rate !== null ? number_format((float) $transaction->rate, 8) : 'N/A' }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink-muted mb-1">MYR Amount</label>
                    <p class="text-sm text-ink">RM {{ number_format($transaction->amount_myr ?? 0, 2) }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink-muted mb-1">Original Date</label>
                    <p class="text-sm text-ink">{{ $transaction->created_at?->toDateTimeString() ?? 'N/A' }}</p>
                </div>
            </div>
        </x-card>

        <x-card title="Reversal Request">
            <form method="POST" action="{{ route('transactions.reverse.store', $transaction->id) }}">
                @csrf
                <x-textarea
                    name="reason"
                    label="Reason for Reversal"
                    :required="true"
                    rows="4"
                    placeholder="Enter the reason for reversal"
                >{{ old('reason') }}</x-textarea>

                <x-checkbox
                    name="confirm_understanding"
                    label="I understand this reversal restores all stock, till, allocation, and accounting entries, and creates a refund transaction requiring compliance approval."
                    :required="true"
                />

                <div class="flex items-center gap-4">
                    <x-button type="submit" variant="danger">Reverse Transaction</x-button>
                    <x-button variant="secondary" href="{{ route('transactions.show', $transaction->id) }}">Back to Transaction</x-button>
                </div>
            </form>
        </x-card>

        <x-alert type="warning" title="Important Notice">
            <p>The reversal is applied immediately: positions, till balances, teller allocations, and journal entries are compensated now.</p>
            <p class="mt-1">A refund transaction is created for the physical settlement — it requires compliance clearance, approval, and completion before the refund is finalized.</p>
        </x-alert>
    </div>
</x-app-layout>
