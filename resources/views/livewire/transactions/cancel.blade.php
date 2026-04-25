<div>
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('transactions.show', $transaction) }}" class="btn btn-ghost btn-icon">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
        </a>
        <div>
            <h1 class="text-xl font-semibold text-[--color-ink]">Cancel Transaction #{{ $transaction->id }}</h1>
            <p class="text-sm text-[--color-ink-muted]">Manager approval required for cancellation</p>
        </div>
    </div>

    <div class="max-w-4xl mx-auto">
        {{-- Status Banner --}}
        <div class="card mb-6 border-2 border-[--color-warning]">
            <div class="card-body">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-[--color-warning]/10 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-[--color-warning]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="font-semibold text-[--color-warning]">Pending Cancellation</p>
                        <p class="text-sm text-[--color-ink-muted]">This transaction is awaiting manager approval to be cancelled</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Cancellation Request Details --}}
        <div class="card mb-6">
            <div class="card-header">
                <h3 class="card-title">Cancellation Request</h3>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <p class="text-sm text-[--color-ink-muted] mb-1">Requested By</p>
                        <p class="font-medium">{{ $this->requestedByUser->name ?? 'Unknown' }}</p>
                        @if($this->requestedByUser)
                            <p class="text-sm text-[--color-ink-muted]">{{ $this->requestedByUser->role->label() }}</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-sm text-[--color-ink-muted] mb-1">Requested At</p>
                        <p class="font-medium">{{ $this->cancellationDetails['requested_at'] ? \Carbon\Carbon::parse($this->cancellationDetails['requested_at'])->format('d M Y, H:i') : 'Unknown' }}</p>
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-sm text-[--color-ink-muted] mb-1">Reason for Cancellation</p>
                    <div class="p-4 bg-[--color-surface-elevated] rounded-lg">
                        <p class="text-[--color-ink]">{{ $this->cancellationDetails['reason'] }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Transaction Details --}}
        <div class="card mb-6">
            <div class="card-header">
                <h3 class="card-title">Transaction Details</h3>
            </div>
            <div class="card-body">
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <p class="text-sm text-[--color-ink-muted] mb-1">Transaction Type</p>
                        <span class="badge {{ $transaction->type->value === 'Buy' ? 'badge-success' : 'badge-warning' }}">
                            {{ $transaction->type->label() }}
                        </span>
                    </div>
                    <div>
                        <p class="text-sm text-[--color-ink-muted] mb-1">Currency</p>
                        <p class="font-mono font-medium">{{ $transaction->currency_code }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-[--color-ink-muted] mb-1">Foreign Amount</p>
                        <p class="font-mono text-xl font-semibold">{{ number_format($transaction->amount_foreign, 2) }} {{ $transaction->currency_code }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-[--color-ink-muted] mb-1">MYR Value</p>
                        <p class="font-mono text-xl font-semibold text-[--color-accent]">{{ number_format($transaction->amount_local, 2) }} MYR</p>
                    </div>
                    <div>
                        <p class="text-sm text-[--color-ink-muted] mb-1">Customer</p>
                        <p class="font-medium">{{ $transaction->customer->name ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-[--color-ink-muted] mb-1">Teller</p>
                        <p class="font-medium">{{ $transaction->user->name ?? 'N/A' }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Action Buttons --}}
        @if(!$showConfirmation)
            <div class="card">
                <div class="card-body">
                    <p class="text-sm text-[--color-ink-muted] mb-4">Please review the cancellation request and choose an action:</p>
                    <div class="flex items-center justify-between p-4 bg-[--color-surface-elevated] rounded-lg mb-4">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-[--color-ink-muted]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="text-sm text-[--color-ink-muted]">Approving will cancel the transaction. Rejecting will return it to its previous status.</span>
                        </div>
                    </div>
                </div>
                <div class="card-footer flex items-center justify-end gap-3">
                    <a href="{{ route('transactions.show', $transaction) }}" class="btn btn-ghost">Back to Transaction</a>
                    <button type="button" wire:click="confirmReject" class="btn btn-secondary">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        Reject Cancellation
                    </button>
                    <button type="button" wire:click="confirmApprove" class="btn btn-danger">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Approve Cancellation
                    </button>
                </div>
            </div>
        @else
            {{-- Confirmation Form --}}
            <div class="card">
                <form wire:submit="processApproval" class="card-body">
                    <div class="flex items-center gap-3 mb-4">
                        @if($action === 'approve')
                            <div class="w-10 h-10 bg-[--color-success]/10 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-[--color-success]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="font-semibold text-[--color-success]">Confirm Cancellation Approval</p>
                                <p class="text-sm text-[--color-ink-muted]">This action cannot be undone</p>
                            </div>
                        @else
                            <div class="w-10 h-10 bg-[--color-warning]/10 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-[--color-warning]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="font-semibold text-[--color-warning]">Confirm Cancellation Rejection</p>
                                <p class="text-sm text-[--color-ink-muted]">Transaction will be returned to its previous status</p>
                            </div>
                        @endif
                    </div>

                    <div class="form-group mb-6">
                        <label class="form-label">
                            @if($action === 'approve')
                                Approval Notes (Optional)
                            @else
                                Rejection Reason (Required)
                            @endif
                        </label>
                        <textarea
                            wire:model="reason"
                            class="form-textarea"
                            rows="3"
                            placeholder="{{ $action === 'approve' ? 'Add any notes about this approval...' : 'Explain why this cancellation is being rejected...' }}"
                            {{ $action === 'reject' ? 'required' : '' }}
                        ></textarea>
                        <p class="form-hint">
                            @if($action === 'approve')
                                These notes will be recorded in the audit trail
                            @else
                                This reason will be recorded and the teller will be notified
                            @endif
                        </p>
                    </div>

                    @if($action === 'approve')
                        <div class="form-group mb-6">
                            <label class="flex items-center gap-3">
                                <input type="checkbox" class="form-checkbox" required>
                                <span class="text-sm text-[--color-ink]">
                                    I confirm this cancellation request is legitimate and complies with BNM regulations
                                </span>
                            </label>
                        </div>
                    @endif
                </form>
                <div class="card-footer flex items-center justify-end gap-3">
                    <button type="button" wire:click="cancelAction" class="btn btn-ghost">Cancel</button>
                    <button type="submit" form="processApproval" class="btn {{ $action === 'approve' ? 'btn-danger' : 'btn-warning' }}">
                        @if($action === 'approve')
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Confirm Approval
                        @else
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            Confirm Rejection
                        @endif
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>
