@extends('layouts.base')

@section('title', 'Approve Transaction #' . ($transaction->id ?? ''))

@section('header-title')
<div class="flex items-center gap-3">
    <a href="/transactions/{{ $transaction->id }}" class="btn btn-ghost btn-icon">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
        </svg>
    </a>
    <div>
        <h1 class="text-xl font-semibold text-[--color-ink]">Approve Transaction #{{ $transaction->id ?? '' }}</h1>
        <p class="text-sm text-[--color-ink-muted]">Manager approval required for this transaction</p>
    </div>
</div>
@endsection

@section('content')
<div class="max-w-4xl mx-auto">
    {{-- AML Monitoring Results --}}
    <div class="card mb-6">
        <div class="card-header">
            <h3 class="card-title">AML Monitoring Results</h3>
            <span class="badge {{ $aml_result['has_high_priority_flags'] ? 'badge-danger' : 'badge-success' }}">
                {{ $aml_result['has_high_priority_flags'] ? 'Issues Found' : 'Clear' }}
            </span>
        </div>
        <div class="card-body">
            @if($aml_result['has_high_priority_flags'])
                <div class="space-y-3">
                    @foreach($aml_result['flags'] as $flag)
                    <div class="flex items-start gap-3 p-4 bg-[--color-danger]/5 rounded-lg border border-[--color-danger]/10">
                        <div class="w-8 h-8 bg-[--color-danger]/10 rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-[--color-danger]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <p class="font-medium text-[--color-danger]">{{ $flag->type->label() }}</p>
                            <p class="text-sm text-[--color-ink-muted] mt-1">{{ $flag->flag_reason }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
                <div class="mt-4 p-4 bg-[--color-warning]/10 rounded-lg border border-[--color-warning]/20">
                    <p class="text-sm text-[--color-warning]">
                        <strong>Warning:</strong> High-priority AML flags detected. Approval is blocked. Please review the flags above and resolve them before approving this transaction.
                    </p>
                </div>
            @else
                <div class="flex items-center gap-3 p-4 bg-[--color-success]/5 rounded-lg">
                    <div class="w-8 h-8 bg-[--color-success]/10 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4 text-[--color-success]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="font-medium text-[--color-success]">No AML Issues Detected</p>
                        <p class="text-sm text-[--color-ink-muted]">Transaction passed all compliance checks</p>
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

    {{-- Approval Form --}}
    @if(!$aml_result['has_high_priority_flags'])
    <form method="POST" action="/transactions/{{ $transaction->id }}/approve" class="card">
        @csrf
        <div class="card-body">
            <div class="form-group mb-6">
                <label class="form-label">Approval Notes (Optional)</label>
                <textarea name="notes" class="form-textarea" rows="3" placeholder="Add any notes about this approval..."></textarea>
                <p class="form-hint">These notes will be recorded in the audit trail</p>
            </div>
            
            <div class="form-group mb-6">
                <label class="flex items-center gap-3">
                    <input type="checkbox" name="approved" class="form-checkbox" required>
                    <span class="text-sm text-[--color-ink]">
                        I approve this transaction and confirm it complies with all BNM regulations
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
                Approve Transaction
            </button>
        </div>
    </form>
    @else
    <div class="card">
        <div class="card-body">
            <div class="flex items-center justify-center gap-3 p-6">
                <div class="w-12 h-12 bg-[--color-warning]/10 rounded-xl flex items-center justify-center">
                    <svg class="w-6 h-6 text-[--color-warning]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                <div>
                    <p class="font-semibold text-[--color-ink]">Approval Blocked</p>
                    <p class="text-sm text-[--color-ink-muted]">Resolve AML flags before approving this transaction</p>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <a href="/transactions/{{ $transaction->id }}" class="btn btn-ghost w-full">Return to Transaction</a>
        </div>
    </div>
    @endif
</div>
@endsection
