@extends('layouts.base')

<div>
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('transactions.index') }}" class="btn btn-ghost btn-icon">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <div>
                <h1 class="text-xl font-semibold text-gray-900">Transaction #{{ $transaction->id }}</h1>
                <p class="text-sm text-gray-500">{{ $transaction->created_at->format('d M Y, H:i:s') }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            @if($transaction->status === \App\Enums\TransactionStatus::PendingApproval)
                <a href="{{ route('transactions.confirm.show', $transaction) }}" class="btn btn-primary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Confirm Transaction
                </a>
            @endif
            @if($transaction->status === \App\Enums\TransactionStatus::Completed)
                <a href="{{ route('transactions.receipt', $transaction) }}" class="btn btn-secondary" target="_blank">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                    </svg>
                    Print Receipt
                </a>
            @endif
            @if($transaction->status !== \App\Enums\TransactionStatus::Cancelled)
                <a href="{{ route('transactions.cancel.show', $transaction) }}" class="btn btn-danger">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    Cancel
                </a>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main Details --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Transaction Details --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Transaction Details</h3>
                    <span class="badge {{ $this->status_class_attribute }}">{{ $transaction->status->label() }}</span>
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
                            <p class="text-sm text-gray-500 mb-1">Exchange Rate</p>
                            <p class="font-mono text-xl font-semibold">{{ $transaction->rate }}</p>
                        </div>
                        <div class="col-span-2">
                            <p class="text-sm text-gray-500 mb-1">MYR Value</p>
                            <p class="font-mono text-3xl font-bold text-amber-500">{{ number_format($transaction->amount_local, 2) }} MYR</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Customer Information --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Customer Information</h3>
                    <a href="{{ route('customers.show', $transaction->customer_id) }}" class="btn btn-ghost btn-sm">View Profile</a>
                </div>
                <div class="card-body">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center">
                            <span class="text-lg font-semibold">{{ substr($transaction->customer->full_name ?? 'N/A', 0, 1) }}</span>
                        </div>
                        <div class="flex-1">
                            <p class="font-semibold text-lg">{{ $transaction->customer->full_name ?? 'N/A' }}</p>
                            <p class="text-sm text-gray-500">{{ $transaction->customer->ic_number ?? 'N/A' }}</p>
                            <div class="flex gap-4 mt-2">
                                <span class="badge badge-default">{{ $transaction->cdd_level->label() ?? 'N/A' }}</span>
                                @if($transaction->customer->is_pep ?? false)
                                    <span class="badge badge-warning">PEP</span>
                                @endif
                                @if($transaction->customer->is_sanctioned ?? false)
                                    <span class="badge badge-danger">Sanctioned</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Audit Trail --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Audit Trail</h3>
                </div>
                <div class="card-body">
                    <div class="space-y-4">
                        @forelse($this->auditLogs ?? [] as $log)
                        <div class="flex items-start gap-3">
                            <div class="w-2 h-2 rounded-full bg-gray-400 mt-2"></div>
                            <div class="flex-1">
                                <p class="text-sm">{{ $log->description ?? 'Action taken' }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ $log->user->username ?? 'System' }} - {{ $log->created_at->format('d M Y, H:i:s') }}
                                </p>
                            </div>
                        </div>
                        @empty
                        <p class="text-sm text-gray-500">No audit logs available</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Counter & Branch --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Location</h3>
                </div>
                <div class="card-body space-y-3">
                    <div>
                        <p class="text-sm text-gray-500">Counter</p>
                        <p class="font-medium">{{ $transaction->counter->name ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Branch</p>
                        <p class="font-medium">{{ $transaction->branch->name ?? 'N/A' }}</p>
                    </div>
                </div>
            </div>

            {{-- Compliance Flags --}}
            @if($transaction->flags && $transaction->flags->count() > 0)
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Compliance Flags</h3>
                </div>
                <div class="card-body space-y-2">
                    @foreach($transaction->flags as $flag)
                    <div class="flex items-center gap-2 p-2 bg-red-600/5 rounded-lg border border-red-600/10">
                        <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        <span class="text-sm font-medium text-red-600">{{ $flag->flagType->label() ?? $flag->type }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Created By --}}
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Created By</h3>
                </div>
                <div class="card-body">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                            <span class="font-semibold">{{ substr($transaction->user->username ?? 'N/A', 0, 1) }}</span>
                        </div>
                        <div>
                            <p class="font-medium">{{ $transaction->user->username ?? 'N/A' }}</p>
                            <p class="text-xs text-gray-500">{{ $transaction->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>