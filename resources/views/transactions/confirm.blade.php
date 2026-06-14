<x-app-layout title="Confirm Transaction">
    <div class="space-y-6">
        <x-page-header
            title="Confirm Transaction"
            description="Review and confirm transaction details"
        />

        <x-card title="Transaction Summary">
            <x-card-section>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Transaction Type</label>
                        <x-badge
                            :variant="($transaction['type'] ?? '') === 'Buy' ? 'success' : 'info'"
                        >
                            {{ $transaction['type'] ?? 'N/A' }}
                        </x-badge>
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
            </x-card-section>
        </x-card>

        <x-card title="Customer Details">
            <x-card-section>
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
            </x-card-section>
        </x-card>

        <x-card title="Financial Details">
            <x-card-section>
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
            </x-card-section>
        </x-card>

        <x-card title="Confirm">
            <x-card-section>
                <form method="POST" action="{{ route('transactions.confirm.store', $transaction['id'] ?? 0) }}">
                    @csrf
                    <div class="mb-4">
                        <label for="mfa_code" class="block text-sm font-medium text-ink-muted mb-2">
                            MFA Verification Code <span class="text-danger-text">*</span>
                        </label>
                        <x-input
                            type="text"
                            id="mfa_code"
                            name="mfa_code"
                            required
                            maxlength="6"
                            pattern="[0-9]{6}"
                            placeholder="Enter 6-digit MFA code"
                            inline
                        />
                        @error('mfa_code')
                            <p class="mt-1 text-sm text-danger-text">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="flex items-center gap-4">
                        <x-button type="submit" variant="primary">Confirm Transaction</x-button>
                        <x-button href="{{ route('transactions.create') }}" variant="secondary">Back</x-button>
                    </div>
                </form>
            </x-card-section>
        </x-card>
    </div>
</x-app-layout>
