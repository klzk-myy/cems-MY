@extends('layouts.base')

@section('title', 'Approve Cancellation')

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">Approve Cancellation</h3></div>
    <div class="card-body">
        <div class="bg-[--color-surface-elevated] p-6 rounded-lg mb-6">
            <h4 class="text-sm font-medium text-[--color-ink-muted] mb-4">Transaction Details</h4>
            <dl class="grid grid-cols-2 gap-4">
                <div>
                    <dt class="text-sm text-[--color-ink-muted]">ID</dt>
                    <dd class="font-mono">{{ $transaction->id ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-[--color-ink-muted]">Type</dt>
                    <dd>
                        <span class="badge @if(($transaction->type ?? '') === 'Buy') badge-success @else badge-warning @endif">
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
                    <dd><span class="badge badge-warning">Pending Cancellation</span></dd>
                </div>
            </dl>
        </div>

        <form method="POST" action="{{ route('transactions.approve-cancel', $transaction->id ?? 0) }}">
            @csrf
            <div class="mb-4">
                <label class="form-label">Approval Note (optional)</label>
                <textarea name="reason" class="form-input" rows="2" placeholder="Optional note for audit trail"></textarea>
            </div>
            <div class="flex gap-3">
                <button type="submit" class="btn btn-danger">Approve Cancellation</button>
                <a href="{{ route('transactions.show', $transaction) }}" class="btn btn-secondary">Back</a>
            </div>
        </form>
    </div>
</div>
@endsection