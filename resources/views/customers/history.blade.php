@extends('layouts.base')

@section('title', 'Customer Transaction History')

<div class="p-6">
    <div class="mb-6">
        <a href="{{ url()->previous() }}" class="text-sm text-[--color-accent] hover:underline">&larr; Back</a>
    </div>

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold">Transaction History</h1>
        <a href="{{ route('customers.export', $customer->id ?? 1) }}" class="px-4 py-2 border border-[--color-border] rounded-lg hover:bg-[--color-bg-tertiary]">Export History</a>
    </div>

    <div class="bg-[--color-bg-secondary] rounded-xl border border-[--color-border] overflow-hidden">
        <table class="w-full">
            <thead class="bg-[--color-bg-tertiary]">
                <tr>
                    <th class="px-4 py-3 text-left text-sm font-medium">Date</th>
                    <th class="px-4 py-3 text-left text-sm font-medium">Transaction ID</th>
                    <th class="px-4 py-3 text-left text-sm font-medium">Type</th>
                    <th class="px-4 py-3 text-left text-sm font-medium">Amount</th>
                    <th class="px-4 py-3 text-left text-sm font-medium">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[--color-border]">
                @forelse($transactions ?? [] as $txn)
                <tr class="hover:bg-[--color-bg-tertiary]/50">
                    <td class="px-4 py-3 text-sm">{{ $txn->created_at ?? now()->format('Y-m-d H:i') }}</td>
                    <td class="px-4 py-3 text-sm">{{ $txn->id ?? '#TXN-001' }}</td>
                    <td class="px-4 py-3 text-sm">{{ $txn->type ?? 'Exchange' }}</td>
                    <td class="px-4 py-3 text-sm">{{ number_format($txn->amount ?? 1000, 2) }}</td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 text-xs rounded-full {{ ($txn->status ?? 'completed') === 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                            {{ ucfirst($txn->status ?? 'completed') }}
                        </span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-[--color-text-muted]">No transactions found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $transactions->links() ?? '' }}
    </div>
</div>