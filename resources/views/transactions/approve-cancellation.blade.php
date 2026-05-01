@extends('layouts.base')

@section('title', 'Approve Cancellation')

@section('content')
<div class="card">
    <div class="px-6 py-4 border-b border-[--color-border]"><h3 class="text-base font-semibold text-[--color-ink]">Approve Cancellation</h3></div>
    <div class="p-6">
        <div class="p-6 bg-[--color-surface-elevated] rounded-lg mb-6">
            <h4 class="text-sm font-medium text-[--color-ink-muted] mb-4">Transaction Details</h4>
            <dl class="grid grid-cols-2 gap-4">
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">ID</dt>
                    <dd class="font-mono">{{ $transaction->id ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-[--color-ink-muted]">Type</dt>
                    <dd>
                        <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded {{ ($transaction->type ?? '') === 'Buy' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                            {{ $transaction->type ?? 'N/A' }}
                        </span>
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Currency</dt>
                    <dd class="font-mono">{{ $transaction->currency ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Amount</dt>
                    <dd class="font-mono text-lg">{{ number_format($transaction->amount ?? 0, 2) }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">MYR Value</dt>
                    <dd class="font-mono text-lg">RM {{ number_format($transaction->myr_value ?? 0, 2) }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">Status</dt>
                    <dd><span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">Pending Cancellation</span></dd>
                </div>
            </dl>
        </div>

        <form method="POST" action="{{ route('transactions.approve-cancel', $transaction->id ?? 0) }}">
            @csrf
            <div class="mb-4">
                <label class="block text-sm font-medium text-[--color-ink] mb-1">Approval Note (optional)</label>
                <textarea name="reason" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" rows="2" placeholder="Optional note for audit trail"></textarea>
            </div>
            <div class="flex gap-3">
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-red-600 text-white hover:bg-red-700">Approve Cancellation</button>
                <a href="{{ route('transactions.show', $transaction) }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[--color-border] hover:bg-[--color-canvas-subtle]">Back</a>
            </div>
        </form>
    </div>
</div>
@endsection