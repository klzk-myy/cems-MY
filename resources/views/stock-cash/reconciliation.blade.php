@extends('layouts.base')

@section('title', 'Till Reconciliation')

@section('content')
<div class="card">
    <div class="card-header flex justify-between items-center">
        <h3 class="card-title">Till Reconciliation - {{ $date }}</h3>
        <a href="{{ route('stock-cash.index') }}" class="btn btn-secondary">Back</a>
    </div>
    <div class="card-body">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="p-4 bg-[--color-surface-elevated] rounded">
                <dt class="text-sm text-[--color-ink-muted]">Opening Balance</dt>
                <dd class="text-xl font-mono">RM {{ number_format($reconciliation['opening_balance'] ?? 0, 2) }}</dd>
            </div>
            <div class="p-4 bg-[--color-surface-elevated] rounded">
                <dt class="text-sm text-[--color-ink-muted]">Total Buys</dt>
                <dd class="text-xl font-mono">{{ $reconciliation['purchases']['count'] ?? 0 }}
                    <span class="text-sm text-green-600">(+RM {{ number_format($reconciliation['purchases']['total'] ?? 0, 2) }})</span>
                </dd>
            </div>
            <div class="p-4 bg-[--color-surface-elevated] rounded">
                <dt class="text-sm text-[--color-ink-muted]">Total Sells</dt>
                <dd class="text-xl font-mono">{{ $reconciliation['sales']['count'] ?? 0 }}
                    <span class="text-sm text-red-600">(-RM {{ number_format($reconciliation['sales']['total'] ?? 0, 2) }})</span>
                </dd>
            </div>
            <div class="p-4 bg-[--color-surface-elevated] rounded">
                <dt class="text-sm text-[--color-ink-muted]">Net Flow</dt>
                <dd class="text-xl font-mono">RM {{ number_format($reconciliation['net_flow'] ?? 0, 2) }}</dd>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="p-4 bg-[--color-surface-elevated] rounded">
                <dt class="text-sm text-[--color-ink-muted]">Expected Closing</dt>
                <dd class="text-2xl font-mono">RM {{ number_format($reconciliation['expected_closing'] ?? 0, 2) }}</dd>
            </div>
            <div class="p-4 bg-[--color-surface-elevated] rounded">
                <dt class="text-sm text-[--color-ink-muted]">Actual Closing</dt>
                <dd class="text-2xl font-mono">@if($reconciliation['actual_closing'])
                    RM {{ number_format($reconciliation['actual_closing'], 2) }}
                @else
                    <span class="text-[--color-ink-muted]">Not Closed</span>
                @endif</dd>
            </div>
            <div class="p-4 bg-[--color-surface-elevated] rounded">
                <dt class="text-sm text-[--color-ink-muted]">Variance</dt>
                <dd class="text-2xl font-mono @if(($reconciliation['variance'] ?? 0) != 0) text-red-600 @endif">
                    {{ $reconciliation['variance'] !== null ? 'RM ' . number_format($reconciliation['variance'], 2) : '-' }}
                </dd>
            </div>
        </div>

        <h4 class="text-sm font-medium text-[--color-ink-muted] mb-4">Transactions</h4>
        <table class="table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Customer</th>
                    <th>Type</th>
                    <th class="text-right">Amount</th>
                    <th class="text-right">Rate</th>
                    <th class="text-right">MYR</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions ?? [] as $tx)
                <tr>
                    <td class="font-mono">{{ $tx->created_at->format('H:i:s') }}</td>
                    <td>{{ $tx->customer->name ?? 'N/A' }}</td>
                    <td>
                        <span class="badge @if($tx->type === \App\Enums\TransactionType::Buy) badge-success @else badge-warning @endif">
                            {{ $tx->type->value }}
                        </span>
                    </td>
                    <td class="font-mono text-right">{{ number_format($tx->amount, 2) }} {{ $tx->currency_code }}</td>
                    <td class="font-mono text-right">{{ number_format($tx->rate, 4) }}</td>
                    <td class="font-mono text-right">RM {{ number_format($tx->myr_value, 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center py-8 text-[--color-ink-muted]">No transactions</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection