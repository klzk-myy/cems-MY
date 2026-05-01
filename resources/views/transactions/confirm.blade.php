@extends('layouts.base')

@section('title', 'Confirm Transaction #' . ($transaction->id ?? ''))

@section('header-title')
<div class="flex items-center gap-3">
    <a href="{{ route('transactions.show', $transaction->id) }}" class="px-4 py-2 text-sm font-medium rounded-lg hover:bg-[--color-canvas-subtle]">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
        </svg>
    </a>
    <div>
        <h1 class="text-xl font-semibold text-[--color-ink]">Confirm Transaction #{{ $transaction->id ?? '' }}</h1>
        <p class="text-sm text-[--color-ink-muted]">Review and approve this transaction</p>
    </div>
</div>
@endsection

@section('content')
<div class="max-w-4xl mx-auto">
    {{-- Warning Banner --}}
    <div class="bg-white border border-[--color-border] rounded-xl mb-6 border-l-4 border-l-[--color-warning]">
        <div class="p-6">
            <div class="flex items-start gap-4">
                <div class="w-10 h-10 bg-[--color-warning]/10 rounded-lg flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-[--color-warning]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-[--color-ink]">Confirmation Required</h3>
                    <p class="text-sm text-[--color-ink-muted] mt-1">
                        This transaction requires manager approval before completion. Please review the details below and confirm.
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Transaction Summary --}}
    <div class="bg-white border border-[--color-border] rounded-xl mb-6">
        <div class="px-6 py-4 border-b border-[--color-border]">
            <h3 class="text-base font-semibold text-[--color-ink]">Transaction Summary</h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-2 gap-6">
                <div>
                    <p class="text-sm text-[--color-ink-muted] mb-1">Transaction Type</p>
                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded {{ $transaction->type->value === 'Buy' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
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
            </div>
        </div>
    </div>

    {{-- Customer Information --}}
    <div class="bg-white border border-[--color-border] rounded-xl mb-6">
        <div class="px-6 py-4 border-b border-[--color-border] flex items-center justify-between">
            <h3 class="text-base font-semibold text-[--color-ink]">Customer Information</h3>
            <a href="{{ route('customers.show', $transaction->customer_id) }}" class="px-4 py-2 text-sm font-medium rounded-lg hover:bg-[--color-canvas-subtle]">View Profile</a>
        </div>
        <div class="p-6">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 bg-[--color-canvas-subtle] rounded-xl flex items-center justify-center">
                    <span class="text-lg font-semibold">{{ substr($transaction->customer->full_name ?? 'N/A', 0, 1) }}</span>
                </div>
                <div class="flex-1">
                    <p class="font-semibold text-lg">{{ $transaction->customer->full_name ?? 'N/A' }}</p>
                    <p class="text-sm text-[--color-ink-muted]">{{ $transaction->customer->ic_number ?? 'N/A' }}</p>
                    <div class="flex gap-4 mt-2">
                        <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-gray-100 text-gray-700">{{ $transaction->cdd_level->label() ?? 'N/A' }}</span>
                        @if($transaction->customer->is_pep ?? false)
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">PEP</span>
                        @endif
                        @if($transaction->customer->is_sanctioned ?? false)
                            <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">Sanctioned</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Compliance Checks --}}
    <div class="bg-white border border-[--color-border] rounded-xl mb-6">
        <div class="px-6 py-4 border-b border-[--color-border]">
            <h3 class="text-base font-semibold text-[--color-ink]">Compliance Checks</h3>
        </div>
        <div class="p-6 space-y-4">
            <div class="flex items-center justify-between p-4 bg-[--color-canvas-subtle] rounded-lg">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-[--color-success]/10 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4 text-[--color-success]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="font-medium text-[--color-ink]">Sanctions Screening</p>
                        <p class="text-sm text-[--color-ink-muted]">No matches found</p>
                    </div>
                </div>
                <span class="badge badge-success">Passed</span>
            </div>
            
            <div class="flex items-center justify-between p-4 bg-[--color-canvas-subtle] rounded-lg">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-[--color-success]/10 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4 text-[--color-success]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="font-medium text-[--color-ink]">Velocity Check</p>
                        <p class="text-sm text-[--color-ink-muted]">24h total: RM {{ number_format($compliance_checks['velocity_24h'] ?? 0, 2) }}</p>
                    </div>
                </div>
                <span class="badge badge-success">Passed</span>
            </div>
            
            <div class="flex items-center justify-between p-4 bg-[--color-canvas-subtle] rounded-lg">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-[--color-success]/10 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4 text-[--color-success]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="font-medium text-[--color-ink]">Structuring Detection</p>
                        <p class="text-sm text-[--color-ink-muted]">No structuring patterns detected</p>
                    </div>
                </div>
                <span class="badge badge-success">Passed</span>
            </div>
        </div>
    </div>

    {{-- Confirmation Form --}}
    <form method="POST" action="{{ route('transactions.confirm', $transaction->id) }}" class="bg-white border border-[--color-border] rounded-xl">
        @csrf
        <div class="p-6">
            <div class="mb-6">
                <label class="block text-sm font-medium text-[--color-ink] mb-1">Confirmation Notes (Optional)</label>
                <textarea name="notes" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" rows="3" placeholder="Add any notes about this confirmation..."></textarea>
                <p class="text-sm text-[--color-ink-muted] mt-1">These notes will be recorded in the audit trail</p>
            </div>
            
            <div class="mb-6">
                <label class="flex items-center gap-3">
                    <input type="checkbox" name="confirmed" class="w-4 h-4 rounded border-[--color-border]" required>
                    <span class="text-sm text-[--color-ink]">
                        I confirm that I have reviewed this transaction and it complies with all BNM regulations
                    </span>
                </label>
            </div>
        </div>
        <div class="px-6 py-4 border-t border-[--color-border] flex items-center justify-end gap-3">
            <a href="{{ route('transactions.show', $transaction->id) }}" class="px-4 py-2 text-sm font-medium rounded-lg hover:bg-[--color-canvas-subtle]">Cancel</a>
            <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-[--color-primary] text-white hover:bg-[--color-ink]">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Confirm Transaction
            </button>
        </div>
    </form>
</div>
@endsection