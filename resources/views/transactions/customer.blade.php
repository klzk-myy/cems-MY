@extends('layouts.base')

@section('title', 'Transaction History')

@section('content')
<div class="card">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">Customer Transaction History</h3>
        <span class="text-sm text-[--color-ink-muted]">{{ $customer->name ?? 'N/A' }}</span>
    </div>
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Currency</th>
                    <th class="text-right">Amount</th>
                    <th class="text-right">MYR</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions ?? [] as $tx)
                <tr>
                    <td>{{ $tx['date'] ?? 'N/A' }}</td>
                    <td>
                        <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded {{ ($tx['type'] ?? '') === 'Buy' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                            {{ $tx['type'] ?? 'N/A' }}
                        </span>
                    </td>
                    <td class="font-mono">{{ $tx['currency'] ?? 'N/A' }}</td>
                    <td class="font-mono text-right">{{ number_format($tx['amount'] ?? 0, 2) }}</td>
                    <td class="font-mono text-right">RM {{ number_format($tx['myr'] ?? 0, 2) }}</td>
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