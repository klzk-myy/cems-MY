@extends('layouts.base')

@section('title', 'Currency Position')

@section('content')
<div class="card">
    <div class="card-header flex justify-between items-center">
        <h3 class="card-title">Position - {{ $position->currency_code ?? 'N/A' }}</h3>
        <a href="{{ route('stock-cash.index') }}" class="btn btn-secondary">Back</a>
    </div>
    <div class="card-body">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="p-4 bg-[--color-surface-elevated] rounded">
                <dt class="text-sm text-[--color-ink-muted]">Quantity</dt>
                <dd class="text-2xl font-mono">{{ number_format($position->quantity ?? 0, 2) }}</dd>
            </div>
            <div class="p-4 bg-[--color-surface-elevated] rounded">
                <dt class="text-sm text-[--color-ink-muted]">Average Cost</dt>
                <dd class="text-2xl font-mono">RM {{ number_format($position->average_cost ?? 0, 4) }}</dd>
            </div>
            <div class="p-4 bg-[--color-surface-elevated] rounded">
                <dt class="text-sm text-[--color-ink-muted]">Market Value</dt>
                <dd class="text-2xl font-mono">RM {{ number_format($position->market_value, 2) }}</dd>
            </div>
            <div class="p-4 bg-[--color-surface-elevated] rounded">
                <dt class="text-sm text-[--color-ink-muted]">Unrealized P/L</dt>
                <dd class="text-2xl font-mono {{ ($position->unrealized_pl ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ ($position->unrealized_pl ?? 0) >= 0 ? '+' : '' }}RM {{ number_format($position->unrealized_pl ?? 0, 2) }}
                </dd>
            </div>
        </div>

        <h4 class="text-sm font-medium text-[--color-ink-muted] mb-4">Recent Transactions</h4>
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th class="text-right">Amount</th>
                    <th class="text-right">Rate</th>
                    <th class="text-right">MYR Value</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions ?? [] as $tx)
                <tr>
                    <td class="font-mono">{{ $tx->created_at->format('Y-m-d H:i') }}</td>
                    <td>
                        <span class="badge @if($tx->type === \App\Enums\TransactionType::Buy) badge-success @else badge-warning @endif">
                            {{ $tx->type->value }}
                        </span>
                    </td>
                    <td class="font-mono text-right">{{ number_format($tx->amount, 2) }}</td>
                    <td class="font-mono text-right">{{ number_format($tx->rate, 4) }}</td>
                    <td class="font-mono text-right">RM {{ number_format($tx->myr_value, 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center py-8 text-[--color-ink-muted]">No transactions</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection