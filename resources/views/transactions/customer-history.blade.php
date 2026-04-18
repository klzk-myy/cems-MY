@extends('layouts.base')

@section('title', 'Customer History')

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Customer Transaction History</h3>
        <div class="text-sm text-[--color-ink-muted]">{{ $customerName ?? 'N/A' }}</div>
    </div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Currency</th>
                    <th class="text-right">Amount</th>
                    <th class="text-right">MYR Value</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($customerTransactions ?? [] as $tx)
                <tr>
                    <td>{{ $tx['date'] ?? 'N/A' }}</td>
                    <td>
                        <span class="badge @if(($tx['type'] ?? '') === 'Buy') badge-success @else badge-warning @endif">
                            {{ $tx['type'] ?? 'N/A' }}
                        </span>
                    </td>
                    <td class="font-mono">{{ $tx['currency'] ?? 'N/A' }}</td>
                    <td class="font-mono text-right">{{ number_format($tx['amount'] ?? 0, 2) }}</td>
                    <td class="font-mono text-right">RM {{ number_format($tx['myr_value'] ?? 0, 2) }}</td>
                    <td>
                        @if(isset($tx['status']))
                            @statuslabel($tx['status'])
                        @else
                            <span class="text-[--color-ink-muted]">N/A</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center py-8 text-[--color-ink-muted]">No transactions found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection