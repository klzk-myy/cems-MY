@extends('layouts.base')

@section('title', 'Confirm Transaction #' . ($transaction->id ?? ''))

@section('header-title')
<div class="flex items-center gap-3">
    <a href="/transactions/{{ $transaction->id }}" class="btn btn-ghost btn-icon">
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
    <div class="card mb-6 border-l-4 border-l-[--color-warning]">
        <div class="card-body">
            <div class="flex items-start gap-4">
                <div class="w-10 h-10 bg-[--color-warning]/10 rounded-lg flex items-center justify-center flex-shrink-0">
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
    <div class="card mb-6">
        <div class="card-header">
            <h3 class="card-title">Transaction Summary</h3>
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
            </div>
        </div>
    </div>

    {{-- Customer Information --}}
    <div class="card mb-6">
        <div class="card-header">
            <h3 class="card-title">Customer Information</h3>
            <a href="/customers/{{ $transaction->customer_id }}" class="btn btn-ghost btn-sm">View Profile</a>
        </div>
        <div class="card-body">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 bg-[--color-canvas-subtle] rounded-xl flex items-center justify-center">
                    <span class="text-lg font-semibold">{{ substr($transaction->customer->full_name ?? 'N/A', 0, 1) }}</span>
                </div>
                <div class="flex-1">
                    <p class="font-semibold text-lg">{{ $transaction->customer->full_name ?? 'N/A' }}</p>
                    <p class="text-sm text-[--color-ink-muted]">{{ $transaction->customer->ic_number ?? 'N/A' }}</p>
                    <div class="flex gap-4 mt-2">
                        <span class="badge badge-default">{{ $transaction->customer->cdd_level->label() ?? 'N/A' }}</span>
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

    {{-- Compliance Checks --}}
    <div class="card mb-6">
        <div class="card-header">
            <h3 class="card-title">Compliance Checks</h3>
        </div>
        <div class="card-body space-y-4">
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
    <form method="POST" action="/transactions/{{ $transaction->id }}/confirm" class="card">
        @csrf
        <div class="card-body">
            <div class="form-group mb-6">
                <label class="form-label">Confirmation Notes (Optional)</label>
                <textarea name="notes" class="form-textarea" rows="3" placeholder="Add any notes about this confirmation..."></textarea>
                <p class="form-hint">These notes will be recorded in the audit trail</p>
            </div>
            
            <div class="form-group mb-6">
                <label class="flex items-center gap-3">
                    <input type="checkbox" name="confirmed" class="form-checkbox" required>
                    <span class="text-sm text-[--color-ink]">
                        I confirm that I have reviewed this transaction and it complies with all BNM regulations
                    </span>
                </label>
            </div>
        </div>
        <div class="card-footer flex items-center justify-end gap-3">
            <a href="/transactions/{{ $transaction->id }}" class="btn btn-ghost">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Confirm Transaction
            </button>
        </div>
    </form>
</div>
@endsection