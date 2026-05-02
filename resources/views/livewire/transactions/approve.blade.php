@extends('layouts.base')

<div>
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('transactions.show', $transaction) }}" class="btn btn-ghost btn-icon">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
        </a>
        <div>
            <h1 class="text-xl font-semibold text-gray-900">Approve Transaction #{{ $transaction->id }}</h1>
            <p class="text-sm text-gray-500">Manager approval required for this transaction</p>
        </div>
    </div>

    <div class="max-w-4xl mx-auto">
        {{-- AML Monitoring Results --}}
        <div class="card mb-6">
            <div class="card-header">
                <h3 class="card-title">AML Monitoring Results</h3>
                <span class="badge {{ $this->has_high_priority_flags ? 'badge-danger' : 'badge-success' }}">
                    {{ $this->has_high_priority_flags ? 'Issues Found' : 'Clear' }}
                </span>
            </div>
            <div class="card-body">
                @if($this->has_high_priority_flags)
                    <div class="space-y-3">
                        @foreach(array_filter($amlResult['flags'] ?? [], fn($flag) => $flag->flag_type->isHighPriority()) as $flag)
                        <div class="flex items-start gap-3 p-4 bg-red-600/5 rounded-lg border border-red-600/10">
                            <div class="w-8 h-8 bg-red-600/10 rounded-lg flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <p class="font-medium text-red-600">{{ $flag->flag_type->label() }}</p>
                                <p class="text-sm text-gray-500 mt-1">{{ $flag->flag_reason }}</p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <div class="mt-4 p-4 bg-amber-500/10 rounded-lg border border-amber-500/20">
                        <p class="text-sm text-amber-500">
                            <strong>Warning:</strong> High-priority AML flags detected. Approval is blocked. Please review the flags above and resolve them before approving this transaction.
                        </p>
                    </div>
                @else
                    <div class="flex items-center gap-3 p-4 bg-green-600/5 rounded-lg">
                        <div class="w-8 h-8 bg-green-600/10 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="font-medium text-green-600">No AML Issues Detected</p>
                            <p class="text-sm text-gray-500">Transaction passed all compliance checks</p>
                        </div>
                    </div>
                @endif
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
                        <p class="text-sm text-gray-500 mb-1">Transaction Type</p>
                        <span class="badge {{ $transaction->type->value === 'Buy' ? 'badge-success' : 'badge-warning' }}">
                            {{ $transaction->type->label() }}
                        </span>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Currency</p>
                        <p class="font-mono font-medium">{{ $transaction->currency_code }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Foreign Amount</p>
                        <p class="font-mono text-xl font-semibold">{{ number_format($transaction->amount_foreign, 2) }} {{ $transaction->currency_code }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 mb-1">MYR Value</p>
                        <p class="font-mono text-xl font-semibold text-amber-500">{{ number_format($transaction->amount_local, 2) }} MYR</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Approval Form --}}
        @if(!$this->has_high_priority_flags)
            @if(!$showConfirmation)
            <div class="card">
                <div class="card-body">
                    <p class="text-sm text-gray-500 mb-4">By clicking "Approve Transaction", you confirm that:</p>
                    <ul class="text-sm text-gray-500 mb-4 list-disc list-inside">
                        <li>You have reviewed this transaction and found it compliant with BNM regulations</li>
                        <li>All required documentation has been verified</li>
                        <li>You approve this transaction under your authority</li>
                    </ul>
                </div>
                <div class="card-footer flex items-center justify-end gap-3">
                    <a href="{{ route('transactions.show', $transaction) }}" class="btn btn-ghost">Cancel</a>
                    <button type="button" wire:click="confirmApproval" class="btn btn-primary">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Approve Transaction
                    </button>
                </div>
            </div>
            @else
            <div class="card">
                <form wire:submit="approve" id="approveForm" class="card-body">
                    <div class="form-group mb-6">
                        <label class="form-label">Approval Notes (Optional)</label>
                        <textarea wire:model="notes" class="form-textarea" rows="3" placeholder="Add any notes about this approval..."></textarea>
                        <p class="form-hint">These notes will be recorded in the audit trail</p>
                    </div>

                    <div class="form-group mb-6">
                        <label class="flex items-center gap-3">
                            <input type="checkbox" class="form-checkbox" required>
                            <span class="text-sm text-gray-900">
                                I approve this transaction and confirm it complies with all BNM regulations
                            </span>
                        </label>
                    </div>
                </form>
                <div class="card-footer flex items-center justify-end gap-3">
                    <button type="button" wire:click="cancelApproval" class="btn btn-ghost">Cancel</button>
                    <button type="submit" form="approveForm" class="btn btn-primary">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Confirm Approval
                    </button>
                </div>
            </div>
            @endif
        @else
        <div class="card">
            <div class="card-body">
                <div class="flex items-center justify-center gap-3 p-6">
                    <div class="w-12 h-12 bg-amber-500/10 rounded-xl flex items-center justify-center">
                        <svg class="w-6 h-6 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-900">Approval Blocked</p>
                        <p class="text-sm text-gray-500">Resolve AML flags before approving this transaction</p>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <a href="{{ route('transactions.show', $transaction) }}" class="btn btn-ghost w-full">Return to Transaction</a>
            </div>
        </div>
        @endif
    </div>
</div>