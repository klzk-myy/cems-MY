@extends('layouts.base')

@section('title', 'Transaction Receipt')

@section('content')
<div class="card">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">Transaction Receipt</h3>
    </div>
    <div class="p-6">
        @if(isset($transaction))
        <div class="bg-[--color-surface-elevated] p-6 rounded-lg mb-6">
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-12 h-12 bg-green-100 rounded-full mb-3">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h2 class="text-lg font-semibold text-[--color-ink]">Transaction Completed</h2>
                <p class="text-sm text-[--color-ink-muted]">Reference: {{ $transaction->reference ?? $transaction->id }}</p>
            </div>

            <dl class="grid grid-cols-2 gap-4">
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Type</dt>
                    <dd>
                        <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded {{ $transaction->type === 'Buy' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                            {{ $transaction->type }}
                        </span>
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Currency</dt>
                    <dd class="font-mono">{{ $transaction->currency_code }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Amount</dt>
                    <dd class="font-mono text-lg">{{ number_format($transaction->amount, 2) }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Exchange Rate</dt>
                    <dd class="font-mono">{{ number_format($transaction->rate, 4) }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">MYR Value</dt>
                    <dd class="font-mono text-lg">RM {{ number_format($transaction->myr_value, 2) }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Customer</dt>
                    <dd>{{ $transaction->customer->name ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Counter</dt>
                    <dd>{{ $transaction->counter->code ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Teller</dt>
                    <dd>{{ $transaction->teller->username ?? 'N/A' }}</dd>
                </div>
            </dl>
        </div>

        <div class="flex gap-3">
            <button onclick="window.print()" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-[--color-primary] text-white hover:bg-[--color-ink]">
                Print Receipt
            </button>
            <a href="{{ route('transactions.index') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[--color-border] hover:bg-[--color-canvas-subtle]">
                New Transaction
            </a>
        </div>
        @else
        <p class="text-[--color-ink-muted]">Transaction not found.</p>
        <a href="{{ route('transactions.index') }}" class="btn btn-secondary mt-4 inline-block">Back to Transactions</a>
        @endif
    </div>
</div>
@endsection