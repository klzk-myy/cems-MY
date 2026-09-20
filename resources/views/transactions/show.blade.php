<x-app-layout title="Transaction Details">
    <div class="space-y-6">
        <x-page-header title="Transaction Details">
            ID: {{ $transaction->id }}

            <x-slot:actions>
                <x-badge
                    :variant="match ($transaction->status) {
                        \App\Enums\TransactionStatus::Completed => 'success',
                        \App\Enums\TransactionStatus::Pending, \App\Enums\TransactionStatus::PendingApproval => 'warning',
                        \App\Enums\TransactionStatus::Cancelled => 'danger',
                        default => 'gray',
                    }"
                >
                    {{ $transaction->status?->label() ?? 'N/A' }}
                </x-badge>
            </x-slot:actions>
        </x-page-header>

        <x-card title="Transaction Information">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-medium text-ink-muted mb-1">Transaction Type</label>
                    <x-badge
                        :variant="$transaction->type?->value === 'Buy' ? 'success' : 'info'"
                    >
                        {{ $transaction->type?->label() ?? 'N/A' }}
                    </x-badge>
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink-muted mb-1">Foreign Currency</label>
                    <p class="text-sm text-ink">{{ $transaction->currency_code ?? 'N/A' }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink-muted mb-1">FCY Amount</label>
                    <p class="text-sm font-medium text-ink">{{ number_format((float) $transaction->quantity, 2) }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink-muted mb-1">Exchange Rate</label>
                    <p class="text-sm text-ink">{{ $transaction->rate }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink-muted mb-1">MYR Amount</label>
                    <p class="text-sm font-medium text-ink">{{ number_format((float) $transaction->amount_myr, 2) }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink-muted mb-1">Branch</label>
                    <p class="text-sm text-ink">{{ $transaction->branch?->name ?? 'N/A' }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink-muted mb-1">Created By</label>
                    <p class="text-sm text-ink">{{ $transaction->user?->username ?? 'N/A' }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink-muted mb-1">Created At</label>
                    <p class="text-sm text-ink">{{ $transaction->created_at?->format('Y-m-d H:i') ?? 'N/A' }}</p>
                </div>
            </div>
        </x-card>

        <x-card title="Customer Details">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-medium text-ink-muted mb-1">Customer Name</label>
                    <p class="text-sm text-ink"><x-customer-link :customer="$transaction->customer" /></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink-muted mb-1">Customer ID</label>
                    <p class="text-sm text-ink">{{ $transaction->customer_id ?? 'N/A' }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink-muted mb-1">ID Type</label>
                    <p class="text-sm text-ink">{{ $transaction->customer?->id_type?->value ?? 'N/A' }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink-muted mb-1">ID Number</label>
                    <p class="text-sm text-ink"><x-customer-link :customer="$transaction->customer" field="id_number" /></p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink-muted mb-1">CDD Level</label>
                    <p class="text-sm text-ink">{{ $transaction->cdd_level?->value ?? 'N/A' }}</p>
                </div>
            </div>
        </x-card>

        @if($requiresManagerConfirmation ?? false)
            <x-alert type="warning" title="Confirmation Required" role="alert">
                This transaction exceeds the manager confirmation threshold and cannot be approved until it has been confirmed.
                @can('approve', $transaction)
                    <div class="mt-3">
                        <x-button href="{{ route('transactions.confirm.show', $transaction->id) }}" variant="primary">
                            Go to Confirmation
                        </x-button>
                    </div>
                @endcan
            </x-alert>
        @endif

        @if($transaction->hold_reason !== null)
            <x-alert type="{{ $transaction->compliance_cleared_at ? 'success' : 'warning' }}"
                     title="{{ $transaction->compliance_cleared_at ? 'Compliance Hold Cleared' : 'Compliance Hold' }}"
                     role="alert">
                {{ $transaction->hold_reason }}
                @if($transaction->compliance_cleared_at)
                    <div class="mt-1 text-xs">Cleared at {{ $transaction->compliance_cleared_at->format('Y-m-d H:i') }}</div>
                @endif
            </x-alert>
        @endif

        @if($transaction->refundTransaction)
            <x-alert type="info" title="Reversed — Refund Issued">
                This transaction was reversed. Refund
                <a href="{{ route('transactions.show', $transaction->refundTransaction->id) }}" class="font-medium underline">
                    #{{ $transaction->refundTransaction->id }}
                </a>
                is {{ $transaction->refundTransaction->status?->label() ?? 'pending' }}.
            </x-alert>
        @endif

        @if($transaction->is_refund && $transaction->originalTransaction)
            <x-alert type="warning" title="Refund Transaction">
                This is a reversal refund for
                <a href="{{ route('transactions.show', $transaction->originalTransaction->id) }}" class="font-medium underline">
                    Transaction #{{ $transaction->originalTransaction->id }}
                </a>
                ({{ $transaction->originalTransaction->status?->label() ?? 'N/A' }}).
            </x-alert>
        @endif

        <x-card title="Actions">
            <div class="flex items-center gap-4 flex-wrap">
                @if($transaction->hold_reason !== null && $transaction->compliance_cleared_at === null)
                    @can('clearHold', $transaction)
                        <form method="POST" action="{{ route('transactions.clear-hold', $transaction->id) }}" class="contents">
                            @csrf
                            <x-button type="submit" variant="primary">Clear Compliance Hold</x-button>
                        </form>
                    @endcan
                @endif
                @if(in_array($transaction->status, [\App\Enums\TransactionStatus::Pending, \App\Enums\TransactionStatus::PendingApproval], true))
                    @can('approve', $transaction)
                        <form method="POST" action="{{ route('transactions.approve', $transaction->id) }}" class="contents">
                            @csrf
                            <x-button type="submit" variant="primary">Approve</x-button>
                        </form>
                        <form method="POST" action="{{ route('transactions.reject', $transaction->id) }}" class="contents">
                            @csrf
                            <x-button type="submit" variant="secondary">Reject</x-button>
                        </form>
                    @endcan
                @endif
                @if($transaction->status?->isPendingCancellation())
                    @can('approveCancellation', $transaction)
                        <x-button href="{{ route('transactions.approve-cancellation', $transaction->id) }}" variant="primary">Approve Cancellation</x-button>
                        <x-button href="{{ route('transactions.reject-cancellation', $transaction->id) }}" variant="danger">Reject Cancellation</x-button>
                    @endcan
                @endif
                @if(! in_array($transaction->status, [\App\Enums\TransactionStatus::Cancelled, \App\Enums\TransactionStatus::Reversed, \App\Enums\TransactionStatus::Rejected, \App\Enums\TransactionStatus::Finalized, \App\Enums\TransactionStatus::PendingCancellation], true))
                    @can('requestCancellation', $transaction)
                        <x-button href="{{ route('transactions.cancel', $transaction->id) }}" variant="danger">Request Cancellation</x-button>
                    @endcan
                @endif
                @if($canReverse ?? false)
                    @can('reverse', $transaction)
                        <x-button href="{{ route('transactions.reverse', $transaction->id) }}" variant="danger">Reverse Transaction</x-button>
                    @endcan
                @endif
                @if($transaction->is_refund && $transaction->status === \App\Enums\TransactionStatus::Approved)
                    @can('completeRefund', $transaction)
                        <form method="POST" action="{{ route('transactions.complete-refund', $transaction->id) }}" class="contents">
                            @csrf
                            <x-button type="submit" variant="primary">Complete Refund</x-button>
                        </form>
                    @endcan
                @endif
                <x-button href="{{ route('transactions.print', $transaction->id) }}" variant="secondary">Print Receipt</x-button>
                <x-button href="{{ route('transactions.index') }}" variant="secondary">Back to List</x-button>
            </div>
        </x-card>
    </div>
</x-app-layout>
