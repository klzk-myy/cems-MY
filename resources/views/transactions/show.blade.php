<x-app-layout title="Transaction Details">
    <div class="space-y-6">
        <x-page-header title="Transaction Details" :actions="true">
            ID: {{ $transaction['id'] ?? 'N/A' }}

            <x-slot:actions>
                <x-badge
                    :variant="match ($transaction['status'] ?? '') {
                        'Completed' => 'success',
                        'Pending' => 'warning',
                        'Cancelled' => 'danger',
                        default => 'gray',
                    }"
                >
                    {{ $transaction['status'] ?? 'N/A' }}
                </x-badge>
            </x-slot:actions>
        </x-page-header>

        <x-card title="Transaction Information">
            <x-card-section>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Transaction Type</label>
                        <x-badge
                            :variant="($transaction['type'] ?? '') === 'Buy' ? 'success' : 'info'"
                        >
                            {{ $transaction['type'] ?? 'N/A' }}
                        </x-badge>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Foreign Currency</label>
                        <p class="text-sm text-ink">{{ $transaction['currency'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">FCY Amount</label>
                        <p class="text-sm font-medium text-ink">{{ $transaction['fcy_amount'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Exchange Rate</label>
                        <p class="text-sm text-ink">{{ $transaction['rate'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">MYR Amount</label>
                        <p class="text-sm font-medium text-ink">{{ $transaction['myr_amount'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Counter</label>
                        <p class="text-sm text-ink">{{ $transaction['counter_id'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Branch</label>
                        <p class="text-sm text-ink">{{ $transaction['branch_name'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Created By</label>
                        <p class="text-sm text-ink">{{ $transaction['created_by'] ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">Created At</label>
                        <p class="text-sm text-ink">{{ $transaction['created_at'] ?? 'N/A' }}</p>
                    </div>
                </div>
            </x-card-section>
        </x-card>

        <x-card title="Customer Details">
            <x-card-section>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
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
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-1">CDD Level</label>
                        <p class="text-sm text-ink">{{ $transaction['cdd_level'] ?? 'N/A' }}</p>
                    </div>
                </div>
            </x-card-section>
        </x-card>

        <x-card title="Actions">
            <x-card-section>
                <div class="flex items-center gap-4 flex-wrap">
                    @if(($transaction['status'] ?? '') === 'Pending')
                        <form method="POST" action="{{ route('transactions.approve', $transaction['id'] ?? 0) }}" class="contents">
                            @csrf
                            <x-button type="submit" variant="primary">Approve</x-button>
                        </form>
                        <form method="POST" action="{{ route('transactions.reject', $transaction['id'] ?? 0) }}" class="contents">
                            @csrf
                            <x-button type="submit" variant="secondary">Reject</x-button>
                        </form>
                    @endif
                    @if(($transaction['status'] ?? '') === 'PendingCancellation')
                        <x-button href="{{ route('transactions.approve-cancellation', $transaction['id'] ?? 0) }}" variant="primary">Approve Cancellation</x-button>
                        <x-button href="{{ route('transactions.reject-cancellation', $transaction['id'] ?? 0) }}" variant="danger">Reject Cancellation</x-button>
                    @endif
                    @if(($transaction['status'] ?? '') === 'Completed')
                        <x-button href="{{ route('transactions.cancel', $transaction['id'] ?? 0) }}" variant="danger">Request Cancellation</x-button>
                    @endif
                    <x-button href="{{ route('transactions.print', $transaction['id'] ?? 0) }}" variant="secondary">Print Receipt</x-button>
                    <x-button href="{{ route('transactions.index') }}" variant="secondary">Back to List</x-button>
                </div>
            </x-card-section>
        </x-card>
    </div>
</x-app-layout>
